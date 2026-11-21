<?php

namespace App\DataFixtures;

use App\Entity\Banque;
use App\Entity\Utilisateur;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Les douze banques de sang réellement recensées par le projet, et les comptes
 * qui les administrent.
 *
 * Les coordonnées sont celles relevées pendant l'étude de terrain : elles
 * servent aussi bien à la carte qu'aux tests de distance.
 */
class BanqueFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    public const BANQUE = 'banque-';

    private const HORAIRES = 'Lun-Ven 07h30-18h00 · Sam 08h00-13h00 · Urgences 24h/24';
    private const URGENCE_NATIONALE = '+229 21 31 20 40';

    /**
     * code, nom, type, adresse, ville, département, latitude, longitude,
     * téléphone, urgence 24h/24, capacité de stockage.
     */
    public const BANQUES = [
        ['BQ-COT-001', 'CNTS — Centre national de transfusion sanguine', 'CNTS',
            'Boulevard de la Marina, Akpakpa', 'Cotonou', 'Littoral',
            '6.3654', '2.4183', '+229 21 31 20 40', true, 900],
        ['BQ-COT-002', 'CNHU-HKM — Centre national hospitalier universitaire', 'CHU',
            'Avenue Jean-Paul II, Cadjèhoun', 'Cotonou', 'Littoral',
            '6.3703', '2.3912', '+229 21 30 01 55', true, 600],
        ['BQ-COT-003', 'Hôpital de zone de Ménontin', 'HOPITAL',
            'Quartier Ménontin, 12e arrondissement', 'Cotonou', 'Littoral',
            '6.3781', '2.3684', '+229 21 38 44 10', false, 250],
        ['BQ-COT-004', 'Hôpital Saint-Luc', 'CLINIQUE',
            'Rue 1248, Gbégamey', 'Cotonou', 'Littoral',
            '6.3625', '2.4026', '+229 21 32 18 76', false, 150],
        ['BQ-ABC-001', 'Hôpital de zone d’Abomey-Calavi', 'HOPITAL',
            'Carrefour Tankpè, route de Godomey', 'Abomey-Calavi', 'Atlantique',
            '6.4489', '2.3556', '+229 21 36 02 18', false, 300],
        ['BQ-PNO-001', 'CHUD Ouémé-Plateau', 'CHU',
            'Quartier Djègan-Kpèvi', 'Porto-Novo', 'Ouémé',
            '6.4969', '2.6289', '+229 20 21 30 12', true, 400],
        ['BQ-PNO-002', 'Clinique Louis Pasteur', 'CLINIQUE',
            'Avenue Pasteur, centre-ville', 'Porto-Novo', 'Ouémé',
            '6.4912', '2.6154', '+229 20 21 45 60', false, 120],
        ['BQ-BOH-001', 'CHUD Borgou-Alibori', 'CHU',
            'Route de Transa', 'Parakou', 'Borgou',
            '9.3372', '2.6303', '+229 23 61 07 44', true, 450],
        ['BQ-ZOU-001', 'CHUD Zou-Collines', 'CHU',
            'Quartier Hounli', 'Abomey', 'Zou',
            '7.1853', '1.9912', '+229 22 50 01 33', true, 280],
        ['BQ-MON-001', 'CHUD Mono-Couffo', 'CHU',
            'Route inter-États, quartier Agnivèdji', 'Lokossa', 'Mono',
            '6.6387', '1.7165', '+229 22 41 10 25', true, 260],
        ['BQ-ATA-001', 'CHUD Atacora-Donga', 'CHU',
            'Quartier Yimporima', 'Natitingou', 'Atacora',
            '10.3045', '1.3796', '+229 23 82 11 07', true, 220],
        ['BQ-ATA-002', 'Hôpital Saint-Jean-de-Dieu', 'HOPITAL',
            'Route de Porga', 'Tanguiéta', 'Atacora',
            '10.6212', '1.2653', '+229 23 83 00 42', false, 180],
    ];

    /**
     * email, rôle, nom, prénom, téléphone, code de la banque (null = national).
     */
    private const COMPTES = [
        ['admin@hemolink.bj', Utilisateur::ROLE_ADMIN, 'ADANDE', 'Sylvestre', '+229 97 11 22 33', null],
        ['gestionnaire.cnts@hemolink.bj', Utilisateur::ROLE_GESTIONNAIRE, 'HOUNKPATIN', 'Rachelle', '+229 97 44 55 66', 'BQ-COT-001'],
        ['gestionnaire.cnhu@hemolink.bj', Utilisateur::ROLE_GESTIONNAIRE, 'TOSSOU', 'Marius', '+229 96 10 20 30', 'BQ-COT-002'],
        ['gestionnaire.chud-oueme@hemolink.bj', Utilisateur::ROLE_GESTIONNAIRE, 'AGOSSOU', 'Delphine', '+229 95 77 88 99', 'BQ-PNO-001'],
        ['gestionnaire.chud-borgou@hemolink.bj', Utilisateur::ROLE_GESTIONNAIRE, 'SEIDOU', 'Abdoulaye', '+229 94 12 34 56', 'BQ-BOH-001'],
        ['agent.cnts@hemolink.bj', Utilisateur::ROLE_AGENT, 'KOTCHOFA', 'Pélagie', '+229 97 65 43 21', 'BQ-COT-001'],
        ['agent.cnhu@hemolink.bj', Utilisateur::ROLE_AGENT, 'DOSSOU', 'Ernest', '+229 96 98 76 54', 'BQ-COT-002'],
        ['agent.chud-oueme@hemolink.bj', Utilisateur::ROLE_AGENT, 'ZANNOU', 'Félicité', '+229 95 13 57 91', 'BQ-PNO-001'],
        // Personnel habilité : il qualifie les dons, il ne gère pas le stock.
        ['biologiste.cnts@hemolink.bj', Utilisateur::ROLE_PERSONNEL, 'AKPOVI', 'Félicienne', '+229 97 22 41 85', 'BQ-COT-001'],
        ['biologiste.cnhu@hemolink.bj', Utilisateur::ROLE_PERSONNEL, 'LAWANI', 'Boubacar', '+229 96 50 73 12', 'BQ-COT-002'],
        // Comptes donneurs, rattachés à aucune banque.
        ['donneur.sossou@hemolink.bj', Utilisateur::ROLE_DONNEUR, 'SOSSOU', 'Armand', '+229 97 31 76 20', null],
        ['donneur.tognon@hemolink.bj', Utilisateur::ROLE_DONNEUR, 'TOGNON', 'Mireille', '+229 96 44 08 15', null],
    ];

    /** Référence Doctrine d'un compte, pour les fixtures qui en ont besoin. */
    public const COMPTE = 'compte-';

    public function __construct(private readonly UserPasswordHasherInterface $hacheur)
    {
    }

    public function load(ObjectManager $manager): void
    {
        $maintenant = new \DateTimeImmutable();
        $creation = $maintenant->modify('-120 days');
        $banques = [];

        foreach (self::BANQUES as [$code, $nom, $type, $adresse, $ville, $departement,
            $latitude, $longitude, $telephone, $urgence, $capacite]) {
            $banque = (new Banque())
                ->setCode($code)
                ->setNom($nom)
                ->setType($type)
                ->setAdresse($adresse)
                ->setVille($ville)
                ->setDepartement($departement)
                ->setLatitude($latitude)
                ->setLongitude($longitude)
                ->setTelephone($telephone)
                ->setTelephoneUrgence($urgence ? self::URGENCE_NATIONALE : null)
                ->setEmail(strtolower(substr($code, 3)).'@hemolink.bj')
                ->setHoraires(self::HORAIRES)
                ->setCapaciteStockage($capacite)
                ->setStatut('ACTIVE')
                ->setDateCreation($creation)
                ->setDateMaj($maintenant);

            $manager->persist($banque);
            $banques[$code] = $banque;
            $this->addReference(self::BANQUE.$code, $banque);
        }

        foreach (self::COMPTES as [$email, $role, $nom, $prenom, $telephone, $codeBanque]) {
            $utilisateur = (new Utilisateur())
                ->setEmail($email)
                ->setRoles([$role])
                ->setNom($nom)
                ->setPrenom($prenom)
                ->setTelephone($telephone)
                ->setBanque($codeBanque ? $banques[$codeBanque] : null)
                ->setActif(true)
                ->setDateCreation($creation->modify('+20 days'));

            // Mots de passe de démonstration : à changer au premier déploiement.
            $motDePasse = \in_array($role, [Utilisateur::ROLE_AGENT, Utilisateur::ROLE_DONNEUR], true)
                ? 'agent2026' : 'hemolink2026';
            $utilisateur->setMotDePasse($this->hacheur->hashPassword($utilisateur, $motDePasse));

            $manager->persist($utilisateur);
            $this->addReference(self::COMPTE.$email, $utilisateur);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [ReferenceFixtures::class];
    }

    public static function getGroups(): array
    {
        return ['demonstration'];
    }
}
