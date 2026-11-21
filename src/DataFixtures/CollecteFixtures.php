<?php

namespace App\DataFixtures;

use App\Entity\Banque;
use App\Entity\CreneauDon;
use App\Entity\Don;
use App\Entity\Donneur;
use App\Entity\GroupeSanguin;
use App\Entity\Poche;
use App\Entity\MouvementStock;
use App\Entity\Qualification;
use App\Entity\RendezVousDon;
use App\Entity\Stock;
use App\Entity\Compteur;
use App\Entity\Utilisateur;
use App\Service\NumerotationService;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Jeu de démonstration du module don : donneurs, créneaux, rendez-vous, dons
 * et qualifications.
 *
 * Un choix important pour que la démonstration reste cohérente : ces
 * fixtures ne créent aucune poche supplémentaire. Les dons conformes sont
 * rattachés à des poches déjà présentes dans le stock — celles que les
 * fixtures de démonstration ont déjà comptées — et un don écarté ne produit
 * rien du tout. Sans cette précaution, le stock affiché ne correspondrait plus
 * au nombre réel de poches.
 */
class CollecteFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    private const GRAINE = 20261002;

    /** Banques qui organisent des collectes. */
    private const BANQUES_COLLECTE = ['BQ-COT-001', 'BQ-COT-002', 'BQ-PNO-001', 'BQ-BOH-001'];

    /** Horaires proposés chaque jour ouvré. */
    private const PLAGES = [['08:00:00', '10:00:00'], ['10:00:00', '12:00:00'],
        ['15:00:00', '17:00:00']];

    private const CAPACITE = 8;

    /**
     * numéro, nom, prénom, sexe, naissance, téléphone, ville, groupe connu,
     * nombre de dons, email du compte (null = pas de compte en ligne).
     */
    /** Nombre de poches créées par un don conforme, pour la numérotation. */
    private int $pochesCreees = 0;

    /** Dernier numéro de rendez-vous attribué. */
    private int $dernierRdv = 0;

    /** @var array<string, int> dernier numéro de don attribué, par ville */
    private array $dernierDon = [];

    public function __construct(private readonly NumerotationService $numerotation)
    {
    }

    private const DONNEURS = [
        ['DN-2026-00001', 'SOSSOU', 'Armand', 'M', '1991-03-14', '+229 97 31 76 20',
            'Cotonou', 'O+', 6, 'donneur.sossou@hemolink.bj'],
        ['DN-2026-00002', 'TOGNON', 'Mireille', 'F', '1996-11-02', '+229 96 44 08 15',
            'Cotonou', 'A+', 3, 'donneur.tognon@hemolink.bj'],
        ['DN-2026-00003', 'ADJOVI', 'Cyrille', 'M', '1988-06-25', '+229 95 12 63 40',
            'Abomey-Calavi', 'B+', 9, null],
        ['DN-2026-00004', 'DANSOU', 'Prudencia', 'F', '2001-01-30', '+229 94 76 55 12',
            'Cotonou', 'O-', 2, null],
        ['DN-2026-00005', 'KPADONOU', 'Serge', 'M', '1979-09-08', '+229 97 08 22 71',
            'Porto-Novo', 'AB+', 11, null],
        ['DN-2026-00006', 'BIO SIKA', 'Nadège', 'F', '1994-04-17', '+229 96 31 90 44',
            'Parakou', 'A-', 4, null],
        // Premier don à venir : son groupe n'est pas encore connu, et c'est normal.
        ['DN-2026-00007', 'ZINSOU', 'Hervé', 'M', '1999-12-05', '+229 95 60 17 38',
            'Porto-Novo', null, 0, null],
        ['DN-2026-00008', 'GANDONOU', 'Reine', 'F', '1985-07-21', '+229 97 45 83 06',
            'Cotonou', 'O+', 7, null],
    ];

    /** Personnel habilité, par banque. */
    private const BIOLOGISTES = [
        'BQ-COT-001' => 'biologiste.cnts@hemolink.bj',
        'BQ-COT-002' => 'biologiste.cnhu@hemolink.bj',
    ];

    public function load(ObjectManager $manager): void
    {
        mt_srand(self::GRAINE);

        $aujourdhui = new \DateTimeImmutable('today');
        $maintenant = new \DateTimeImmutable();

        /** @var array<string, Banque> $banques */
        $banques = [];
        foreach (self::BANQUES_COLLECTE as $code) {
            $banques[$code] = $this->getReference(BanqueFixtures::BANQUE.$code, Banque::class);
        }

        $donneurs = $this->creerDonneurs($manager, $maintenant);
        $creneaux = $this->creerCreneaux($manager, $banques, $aujourdhui);
        $rendezVous = $this->creerRendezVous($manager, $donneurs, $creneaux, $maintenant);
        $this->creerDons($manager, $donneurs, $rendezVous, $maintenant);
        $this->amorcerCompteurs($manager, $rendezVous, $maintenant);

        $manager->flush();
    }

    /**
     * @return array<int, Donneur>
     */
    private function creerDonneurs(ObjectManager $manager, \DateTimeImmutable $maintenant): array
    {
        $donneurs = [];

        foreach (self::DONNEURS as [$numero, $nom, $prenom, $sexe, $naissance, $telephone,
            $ville, $groupe, $nombreDons, $email]) {
            $donneur = (new Donneur())
                ->setNumeroDonneur($numero)
                ->setNom($nom)
                ->setPrenom($prenom)
                ->setSexe($sexe)
                ->setDateNaissance(new \DateTimeImmutable($naissance))
                ->setTelephone($telephone)
                ->setVille($ville)
                ->setNombreDons($nombreDons)
                ->setApte(true)
                ->setDateCreation($maintenant->modify('-'.(120 + $nombreDons * 30).' days'));

            if ($groupe) {
                $donneur->setGroupeSanguin(
                    $this->getReference(ReferenceFixtures::GROUPE.$groupe, GroupeSanguin::class));
            }

            if ($email) {
                $compte = $this->getReference(BanqueFixtures::COMPTE.$email, Utilisateur::class);
                $donneur->setUtilisateur($compte)->setEmail($email);
            }

            // Un dernier don assez ancien pour que la plupart soient à nouveau
            // éligibles : la démonstration doit permettre de réserver.
            if ($nombreDons > 0) {
                $donneur->setDernierDon($maintenant->modify('-'.(95 + 7 * \count($donneurs)).' days'));
            }

            $manager->persist($donneur);
            $donneurs[] = $donneur;
        }

        return $donneurs;
    }

    /**
     * Ouvre les créneaux de J-7 à J+14 : pas de dimanche, samedi matin seul.
     *
     * @param array<string, Banque> $banques
     *
     * @return array<int, CreneauDon>
     */
    private function creerCreneaux(ObjectManager $manager, array $banques,
        \DateTimeImmutable $aujourdhui): array
    {
        $creneaux = [];

        foreach ($banques as $banque) {
            for ($jour = -7; $jour <= 14; ++$jour) {
                $date = $aujourdhui->modify(sprintf('%+d days', $jour));
                $semaine = (int) $date->format('N');

                if (7 === $semaine) {
                    continue;
                }

                $plages = 6 === $semaine ? [self::PLAGES[0]] : self::PLAGES;

                foreach ($plages as [$debut, $fin]) {
                    $creneau = (new CreneauDon())
                        ->setBanque($banque)
                        ->setDateCreneau($date)
                        ->setHeureDebut($debut)
                        ->setHeureFin($fin)
                        ->setCapacite(self::CAPACITE)
                        ->setPlacesReservees(0)
                        ->setStatut($date < $aujourdhui ? CreneauDon::CLOS : CreneauDon::OUVERT)
                        ->setDateCreation($aujourdhui->modify('-21 days'));

                    $manager->persist($creneau);
                    $creneaux[] = $creneau;
                }
            }
        }

        // Une collecte mobile, pour montrer que le lieu peut sortir des murs.
        $mobile = (new CreneauDon())
            ->setBanque($banques['BQ-COT-001'])
            ->setDateCreneau($aujourdhui->modify('+10 days'))
            ->setHeureDebut('09:00:00')
            ->setHeureFin('13:00:00')
            ->setCapacite(20)
            ->setPlacesReservees(0)
            ->setLieu('Collecte mobile — Place de l’Amazone, Cotonou')
            ->setStatut(CreneauDon::OUVERT)
            ->setDateCreation($aujourdhui->modify('-14 days'));

        $manager->persist($mobile);
        $creneaux[] = $mobile;

        return $creneaux;
    }

    /**
     * @param array<int, Donneur>    $donneurs
     * @param array<int, CreneauDon> $creneaux
     *
     * @return array<int, RendezVousDon>
     */
    private function creerRendezVous(ObjectManager $manager, array $donneurs, array $creneaux,
        \DateTimeImmutable $maintenant): array
    {
        $passes = array_values(array_filter($creneaux,
            static fn (CreneauDon $c) => CreneauDon::CLOS === $c->getStatut()));
        $aVenir = array_values(array_filter($creneaux,
            static fn (CreneauDon $c) => CreneauDon::OUVERT === $c->getStatut()));

        $rendezVous = [];
        $numero = 0;

        // Rendez-vous passés : la plupart honorés, une absence, une annulation.
        foreach (array_slice($passes, 0, 18) as $index => $creneau) {
            $statut = match ($index) {
                4 => RendezVousDon::ANNULE,
                11 => RendezVousDon::ABSENT,
                default => RendezVousDon::HONORE,
            };

            $rendezVous[] = $this->rendezVous($manager, $creneau,
                $donneurs[$index % \count($donneurs)], $statut, ++$numero, $maintenant);
        }

        // Rendez-vous à venir, dont ceux des deux donneurs qui ont un compte.
        foreach (array_slice($aVenir, 2, 5) as $index => $creneau) {
            $rendezVous[] = $this->rendezVous($manager, $creneau,
                $donneurs[($index + 1) % \count($donneurs)], RendezVousDon::PLANIFIE,
                ++$numero, $maintenant);
        }

        return $rendezVous;
    }

    private function rendezVous(ObjectManager $manager, CreneauDon $creneau, Donneur $donneur,
        string $statut, int $numero, \DateTimeImmutable $maintenant): RendezVousDon
    {
        $rendezVous = (new RendezVousDon())
            ->setNumero(sprintf('RDV-%s-%05d', $maintenant->format('Y'), $numero))
            ->setDonneur($donneur)
            ->setCreneau($creneau)
            ->setStatut($statut)
            ->setDateCreation($creneau->getDebut()->modify('-6 days'));

        if (RendezVousDon::ANNULE === $statut) {
            $rendezVous->setDateAnnulation($creneau->getDebut()->modify('-2 days'))
                ->setCommentaire('Annulé par le donneur');
        }

        // Une place n'est décomptée que si le rendez-vous tient toujours :
        // une annulation ou une absence rend la place au créneau.
        if (\in_array($statut, [RendezVousDon::PLANIFIE, RendezVousDon::HONORE], true)) {
            $creneau->setPlacesReservees($creneau->getPlacesReservees() + 1);
        }

        $manager->persist($rendezVous);

        return $rendezVous;
    }

    /**
     * Crée les dons des rendez-vous honorés, puis leur qualification.
     *
     * @param array<int, Donneur>       $donneurs
     * @param array<int, RendezVousDon> $rendezVous
     */
    private function creerDons(ObjectManager $manager, array $donneurs,
        array $rendezVous, \DateTimeImmutable $maintenant): void
    {
        /** @var array<int, true> $pochesUtilisees */
        $pochesUtilisees = [];

        $honores = array_values(array_filter($rendezVous,
            static fn (RendezVousDon $r) => RendezVousDon::HONORE === $r->getStatut()));

        foreach ($honores as $index => $rdv) {
            $donneur = $rdv->getDonneur();
            $banque = $rdv->getCreneau()->getBanque();
            $groupe = $donneur->getGroupeSanguin();

            // Premier don : le groupe n'est pas connu à l'accueil, c'est le
            // laboratoire qui le détermine. On le fixe ici pour que la
            // qualification puisse rattacher une poche, et on le reporte sur
            // la fiche du donneur comme le ferait le personnel habilité.
            if (!$groupe) {
                $groupe = $this->getReference(ReferenceFixtures::GROUPE.'O+', GroupeSanguin::class);
                $donneur->setGroupeSanguin($groupe);
            }

            $don = (new Don())
                ->setCodeDon($this->codeDon($banque, $maintenant))
                ->setDonneur($donneur)
                ->setBanque($banque)
                ->setRendezVous($rdv)
                ->setGroupeSanguin($groupe)
                ->setDateDon($rdv->getCreneau()->getDebut()->modify('+30 minutes'))
                ->setVolumeMl(450)
                ->setHemoglobine(number_format(12.4 + mt_rand(0, 25) / 10, 1, '.', ''))
                ->setDateCreation($rdv->getCreneau()->getDebut()->modify('+30 minutes'));

            $biologiste = $this->biologistePour($banque);

            // Les deux derniers dons restent en qualification : la file du
            // laboratoire ne doit pas être vide dans la démonstration.
            if ($index >= \count($honores) - 2 || !$biologiste) {
                $don->setStatut(Don::EN_QUALIFICATION);
                $manager->persist($don);

                continue;
            }

            // Deux dons écartés, pour montrer qu'ils ne rejoignent pas le stock.
            $rejete = \in_array($index, [3, 8], true);
            $don->setStatut($rejete ? Don::REJETE : Don::QUALIFIE);
            $manager->persist($don);

            $qualification = (new Qualification())
                ->setDon($don)
                ->setPersonnel($biologiste)
                ->setGroupeSanguin($groupe)
                ->setDateQualification($don->getDateDon()->modify('+2 days'))
                ->setVih(Qualification::NEGATIF)
                ->setHepatiteB(3 === $index ? Qualification::POSITIF : Qualification::NEGATIF)
                ->setHepatiteC(Qualification::NEGATIF)
                ->setSyphilis(8 === $index ? Qualification::POSITIF : Qualification::NEGATIF);

            if ($rejete) {
                $qualification
                    ->setResultat(Qualification::NON_CONFORME)
                    ->setMotifRejet(3 === $index
                        ? 'Analyse positive : Hépatite B'
                        : 'Analyse positive : Syphilis');
            } else {
                // Règle DON-05 : un don conforme entre au stock, donc il porte
                // toujours une poche. Aucune exception possible, sinon la
                // traçabilité du produit est rompue.
                $qualification->setResultat(Qualification::CONFORME);
                $qualification->setPoche($this->pocheLibre($manager, $banque, $groupe,
                    $pochesUtilisees, $don->getDateDon()));
            }

            $manager->persist($qualification);
        }
    }

    /**
     * Code du don, au format exact que produit `DonService` :
     * DON-{ville}-{annee}-{numero}. La démonstration et l'application doivent
     * numéroter de la même façon.
     */
    private function codeDon(Banque $banque, \DateTimeImmutable $maintenant): string
    {
        $ville = $this->numerotation->codeVille($banque);
        $annee = (int) $maintenant->format('Y');
        $this->dernierDon[$ville] = ($this->dernierDon[$ville] ?? 0) + 1;

        return sprintf('DON-%s-%d-%04d', $ville, $annee, $this->dernierDon[$ville]);
    }

    /**
     * Positionne les compteurs des rendez-vous et des dons sur les derniers
     * numéros écrits, pour que le premier enregistrement fait depuis
     * l'application ne heurte pas la contrainte d'unicité.
     *
     * @param array<int, RendezVousDon> $rendezVous
     */
    private function amorcerCompteurs(ObjectManager $manager, array $rendezVous,
        \DateTimeImmutable $maintenant): void
    {
        $annee = (int) $maintenant->format('Y');

        $manager->persist(
            (new Compteur())
                ->setCle(sprintf('RENDEZVOUS-%d', $annee))
                ->setPrefixe('RDV')
                ->setAnnee($annee)
                ->setValeur(\count($rendezVous))
                ->setDateMaj($maintenant)
        );

        foreach ($this->dernierDon as $ville => $valeur) {
            $manager->persist(
                (new Compteur())
                    ->setCle(sprintf('DON-%s-%d', $ville, $annee))
                    ->setPrefixe('DON-'.$ville)
                    ->setAnnee($annee)
                    ->setValeur($valeur)
                    ->setDateMaj($maintenant)
            );
        }
    }

    /**
     * Fournit la poche du don conforme.
     *
     * On réutilise d'abord une poche déjà présente dans le stock de la banque,
     * pour ne pas gonfler artificiellement les quantités de démonstration. Si
     * le groupe est rare et qu'aucune poche n'est libre, on en crée une et on
     * incrémente la ligne de stock : c'est exactement ce que fait
     * `StockService::enregistrerEntree()` en production.
     *
     * @param array<int, true> $dejaUtilisees
     */
    private function pocheLibre(ObjectManager $manager, Banque $banque, GroupeSanguin $groupe,
        array &$dejaUtilisees, \DateTimeInterface $dateDon): Poche
    {
        $candidates = $manager->getRepository(Poche::class)->findBy([
            'banque' => $banque,
            'groupeSanguin' => $groupe,
            'statut' => Poche::DISPONIBLE,
        ], ['id' => 'ASC'], 80);

        foreach ($candidates as $poche) {
            if (!isset($dejaUtilisees[$poche->getId()])) {
                $dejaUtilisees[$poche->getId()] = true;

                return $poche;
            }
        }

        return $this->creerPoche($manager, $banque, $groupe, $dateDon);
    }

    /**
     * Crée une poche issue du don et met la ligne de stock à jour.
     */
    private function creerPoche(ObjectManager $manager, Banque $banque, GroupeSanguin $groupe,
        \DateTimeInterface $dateDon): Poche
    {
        $prelevement = \DateTimeImmutable::createFromInterface($dateDon);

        $poche = (new Poche())
            ->setCodePoche(sprintf('%s-D%05d', substr($banque->getCode(), 3, 3),
                ++$this->pochesCreees))
            ->setDatePrelevement($prelevement)
            ->setDatePeremption($prelevement->modify('+42 days'))
            ->setVolumeMl(450)
            ->setStatut(Poche::DISPONIBLE)
            ->setDateCreation($prelevement)
            ->setBanque($banque)
            ->setGroupeSanguin($groupe);

        $manager->persist($poche);

        $stock = $manager->getRepository(Stock::class)
            ->findOneBy(['banque' => $banque, 'groupeSanguin' => $groupe]);

        if ($stock) {
            $quantite = $stock->getQuantiteDisponible() + 1;

            $stock
                ->setQuantiteDisponible($quantite)
                ->setEtat(match (true) {
                    0 === $quantite => Stock::RUPTURE,
                    $quantite < 5 => Stock::TENSION,
                    default => Stock::DISPONIBLE,
                })
                ->setDateMaj($prelevement);

            $manager->persist(
                (new MouvementStock())
                    ->setType(MouvementStock::ENTREE)
                    ->setQuantite(1)
                    ->setQuantiteApres($quantite)
                    ->setMotif('Poche issue d\'un don qualifié conforme')
                    ->setDateMouvement($prelevement)
                    ->setBanque($banque)
                    ->setGroupeSanguin($groupe)
                    ->setPoche($poche)
            );
        }

        return $poche;
    }

    private function biologistePour(Banque $banque): ?Utilisateur
    {
        $email = self::BIOLOGISTES[$banque->getCode()] ?? null;

        return $email
            ? $this->getReference(BanqueFixtures::COMPTE.$email, Utilisateur::class)
            : null;
    }

    public function getDependencies(): array
    {
        // Les poches de démonstration doivent exister avant qu'un don conforme
        // puisse s'y rattacher.
        return [ReferenceFixtures::class, BanqueFixtures::class, DemonstrationFixtures::class];
    }

    public static function getGroups(): array
    {
        return ['demonstration'];
    }
}
