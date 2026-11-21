<?php

namespace App\Service;

use App\Entity\CreneauDon;
use App\Entity\Donneur;
use App\Entity\RendezVousDon;
use App\Entity\Utilisateur;
use App\Repository\ParametreRepository;
use App\Repository\RendezVousDonRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Rendez-vous de don : réservation, annulation, pointage.
 *
 * Le point sensible est le même que pour les précommandes : deux donneurs qui
 * cliquent en même temps sur la dernière place. La réservation verrouille donc
 * la ligne du créneau le temps de vérifier la capacité et d'incrémenter le
 * compteur — personne ne se déplace pour rien.
 */
class RendezVousService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RendezVousDonRepository $rendezVous,
        private readonly ParametreRepository $parametres,
        private readonly NumerotationService $numerotation,
        private readonly JournalService $journal,
        private readonly SmsNotifier $sms,
    ) {
    }

    /**
     * Réserve une place sur un créneau.
     *
     * @throws \RuntimeException si le créneau est fermé, passé ou complet,
     *                           ou si le donneur n'est pas encore éligible
     */
    public function reserver(CreneauDon $creneau, Donneur $donneur,
        ?string $commentaire = null): RendezVousDon
    {
        $this->verifierEligibilite($donneur);

        $connexion = $this->em->getConnection();
        $connexion->beginTransaction();

        try {
            // Verrou sur la ligne du créneau : la place est à nous ou à personne.
            $verrouille = $this->em->find(CreneauDon::class, $creneau->getId(),
                LockMode::PESSIMISTIC_WRITE);

            if (!$verrouille) {
                throw new \RuntimeException('Ce créneau n’existe plus.');
            }

            if (CreneauDon::OUVERT !== $verrouille->getStatut()) {
                throw new \RuntimeException('Ce créneau n’est plus ouvert à la réservation.');
            }

            if ($verrouille->estPasse()) {
                throw new \RuntimeException('Ce créneau est déjà passé.');
            }

            if ($verrouille->getPlacesRestantes() < 1) {
                throw new \RuntimeException('Ce créneau est complet : choisissez-en un autre.');
            }

            if ($this->dejaInscrit($verrouille, $donneur)) {
                throw new \RuntimeException('Vous avez déjà un rendez-vous sur ce créneau.');
            }

            $numero = $this->prochainNumero();

            $rendezVous = (new RendezVousDon())
                ->setNumero($numero)
                ->setDonneur($donneur)
                ->setCreneau($verrouille)
                ->setStatut(RendezVousDon::PLANIFIE)
                ->setCommentaire($commentaire)
                ->setDateCreation(new \DateTimeImmutable());

            $verrouille->setPlacesReservees($verrouille->getPlacesReservees() + 1);

            if ($verrouille->getPlacesRestantes() < 1) {
                $verrouille->setStatut(CreneauDon::COMPLET);
            }

            $this->em->persist($rendezVous);
            $this->em->flush();
            $connexion->commit();
        } catch (\Throwable $e) {
            $connexion->rollBack();

            throw $e;
        }

        $this->journal->tracer('RDV_PRIS', 'RendezVousDon', $rendezVous->getId(),
            sprintf('Rendez-vous %s pour %s le %s', $rendezVous->getNumero(),
                $donneur->getNomComplet(), (string) $creneau));

        $this->prevenir($rendezVous, sprintf(
            'HemoLink : rendez-vous de don %s confirme le %s a %s - %s. Merci de venir '
            .'avec une piece d identite.',
            $rendezVous->getNumero(),
            $creneau->getDateCreneau()->format('d/m/Y'),
            substr($creneau->getHeureDebut(), 0, 5),
            $creneau->getBanque()?->getNom() ?? ''
        ));

        return $rendezVous;
    }

    /**
     * Annule un rendez-vous et rend la place au créneau.
     */
    public function annuler(RendezVousDon $rendezVous, ?string $raison = null,
        ?Utilisateur $auteur = null): void
    {
        $delai = $this->parametres->entier('delai_annulation_rdv_h', 12);

        if (!$rendezVous->estModifiable($delai) && null === $auteur) {
            throw new \RuntimeException(sprintf(
                'Un rendez-vous ne peut plus être annulé moins de %d heures avant le créneau. '
                .'Appelez la banque.', $delai));
        }

        $rendezVous
            ->setStatut(RendezVousDon::ANNULE)
            ->setDateAnnulation(new \DateTimeImmutable())
            ->setCommentaire($raison ?? $rendezVous->getCommentaire());

        $this->libererPlace($rendezVous);
        $this->em->flush();

        $this->journal->tracer('RDV_ANNULE', 'RendezVousDon', $rendezVous->getId(),
            sprintf('Rendez-vous %s annulé%s', $rendezVous->getNumero(),
                $raison ? ' : '.$raison : ''), $auteur);

        $this->prevenir($rendezVous, sprintf(
            'HemoLink : votre rendez-vous de don %s est annule. Vous pouvez en reprendre '
            .'un autre sur hemolink.bj.', $rendezVous->getNumero()));
    }

    /**
     * Déplace un rendez-vous sur un autre créneau : annulation silencieuse puis
     * nouvelle réservation, pour que les compteurs restent justes.
     */
    public function deplacer(RendezVousDon $rendezVous, CreneauDon $nouveau): RendezVousDon
    {
        $delai = $this->parametres->entier('delai_annulation_rdv_h', 12);

        if (!$rendezVous->estModifiable($delai)) {
            throw new \RuntimeException(sprintf(
                'Ce rendez-vous ne peut plus être déplacé à moins de %d heures du créneau.',
                $delai));
        }

        $donneur = $rendezVous->getDonneur();

        $rendezVous
            ->setStatut(RendezVousDon::ANNULE)
            ->setDateAnnulation(new \DateTimeImmutable())
            ->setCommentaire('Déplacé par le donneur');
        $this->libererPlace($rendezVous);
        $this->em->flush();

        return $this->reserver($nouveau, $donneur, 'Déplacement du rendez-vous '
            .$rendezVous->getNumero());
    }

    /**
     * Pointage à l'accueil : le donneur s'est présenté.
     */
    public function marquerHonore(RendezVousDon $rendezVous, ?Utilisateur $auteur = null): void
    {
        $rendezVous->setStatut(RendezVousDon::HONORE);
        $this->em->flush();

        $this->journal->tracer('RDV_HONORE', 'RendezVousDon', $rendezVous->getId(),
            'Donneur présenté : '.$rendezVous->getNumero(), $auteur);
    }

    /**
     * Pointage à l'accueil : le donneur ne s'est pas présenté. La place est
     * rendue au créneau, qui peut redevenir réservable.
     */
    public function marquerAbsent(RendezVousDon $rendezVous, ?Utilisateur $auteur = null): void
    {
        $rendezVous->setStatut(RendezVousDon::ABSENT);
        $this->libererPlace($rendezVous);
        $this->em->flush();

        $this->journal->tracer('RDV_ABSENT', 'RendezVousDon', $rendezVous->getId(),
            'Absence constatée : '.$rendezVous->getNumero(), $auteur);
    }

    /**
     * Vérifie qu'un donneur a le droit de réserver aujourd'hui.
     *
     * @throws \RuntimeException avec un message compréhensible par le donneur
     */
    public function verifierEligibilite(Donneur $donneur): void
    {
        if (!$donneur->isApte()) {
            throw new \RuntimeException($donneur->getMotifInaptitude()
                ?? 'Vous ne pouvez pas donner votre sang actuellement. '
                   .'Rapprochez-vous de la banque.');
        }

        $ageMin = $this->parametres->entier('age_min_don', 18);
        $ageMax = $this->parametres->entier('age_max_don', 65);
        $age = $donneur->getAge();

        if ($age < $ageMin || $age > $ageMax) {
            throw new \RuntimeException(sprintf(
                'Le don du sang est réservé aux personnes de %d à %d ans.', $ageMin, $ageMax));
        }

        $delai = $this->parametres->entier('delai_entre_dons_jours', 90);
        $jours = $donneur->joursAvantProchainDon($delai);

        if ($jours > 0) {
            throw new \RuntimeException(sprintf(
                'Votre dernier don est trop récent : vous pourrez redonner dans %d jour%s.',
                $jours, $jours > 1 ? 's' : ''));
        }

        if ($this->rendezVous->compterActifsPour($donneur) >= 1) {
            throw new \RuntimeException('Vous avez déjà un rendez-vous à venir. '
                .'Annulez-le ou déplacez-le avant d’en prendre un autre.');
        }
    }

    private function dejaInscrit(CreneauDon $creneau, Donneur $donneur): bool
    {
        foreach ($creneau->getRendezVous() as $rendezVous) {
            if ($rendezVous->getDonneur() === $donneur && $rendezVous->estActif()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Rend une place au créneau et le rouvre s'il était complet.
     */
    private function libererPlace(RendezVousDon $rendezVous): void
    {
        $creneau = $rendezVous->getCreneau();

        if (!$creneau) {
            return;
        }

        $creneau->setPlacesReservees(max(0, $creneau->getPlacesReservees() - 1));

        if (CreneauDon::COMPLET === $creneau->getStatut() && !$creneau->estPasse()) {
            $creneau->setStatut(CreneauDon::OUVERT);
        }
    }

    private function prochainNumero(): string
    {
        $annee = (int) date('Y');
        $valeur = $this->numerotation->incrementer(
            sprintf('RENDEZVOUS-%d', $annee), 'RDV', $annee);

        return sprintf('RDV-%d-%05d', $annee, $valeur);
    }

    private function prevenir(RendezVousDon $rendezVous, string $message): void
    {
        $telephone = $rendezVous->getDonneur()?->getTelephone();

        if ($telephone) {
            $this->sms->mettreEnFile($telephone, $message, 'RAPPEL');
        }
    }
}
