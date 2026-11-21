<?php

namespace App\Service;

use App\Entity\Banque;
use App\Entity\Don;
use App\Entity\Donneur;
use App\Entity\GroupeSanguin;
use App\Entity\Poche;
use App\Entity\Qualification;
use App\Entity\RendezVousDon;
use App\Entity\Utilisateur;
use App\Exception\RegleMetier;
use App\Repository\ParametreRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Dons et qualification des produits sanguins.
 *
 * Règle intangible (DON-05 du cahier des charges) : un don ne devient du stock
 * utilisable qu'après une qualification CONFORME prononcée par un personnel
 * habilité. Tant que les analyses ne sont pas rendues, la poche n'existe pas —
 * elle n'est pas « en attente » dans un coin du stock, elle n'est pas créée.
 * C'est la seule façon d'être certain qu'aucun produit non qualifié ne puisse
 * être réservé par une précommande.
 */
class DonService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly StockService $stocks,
        private readonly JournalService $journal,
        private readonly SmsNotifier $sms,
        private readonly ParametreRepository $parametres,
        private readonly NumerotationService $numerotation,
    ) {
    }

    /**
     * Enregistre un prélèvement réalisé, avec ou sans rendez-vous.
     */
    public function enregistrer(
        Donneur $donneur,
        Banque $banque,
        ?RendezVousDon $rendezVous = null,
        ?GroupeSanguin $groupeDeclare = null,
        ?float $hemoglobine = null,
        int $volumeMl = 450,
        ?Utilisateur $auteur = null,
        ?string $observation = null,
    ): Don {
        $maintenant = new \DateTimeImmutable();

        $don = (new Don())
            ->setCodeDon($this->prochainCode($banque))
            ->setDonneur($donneur)
            ->setBanque($banque)
            ->setRendezVous($rendezVous)
            ->setGroupeSanguin($groupeDeclare ?? $donneur->getGroupeSanguin())
            ->setDateDon($maintenant)
            ->setVolumeMl($volumeMl)
            ->setHemoglobine(null !== $hemoglobine ? number_format($hemoglobine, 1, '.', '') : null)
            ->setStatut(Don::EN_QUALIFICATION)
            ->setUtilisateur($auteur)
            ->setObservation($observation)
            ->setDateCreation($maintenant);

        $this->em->persist($don);

        if ($rendezVous && RendezVousDon::HONORE !== $rendezVous->getStatut()) {
            $rendezVous->setStatut(RendezVousDon::HONORE);
        }

        $donneur
            ->setDernierDon($maintenant)
            ->setNombreDons($donneur->getNombreDons() + 1);

        $this->em->flush();

        $this->journal->tracer('DON_ENREGISTRE', 'Don', $don->getId(),
            sprintf('Don %s de %s (%d ml) — en attente de qualification',
                $don->getCodeDon(), $donneur->getNomComplet(), $volumeMl), $auteur);

        $delai = $this->parametres->entier('delai_entre_dons_jours', 90);

        $this->sms->mettreEnFile($donneur->getTelephone(), sprintf(
            'HemoLink : merci pour votre don %s. Vous pourrez redonner dans %d jours. '
            .'Buvez et reposez-vous aujourd hui.', $don->getCodeDon(), $delai), 'AUTRE');

        return $don;
    }

    /**
     * Prononce la qualification d'un don.
     *
     * @param array<string, string> $tests resultats des analyses, par exemple
     *                                     ['vih' => 'NEGATIF', 'hepatiteB' => 'NEGATIF']
     */
    public function qualifier(
        Don $don,
        Utilisateur $personnel,
        array $tests,
        ?GroupeSanguin $groupeConfirme = null,
        ?string $motifRejet = null,
    ): Qualification {
        if (!$don->estQualifiable()) {
            throw new RegleMetier('Ce don a déjà été qualifié.');
        }

        if (!\in_array(Utilisateur::ROLE_PERSONNEL, $personnel->getRoles(), true)
            && !\in_array(Utilisateur::ROLE_ADMIN, $personnel->getRoles(), true)) {
            throw new RegleMetier(
                'Seul un personnel habilité peut qualifier un produit sanguin.');
        }

        $maintenant = new \DateTimeImmutable();
        $groupe = $groupeConfirme ?? $don->getGroupeSanguin();

        $qualification = (new Qualification())
            ->setDon($don)
            ->setPersonnel($personnel)
            ->setGroupeSanguin($groupe)
            ->setDateQualification($maintenant)
            ->setVih($tests['vih'] ?? Qualification::NON_FAIT)
            ->setHepatiteB($tests['hepatiteB'] ?? Qualification::NON_FAIT)
            ->setHepatiteC($tests['hepatiteC'] ?? Qualification::NON_FAIT)
            ->setSyphilis($tests['syphilis'] ?? Qualification::NON_FAIT);

        $positifs = $qualification->testsPositifs();
        $conforme = !$positifs && null === $motifRejet && null !== $groupe;

        if (!$conforme) {
            $raison = $motifRejet
                ?? ($positifs
                    ? 'Analyse positive : '.implode(', ', $positifs)
                    : 'Groupe sanguin non déterminé');

            $qualification
                ->setResultat(Qualification::NON_CONFORME)
                ->setMotifRejet($raison);

            $don->setStatut(Don::REJETE);

            $this->em->persist($qualification);
            $this->em->flush();

            $this->journal->tracer('QUALIFICATION', 'Don', $don->getId(), sprintf(
                'Don %s déclaré NON CONFORME (%s) : aucune poche mise en stock',
                $don->getCodeDon(), $raison), $personnel);

            $this->prevenirDonneur($don, false);

            return $qualification;
        }

        // Produit conforme : c'est seulement ici qu'une poche entre au stock.
        $poches = $this->stocks->enregistrerEntree(
            $don->getBanque(),
            $groupe,
            1,
            $don->getDateDon(),
            $personnel,
            sprintf('Qualification conforme du don %s', $don->getCodeDon()),
            $don->getVolumeMl(),
        );

        $poche = $poches[0] ?? null;

        $qualification
            ->setResultat(Qualification::CONFORME)
            ->setPoche($poche);

        $don->setStatut(Don::QUALIFIE);

        if ($poche instanceof Poche) {
            $poche->setDatePrelevement($don->getDateDon());
        }

        // Le groupe du donneur est désormais connu de façon sûre.
        $donneur = $don->getDonneur();
        if ($donneur && null === $donneur->getGroupeSanguin()) {
            $donneur->setGroupeSanguin($groupe);
        }

        $this->em->persist($qualification);
        $this->em->flush();

        $this->journal->tracer('QUALIFICATION', 'Don', $don->getId(), sprintf(
            'Don %s qualifié CONFORME (%s) : poche %s mise en stock',
            $don->getCodeDon(), $groupe->getCode(), $poche?->getCodePoche() ?? '—'), $personnel);

        $this->prevenirDonneur($don, true);

        return $qualification;
    }

    /**
     * Le donneur est prévenu dans les deux cas, mais jamais par SMS d'un
     * résultat d'analyse : un résultat se donne en consultation, pas sur un
     * écran de téléphone qui peut être lu par n'importe qui.
     */
    private function prevenirDonneur(Don $don, bool $conforme): void
    {
        $donneur = $don->getDonneur();

        if (!$donneur) {
            return;
        }

        $message = $conforme
            ? sprintf('HemoLink : votre don %s a ete valide et rejoint la reserve. '
                .'Merci, vous venez d aider un patient.', $don->getCodeDon())
            : sprintf('HemoLink : merci pour votre don %s. Veuillez contacter la banque '
                .'de sang au %s, un entretien medical est necessaire.',
                $don->getCodeDon(),
                $don->getBanque()?->getTelephone() ?? '+229 21 31 20 40');

        $this->sms->mettreEnFile($donneur->getTelephone(), $message, 'AUTRE');
    }

    private function prochainCode(Banque $banque): string
    {
        $annee = (int) date('Y');
        $ville = $this->numerotation->codeVille($banque);
        $valeur = $this->numerotation->incrementer(
            sprintf('DON-%s-%d', $ville, $annee), 'DON-'.$ville, $annee);

        return sprintf('DON-%s-%d-%04d', $ville, $annee, $valeur);
    }
}
