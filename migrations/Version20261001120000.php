<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Schéma initial de HemoLink : 14 tables, 21 clés étrangères, 2 vues.
 *
 * Le contenu reprend celui du script `base/hemolink.sql` livré avec le projet,
 * pour que la base créée par Doctrine et celle importée dans phpMyAdmin soient
 * strictement identiques. Les données de référence (groupes sanguins, table de
 * compatibilité, paramètres) sont chargées par les fixtures, pas ici.
 */
final class Version20261001120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Schéma initial HemoLink : 14 tables, 21 cles etrangeres, 2 vues.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
            'HemoLink utilise MySQL 8 ou MariaDB 10.4 (types ENUM, JSON et vues).'
        );

        $this->addSql(<<<'SQL'
            CREATE TABLE `groupe_sanguin` (
              `id`            TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
              `code`          VARCHAR(3)   NOT NULL COMMENT 'O+, O-, A+, A-, B+, B-, AB+, AB-',
              `systeme_abo`   ENUM('O','A','B','AB') NOT NULL,
              `rhesus`        ENUM('POSITIF','NEGATIF') NOT NULL,
              `part_population` DECIMAL(5,2) NOT NULL DEFAULT 0.00
                              COMMENT 'Part estimée de la population, en pourcentage',
              `rang`          TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Ordre d''affichage',
              PRIMARY KEY (`id`),
              UNIQUE KEY `uk_groupe_code` (`code`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE `compatibilite` (
              `groupe_receveur_id` TINYINT UNSIGNED NOT NULL,
              `groupe_donneur_id`  TINYINT UNSIGNED NOT NULL,
              `priorite`           TINYINT UNSIGNED NOT NULL DEFAULT 1
                                   COMMENT '1 = groupe identique, 2 = substitution acceptée',
              PRIMARY KEY (`groupe_receveur_id`, `groupe_donneur_id`),
              KEY `idx_compat_donneur` (`groupe_donneur_id`),
              CONSTRAINT `fk_compat_receveur` FOREIGN KEY (`groupe_receveur_id`)
                REFERENCES `groupe_sanguin` (`id`) ON DELETE CASCADE,
              CONSTRAINT `fk_compat_donneur` FOREIGN KEY (`groupe_donneur_id`)
                REFERENCES `groupe_sanguin` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE `banque` (
              `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
              `code`          VARCHAR(20)  NOT NULL COMMENT 'BQ-COT-001',
              `nom`           VARCHAR(150) NOT NULL,
              `type`          ENUM('CNTS','CHU','HOPITAL','CLINIQUE','CENTRE') NOT NULL
                              DEFAULT 'HOPITAL',
              `adresse`       VARCHAR(200) NOT NULL,
              `ville`         VARCHAR(80)  NOT NULL,
              `departement`   VARCHAR(80)  NOT NULL,
              `latitude`      DECIMAL(10,7) NOT NULL,
              `longitude`     DECIMAL(10,7) NOT NULL,
              `telephone`     VARCHAR(25)  NOT NULL,
              `telephone_urgence` VARCHAR(25) DEFAULT NULL,
              `email`         VARCHAR(120) DEFAULT NULL,
              `horaires`      VARCHAR(200) DEFAULT NULL,
              `capacite_stockage` SMALLINT UNSIGNED NOT NULL DEFAULT 0
                              COMMENT 'Nombre maximal de poches stockables',
              `statut`        ENUM('ACTIVE','SUSPENDUE','FERMEE') NOT NULL DEFAULT 'ACTIVE',
              `date_creation` DATETIME NOT NULL,
              `date_maj`      DATETIME DEFAULT NULL,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uk_banque_code` (`code`),
              KEY `idx_banque_position` (`latitude`, `longitude`),
              KEY `idx_banque_ville` (`ville`),
              KEY `idx_banque_statut` (`statut`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE `utilisateur` (
              `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
              `banque_id`     INT UNSIGNED DEFAULT NULL
                              COMMENT 'NULL pour l''administrateur national',
              `email`         VARCHAR(180) NOT NULL,
              `mot_de_passe`  VARCHAR(255) NOT NULL COMMENT 'Hachage bcrypt',
              `roles`         JSON NOT NULL,
              `nom`           VARCHAR(80)  NOT NULL,
              `prenom`        VARCHAR(80)  NOT NULL,
              `telephone`     VARCHAR(25)  DEFAULT NULL,
              `actif`         TINYINT(1)   NOT NULL DEFAULT 1,
              `derniere_connexion` DATETIME DEFAULT NULL,
              `date_creation` DATETIME NOT NULL,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uk_utilisateur_email` (`email`),
              KEY `idx_utilisateur_banque` (`banque_id`),
              CONSTRAINT `fk_utilisateur_banque` FOREIGN KEY (`banque_id`)
                REFERENCES `banque` (`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE `stock` (
              `id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
              `banque_id`          INT UNSIGNED NOT NULL,
              `groupe_sanguin_id`  TINYINT UNSIGNED NOT NULL,
              `quantite_disponible` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
              `quantite_reservee`   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
              `seuil_alerte`        SMALLINT UNSIGNED NOT NULL DEFAULT 5,
              `etat`               ENUM('DISPONIBLE','TENSION','RUPTURE','NON_COMMUNIQUE')
                                   NOT NULL DEFAULT 'NON_COMMUNIQUE',
              `date_maj`           DATETIME NOT NULL,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uk_stock_banque_groupe` (`banque_id`, `groupe_sanguin_id`),
              KEY `idx_stock_etat` (`etat`),
              KEY `idx_stock_groupe` (`groupe_sanguin_id`),
              CONSTRAINT `fk_stock_banque` FOREIGN KEY (`banque_id`)
                REFERENCES `banque` (`id`) ON DELETE CASCADE,
              CONSTRAINT `fk_stock_groupe` FOREIGN KEY (`groupe_sanguin_id`)
                REFERENCES `groupe_sanguin` (`id`) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE `demandeur` (
              `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
              `type`          ENUM('ETABLISSEMENT','PARTICULIER') NOT NULL DEFAULT 'ETABLISSEMENT',
              `nom`           VARCHAR(150) NOT NULL,
              `responsable`   VARCHAR(120) DEFAULT NULL,
              `telephone`     VARCHAR(25)  NOT NULL,
              `email`         VARCHAR(120) DEFAULT NULL,
              `adresse`       VARCHAR(200) DEFAULT NULL,
              `ville`         VARCHAR(80)  DEFAULT NULL,
              `numero_agrement` VARCHAR(40) DEFAULT NULL,
              `date_creation` DATETIME NOT NULL,
              PRIMARY KEY (`id`),
              KEY `idx_demandeur_telephone` (`telephone`),
              KEY `idx_demandeur_nom` (`nom`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE `precommande` (
              `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
              `numero`        VARCHAR(25)  NOT NULL COMMENT 'PRE-COT-2026-00001',
              `banque_id`     INT UNSIGNED NOT NULL,
              `demandeur_id`  INT UNSIGNED NOT NULL,
              `utilisateur_traitant_id` INT UNSIGNED DEFAULT NULL,
              `statut`        ENUM('EN_ATTENTE','CONFIRMEE','PRETE','RETIREE','ANNULEE','EXPIREE')
                              NOT NULL DEFAULT 'EN_ATTENTE',
              `urgence`       ENUM('NORMALE','URGENTE','VITALE') NOT NULL DEFAULT 'NORMALE',
              `date_souhaitee` DATE NOT NULL,
              `motif`         VARCHAR(150) DEFAULT NULL,
              `reference_patient` VARCHAR(40) DEFAULT NULL
                              COMMENT 'Référence anonyme, jamais le nom du patient',
              `date_creation` DATETIME NOT NULL,
              `date_confirmation` DATETIME DEFAULT NULL,
              `date_retrait`  DATETIME DEFAULT NULL,
              `date_annulation` DATETIME DEFAULT NULL,
              `commentaire`   VARCHAR(255) DEFAULT NULL,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uk_precommande_numero` (`numero`),
              KEY `idx_precommande_banque` (`banque_id`),
              KEY `idx_precommande_statut` (`statut`),
              KEY `idx_precommande_date` (`date_creation`),
              CONSTRAINT `fk_precommande_banque` FOREIGN KEY (`banque_id`)
                REFERENCES `banque` (`id`) ON DELETE RESTRICT,
              CONSTRAINT `fk_precommande_demandeur` FOREIGN KEY (`demandeur_id`)
                REFERENCES `demandeur` (`id`) ON DELETE RESTRICT,
              CONSTRAINT `fk_precommande_traitant` FOREIGN KEY (`utilisateur_traitant_id`)
                REFERENCES `utilisateur` (`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE `ligne_precommande` (
              `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
              `precommande_id`  INT UNSIGNED NOT NULL,
              `groupe_sanguin_id` TINYINT UNSIGNED NOT NULL,
              `quantite_demandee` SMALLINT UNSIGNED NOT NULL,
              `quantite_confirmee` SMALLINT UNSIGNED DEFAULT NULL,
              `groupe_substitue_id` TINYINT UNSIGNED DEFAULT NULL
                                COMMENT 'Groupe compatible réellement délivré',
              PRIMARY KEY (`id`),
              UNIQUE KEY `uk_ligne_precommande_groupe` (`precommande_id`, `groupe_sanguin_id`),
              KEY `idx_ligne_groupe` (`groupe_sanguin_id`),
              CONSTRAINT `fk_ligne_precommande` FOREIGN KEY (`precommande_id`)
                REFERENCES `precommande` (`id`) ON DELETE CASCADE,
              CONSTRAINT `fk_ligne_groupe` FOREIGN KEY (`groupe_sanguin_id`)
                REFERENCES `groupe_sanguin` (`id`) ON DELETE RESTRICT,
              CONSTRAINT `fk_ligne_substitue` FOREIGN KEY (`groupe_substitue_id`)
                REFERENCES `groupe_sanguin` (`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE `poche` (
              `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
              `code_poche`      VARCHAR(25) NOT NULL COMMENT 'Code-barres de la poche',
              `banque_id`       INT UNSIGNED NOT NULL,
              `groupe_sanguin_id` TINYINT UNSIGNED NOT NULL,
              `date_prelevement` DATE NOT NULL,
              `date_peremption`  DATE NOT NULL,
              `volume_ml`       SMALLINT UNSIGNED NOT NULL DEFAULT 450,
              `statut`          ENUM('DISPONIBLE','RESERVEE','DELIVREE','PERIMEE','DETRUITE')
                                NOT NULL DEFAULT 'DISPONIBLE',
              `precommande_id`  INT UNSIGNED DEFAULT NULL,
              `date_creation`   DATETIME NOT NULL,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uk_poche_code` (`code_poche`),
              KEY `idx_poche_banque_groupe` (`banque_id`, `groupe_sanguin_id`, `statut`),
              KEY `idx_poche_peremption` (`date_peremption`),
              KEY `idx_poche_precommande` (`precommande_id`),
              CONSTRAINT `fk_poche_banque` FOREIGN KEY (`banque_id`)
                REFERENCES `banque` (`id`) ON DELETE CASCADE,
              CONSTRAINT `fk_poche_groupe` FOREIGN KEY (`groupe_sanguin_id`)
                REFERENCES `groupe_sanguin` (`id`) ON DELETE RESTRICT,
              CONSTRAINT `fk_poche_precommande` FOREIGN KEY (`precommande_id`)
                REFERENCES `precommande` (`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE `mouvement_stock` (
              `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
              `banque_id`       INT UNSIGNED NOT NULL,
              `groupe_sanguin_id` TINYINT UNSIGNED NOT NULL,
              `poche_id`        INT UNSIGNED DEFAULT NULL,
              `type`            ENUM('ENTREE','SORTIE','RESERVATION','LIBERATION','PEREMPTION',
                                'AJUSTEMENT') NOT NULL,
              `quantite`        SMALLINT NOT NULL COMMENT 'Négatif pour une sortie',
              `quantite_apres`  SMALLINT UNSIGNED NOT NULL COMMENT 'Stock disponible après le mouvement',
              `precommande_id`  INT UNSIGNED DEFAULT NULL,
              `utilisateur_id`  INT UNSIGNED DEFAULT NULL,
              `motif`           VARCHAR(150) DEFAULT NULL,
              `date_mouvement`  DATETIME NOT NULL,
              PRIMARY KEY (`id`),
              KEY `idx_mouvement_banque_date` (`banque_id`, `date_mouvement`),
              KEY `idx_mouvement_type` (`type`),
              KEY `idx_mouvement_groupe` (`groupe_sanguin_id`),
              CONSTRAINT `fk_mouvement_banque` FOREIGN KEY (`banque_id`)
                REFERENCES `banque` (`id`) ON DELETE CASCADE,
              CONSTRAINT `fk_mouvement_groupe` FOREIGN KEY (`groupe_sanguin_id`)
                REFERENCES `groupe_sanguin` (`id`) ON DELETE RESTRICT,
              CONSTRAINT `fk_mouvement_poche` FOREIGN KEY (`poche_id`)
                REFERENCES `poche` (`id`) ON DELETE SET NULL,
              CONSTRAINT `fk_mouvement_precommande` FOREIGN KEY (`precommande_id`)
                REFERENCES `precommande` (`id`) ON DELETE SET NULL,
              CONSTRAINT `fk_mouvement_utilisateur` FOREIGN KEY (`utilisateur_id`)
                REFERENCES `utilisateur` (`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE `notification_sms` (
              `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
              `destinataire`   VARCHAR(25)  NOT NULL,
              `message`        VARCHAR(480) NOT NULL,
              `type`           ENUM('CONFIRMATION','RAPPEL','ALERTE_RUPTURE','ALERTE_PEREMPTION',
                               'AUTRE') NOT NULL DEFAULT 'AUTRE',
              `precommande_id` INT UNSIGNED DEFAULT NULL,
              `statut`         ENUM('EN_ATTENTE','ENVOYE','ECHEC') NOT NULL DEFAULT 'EN_ATTENTE',
              `tentatives`     TINYINT UNSIGNED NOT NULL DEFAULT 0,
              `date_creation`  DATETIME NOT NULL,
              `date_envoi`     DATETIME DEFAULT NULL,
              `erreur`         VARCHAR(255) DEFAULT NULL,
              PRIMARY KEY (`id`),
              KEY `idx_sms_statut` (`statut`),
              KEY `idx_sms_precommande` (`precommande_id`),
              CONSTRAINT `fk_sms_precommande` FOREIGN KEY (`precommande_id`)
                REFERENCES `precommande` (`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE `compteur` (
              `cle`       VARCHAR(50) NOT NULL COMMENT 'PRECOMMANDE-COT-2026',
              `prefixe`   VARCHAR(20) NOT NULL,
              `annee`     SMALLINT UNSIGNED NOT NULL,
              `valeur`    INT UNSIGNED NOT NULL DEFAULT 0,
              `date_maj`  DATETIME NOT NULL,
              PRIMARY KEY (`cle`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE `journal` (
              `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
              `utilisateur_id` INT UNSIGNED DEFAULT NULL,
              `action`         VARCHAR(60)  NOT NULL,
              `entite`         VARCHAR(50)  DEFAULT NULL,
              `entite_id`      INT UNSIGNED DEFAULT NULL,
              `details`        VARCHAR(255) DEFAULT NULL,
              `adresse_ip`     VARCHAR(45)  DEFAULT NULL,
              `date_action`    DATETIME NOT NULL,
              PRIMARY KEY (`id`),
              KEY `idx_journal_date` (`date_action`),
              KEY `idx_journal_utilisateur` (`utilisateur_id`),
              CONSTRAINT `fk_journal_utilisateur` FOREIGN KEY (`utilisateur_id`)
                REFERENCES `utilisateur` (`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE `parametre` (
              `cle`      VARCHAR(60)  NOT NULL,
              `valeur`   VARCHAR(255) NOT NULL,
              `libelle`  VARCHAR(200) NOT NULL,
              `type`     ENUM('TEXTE','NOMBRE','BOOLEEN','JSON') NOT NULL DEFAULT 'TEXTE',
              `date_maj` DATETIME NOT NULL,
              PRIMARY KEY (`cle`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

        $this->addSql(<<<'SQL'
            CREATE VIEW `vue_stock_banque` AS
            SELECT  b.`id`            AS banque_id,
                    b.`nom`           AS banque,
                    b.`ville`,
                    b.`latitude`,
                    b.`longitude`,
                    g.`code`          AS groupe,
                    s.`quantite_disponible`,
                    s.`quantite_reservee`,
                    s.`etat`,
                    s.`date_maj`
            FROM `stock` s
            JOIN `banque` b         ON b.`id` = s.`banque_id`
            JOIN `groupe_sanguin` g ON g.`id` = s.`groupe_sanguin_id`
            WHERE b.`statut` = 'ACTIVE'
            SQL);

        $this->addSql(<<<'SQL'
            CREATE VIEW `vue_precommande_en_cours` AS
            SELECT  p.`numero`,
                    p.`statut`,
                    p.`urgence`,
                    b.`nom`  AS banque,
                    d.`nom`  AS demandeur,
                    d.`telephone`,
                    g.`code` AS groupe,
                    l.`quantite_demandee`,
                    l.`quantite_confirmee`,
                    p.`date_creation`
            FROM `precommande` p
            JOIN `ligne_precommande` l ON l.`precommande_id` = p.`id`
            JOIN `groupe_sanguin` g    ON g.`id` = l.`groupe_sanguin_id`
            JOIN `banque` b            ON b.`id` = p.`banque_id`
            JOIN `demandeur` d         ON d.`id` = p.`demandeur_id`
            WHERE p.`statut` IN ('EN_ATTENTE','CONFIRMEE','PRETE')
            SQL);

        $this->write('  Schema HemoLink cree : 14 tables, 2 vues.');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('SET FOREIGN_KEY_CHECKS = 0');
        $this->addSql('DROP VIEW IF EXISTS `vue_stock_banque`');
        $this->addSql('DROP VIEW IF EXISTS `vue_precommande_en_cours`');
        $this->addSql('DROP TABLE IF EXISTS `parametre`');
        $this->addSql('DROP TABLE IF EXISTS `journal`');
        $this->addSql('DROP TABLE IF EXISTS `compteur`');
        $this->addSql('DROP TABLE IF EXISTS `notification_sms`');
        $this->addSql('DROP TABLE IF EXISTS `mouvement_stock`');
        $this->addSql('DROP TABLE IF EXISTS `poche`');
        $this->addSql('DROP TABLE IF EXISTS `ligne_precommande`');
        $this->addSql('DROP TABLE IF EXISTS `precommande`');
        $this->addSql('DROP TABLE IF EXISTS `demandeur`');
        $this->addSql('DROP TABLE IF EXISTS `stock`');
        $this->addSql('DROP TABLE IF EXISTS `utilisateur`');
        $this->addSql('DROP TABLE IF EXISTS `banque`');
        $this->addSql('DROP TABLE IF EXISTS `compatibilite`');
        $this->addSql('DROP TABLE IF EXISTS `groupe_sanguin`');
        $this->addSql('SET FOREIGN_KEY_CHECKS = 1');
    }
}
