<?php

namespace App\DataFixtures;

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
    /**
     * Réglages modifiables sans redéploiement.
     *
     * Cette liste doit rester identique à celle du script `base/hemolink.sql` :
     * une application installée par import SQL et une application installée par
     * fixtures doivent se comporter exactement de la même façon. Le type suit
     * l'ENUM de la table : TEXTE, NOMBRE, BOOLEEN ou JSON.
     */
    public const PARAMETRES = [
        'rayon_recherche_km' => ['25', 'Rayon par défaut de la recherche de banques', 'NOMBRE'],
        'seuil_tension' => ['5', 'En dessous de ce nombre de poches, le stock est « en tension »', 'NOMBRE'],
        'delai_stock_obsolete_h' => ['24', 'Au-delà de ce délai sans mise à jour, le stock est « non communiqué »', 'NOMBRE'],
        'duree_validite_precommande_h' => ['48', 'Délai avant expiration automatique d\'une précommande non retirée', 'NOMBRE'],
        'alerte_peremption_jours' => ['7', 'Nombre de jours avant péremption déclenchant une alerte', 'NOMBRE'],
        'duree_conservation_jours' => ['42', 'Durée de conservation d\'un concentré de globules rouges', 'NOMBRE'],
        'quantite_max_precommande' => ['10', 'Nombre maximal de poches par précommande', 'NOMBRE'],
        'sms_actif' => ['1', 'Activation de l\'envoi des SMS', 'BOOLEEN'],
        'sms_expediteur' => ['HEMOLINK', 'Nom affiché comme expéditeur des SMS', 'TEXTE'],
        'organisation' => ['Centre national de transfusion sanguine du Bénin', 'Organisation propriétaire de la plateforme', 'TEXTE'],
        'email_contact' => ['contact@hemolink.bj', 'Adresse de contact affichée', 'TEXTE'],
        'telephone_urgence' => ['+229 21 31 20 40', 'Numéro d\'urgence affiché sur la carte', 'TEXTE'],
        // Module don
        'capacite_creneau_defaut' => ['8', 'Nombre de donneurs accueillis par créneau', 'NOMBRE'],
        'horizon_creneaux_jours' => ['30', 'Nombre de jours de créneaux proposés au donneur', 'NOMBRE'],
        'delai_annulation_rdv_h' => ['12', 'Délai minimal avant le créneau pour annuler ou déplacer un rendez-vous', 'NOMBRE'],
        'delai_entre_dons_jours' => ['90', 'Intervalle minimal entre deux dons de sang total', 'NOMBRE'],
        'age_min_don' => ['18', 'Âge minimal pour donner son sang', 'NOMBRE'],
        'age_max_don' => ['65', 'Âge maximal pour donner son sang', 'NOMBRE'],
        'hemoglobine_min_femme' => ['12.0', 'Taux d\'hémoglobine minimal avant prélèvement, en g/dL (femme)', 'TEXTE'],
        'hemoglobine_min_homme' => ['13.0', 'Taux d\'hémoglobine minimal avant prélèvement, en g/dL (homme)', 'TEXTE'],
        'version_schema' => ['1.1', 'Version du schéma de base de données', 'TEXTE'],
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

        // Les compteurs de numérotation ne sont pas créés ici : chaque jeu de
        // données les positionne lui-même, à la valeur du dernier numéro qu'il
        // a écrit (voir DemonstrationFixtures et CollecteFixtures). Les amorcer
        // à zéro ici provoquait une collision de clé unique au premier
        // enregistrement fait depuis l'application.

        $manager->flush();
    }
}
