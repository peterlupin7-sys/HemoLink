<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Module don : donneurs, creneaux de collecte, rendez-vous, dons et
 * qualification des produits sanguins (5 tables, 1 vue).
 *
 * Le contenu reprend mot pour mot celui du script `base/hemolink.sql`, pour
 * que la base creee par Doctrine et celle importee dans phpMyAdmin restent
 * strictement identiques.
 *
 * Regle portee par ce schema : `qualification`.`poche_id` n'est renseigne que
 * lorsque le resultat vaut CONFORME. Un produit ecarte n'a aucune poche, donc
 * rien n'entre au stock utilisable.
 */
final class Version20261002090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Module don : 5 tables, 13 cles etrangeres, 1 vue.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
            'HemoLink utilise MySQL 8 ou MariaDB 10.4 (types ENUM et vues).'
        );

        $this->addSql(<<<'SQL'
            CREATE TABLE `donneur` (
              `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
              `utilisateur_id`    INT UNSIGNED DEFAULT NULL
                                  COMMENT 'Compte ROLE_DONNEUR, facultatif : on peut donner sans compte',
              `groupe_sanguin_id` TINYINT UNSIGNED DEFAULT NULL
                                  COMMENT 'Connu seulement apres une premiere qualification',
              `numero_donneur`    VARCHAR(20)  NOT NULL,
              `nom`               VARCHAR(80)  NOT NULL,
              `prenom`            VARCHAR(80)  NOT NULL,
              `sexe`              ENUM('F','M') NOT NULL,
              `date_naissance`    DATE NOT NULL,
              `telephone`         VARCHAR(30)  NOT NULL,
              `email`             VARCHAR(180) DEFAULT NULL,
              `ville`             VARCHAR(80)  DEFAULT NULL,
              `dernier_don`       DATE DEFAULT NULL,
              `nombre_dons`       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
              `apte`              TINYINT(1) NOT NULL DEFAULT 1,
              `motif_inaptitude`  VARCHAR(255) DEFAULT NULL,
              `date_creation`     DATETIME NOT NULL,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uk_donneur_numero` (`numero_donneur`),
              UNIQUE KEY `uk_donneur_utilisateur` (`utilisateur_id`),
              KEY `idx_donneur_groupe` (`groupe_sanguin_id`),
              KEY `idx_donneur_nom` (`nom`, `prenom`),
              CONSTRAINT `fk_donneur_utilisateur` FOREIGN KEY (`utilisateur_id`)
                REFERENCES `utilisateur` (`id`) ON DELETE SET NULL,
              CONSTRAINT `fk_donneur_groupe` FOREIGN KEY (`groupe_sanguin_id`)
                REFERENCES `groupe_sanguin` (`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE `creneau_don` (
              `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
              `banque_id`        INT UNSIGNED NOT NULL,
              `date_creneau`     DATE NOT NULL,
              `heure_debut`      TIME NOT NULL,
              `heure_fin`        TIME NOT NULL,
              `capacite`         SMALLINT UNSIGNED NOT NULL DEFAULT 8,
              `places_reservees` SMALLINT UNSIGNED NOT NULL DEFAULT 0
                                 COMMENT 'Compte exact des rendez-vous PLANIFIE ou HONORE',
              `lieu`             VARCHAR(150) DEFAULT NULL
                                 COMMENT 'Renseigne pour une collecte mobile hors les murs',
              `statut`           ENUM('OUVERT','COMPLET','ANNULE','CLOS') NOT NULL DEFAULT 'OUVERT',
              `date_creation`    DATETIME NOT NULL,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uk_creneau_banque_date_heure` (`banque_id`, `date_creneau`, `heure_debut`),
              KEY `idx_creneau_date` (`date_creneau`),
              KEY `idx_creneau_statut` (`statut`),
              CONSTRAINT `fk_creneau_banque` FOREIGN KEY (`banque_id`)
                REFERENCES `banque` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE `rendez_vous_don` (
              `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
              `numero`          VARCHAR(25) NOT NULL,
              `donneur_id`      INT UNSIGNED NOT NULL,
              `creneau_id`      INT UNSIGNED NOT NULL,
              `statut`          ENUM('PLANIFIE','HONORE','ANNULE','ABSENT') NOT NULL DEFAULT 'PLANIFIE',
              `commentaire`     VARCHAR(255) DEFAULT NULL,
              `date_creation`   DATETIME NOT NULL,
              `date_annulation` DATETIME DEFAULT NULL,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uk_rdv_numero` (`numero`),
              UNIQUE KEY `uk_rdv_donneur_creneau` (`donneur_id`, `creneau_id`),
              KEY `idx_rdv_statut` (`statut`),
              CONSTRAINT `fk_rdv_donneur` FOREIGN KEY (`donneur_id`)
                REFERENCES `donneur` (`id`) ON DELETE CASCADE,
              CONSTRAINT `fk_rdv_creneau` FOREIGN KEY (`creneau_id`)
                REFERENCES `creneau_don` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE `don` (
              `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
              `code_don`          VARCHAR(25) NOT NULL,
              `donneur_id`        INT UNSIGNED NOT NULL,
              `banque_id`         INT UNSIGNED NOT NULL,
              `rendez_vous_id`    INT UNSIGNED DEFAULT NULL
                                  COMMENT 'NULL pour un don spontane, sans rendez-vous',
              `groupe_sanguin_id` TINYINT UNSIGNED DEFAULT NULL
                                  COMMENT 'Groupe declare, confirme par la qualification',
              `date_don`          DATETIME NOT NULL,
              `volume_ml`         SMALLINT UNSIGNED NOT NULL DEFAULT 450,
              `hemoglobine`       DECIMAL(4,1) DEFAULT NULL COMMENT 'g/dL releves avant prelevement',
              `statut`            ENUM('ENREGISTRE','EN_QUALIFICATION','QUALIFIE','REJETE')
                                  NOT NULL DEFAULT 'ENREGISTRE',
              `utilisateur_id`    INT UNSIGNED DEFAULT NULL COMMENT 'Agent qui a enregistre le don',
              `observation`       VARCHAR(255) DEFAULT NULL,
              `date_creation`     DATETIME NOT NULL,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uk_don_code` (`code_don`),
              UNIQUE KEY `uk_don_rendez_vous` (`rendez_vous_id`),
              KEY `idx_don_banque_date` (`banque_id`, `date_don`),
              KEY `idx_don_statut` (`statut`),
              CONSTRAINT `fk_don_donneur` FOREIGN KEY (`donneur_id`)
                REFERENCES `donneur` (`id`) ON DELETE RESTRICT,
              CONSTRAINT `fk_don_banque` FOREIGN KEY (`banque_id`)
                REFERENCES `banque` (`id`) ON DELETE RESTRICT,
              CONSTRAINT `fk_don_rendez_vous` FOREIGN KEY (`rendez_vous_id`)
                REFERENCES `rendez_vous_don` (`id`) ON DELETE SET NULL,
              CONSTRAINT `fk_don_groupe` FOREIGN KEY (`groupe_sanguin_id`)
                REFERENCES `groupe_sanguin` (`id`) ON DELETE SET NULL,
              CONSTRAINT `fk_don_utilisateur` FOREIGN KEY (`utilisateur_id`)
                REFERENCES `utilisateur` (`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE `qualification` (
              `id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
              `don_id`             INT UNSIGNED NOT NULL,
              `poche_id`           INT UNSIGNED DEFAULT NULL
                                   COMMENT 'Poche creee uniquement si le resultat est CONFORME',
              `personnel_id`       INT UNSIGNED NOT NULL COMMENT 'Utilisateur ROLE_PERSONNEL habilite',
              `groupe_sanguin_id`  TINYINT UNSIGNED DEFAULT NULL COMMENT 'Groupe confirme au laboratoire',
              `resultat`           ENUM('CONFORME','NON_CONFORME') NOT NULL,
              `vih`                ENUM('NEGATIF','POSITIF','NON_FAIT') NOT NULL DEFAULT 'NON_FAIT',
              `hepatite_b`         ENUM('NEGATIF','POSITIF','NON_FAIT') NOT NULL DEFAULT 'NON_FAIT',
              `hepatite_c`         ENUM('NEGATIF','POSITIF','NON_FAIT') NOT NULL DEFAULT 'NON_FAIT',
              `syphilis`           ENUM('NEGATIF','POSITIF','NON_FAIT') NOT NULL DEFAULT 'NON_FAIT',
              `motif_rejet`        VARCHAR(255) DEFAULT NULL,
              `date_qualification` DATETIME NOT NULL,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uk_qualification_don` (`don_id`),
              UNIQUE KEY `uk_qualification_poche` (`poche_id`),
              KEY `idx_qualification_resultat` (`resultat`),
              CONSTRAINT `fk_qualification_don` FOREIGN KEY (`don_id`)
                REFERENCES `don` (`id`) ON DELETE CASCADE,
              CONSTRAINT `fk_qualification_poche` FOREIGN KEY (`poche_id`)
                REFERENCES `poche` (`id`) ON DELETE SET NULL,
              CONSTRAINT `fk_qualification_personnel` FOREIGN KEY (`personnel_id`)
                REFERENCES `utilisateur` (`id`) ON DELETE RESTRICT,
              CONSTRAINT `fk_qualification_groupe` FOREIGN KEY (`groupe_sanguin_id`)
                REFERENCES `groupe_sanguin` (`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

        $this->addSql(<<<'SQL'
            CREATE VIEW `vue_creneau_disponible` AS
            SELECT  c.`id`               AS creneau_id,
                    b.`id`               AS banque_id,
                    b.`nom`              AS banque,
                    b.`ville`,
                    c.`date_creneau`,
                    c.`heure_debut`,
                    c.`heure_fin`,
                    c.`lieu`,
                    c.`capacite`,
                    c.`places_reservees`,
                    (c.`capacite` - c.`places_reservees`) AS places_restantes
            FROM `creneau_don` c
            JOIN `banque` b ON b.`id` = c.`banque_id`
            WHERE c.`statut` = 'OUVERT'
              AND b.`statut` = 'ACTIVE'
              AND c.`places_reservees` < c.`capacite`
            SQL);

        $this->write('  Module don cree : 5 tables, 1 vue.');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('SET FOREIGN_KEY_CHECKS = 0');
        $this->addSql('DROP VIEW IF EXISTS `vue_creneau_disponible`');
        $this->addSql('DROP TABLE IF EXISTS `qualification`');
        $this->addSql('DROP TABLE IF EXISTS `don`');
        $this->addSql('DROP TABLE IF EXISTS `rendez_vous_don`');
        $this->addSql('DROP TABLE IF EXISTS `creneau_don`');
        $this->addSql('DROP TABLE IF EXISTS `donneur`');
        $this->addSql('SET FOREIGN_KEY_CHECKS = 1');
    }
}
