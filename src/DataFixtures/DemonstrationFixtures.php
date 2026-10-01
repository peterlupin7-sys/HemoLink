<?php

namespace App\DataFixtures;

use App\Entity\Banque;
use App\Entity\Demandeur;
use App\Entity\GroupeSanguin;
use App\Entity\LignePrecommande;
use App\Entity\MouvementStock;
use App\Entity\NotificationSms;
use App\Entity\Poche;
use App\Entity\Precommande;
use App\Entity\Stock;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Jeu de démonstration : poches en stock, demandeurs, précommandes.
 *
 * Le tirage est volontairement reproductible (graine fixe) : deux chargements
 * successifs donnent exactement la même base, ce qui rend les copies d'écran
 * du mémoire et les démonstrations devant le jury prévisibles.
 *
 * Point important : les quantités de la table `stock` ne sont jamais inventées,
 * elles sont comptées à partir des poches réellement créées. C'est la règle de
 * gestion n°1 du projet, et elle vaut aussi pour les fixtures.
 */
class DemonstrationFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    private const GRAINE = 20261001;

    /** Volume d'un concentré de globules rouges, en millilitres. */
    private const VOLUME_POCHE = 450;

    /** Durée de conservation d'une poche, en jours. */
    private const CONSERVATION = 42;

    /**
     * Niveau de remplissage par banque : nombre de poches visé pour un groupe
     * « courant ». Les banques de référence sont mieux approvisionnées.
     */
    private const NIVEAUX = [
        'BQ-COT-001' => 22, 'BQ-COT-002' => 16, 'BQ-COT-003' => 7, 'BQ-COT-004' => 5,
        'BQ-ABC-001' => 8, 'BQ-PNO-001' => 12, 'BQ-PNO-002' => 4, 'BQ-BOH-001' => 13,
        'BQ-ZOU-001' => 8, 'BQ-MON-001' => 7, 'BQ-ATA-001' => 6, 'BQ-ATA-002' => 4,
    ];

    /**
     * nom, type, responsable, téléphone, ville, agrément.
     */
    private const DEMANDEURS = [
        ['Clinique Mahouna', 'ETABLISSEMENT', 'Dr HOUNSA Gilbert', '+229 97 02 14 58', 'Cotonou', 'AGR-2024-118'],
        ['Hôpital de zone de Suru-Léré', 'ETABLISSEMENT', 'Dr BIO Chabi', '+229 96 33 47 20', 'Cotonou', 'AGR-2023-092'],
        ['Centre de santé d’Akpakpa', 'ETABLISSEMENT', 'Mme AHOYO Carole', '+229 95 61 08 77', 'Cotonou', 'AGR-2025-207'],
        ['Clinique de la Paix', 'ETABLISSEMENT', 'Dr SOGLO Armand', '+229 94 85 12 36', 'Porto-Novo', 'AGR-2024-143'],
        ['ADJIBI Rachelle', 'PARTICULIER', null, '+229 97 54 31 09', 'Cotonou', null],
        ['GNONLONFOUN Pascal', 'PARTICULIER', null, '+229 96 77 90 42', 'Abomey-Calavi', null],
    ];

    public function load(ObjectManager $manager): void
    {
        mt_srand(self::GRAINE);

        $maintenant = new \DateTimeImmutable();

        /** @var array<string, GroupeSanguin> $groupes */
        $groupes = [];
        foreach (array_keys(ReferenceFixtures::GROUPES) as $code) {
            $groupes[$code] = $this->getReference(ReferenceFixtures::GROUPE.$code, GroupeSanguin::class);
        }

        /** @var array<string, Banque> $banques */
        $banques = [];
        foreach (self::NIVEAUX as $code => $niveau) {
            $banques[$code] = $this->getReference(BanqueFixtures::BANQUE.$code, Banque::class);
        }

        $poches = $this->creerStocks($manager, $banques, $groupes, $maintenant);
        $demandeurs = $this->creerDemandeurs($manager, $maintenant);

        $this->creerPrecommandes($manager, $banques, $groupes, $demandeurs, $poches, $maintenant);

        $manager->flush();
    }

    /**
     * Crée les poches puis les lignes de stock qui les comptent.
     *
     * @param array<string, Banque>        $banques
     * @param array<string, GroupeSanguin> $groupes
     *
     * @return array<string, array<int, Poche>> poches disponibles, indexées « codeBanque|groupe »
     */
    private function creerStocks(
        ObjectManager $manager,
        array $banques,
        array $groupes,
        \DateTimeImmutable $maintenant,
    ): array {
        $disponibles = [];
        $compteurPoche = 0;

        foreach ($banques as $code => $banque) {
            $niveau = self::NIVEAUX[$code];

            foreach ($groupes as $codeGroupe => $groupe) {
                // Les groupes rares sont proportionnellement moins nombreux.
                $part = (float) ReferenceFixtures::GROUPES[$codeGroupe][2];
                $cible = (int) round($niveau * $part / 25);
                $cible = max(0, $cible + mt_rand(-2, 2));

                // Une banque sur huit n'a pas communiqué son stock du jour.
                $communique = 0 !== mt_rand(0, 7);
                $dateMaj = $communique
                    ? $maintenant->modify(sprintf('-%d hours', mt_rand(0, 20)))
                    : $maintenant->modify(sprintf('-%d hours', mt_rand(26, 72)));

                $cle = $code.'|'.$codeGroupe;
                $disponibles[$cle] = [];

                for ($i = 0; $i < $cible; ++$i) {
                    $age = mt_rand(0, self::CONSERVATION - 3);
                    $prelevement = $maintenant->modify(sprintf('-%d days', $age));

                    $poche = (new Poche())
                        ->setCodePoche(sprintf('%s-%06d', substr($code, 3, 3), ++$compteurPoche))
                        ->setDatePrelevement($prelevement)
                        ->setDatePeremption($prelevement->modify('+'.self::CONSERVATION.' days'))
                        ->setVolumeMl(self::VOLUME_POCHE)
                        ->setStatut(Poche::DISPONIBLE)
                        ->setDateCreation($prelevement)
                        ->setBanque($banque)
                        ->setGroupeSanguin($groupe);

                    $manager->persist($poche);
                    $disponibles[$cle][] = $poche;
                }

                $quantite = \count($disponibles[$cle]);

                $stock = (new Stock())
                    ->setBanque($banque)
                    ->setGroupeSanguin($groupe)
                    ->setQuantiteDisponible($quantite)
                    ->setQuantiteReservee(0)
                    ->setSeuilAlerte(5)
                    ->setEtat($this->etat($quantite, $communique))
                    ->setDateMaj($dateMaj);

                $manager->persist($stock);

                if ($quantite > 0) {
                    $manager->persist(
                        (new MouvementStock())
                            ->setType(MouvementStock::ENTREE)
                            ->setQuantite($quantite)
                            ->setQuantiteApres($quantite)
                            ->setMotif('Collecte initiale enregistrée au chargement du jeu de démonstration')
                            ->setDateMouvement($dateMaj)
                            ->setBanque($banque)
                            ->setGroupeSanguin($groupe)
                    );
                }
            }
        }

        return $disponibles;
    }

    private function etat(int $quantite, bool $communique): string
    {
        if (!$communique) {
            return Stock::NON_COMMUNIQUE;
        }

        if (0 === $quantite) {
            return Stock::RUPTURE;
        }

        return $quantite < 5 ? Stock::TENSION : Stock::DISPONIBLE;
    }

    /**
     * @return array<int, Demandeur>
     */
    private function creerDemandeurs(ObjectManager $manager, \DateTimeImmutable $maintenant): array
    {
        $demandeurs = [];

        foreach (self::DEMANDEURS as [$nom, $type, $responsable, $telephone, $ville, $agrement]) {
            $demandeur = (new Demandeur())
                ->setType($type)
                ->setNom($nom)
                ->setResponsable($responsable)
                ->setTelephone($telephone)
                ->setEmail(null)
                ->setVille($ville)
                ->setNumeroAgrement($agrement)
                ->setDateCreation($maintenant->modify(sprintf('-%d days', mt_rand(5, 90))));

            $manager->persist($demandeur);
            $demandeurs[] = $demandeur;
        }

        return $demandeurs;
    }

    /**
     * Sept précommandes couvrant tous les états du parcours, pour que la
     * soutenance puisse montrer chaque écran sans rien saisir.
     *
     * @param array<string, Banque>            $banques
     * @param array<string, GroupeSanguin>     $groupes
     * @param array<int, Demandeur>            $demandeurs
     * @param array<string, array<int, Poche>> $poches
     */
    private function creerPrecommandes(
        ObjectManager $manager,
        array $banques,
        array $groupes,
        array $demandeurs,
        array $poches,
        \DateTimeImmutable $maintenant,
    ): array {
        $scenarios = [
            // banque, demandeur, statut, urgence, heures écoulées, lignes [groupe => quantité], motif
            ['BQ-COT-001', 0, Precommande::EN_ATTENTE, 'URGENTE', 3, ['O+' => 2], 'Intervention chirurgicale programmée'],
            ['BQ-COT-001', 4, Precommande::CONFIRMEE, 'NORMALE', 9, ['A+' => 1], 'Transfusion pour anémie sévère'],
            ['BQ-COT-002', 1, Precommande::PRETE, 'VITALE', 14, ['O-' => 2, 'O+' => 1], 'Hémorragie du post-partum'],
            ['BQ-COT-002', 2, Precommande::RETIREE, 'NORMALE', 50, ['B+' => 2], 'Drépanocytose, transfusion de routine'],
            ['BQ-PNO-001', 3, Precommande::CONFIRMEE, 'URGENTE', 6, ['AB+' => 1, 'A-' => 1], 'Accident de la voie publique'],
            ['BQ-PNO-001', 5, Precommande::ANNULEE, 'NORMALE', 72, ['O+' => 1], 'Patient transféré vers un autre centre'],
            ['BQ-BOH-001', 0, Precommande::EXPIREE, 'NORMALE', 96, ['A+' => 2], 'Précommande non retirée dans les délais'],
        ];

        $compteurs = ['COT' => 0, 'PNO' => 0, 'BOH' => 0];
        $creees = [];

        foreach ($scenarios as [$codeBanque, $indexDemandeur, $statut, $urgence, $heures, $lignes, $motif]) {
            $banque = $banques[$codeBanque];
            $prefixe = substr($codeBanque, 3, 3);
            $numero = sprintf('PRE-%s-%d-%05d', $prefixe, (int) $maintenant->format('Y'),
                ++$compteurs[$prefixe]);

            $creation = $maintenant->modify(sprintf('-%d hours', $heures));

            $precommande = (new Precommande())
                ->setNumero($numero)
                ->setStatut($statut)
                ->setUrgence($urgence)
                ->setDateSouhaitee($creation->modify('+1 day'))
                ->setMotif($motif)
                ->setReferencePatient(sprintf('PAT-%04d', mt_rand(1000, 9999)))
                ->setDateCreation($creation)
                ->setBanque($banque)
                ->setDemandeur($demandeurs[$indexDemandeur]);

            if (\in_array($statut, [Precommande::CONFIRMEE, Precommande::PRETE, Precommande::RETIREE], true)) {
                $precommande->setDateConfirmation($creation->modify('+45 minutes'));
            }

            if (Precommande::RETIREE === $statut) {
                $precommande->setDateRetrait($creation->modify('+6 hours'));
            }

            if (Precommande::ANNULEE === $statut) {
                $precommande->setDateAnnulation($creation->modify('+2 hours'));
                $precommande->setCommentaire('Annulée par la banque : patient transféré.');
            }

            foreach ($lignes as $codeGroupe => $quantite) {
                $ligne = (new LignePrecommande())
                    ->setGroupeSanguin($groupes[$codeGroupe])
                    ->setQuantiteDemandee($quantite);

                if (\in_array($statut, [Precommande::CONFIRMEE, Precommande::PRETE,
                    Precommande::RETIREE], true)) {
                    $ligne->setQuantiteConfirmee($quantite);
                }

                $precommande->addLigne($ligne);
                $manager->persist($ligne);

                // Réservation effective des poches, du plus proche péremption au plus loin.
                if (\in_array($statut, [Precommande::PRETE, Precommande::RETIREE], true)) {
                    $this->reserver($poches, $codeBanque, $codeGroupe, $quantite, $precommande,
                        Precommande::RETIREE === $statut ? Poche::DELIVREE : Poche::RESERVEE);
                }
            }

            $manager->persist($precommande);
            $creees[] = $precommande;

            $this->notifier($manager, $precommande, $statut, $creation);
        }

        return $creees;
    }

    /**
     * @param array<string, array<int, Poche>> $poches
     */
    private function reserver(
        array &$poches,
        string $codeBanque,
        string $codeGroupe,
        int $quantite,
        Precommande $precommande,
        string $statut,
    ): void {
        $cle = $codeBanque.'|'.$codeGroupe;

        if (empty($poches[$cle])) {
            return;
        }

        usort($poches[$cle], static fn (Poche $a, Poche $b) => $a->getDatePeremption() <=> $b->getDatePeremption());

        for ($i = 0; $i < $quantite && $poches[$cle]; ++$i) {
            $poche = array_shift($poches[$cle]);
            $poche->setStatut($statut)->setPrecommande($precommande);
        }
    }

    private function notifier(
        ObjectManager $manager,
        Precommande $precommande,
        string $statut,
        \DateTimeImmutable $creation,
    ): void {
        $messages = [
            Precommande::EN_ATTENTE => 'HemoLink : precommande %s enregistree. Vous recevrez un SMS des confirmation.',
            Precommande::CONFIRMEE => 'HemoLink : precommande %s confirmee par la banque de sang.',
            Precommande::PRETE => 'HemoLink : precommande %s prete au retrait. Munissez-vous du bon.',
            Precommande::RETIREE => 'HemoLink : precommande %s retiree. Merci.',
            Precommande::ANNULEE => 'HemoLink : precommande %s annulee. Contactez la banque au 21312040.',
            Precommande::EXPIREE => 'HemoLink : precommande %s expiree faute de retrait sous 48h.',
        ];

        $texte = sprintf($messages[$statut] ?? $messages[Precommande::EN_ATTENTE],
            $precommande->getNumero());

        $envoye = Precommande::EN_ATTENTE !== $statut;

        $manager->persist(
            (new NotificationSms())
                ->setDestinataire($precommande->getDemandeur()->getTelephone())
                ->setMessage($texte)
                ->setType(Precommande::EN_ATTENTE === $statut ? 'CONFIRMATION' : 'AUTRE')
                ->setStatut($envoye ? NotificationSms::ENVOYE : NotificationSms::EN_ATTENTE)
                ->setTentatives($envoye ? 1 : 0)
                ->setDateCreation($creation)
                ->setDateEnvoi($envoye ? $creation->modify('+2 minutes') : null)
                ->setPrecommande($precommande)
        );
    }

    public function getDependencies(): array
    {
        return [ReferenceFixtures::class, BanqueFixtures::class];
    }

    public static function getGroups(): array
    {
        return ['demonstration'];
    }
}
