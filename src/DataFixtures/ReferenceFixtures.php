<?php

namespace App\DataFixtures;

use App\Entity\Compteur;
use App\Entity\GroupeSanguin;
use App\Entity\Parametre;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Données de référence : elles ne sont PAS de la démonstration.
 *
 * Les huit groupes sanguins, la table de compatibilité et les paramètres de
 * fonctionnement doivent exister aussi en production. C'est pourquoi ce groupe
 * de fixtures est séparé de la démonstration.
 */
class ReferenceFixtures extends Fixture
{
    public const GROUPE = 'groupe-';

    /**
     * code => [système ABO, rhésus, part de la population au Bénin, rang].
     */
    public const GROUPES = [
        'O+' => ['O', 'POSITIF', '38.50', 1],
        'O-' => ['O', 'NEGATIF', '4.30', 2],
        'A+' => ['A', 'POSITIF', '26.80', 3],
        'A-' => ['A', 'NEGATIF', '2.60', 4],
        'B+' => ['B', 'POSITIF', '20.40', 5],
        'B-' => ['B', 'NEGATIF', '1.70', 6],
        'AB+' => ['AB', 'POSITIF', '4.90', 7],
        'AB-' => ['AB', 'NEGATIF', '0.80', 8],
    ];

    /**
     * Receveur => donneurs acceptés (concentrés de globules rouges).
     * Source : règles transfusionnelles ABO/Rhésus, reprises du cahier des charges.
     */
    public const COMPATIBILITE = [
        'O+' => ['O+', 'O-'],
        'O-' => ['O-'],
        'A+' => ['A+', 'A-', 'O+', 'O-'],
        'A-' => ['A-', 'O-'],
        'B+' => ['B+', 'B-', 'O+', 'O-'],
        'B-' => ['B-', 'O-'],
        'AB+' => ['AB+', 'AB-', 'A+', 'A-', 'B+', 'B-', 'O+', 'O-'],
        'AB-' => ['AB-', 'A-', 'B-', 'O-'],
    ];

    /**
     * clé => [valeur, libellé, type].
     */
    public const PARAMETRES = [
        'rayon_recherche_km' => ['25', 'Rayon de recherche par défaut sur la carte (km)', 'ENTIER'],
        'seuil_tension' => ['5', 'En dessous de ce nombre de poches, le stock est « en tension »', 'ENTIER'],
        'delai_stock_obsolete_h' => ['24', 'Au-delà, un stock non mis à jour est affiché « non communiqué »', 'ENTIER'],
        'duree_validite_precommande_h' => ['48', 'Durée de validité d\'une précommande avant expiration', 'ENTIER'],
        'alerte_peremption_jours' => ['7', 'Alerte de péremption envoyée ce nombre de jours avant la date', 'ENTIER'],
        'duree_conservation_jours' => ['42', 'Durée de conservation d\'un concentré de globules rouges', 'ENTIER'],
        'quantite_max_precommande' => ['10', 'Nombre maximal de poches par précommande', 'ENTIER'],
        'sms_actif' => ['1', 'Envoi des SMS activé', 'BOOLEEN'],
        'nom_organisation' => ['Agence nationale de la transfusion sanguine', 'Nom affiché dans le pied de page', 'TEXTE'],
        'telephone_assistance' => ['+229 21 31 20 40', 'Numéro d\'assistance affiché aux demandeurs', 'TEXTE'],
        'message_accueil' => ['Trouvez une poche de sang disponible près de vous.', 'Phrase d\'accueil de la page carte', 'TEXTE'],
    ];

    public function load(ObjectManager $manager): void
    {
        $maintenant = new \DateTimeImmutable();
        $groupes = [];

        foreach (self::GROUPES as $code => [$abo, $rhesus, $part, $rang]) {
            $groupe = (new GroupeSanguin())
                ->setCode($code)
                ->setSystemeAbo($abo)
                ->setRhesus($rhesus)
                ->setPartPopulation($part)
                ->setRang($rang);

            $manager->persist($groupe);
            $groupes[$code] = $groupe;
            $this->addReference(self::GROUPE.$code, $groupe);
        }

        foreach (self::COMPATIBILITE as $receveur => $donneurs) {
            foreach ($donneurs as $donneur) {
                $groupes[$receveur]->addDonneursCompatible($groupes[$donneur]);
            }
        }

        foreach (self::PARAMETRES as $cle => [$valeur, $libelle, $type]) {
            $manager->persist(
                (new Parametre())
                    ->setCle($cle)
                    ->setValeur($valeur)
                    ->setLibelle($libelle)
                    ->setType($type)
                    ->setDateMaj($maintenant)
            );
        }

        // Compteurs de numérotation : un par préfixe de ville desservie.
        foreach (['precommande_COT' => 'COT', 'precommande_PNO' => 'PNO'] as $cle => $prefixe) {
            $manager->persist(
                (new Compteur())
                    ->setCle($cle)
                    ->setPrefixe($prefixe)
                    ->setAnnee((int) $maintenant->format('Y'))
                    ->setValeur(0)
                    ->setDateMaj($maintenant)
            );
        }

        $manager->flush();
    }
}
