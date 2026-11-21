
-- =====================================================================
--  HemoLink — Plateforme de géolocalisation des banques de sang
--  et de précommande de poches
--  Base de données : hemolink
--  Version 1.0 — Octobre 2026
--  Projet encadré par Monsieur Briand SAIZONOU
--
--  Cible : MySQL 8.x ou MariaDB 10.4+ (XAMPP) — moteur InnoDB, UTF-8.
--
--  INSTALLATION (phpMyAdmin)
--    1. Onglet « Importer » > choisir ce fichier > Exécuter.
--    2. La base `hemolink` est créée, remplie et prête à l'emploi.
--  INSTALLATION (ligne de commande)
--    mysql -u root -p < hemolink.sql
--
--  COMPTES DE DEMONSTRATION (a supprimer avant une mise en production)
--    admin@hemolink.bj ................ hemolink2026  (ROLE_ADMIN)
--    gestionnaire.cnts@hemolink.bj .... hemolink2026  (ROLE_GESTIONNAIRE)
--    agent.cnts@hemolink.bj ........... agent2026     (ROLE_AGENT)
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';

CREATE DATABASE IF NOT EXISTS `hemolink`
  DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `hemolink`;

DROP VIEW  IF EXISTS `vue_stock_banque`;
DROP VIEW  IF EXISTS `vue_precommande_en_cours`;
DROP VIEW  IF EXISTS `vue_creneau_disponible`;
DROP TABLE IF EXISTS `qualification`;
DROP TABLE IF EXISTS `don`;
DROP TABLE IF EXISTS `rendez_vous_don`;
DROP TABLE IF EXISTS `creneau_don`;
DROP TABLE IF EXISTS `donneur`;
DROP TABLE IF EXISTS `journal`;
DROP TABLE IF EXISTS `notification_sms`;
DROP TABLE IF EXISTS `mouvement_stock`;
DROP TABLE IF EXISTS `poche`;
DROP TABLE IF EXISTS `ligne_precommande`;
DROP TABLE IF EXISTS `precommande`;
DROP TABLE IF EXISTS `demandeur`;
DROP TABLE IF EXISTS `stock`;
DROP TABLE IF EXISTS `utilisateur`;
DROP TABLE IF EXISTS `banque`;
DROP TABLE IF EXISTS `compatibilite`;
DROP TABLE IF EXISTS `groupe_sanguin`;
DROP TABLE IF EXISTS `compteur`;
DROP TABLE IF EXISTS `parametre`;

-- ---------------------------------------------------------------------
-- 1. groupe_sanguin — les 8 groupes du système ABO/Rhésus
-- ---------------------------------------------------------------------
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 2. compatibilite — qui peut recevoir de qui (globules rouges)
-- ---------------------------------------------------------------------
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 3. banque — les banques de sang géolocalisées
-- ---------------------------------------------------------------------
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 4. utilisateur — comptes du personnel (Symfony Security)
-- ---------------------------------------------------------------------
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 5. stock — état agrégé par banque et par groupe (lu par la carte)
-- ---------------------------------------------------------------------
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 6. demandeur — établissements de soins et particuliers
-- ---------------------------------------------------------------------
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 7. precommande — la réservation de poches
-- ---------------------------------------------------------------------
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 8. ligne_precommande — le détail par groupe sanguin
-- ---------------------------------------------------------------------
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 9. poche — l'unité physique, tracée du prélèvement à la délivrance
-- ---------------------------------------------------------------------
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 10. mouvement_stock — l'historique de toute variation
-- ---------------------------------------------------------------------
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 11. notification_sms — file d'envoi, résistante aux coupures réseau
-- ---------------------------------------------------------------------
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 12. compteur — numérotation sans trou ni doublon (verrou applicatif)
-- ---------------------------------------------------------------------
CREATE TABLE `compteur` (
  `cle`       VARCHAR(50) NOT NULL COMMENT 'PRECOMMANDE-COT-2026',
  `prefixe`   VARCHAR(20) NOT NULL,
  `annee`     SMALLINT UNSIGNED NOT NULL,
  `valeur`    INT UNSIGNED NOT NULL DEFAULT 0,
  `date_maj`  DATETIME NOT NULL,
  PRIMARY KEY (`cle`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 13. journal — traçabilité des actions sensibles
-- ---------------------------------------------------------------------
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 14. parametre — réglages modifiables sans redéploiement
-- ---------------------------------------------------------------------
CREATE TABLE `parametre` (
  `cle`      VARCHAR(60)  NOT NULL,
  `valeur`   VARCHAR(255) NOT NULL,
  `libelle`  VARCHAR(200) NOT NULL,
  `type`     ENUM('TEXTE','NOMBRE','BOOLEEN','JSON') NOT NULL DEFAULT 'TEXTE',
  `date_maj` DATETIME NOT NULL,
  PRIMARY KEY (`cle`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
--  MODULE DON — rendez-vous, dons et qualification des produits
--
--  Regle intangible (DON-05) : une poche n'entre au stock utilisable
--  qu'apres une qualification CONFORME prononcee par un personnel
--  habilite. Tant que la qualification n'existe pas, le don n'est pas
--  du stock : il n'apparait ni sur la carte, ni dans les precommandes.
-- =====================================================================

-- ---------------------------------------------------------------------
-- 15. donneur — personnes qui donnent leur sang
-- ---------------------------------------------------------------------
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 16. creneau_don — plages de collecte ouvertes par une banque
-- ---------------------------------------------------------------------
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 17. rendez_vous_don — reservation d'une place sur un creneau
-- ---------------------------------------------------------------------
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 18. don — prelevement effectivement realise
-- ---------------------------------------------------------------------
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 19. qualification — analyses et validation du produit sanguin
-- ---------------------------------------------------------------------
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================================
--  DONNEES DE REFERENCE
-- =====================================================================

INSERT INTO `groupe_sanguin` (`id`, `code`, `systeme_abo`, `rhesus`, `part_population`, `rang`) VALUES
  (1, 'O+', 'O', 'POSITIF', 38.5, 1),
  (2, 'O-', 'O', 'NEGATIF', 4.3, 2),
  (3, 'A+', 'A', 'POSITIF', 26.8, 3),
  (4, 'A-', 'A', 'NEGATIF', 2.6, 4),
  (5, 'B+', 'B', 'POSITIF', 20.4, 5),
  (6, 'B-', 'B', 'NEGATIF', 1.7, 6),
  (7, 'AB+', 'AB', 'POSITIF', 4.9, 7),
  (8, 'AB-', 'AB', 'NEGATIF', 0.8, 8);

INSERT INTO `compatibilite` (`groupe_receveur_id`, `groupe_donneur_id`, `priorite`) VALUES
  (2, 2, 1),
  (1, 2, 2),
  (1, 1, 1),
  (4, 2, 2),
  (4, 4, 1),
  (3, 2, 2),
  (3, 1, 2),
  (3, 4, 2),
  (3, 3, 1),
  (6, 2, 2),
  (6, 6, 1),
  (5, 2, 2),
  (5, 1, 2),
  (5, 6, 2),
  (5, 5, 1),
  (8, 2, 2),
  (8, 4, 2),
  (8, 6, 2),
  (8, 8, 1),
  (7, 2, 2),
  (7, 1, 2),
  (7, 4, 2),
  (7, 3, 2),
  (7, 6, 2),
  (7, 5, 2),
  (7, 8, 2),
  (7, 7, 1);

INSERT INTO `banque` (`id`, `code`, `nom`, `type`, `adresse`, `ville`, `departement`, `latitude`, `longitude`, `telephone`, `telephone_urgence`, `email`, `horaires`, `capacite_stockage`, `statut`, `date_creation`, `date_maj`) VALUES
  (1, 'BQ-COT-001', 'CNTS — Centre national de transfusion sanguine', 'CNTS', 'Boulevard de la Marina, Akpakpa', 'Cotonou', 'Littoral', 6.3654, 2.4183, '+229 21 31 20 40', '+229 21 31 20 40', 'cot-001@hemolink.bj', 'Lun-Ven 07h30-18h00 · Sam 08h00-13h00 · Urgences 24h/24', 900, 'ACTIVE', '2026-06-03 09:30:00', '2026-09-21 09:30:00'),
  (2, 'BQ-COT-002', 'CNHU-HKM — Centre national hospitalier universitaire', 'CHU', 'Avenue Jean-Paul II, Cadjèhoun', 'Cotonou', 'Littoral', 6.3703, 2.3912, '+229 21 30 01 55', '+229 21 31 20 40', 'cot-002@hemolink.bj', 'Lun-Ven 07h30-18h00 · Sam 08h00-13h00 · Urgences 24h/24', 600, 'ACTIVE', '2026-06-03 09:30:00', '2026-09-22 09:30:00'),
  (3, 'BQ-COT-003', 'Hôpital de zone de Ménontin', 'HOPITAL', 'Quartier Ménontin, 12e arrondissement', 'Cotonou', 'Littoral', 6.3781, 2.3684, '+229 21 38 44 10', NULL, 'cot-003@hemolink.bj', 'Lun-Ven 07h30-18h00 · Sam 08h00-13h00 · Urgences 24h/24', 250, 'ACTIVE', '2026-06-03 09:30:00', '2026-09-16 09:30:00'),
  (4, 'BQ-COT-004', 'Hôpital Saint-Luc', 'CLINIQUE', 'Rue 1248, Gbégamey', 'Cotonou', 'Littoral', 6.3625, 2.4026, '+229 21 32 18 76', NULL, 'cot-004@hemolink.bj', 'Lun-Ven 07h30-18h00 · Sam 08h00-13h00 · Urgences 24h/24', 150, 'ACTIVE', '2026-06-03 09:30:00', '2026-09-11 09:30:00'),
  (5, 'BQ-ABC-001', 'Hôpital de zone d’Abomey-Calavi', 'HOPITAL', 'Carrefour Tankpè, route de Godomey', 'Abomey-Calavi', 'Atlantique', 6.4489, 2.3556, '+229 21 36 02 18', NULL, 'abc-001@hemolink.bj', 'Lun-Ven 07h30-18h00 · Sam 08h00-13h00 · Urgences 24h/24', 300, 'ACTIVE', '2026-06-03 09:30:00', '2026-09-23 09:30:00'),
  (6, 'BQ-PNO-001', 'CHUD Ouémé-Plateau', 'CHU', 'Quartier Djègan-Kpèvi', 'Porto-Novo', 'Ouémé', 6.4969, 2.6289, '+229 20 21 30 12', '+229 21 31 20 40', 'pno-001@hemolink.bj', 'Lun-Ven 07h30-18h00 · Sam 08h00-13h00 · Urgences 24h/24', 400, 'ACTIVE', '2026-06-03 09:30:00', '2026-09-22 09:30:00'),
  (7, 'BQ-PNO-002', 'Clinique Louis Pasteur', 'CLINIQUE', 'Avenue Pasteur, centre-ville', 'Porto-Novo', 'Ouémé', 6.4912, 2.6154, '+229 20 21 45 60', NULL, 'pno-002@hemolink.bj', 'Lun-Ven 07h30-18h00 · Sam 08h00-13h00 · Urgences 24h/24', 120, 'ACTIVE', '2026-06-03 09:30:00', '2026-09-25 09:30:00'),
  (8, 'BQ-BOH-001', 'CHUD Borgou-Alibori', 'CHU', 'Route de Transa', 'Parakou', 'Borgou', 9.3372, 2.6303, '+229 23 61 07 44', '+229 21 31 20 40', 'boh-001@hemolink.bj', 'Lun-Ven 07h30-18h00 · Sam 08h00-13h00 · Urgences 24h/24', 450, 'ACTIVE', '2026-06-03 09:30:00', '2026-09-05 09:30:00'),
  (9, 'BQ-ZOU-001', 'CHUD Zou-Collines', 'CHU', 'Quartier Hounli', 'Abomey', 'Zou', 7.1853, 1.9912, '+229 22 50 01 33', '+229 21 31 20 40', 'zou-001@hemolink.bj', 'Lun-Ven 07h30-18h00 · Sam 08h00-13h00 · Urgences 24h/24', 280, 'ACTIVE', '2026-06-03 09:30:00', '2026-09-12 09:30:00'),
  (10, 'BQ-MON-001', 'CHUD Mono-Couffo', 'CHU', 'Route inter-États, quartier Agnivèdji', 'Lokossa', 'Mono', 6.6387, 1.7165, '+229 22 41 10 25', '+229 21 31 20 40', 'mon-001@hemolink.bj', 'Lun-Ven 07h30-18h00 · Sam 08h00-13h00 · Urgences 24h/24', 260, 'ACTIVE', '2026-06-03 09:30:00', '2026-09-18 09:30:00'),
  (11, 'BQ-ATA-001', 'CHUD Atacora-Donga', 'CHU', 'Quartier Yimporima', 'Natitingou', 'Atacora', 10.3045, 1.3796, '+229 23 82 11 07', '+229 21 31 20 40', 'ata-001@hemolink.bj', 'Lun-Ven 07h30-18h00 · Sam 08h00-13h00 · Urgences 24h/24', 220, 'ACTIVE', '2026-06-03 09:30:00', '2026-09-16 09:30:00'),
  (12, 'BQ-ATA-002', 'Hôpital Saint-Jean-de-Dieu', 'HOPITAL', 'Route de Porga', 'Tanguiéta', 'Atacora', 10.6212, 1.2653, '+229 23 83 00 42', NULL, 'ata-002@hemolink.bj', 'Lun-Ven 07h30-18h00 · Sam 08h00-13h00 · Urgences 24h/24', 180, 'ACTIVE', '2026-06-03 09:30:00', '2026-09-24 09:30:00');

INSERT INTO `utilisateur` (`id`, `banque_id`, `email`, `mot_de_passe`, `roles`, `nom`, `prenom`, `telephone`, `actif`, `derniere_connexion`, `date_creation`) VALUES
  (1, NULL, 'admin@hemolink.bj', '$2y$12$r9kupNqOz4Ebw7L7k6cM7ektbeGbkATI9lQTr2zdqqkIiWfvD9WP.', '["ROLE_ADMIN"]', 'ADANDE', 'Sylvestre', '+229 97 11 22 33', 1, '2026-09-30 14:30:00', '2026-06-23 09:30:00'),
  (2, 1, 'gestionnaire.cnts@hemolink.bj', '$2y$12$r9kupNqOz4Ebw7L7k6cM7ektbeGbkATI9lQTr2zdqqkIiWfvD9WP.', '["ROLE_GESTIONNAIRE"]', 'HOUNKPATIN', 'Rachelle', '+229 97 44 55 66', 1, '2026-09-30 11:30:00', '2026-06-23 09:30:00'),
  (3, 2, 'gestionnaire.cnhu@hemolink.bj', '$2y$12$r9kupNqOz4Ebw7L7k6cM7ektbeGbkATI9lQTr2zdqqkIiWfvD9WP.', '["ROLE_GESTIONNAIRE"]', 'TOSSOU', 'Marius', '+229 96 10 20 30', 1, '2026-10-01 07:30:00', '2026-06-23 09:30:00'),
  (4, 6, 'gestionnaire.chud-oueme@hemolink.bj', '$2y$12$r9kupNqOz4Ebw7L7k6cM7ektbeGbkATI9lQTr2zdqqkIiWfvD9WP.', '["ROLE_GESTIONNAIRE"]', 'AGOSSOU', 'Delphine', '+229 95 77 88 99', 1, '2026-09-30 08:30:00', '2026-06-23 09:30:00'),
  (5, 8, 'gestionnaire.chud-borgou@hemolink.bj', '$2y$12$r9kupNqOz4Ebw7L7k6cM7ektbeGbkATI9lQTr2zdqqkIiWfvD9WP.', '["ROLE_GESTIONNAIRE"]', 'SEIDOU', 'Abdoulaye', '+229 94 12 34 56', 1, '2026-09-30 11:30:00', '2026-06-23 09:30:00'),
  (6, 1, 'agent.cnts@hemolink.bj', '$2y$12$dMCaCQME1obVw3hSAHDcYuXm8M7RU7a2zW/RtOBbSCyQEQzr3M5qm', '["ROLE_AGENT"]', 'KOTCHOFA', 'Pélagie', '+229 97 65 43 21', 1, '2026-09-29 06:30:00', '2026-06-23 09:30:00'),
  (7, 2, 'agent.cnhu@hemolink.bj', '$2y$12$dMCaCQME1obVw3hSAHDcYuXm8M7RU7a2zW/RtOBbSCyQEQzr3M5qm', '["ROLE_AGENT"]', 'DOSSOU', 'Ibrahim', '+229 96 98 76 54', 1, '2026-09-30 03:30:00', '2026-06-23 09:30:00'),
  (8, 6, 'agent.chud-oueme@hemolink.bj', '$2y$12$dMCaCQME1obVw3hSAHDcYuXm8M7RU7a2zW/RtOBbSCyQEQzr3M5qm', '["ROLE_AGENT"]', 'AHOYO', 'Clarisse', '+229 95 32 10 98', 1, '2026-09-29 05:30:00', '2026-06-23 09:30:00'),
  (9, 1, 'biologiste.cnts@hemolink.bj', '$2y$12$r9kupNqOz4Ebw7L7k6cM7ektbeGbkATI9lQTr2zdqqkIiWfvD9WP.', '["ROLE_PERSONNEL"]', 'AKPOVI', 'Félicienne', '+229 97 20 41 83', 1, '2026-09-28 13:30:00', '2026-06-23 09:30:00'),
  (10, 2, 'biologiste.cnhu@hemolink.bj', '$2y$12$r9kupNqOz4Ebw7L7k6cM7ektbeGbkATI9lQTr2zdqqkIiWfvD9WP.', '["ROLE_PERSONNEL"]', 'LAWANI', 'Boubacar', '+229 96 57 19 02', 1, '2026-09-29 08:30:00', '2026-06-23 09:30:00'),
  (11, NULL, 'donneur.sossou@hemolink.bj', '$2y$12$r9kupNqOz4Ebw7L7k6cM7ektbeGbkATI9lQTr2zdqqkIiWfvD9WP.', '["ROLE_DONNEUR"]', 'SOSSOU', 'Armand', '+229 97 31 76 20', 1, '2026-09-30 07:30:00', '2026-06-23 09:30:00'),
  (12, NULL, 'donneur.tognon@hemolink.bj', '$2y$12$r9kupNqOz4Ebw7L7k6cM7ektbeGbkATI9lQTr2zdqqkIiWfvD9WP.', '["ROLE_DONNEUR"]', 'TOGNON', 'Mireille', '+229 96 44 08 15', 1, '2026-09-30 13:30:00', '2026-06-23 09:30:00');

INSERT INTO `demandeur` (`id`, `type`, `nom`, `responsable`, `telephone`, `email`, `adresse`, `ville`, `numero_agrement`, `date_creation`) VALUES
  (1, 'ETABLISSEMENT', 'Clinique Mahouna', 'Dr Félicien AGBODJAN', '+229 97 20 30 40', 'contact@mahouna.bj', 'Rue 420, Fidjrossè', 'Cotonou', 'AGR-2021-0147', '2026-08-22 09:30:00'),
  (2, 'ETABLISSEMENT', 'Hôpital de zone de Sèmè-Kpodji', 'Dr Nadège KPOGNON', '+229 96 51 62 73', 'hz.seme@sante.gouv.bj', 'Route de Lagos', 'Sèmè-Kpodji', 'AGR-2019-0032', '2026-04-06 09:30:00'),
  (3, 'ETABLISSEMENT', 'Centre médical Bon Samaritain', 'Mme Estelle ZINSOU', '+229 95 14 25 36', 'bonsamaritain@gmail.com', 'Quartier Vêdoko', 'Cotonou', 'AGR-2022-0211', '2026-06-11 09:30:00'),
  (4, 'ETABLISSEMENT', 'Clinique de l’Étoile', 'Dr Rodrigue AÏVODJI', '+229 97 83 94 05', 'etoile.clinique@yahoo.fr', 'Avenue Pape Jean-Paul II', 'Porto-Novo', 'AGR-2020-0088', '2025-12-25 09:30:00'),
  (5, 'PARTICULIER', 'FAMILLE SOGLO', 'Jean SOGLO', '+229 94 46 57 68', NULL, 'Quartier Zogbo', 'Cotonou', NULL, '2026-08-10 09:30:00'),
  (6, 'ETABLISSEMENT', 'Hôpital évangélique Bethesda', 'Dr Pauline HOUNSOU', '+229 21 33 44 55', 'bethesda@hopital.bj', 'Rue 1105, Akpakpa', 'Cotonou', 'AGR-2018-0015', '2026-04-22 09:30:00');

INSERT INTO `parametre` (`cle`, `valeur`, `libelle`, `type`, `date_maj`) VALUES
  ('rayon_recherche_km', '25', 'Rayon par défaut de la recherche de banques', 'NOMBRE', '2026-09-01 09:30:00'),
  ('seuil_tension', '5', 'En dessous de ce nombre de poches, le stock est « en tension »', 'NOMBRE', '2026-09-01 09:30:00'),
  ('delai_stock_obsolete_h', '24', 'Au-delà de ce délai sans mise à jour, le stock est « non communiqué »', 'NOMBRE', '2026-09-01 09:30:00'),
  ('duree_validite_precommande_h', '48', 'Délai avant expiration automatique d’une précommande non retirée', 'NOMBRE', '2026-09-01 09:30:00'),
  ('alerte_peremption_jours', '7', 'Nombre de jours avant péremption déclenchant une alerte', 'NOMBRE', '2026-09-01 09:30:00'),
  ('sms_actif', '1', 'Activation de l’envoi des SMS', 'BOOLEEN', '2026-09-01 09:30:00'),
  ('sms_expediteur', 'HEMOLINK', 'Nom affiché comme expéditeur des SMS', 'TEXTE', '2026-09-01 09:30:00'),
  ('organisation', 'Centre national de transfusion sanguine du Bénin', 'Organisation propriétaire de la plateforme', 'TEXTE', '2026-09-01 09:30:00'),
  ('email_contact', 'contact@hemolink.bj', 'Adresse de contact affichée', 'TEXTE', '2026-09-01 09:30:00'),
  ('telephone_urgence', '+229 21 31 20 40', 'Numéro d’urgence affiché sur la carte', 'TEXTE', '2026-09-01 09:30:00'),
  ('capacite_creneau_defaut', '8', 'Nombre de donneurs accueillis par créneau', 'NOMBRE', '2026-09-01 09:30:00'),
  ('delai_annulation_rdv_h', '12', 'Délai minimal avant le créneau pour annuler ou déplacer un rendez-vous', 'NOMBRE', '2026-09-01 09:30:00'),
  ('delai_entre_dons_jours', '90', 'Intervalle minimal entre deux dons de sang total', 'NOMBRE', '2026-09-01 09:30:00'),
  ('age_min_don', '18', 'Âge minimal pour donner son sang', 'NOMBRE', '2026-09-01 09:30:00'),
  ('age_max_don', '65', 'Âge maximal pour donner son sang', 'NOMBRE', '2026-09-01 09:30:00'),
  ('hemoglobine_min_femme', '12.0', 'Taux d’hémoglobine minimal avant prélèvement, en g/dL (femme)', 'TEXTE', '2026-09-01 09:30:00'),
  ('hemoglobine_min_homme', '13.0', 'Taux d’hémoglobine minimal avant prélèvement, en g/dL (homme)', 'TEXTE', '2026-09-01 09:30:00'),
  ('version_schema', '1.0', 'Version du schéma de base de données', 'TEXTE', '2026-09-01 09:30:00');


-- =====================================================================
--  JEU DE DEMONSTRATION
-- =====================================================================

INSERT INTO `precommande` (`id`, `numero`, `banque_id`, `demandeur_id`, `utilisateur_traitant_id`, `statut`, `urgence`, `date_souhaitee`, `motif`, `reference_patient`, `date_creation`, `date_confirmation`, `date_retrait`, `date_annulation`, `commentaire`) VALUES
  (1, 'PRE-COT-2026-00001', 1, 1, 2, 'RETIREE', 'NORMALE', '2026-09-26', 'Programme chirurgical', 'PAT-2026-1001', '2026-09-25 07:30:00', '2026-09-25 09:30:00', '2026-09-26 09:30:00', NULL, NULL),
  (2, 'PRE-COT-2026-00002', 2, 2, 3, 'PRETE', 'URGENTE', '2026-09-30', 'Hémorragie du post-partum', 'PAT-2026-1002', '2026-09-29 03:30:00', '2026-09-29 05:30:00', NULL, NULL, NULL),
  (3, 'PRE-COT-2026-00003', 1, 6, 2, 'CONFIRMEE', 'VITALE', '2026-10-01', 'Accident de la voie publique', 'PAT-2026-1003', '2026-09-30 04:30:00', '2026-09-30 06:30:00', NULL, NULL, NULL),
  (4, 'PRE-COT-2026-00004', 6, 4, 4, 'CONFIRMEE', 'NORMALE', '2026-10-01', 'Drépanocytose — transfusion', 'PAT-2026-1004', '2026-09-30 08:30:00', '2026-09-30 10:30:00', NULL, NULL, NULL),
  (5, 'PRE-COT-2026-00005', 2, 3, NULL, 'EN_ATTENTE', 'URGENTE', '2026-10-02', 'Intervention programmée', 'PAT-2026-1005', '2026-10-01 03:30:00', NULL, NULL, NULL, NULL),
  (6, 'PRE-COT-2026-00006', 8, 5, NULL, 'EN_ATTENTE', 'NORMALE', '2026-10-02', 'Don dirigé familial', 'PAT-2026-1006', '2026-10-01 03:30:00', NULL, NULL, NULL, NULL),
  (7, 'PRE-COT-2026-00007', 4, 1, NULL, 'ANNULEE', 'NORMALE', '2026-09-28', 'Patient transféré', 'PAT-2026-1007', '2026-09-27 07:30:00', NULL, NULL, '2026-09-27 12:30:00', 'Patient transféré au CNHU');

INSERT INTO `ligne_precommande` (`id`, `precommande_id`, `groupe_sanguin_id`, `quantite_demandee`, `quantite_confirmee`, `groupe_substitue_id`) VALUES
  (1, 1, 1, 4, 4, NULL),
  (2, 2, 2, 3, 3, NULL),
  (3, 2, 3, 2, 2, NULL),
  (4, 3, 5, 5, 5, NULL),
  (5, 4, 3, 6, 4, NULL),
  (6, 5, 7, 2, NULL, NULL),
  (7, 6, 1, 2, NULL, NULL),
  (8, 7, 6, 3, NULL, NULL);

INSERT INTO `poche` (`id`, `code_poche`, `banque_id`, `groupe_sanguin_id`, `date_prelevement`, `date_peremption`, `volume_ml`, `statut`, `precommande_id`, `date_creation`) VALUES
  (1, 'PO-01-00001', 1, 1, '2026-09-20', '2026-11-01', 350, 'DELIVREE', 1, '2026-09-20 08:15:00'),
  (2, 'PO-01-00002', 1, 1, '2026-09-04', '2026-10-16', 450, 'DELIVREE', 1, '2026-09-04 08:15:00'),
  (3, 'PO-01-00003', 1, 1, '2026-09-12', '2026-10-24', 450, 'DELIVREE', 1, '2026-09-12 08:15:00'),
  (4, 'PO-01-00004', 1, 1, '2026-09-10', '2026-10-22', 350, 'DELIVREE', 1, '2026-09-10 08:15:00'),
  (5, 'PO-01-00005', 1, 1, '2026-09-02', '2026-10-14', 250, 'DISPONIBLE', NULL, '2026-09-02 08:15:00'),
  (6, 'PO-01-00006', 1, 1, '2026-09-11', '2026-10-23', 250, 'DISPONIBLE', NULL, '2026-09-11 08:15:00'),
  (7, 'PO-01-00007', 1, 1, '2026-09-24', '2026-11-05', 450, 'DISPONIBLE', NULL, '2026-09-24 08:15:00'),
  (8, 'PO-01-00008', 1, 1, '2026-09-28', '2026-11-09', 450, 'DISPONIBLE', NULL, '2026-09-28 08:15:00'),
  (9, 'PO-01-00009', 1, 1, '2026-09-26', '2026-11-07', 250, 'DISPONIBLE', NULL, '2026-09-26 08:15:00'),
  (10, 'PO-01-00010', 1, 1, '2026-09-02', '2026-10-14', 250, 'DISPONIBLE', NULL, '2026-09-02 08:15:00'),
  (11, 'PO-01-00011', 1, 1, '2026-09-16', '2026-10-28', 350, 'DISPONIBLE', NULL, '2026-09-16 08:15:00'),
  (12, 'PO-01-00012', 1, 1, '2026-09-24', '2026-11-05', 350, 'DISPONIBLE', NULL, '2026-09-24 08:15:00'),
  (13, 'PO-01-00013', 1, 1, '2026-09-24', '2026-11-05', 450, 'DISPONIBLE', NULL, '2026-09-24 08:15:00'),
  (14, 'PO-01-00014', 1, 2, '2026-09-22', '2026-11-03', 350, 'DISPONIBLE', NULL, '2026-09-22 08:15:00'),
  (15, 'PO-01-00015', 1, 2, '2026-09-05', '2026-10-17', 250, 'DISPONIBLE', NULL, '2026-09-05 08:15:00'),
  (16, 'PO-01-00016', 1, 2, '2026-09-20', '2026-11-01', 250, 'DISPONIBLE', NULL, '2026-09-20 08:15:00'),
  (17, 'PO-01-00017', 1, 2, '2026-09-03', '2026-10-15', 250, 'DISPONIBLE', NULL, '2026-09-03 08:15:00'),
  (18, 'PO-01-00018', 1, 2, '2026-09-05', '2026-10-17', 450, 'DISPONIBLE', NULL, '2026-09-05 08:15:00'),
  (19, 'PO-01-00019', 1, 2, '2026-09-23', '2026-11-04', 450, 'DISPONIBLE', NULL, '2026-09-23 08:15:00'),
  (20, 'PO-01-00020', 1, 2, '2026-09-22', '2026-11-03', 350, 'DISPONIBLE', NULL, '2026-09-22 08:15:00'),
  (21, 'PO-01-00021', 1, 2, '2026-09-16', '2026-10-28', 250, 'DISPONIBLE', NULL, '2026-09-16 08:15:00'),
  (22, 'PO-01-00022', 1, 2, '2026-09-19', '2026-10-31', 350, 'DISPONIBLE', NULL, '2026-09-19 08:15:00'),
  (23, 'PO-01-00023', 1, 2, '2026-09-14', '2026-10-26', 450, 'DISPONIBLE', NULL, '2026-09-14 08:15:00'),
  (24, 'PO-01-00024', 1, 2, '2026-09-04', '2026-10-16', 450, 'DISPONIBLE', NULL, '2026-09-04 08:15:00'),
  (25, 'PO-01-00025', 1, 2, '2026-09-29', '2026-11-10', 350, 'DISPONIBLE', NULL, '2026-09-29 08:15:00'),
  (26, 'PO-01-00026', 1, 2, '2026-09-18', '2026-10-30', 450, 'DISPONIBLE', NULL, '2026-09-18 08:15:00'),
  (27, 'PO-01-00027', 1, 2, '2026-09-10', '2026-10-22', 350, 'DISPONIBLE', NULL, '2026-09-10 08:15:00'),
  (28, 'PO-01-00028', 1, 3, '2026-09-27', '2026-11-08', 450, 'DISPONIBLE', NULL, '2026-09-27 08:15:00'),
  (29, 'PO-01-00029', 1, 3, '2026-09-01', '2026-10-13', 350, 'DISPONIBLE', NULL, '2026-09-01 08:15:00'),
  (30, 'PO-01-00030', 1, 3, '2026-09-18', '2026-10-30', 250, 'DISPONIBLE', NULL, '2026-09-18 08:15:00'),
  (31, 'PO-01-00031', 1, 3, '2026-09-17', '2026-10-29', 450, 'DISPONIBLE', NULL, '2026-09-17 08:15:00'),
  (32, 'PO-01-00032', 1, 3, '2026-09-25', '2026-11-06', 450, 'DISPONIBLE', NULL, '2026-09-25 08:15:00'),
  (33, 'PO-01-00033', 1, 3, '2026-09-07', '2026-10-19', 450, 'DISPONIBLE', NULL, '2026-09-07 08:15:00'),
  (34, 'PO-01-00034', 1, 3, '2026-09-21', '2026-11-02', 250, 'DISPONIBLE', NULL, '2026-09-21 08:15:00'),
  (35, 'PO-01-00035', 1, 3, '2026-09-15', '2026-10-27', 350, 'DISPONIBLE', NULL, '2026-09-15 08:15:00'),
  (36, 'PO-01-00036', 1, 3, '2026-09-30', '2026-11-11', 450, 'DISPONIBLE', NULL, '2026-09-30 08:15:00'),
  (37, 'PO-01-00037', 1, 4, '2026-09-12', '2026-10-24', 450, 'DISPONIBLE', NULL, '2026-09-12 08:15:00'),
  (38, 'PO-01-00038', 1, 4, '2026-09-16', '2026-10-28', 250, 'DISPONIBLE', NULL, '2026-09-16 08:15:00'),
  (39, 'PO-01-00039', 1, 5, '2026-09-07', '2026-10-19', 350, 'RESERVEE', 3, '2026-09-07 08:15:00'),
  (40, 'PO-01-00040', 1, 5, '2026-09-12', '2026-10-24', 250, 'RESERVEE', 3, '2026-09-12 08:15:00'),
  (41, 'PO-01-00041', 1, 5, '2026-09-23', '2026-11-04', 250, 'RESERVEE', 3, '2026-09-23 08:15:00'),
  (42, 'PO-01-00042', 1, 5, '2026-09-24', '2026-11-05', 250, 'RESERVEE', 3, '2026-09-24 08:15:00'),
  (43, 'PO-01-00043', 1, 5, '2026-09-24', '2026-11-05', 250, 'RESERVEE', 3, '2026-09-24 08:15:00'),
  (44, 'PO-01-00044', 1, 5, '2026-09-12', '2026-10-24', 350, 'DISPONIBLE', NULL, '2026-09-12 08:15:00'),
  (45, 'PO-01-00045', 1, 5, '2026-09-06', '2026-10-18', 250, 'DISPONIBLE', NULL, '2026-09-06 08:15:00'),
  (46, 'PO-01-00046', 1, 5, '2026-09-16', '2026-10-28', 250, 'DISPONIBLE', NULL, '2026-09-16 08:15:00'),
  (47, 'PO-01-00047', 1, 5, '2026-09-19', '2026-10-31', 350, 'DISPONIBLE', NULL, '2026-09-19 08:15:00'),
  (48, 'PO-01-00048', 1, 5, '2026-09-12', '2026-10-24', 450, 'DISPONIBLE', NULL, '2026-09-12 08:15:00'),
  (49, 'PO-01-00049', 1, 5, '2026-09-28', '2026-11-09', 250, 'DISPONIBLE', NULL, '2026-09-28 08:15:00'),
  (50, 'PO-01-00050', 1, 5, '2026-09-01', '2026-10-13', 350, 'DISPONIBLE', NULL, '2026-09-01 08:15:00'),
  (51, 'PO-01-00051', 1, 5, '2026-09-04', '2026-10-16', 450, 'DISPONIBLE', NULL, '2026-09-04 08:15:00'),
  (52, 'PO-01-00052', 1, 5, '2026-09-16', '2026-10-28', 350, 'DISPONIBLE', NULL, '2026-09-16 08:15:00'),
  (53, 'PO-01-00053', 1, 5, '2026-09-14', '2026-10-26', 350, 'DISPONIBLE', NULL, '2026-09-14 08:15:00'),
  (54, 'PO-01-00054', 1, 5, '2026-09-15', '2026-10-27', 350, 'DISPONIBLE', NULL, '2026-09-15 08:15:00'),
  (55, 'PO-01-00055', 1, 5, '2026-09-13', '2026-10-25', 450, 'DISPONIBLE', NULL, '2026-09-13 08:15:00'),
  (56, 'PO-01-00056', 1, 5, '2026-09-13', '2026-10-25', 250, 'DISPONIBLE', NULL, '2026-09-13 08:15:00'),
  (57, 'PO-01-00057', 1, 5, '2026-09-24', '2026-11-05', 450, 'DISPONIBLE', NULL, '2026-09-24 08:15:00'),
  (58, 'PO-01-00058', 1, 5, '2026-09-09', '2026-10-21', 350, 'DISPONIBLE', NULL, '2026-09-09 08:15:00'),
  (59, 'PO-01-00059', 1, 5, '2026-09-16', '2026-10-28', 250, 'DISPONIBLE', NULL, '2026-09-16 08:15:00'),
  (60, 'PO-01-00060', 1, 6, '2026-09-23', '2026-11-04', 450, 'DISPONIBLE', NULL, '2026-09-23 08:15:00');
INSERT INTO `poche` (`id`, `code_poche`, `banque_id`, `groupe_sanguin_id`, `date_prelevement`, `date_peremption`, `volume_ml`, `statut`, `precommande_id`, `date_creation`) VALUES
  (61, 'PO-01-00061', 1, 6, '2026-09-29', '2026-11-10', 450, 'DISPONIBLE', NULL, '2026-09-29 08:15:00'),
  (62, 'PO-01-00062', 1, 6, '2026-09-06', '2026-10-18', 350, 'DISPONIBLE', NULL, '2026-09-06 08:15:00'),
  (63, 'PO-01-00063', 1, 6, '2026-09-16', '2026-10-28', 250, 'DISPONIBLE', NULL, '2026-09-16 08:15:00'),
  (64, 'PO-01-00064', 1, 6, '2026-09-28', '2026-11-09', 250, 'DISPONIBLE', NULL, '2026-09-28 08:15:00'),
  (65, 'PO-01-00065', 1, 6, '2026-09-04', '2026-10-16', 250, 'DISPONIBLE', NULL, '2026-09-04 08:15:00'),
  (66, 'PO-01-00066', 1, 6, '2026-09-15', '2026-10-27', 450, 'DISPONIBLE', NULL, '2026-09-15 08:15:00'),
  (67, 'PO-01-00067', 1, 6, '2026-09-13', '2026-10-25', 450, 'DISPONIBLE', NULL, '2026-09-13 08:15:00'),
  (68, 'PO-01-00068', 1, 6, '2026-09-21', '2026-11-02', 250, 'DISPONIBLE', NULL, '2026-09-21 08:15:00'),
  (69, 'PO-01-00069', 1, 6, '2026-09-25', '2026-11-06', 250, 'DISPONIBLE', NULL, '2026-09-25 08:15:00'),
  (70, 'PO-01-00070', 1, 6, '2026-09-14', '2026-10-26', 350, 'DISPONIBLE', NULL, '2026-09-14 08:15:00'),
  (71, 'PO-01-00071', 1, 6, '2026-09-03', '2026-10-15', 350, 'DISPONIBLE', NULL, '2026-09-03 08:15:00'),
  (72, 'PO-01-00072', 1, 6, '2026-09-11', '2026-10-23', 450, 'DISPONIBLE', NULL, '2026-09-11 08:15:00'),
  (73, 'PO-01-00073', 1, 6, '2026-09-13', '2026-10-25', 450, 'DISPONIBLE', NULL, '2026-09-13 08:15:00'),
  (74, 'PO-01-00074', 1, 6, '2026-09-24', '2026-11-05', 450, 'DISPONIBLE', NULL, '2026-09-24 08:15:00'),
  (75, 'PO-01-00075', 1, 6, '2026-09-26', '2026-11-07', 450, 'DISPONIBLE', NULL, '2026-09-26 08:15:00'),
  (76, 'PO-01-00076', 1, 6, '2026-09-22', '2026-11-03', 250, 'DISPONIBLE', NULL, '2026-09-22 08:15:00'),
  (77, 'PO-01-00077', 1, 6, '2026-09-27', '2026-11-08', 350, 'DISPONIBLE', NULL, '2026-09-27 08:15:00'),
  (78, 'PO-01-00078', 1, 6, '2026-09-15', '2026-10-27', 250, 'DISPONIBLE', NULL, '2026-09-15 08:15:00'),
  (79, 'PO-01-00079', 1, 6, '2026-09-06', '2026-10-18', 250, 'DISPONIBLE', NULL, '2026-09-06 08:15:00'),
  (80, 'PO-01-00080', 1, 7, '2026-09-08', '2026-10-20', 450, 'DISPONIBLE', NULL, '2026-09-08 08:15:00'),
  (81, 'PO-01-00081', 1, 7, '2026-09-04', '2026-10-16', 450, 'DISPONIBLE', NULL, '2026-09-04 08:15:00'),
  (82, 'PO-01-00082', 1, 7, '2026-09-26', '2026-11-07', 450, 'DISPONIBLE', NULL, '2026-09-26 08:15:00'),
  (83, 'PO-01-00083', 1, 7, '2026-09-01', '2026-10-13', 350, 'DISPONIBLE', NULL, '2026-09-01 08:15:00'),
  (84, 'PO-01-00084', 1, 7, '2026-09-01', '2026-10-13', 350, 'DISPONIBLE', NULL, '2026-09-01 08:15:00'),
  (85, 'PO-01-00085', 1, 7, '2026-09-22', '2026-11-03', 250, 'DISPONIBLE', NULL, '2026-09-22 08:15:00'),
  (86, 'PO-01-00086', 1, 7, '2026-09-10', '2026-10-22', 450, 'DISPONIBLE', NULL, '2026-09-10 08:15:00'),
  (87, 'PO-01-00087', 1, 7, '2026-09-23', '2026-11-04', 250, 'DISPONIBLE', NULL, '2026-09-23 08:15:00'),
  (88, 'PO-01-00088', 1, 7, '2026-09-30', '2026-11-11', 450, 'DISPONIBLE', NULL, '2026-09-30 08:15:00'),
  (89, 'PO-01-00089', 1, 7, '2026-09-18', '2026-10-30', 250, 'DISPONIBLE', NULL, '2026-09-18 08:15:00'),
  (90, 'PO-01-00090', 1, 7, '2026-09-10', '2026-10-22', 250, 'DISPONIBLE', NULL, '2026-09-10 08:15:00'),
  (91, 'PO-01-00091', 1, 8, '2026-09-15', '2026-10-27', 250, 'DISPONIBLE', NULL, '2026-09-15 08:15:00'),
  (92, 'PO-01-00092', 1, 8, '2026-09-04', '2026-10-16', 450, 'DISPONIBLE', NULL, '2026-09-04 08:15:00'),
  (93, 'PO-01-00093', 1, 8, '2026-09-03', '2026-10-15', 350, 'DISPONIBLE', NULL, '2026-09-03 08:15:00'),
  (94, 'PO-01-00094', 1, 8, '2026-09-27', '2026-11-08', 450, 'DISPONIBLE', NULL, '2026-09-27 08:15:00'),
  (95, 'PO-01-00095', 1, 8, '2026-09-14', '2026-10-26', 350, 'DISPONIBLE', NULL, '2026-09-14 08:15:00'),
  (96, 'PO-01-00096', 1, 8, '2026-09-20', '2026-11-01', 350, 'DISPONIBLE', NULL, '2026-09-20 08:15:00'),
  (97, 'PO-01-00097', 1, 8, '2026-09-21', '2026-11-02', 350, 'DISPONIBLE', NULL, '2026-09-21 08:15:00'),
  (98, 'PO-01-00098', 1, 8, '2026-09-13', '2026-10-25', 450, 'DISPONIBLE', NULL, '2026-09-13 08:15:00'),
  (99, 'PO-01-00099', 1, 8, '2026-09-18', '2026-10-30', 250, 'DISPONIBLE', NULL, '2026-09-18 08:15:00'),
  (100, 'PO-01-00100', 1, 8, '2026-09-19', '2026-10-31', 450, 'DISPONIBLE', NULL, '2026-09-19 08:15:00'),
  (101, 'PO-02-00101', 2, 1, '2026-09-20', '2026-11-01', 250, 'DISPONIBLE', NULL, '2026-09-20 08:15:00'),
  (102, 'PO-02-00102', 2, 1, '2026-09-07', '2026-10-19', 350, 'DISPONIBLE', NULL, '2026-09-07 08:15:00'),
  (103, 'PO-02-00103', 2, 1, '2026-09-02', '2026-10-14', 250, 'DISPONIBLE', NULL, '2026-09-02 08:15:00'),
  (104, 'PO-02-00104', 2, 1, '2026-09-05', '2026-10-17', 350, 'DISPONIBLE', NULL, '2026-09-05 08:15:00'),
  (105, 'PO-02-00105', 2, 1, '2026-09-06', '2026-10-18', 450, 'DISPONIBLE', NULL, '2026-09-06 08:15:00'),
  (106, 'PO-02-00106', 2, 1, '2026-09-27', '2026-11-08', 450, 'DISPONIBLE', NULL, '2026-09-27 08:15:00'),
  (107, 'PO-02-00107', 2, 1, '2026-09-06', '2026-10-18', 350, 'DISPONIBLE', NULL, '2026-09-06 08:15:00'),
  (108, 'PO-02-00108', 2, 1, '2026-09-12', '2026-10-24', 350, 'DISPONIBLE', NULL, '2026-09-12 08:15:00'),
  (109, 'PO-02-00109', 2, 1, '2026-09-26', '2026-11-07', 450, 'DISPONIBLE', NULL, '2026-09-26 08:15:00'),
  (110, 'PO-02-00110', 2, 1, '2026-09-05', '2026-10-17', 350, 'DISPONIBLE', NULL, '2026-09-05 08:15:00'),
  (111, 'PO-02-00111', 2, 1, '2026-09-19', '2026-10-31', 450, 'DISPONIBLE', NULL, '2026-09-19 08:15:00'),
  (112, 'PO-02-00112', 2, 1, '2026-09-26', '2026-11-07', 250, 'DISPONIBLE', NULL, '2026-09-26 08:15:00'),
  (113, 'PO-02-00113', 2, 1, '2026-09-19', '2026-10-31', 350, 'DISPONIBLE', NULL, '2026-09-19 08:15:00'),
  (114, 'PO-02-00114', 2, 2, '2026-09-06', '2026-10-18', 250, 'RESERVEE', 2, '2026-09-06 08:15:00'),
  (115, 'PO-02-00115', 2, 2, '2026-09-18', '2026-10-30', 450, 'RESERVEE', 2, '2026-09-18 08:15:00'),
  (116, 'PO-02-00116', 2, 2, '2026-09-11', '2026-10-23', 250, 'RESERVEE', 2, '2026-09-11 08:15:00'),
  (117, 'PO-02-00117', 2, 2, '2026-09-27', '2026-11-08', 450, 'DISPONIBLE', NULL, '2026-09-27 08:15:00'),
  (118, 'PO-02-00118', 2, 2, '2026-09-25', '2026-11-06', 350, 'DISPONIBLE', NULL, '2026-09-25 08:15:00'),
  (119, 'PO-02-00119', 2, 2, '2026-09-22', '2026-11-03', 350, 'DISPONIBLE', NULL, '2026-09-22 08:15:00'),
  (120, 'PO-02-00120', 2, 2, '2026-09-02', '2026-10-14', 250, 'DISPONIBLE', NULL, '2026-09-02 08:15:00');
INSERT INTO `poche` (`id`, `code_poche`, `banque_id`, `groupe_sanguin_id`, `date_prelevement`, `date_peremption`, `volume_ml`, `statut`, `precommande_id`, `date_creation`) VALUES
  (121, 'PO-02-00121', 2, 2, '2026-09-25', '2026-11-06', 250, 'DISPONIBLE', NULL, '2026-09-25 08:15:00'),
  (122, 'PO-02-00122', 2, 2, '2026-09-11', '2026-10-23', 450, 'DISPONIBLE', NULL, '2026-09-11 08:15:00'),
  (123, 'PO-02-00123', 2, 2, '2026-09-04', '2026-10-16', 350, 'DISPONIBLE', NULL, '2026-09-04 08:15:00'),
  (124, 'PO-02-00124', 2, 2, '2026-09-10', '2026-10-22', 250, 'DISPONIBLE', NULL, '2026-09-10 08:15:00'),
  (125, 'PO-02-00125', 2, 3, '2026-09-25', '2026-11-06', 450, 'RESERVEE', 2, '2026-09-25 08:15:00'),
  (126, 'PO-02-00126', 2, 3, '2026-09-20', '2026-11-01', 450, 'RESERVEE', 2, '2026-09-20 08:15:00'),
  (127, 'PO-02-00127', 2, 3, '2026-09-22', '2026-11-03', 350, 'DISPONIBLE', NULL, '2026-09-22 08:15:00'),
  (128, 'PO-02-00128', 2, 3, '2026-09-30', '2026-11-11', 450, 'DISPONIBLE', NULL, '2026-09-30 08:15:00'),
  (129, 'PO-02-00129', 2, 3, '2026-09-21', '2026-11-02', 250, 'DISPONIBLE', NULL, '2026-09-21 08:15:00'),
  (130, 'PO-02-00130', 2, 3, '2026-09-07', '2026-10-19', 350, 'DISPONIBLE', NULL, '2026-09-07 08:15:00'),
  (131, 'PO-02-00131', 2, 3, '2026-09-01', '2026-10-13', 350, 'DISPONIBLE', NULL, '2026-09-01 08:15:00'),
  (132, 'PO-02-00132', 2, 3, '2026-09-02', '2026-10-14', 450, 'DISPONIBLE', NULL, '2026-09-02 08:15:00'),
  (133, 'PO-02-00133', 2, 3, '2026-09-02', '2026-10-14', 250, 'DISPONIBLE', NULL, '2026-09-02 08:15:00'),
  (134, 'PO-02-00134', 2, 3, '2026-09-09', '2026-10-21', 250, 'DISPONIBLE', NULL, '2026-09-09 08:15:00'),
  (135, 'PO-02-00135', 2, 4, '2026-09-09', '2026-10-21', 350, 'DISPONIBLE', NULL, '2026-09-09 08:15:00'),
  (136, 'PO-02-00136', 2, 5, '2026-09-02', '2026-10-14', 250, 'DISPONIBLE', NULL, '2026-09-02 08:15:00'),
  (137, 'PO-02-00137', 2, 5, '2026-09-16', '2026-10-28', 450, 'DISPONIBLE', NULL, '2026-09-16 08:15:00'),
  (138, 'PO-02-00138', 2, 5, '2026-09-25', '2026-11-06', 250, 'DISPONIBLE', NULL, '2026-09-25 08:15:00'),
  (139, 'PO-02-00139', 2, 5, '2026-09-12', '2026-10-24', 250, 'DISPONIBLE', NULL, '2026-09-12 08:15:00'),
  (140, 'PO-02-00140', 2, 5, '2026-09-27', '2026-11-08', 450, 'DISPONIBLE', NULL, '2026-09-27 08:15:00'),
  (141, 'PO-02-00141', 2, 5, '2026-09-30', '2026-11-11', 250, 'DISPONIBLE', NULL, '2026-09-30 08:15:00'),
  (142, 'PO-02-00142', 2, 5, '2026-09-29', '2026-11-10', 350, 'DISPONIBLE', NULL, '2026-09-29 08:15:00'),
  (143, 'PO-02-00143', 2, 5, '2026-09-17', '2026-10-29', 450, 'DISPONIBLE', NULL, '2026-09-17 08:15:00'),
  (144, 'PO-02-00144', 2, 5, '2026-09-10', '2026-10-22', 450, 'DISPONIBLE', NULL, '2026-09-10 08:15:00'),
  (145, 'PO-02-00145', 2, 5, '2026-09-23', '2026-11-04', 450, 'DISPONIBLE', NULL, '2026-09-23 08:15:00'),
  (146, 'PO-02-00146', 2, 5, '2026-09-12', '2026-10-24', 350, 'DISPONIBLE', NULL, '2026-09-12 08:15:00'),
  (147, 'PO-02-00147', 2, 6, '2026-09-23', '2026-11-04', 250, 'DISPONIBLE', NULL, '2026-09-23 08:15:00'),
  (148, 'PO-02-00148', 2, 6, '2026-09-01', '2026-10-13', 250, 'DISPONIBLE', NULL, '2026-09-01 08:15:00'),
  (149, 'PO-02-00149', 2, 6, '2026-09-08', '2026-10-20', 350, 'DISPONIBLE', NULL, '2026-09-08 08:15:00'),
  (150, 'PO-02-00150', 2, 6, '2026-09-09', '2026-10-21', 250, 'DISPONIBLE', NULL, '2026-09-09 08:15:00'),
  (151, 'PO-02-00151', 2, 6, '2026-09-15', '2026-10-27', 250, 'DISPONIBLE', NULL, '2026-09-15 08:15:00'),
  (152, 'PO-02-00152', 2, 6, '2026-09-26', '2026-11-07', 450, 'DISPONIBLE', NULL, '2026-09-26 08:15:00'),
  (153, 'PO-02-00153', 2, 6, '2026-09-30', '2026-11-11', 450, 'DISPONIBLE', NULL, '2026-09-30 08:15:00'),
  (154, 'PO-02-00154', 2, 6, '2026-09-08', '2026-10-20', 350, 'DISPONIBLE', NULL, '2026-09-08 08:15:00'),
  (155, 'PO-02-00155', 2, 6, '2026-09-25', '2026-11-06', 450, 'DISPONIBLE', NULL, '2026-09-25 08:15:00'),
  (156, 'PO-02-00156', 2, 6, '2026-09-05', '2026-10-17', 350, 'DISPONIBLE', NULL, '2026-09-05 08:15:00'),
  (157, 'PO-02-00157', 2, 6, '2026-09-16', '2026-10-28', 350, 'DISPONIBLE', NULL, '2026-09-16 08:15:00'),
  (158, 'PO-02-00158', 2, 6, '2026-09-10', '2026-10-22', 250, 'DISPONIBLE', NULL, '2026-09-10 08:15:00'),
  (159, 'PO-02-00159', 2, 6, '2026-09-04', '2026-10-16', 350, 'DISPONIBLE', NULL, '2026-09-04 08:15:00'),
  (160, 'PO-02-00160', 2, 6, '2026-09-19', '2026-10-31', 350, 'DISPONIBLE', NULL, '2026-09-19 08:15:00'),
  (161, 'PO-02-00161', 2, 6, '2026-09-16', '2026-10-28', 350, 'DISPONIBLE', NULL, '2026-09-16 08:15:00'),
  (162, 'PO-02-00162', 2, 6, '2026-09-10', '2026-10-22', 450, 'DISPONIBLE', NULL, '2026-09-10 08:15:00'),
  (163, 'PO-02-00163', 2, 6, '2026-09-01', '2026-10-13', 250, 'DISPONIBLE', NULL, '2026-09-01 08:15:00'),
  (164, 'PO-02-00164', 2, 6, '2026-09-27', '2026-11-08', 450, 'DISPONIBLE', NULL, '2026-09-27 08:15:00'),
  (165, 'PO-02-00165', 2, 6, '2026-09-20', '2026-11-01', 450, 'DISPONIBLE', NULL, '2026-09-20 08:15:00'),
  (166, 'PO-02-00166', 2, 6, '2026-09-18', '2026-10-30', 250, 'DISPONIBLE', NULL, '2026-09-18 08:15:00'),
  (167, 'PO-02-00167', 2, 6, '2026-09-20', '2026-11-01', 350, 'DISPONIBLE', NULL, '2026-09-20 08:15:00'),
  (168, 'PO-02-00168', 2, 6, '2026-09-09', '2026-10-21', 250, 'DISPONIBLE', NULL, '2026-09-09 08:15:00'),
  (169, 'PO-02-00169', 2, 7, '2026-09-22', '2026-11-03', 450, 'DISPONIBLE', NULL, '2026-09-22 08:15:00'),
  (170, 'PO-02-00170', 2, 7, '2026-09-21', '2026-11-02', 250, 'DISPONIBLE', NULL, '2026-09-21 08:15:00'),
  (171, 'PO-02-00171', 2, 7, '2026-09-15', '2026-10-27', 250, 'DISPONIBLE', NULL, '2026-09-15 08:15:00'),
  (172, 'PO-02-00172', 2, 7, '2026-09-30', '2026-11-11', 450, 'DISPONIBLE', NULL, '2026-09-30 08:15:00'),
  (173, 'PO-02-00173', 2, 7, '2026-09-24', '2026-11-05', 350, 'DISPONIBLE', NULL, '2026-09-24 08:15:00'),
  (174, 'PO-02-00174', 2, 7, '2026-09-12', '2026-10-24', 450, 'DISPONIBLE', NULL, '2026-09-12 08:15:00'),
  (175, 'PO-02-00175', 2, 7, '2026-09-02', '2026-10-14', 350, 'DISPONIBLE', NULL, '2026-09-02 08:15:00'),
  (176, 'PO-02-00176', 2, 7, '2026-09-26', '2026-11-07', 450, 'DISPONIBLE', NULL, '2026-09-26 08:15:00'),
  (177, 'PO-02-00177', 2, 8, '2026-09-09', '2026-10-21', 450, 'DISPONIBLE', NULL, '2026-09-09 08:15:00'),
  (178, 'PO-02-00178', 2, 8, '2026-09-06', '2026-10-18', 350, 'DISPONIBLE', NULL, '2026-09-06 08:15:00'),
  (179, 'PO-02-00179', 2, 8, '2026-09-21', '2026-11-02', 250, 'DISPONIBLE', NULL, '2026-09-21 08:15:00'),
  (180, 'PO-02-00180', 2, 8, '2026-09-16', '2026-10-28', 250, 'DISPONIBLE', NULL, '2026-09-16 08:15:00');
INSERT INTO `poche` (`id`, `code_poche`, `banque_id`, `groupe_sanguin_id`, `date_prelevement`, `date_peremption`, `volume_ml`, `statut`, `precommande_id`, `date_creation`) VALUES
  (181, 'PO-02-00181', 2, 8, '2026-09-28', '2026-11-09', 350, 'DISPONIBLE', NULL, '2026-09-28 08:15:00'),
  (182, 'PO-02-00182', 2, 8, '2026-09-13', '2026-10-25', 450, 'DISPONIBLE', NULL, '2026-09-13 08:15:00'),
  (183, 'PO-02-00183', 2, 8, '2026-09-27', '2026-11-08', 350, 'DISPONIBLE', NULL, '2026-09-27 08:15:00'),
  (184, 'PO-02-00184', 2, 8, '2026-09-07', '2026-10-19', 250, 'DISPONIBLE', NULL, '2026-09-07 08:15:00'),
  (185, 'PO-03-00185', 3, 1, '2026-09-19', '2026-10-31', 350, 'DISPONIBLE', NULL, '2026-09-19 08:15:00'),
  (186, 'PO-03-00186', 3, 1, '2026-09-28', '2026-11-09', 350, 'DISPONIBLE', NULL, '2026-09-28 08:15:00'),
  (187, 'PO-03-00187', 3, 1, '2026-09-21', '2026-11-02', 450, 'DISPONIBLE', NULL, '2026-09-21 08:15:00'),
  (188, 'PO-03-00188', 3, 2, '2026-09-09', '2026-10-21', 450, 'DISPONIBLE', NULL, '2026-09-09 08:15:00'),
  (189, 'PO-03-00189', 3, 2, '2026-09-12', '2026-10-24', 350, 'DISPONIBLE', NULL, '2026-09-12 08:15:00'),
  (190, 'PO-03-00190', 3, 2, '2026-09-03', '2026-10-15', 450, 'DISPONIBLE', NULL, '2026-09-03 08:15:00'),
  (191, 'PO-03-00191', 3, 2, '2026-09-26', '2026-11-07', 350, 'DISPONIBLE', NULL, '2026-09-26 08:15:00'),
  (192, 'PO-03-00192', 3, 2, '2026-09-26', '2026-11-07', 250, 'DISPONIBLE', NULL, '2026-09-26 08:15:00'),
  (193, 'PO-03-00193', 3, 2, '2026-09-18', '2026-10-30', 350, 'DISPONIBLE', NULL, '2026-09-18 08:15:00'),
  (194, 'PO-03-00194', 3, 2, '2026-09-23', '2026-11-04', 450, 'DISPONIBLE', NULL, '2026-09-23 08:15:00'),
  (195, 'PO-03-00195', 3, 2, '2026-09-02', '2026-10-14', 350, 'DISPONIBLE', NULL, '2026-09-02 08:15:00'),
  (196, 'PO-03-00196', 3, 2, '2026-09-25', '2026-11-06', 250, 'DISPONIBLE', NULL, '2026-09-25 08:15:00'),
  (197, 'PO-03-00197', 3, 2, '2026-09-28', '2026-11-09', 350, 'DISPONIBLE', NULL, '2026-09-28 08:15:00'),
  (198, 'PO-03-00198', 3, 2, '2026-09-14', '2026-10-26', 250, 'DISPONIBLE', NULL, '2026-09-14 08:15:00'),
  (199, 'PO-03-00199', 3, 2, '2026-09-19', '2026-10-31', 450, 'DISPONIBLE', NULL, '2026-09-19 08:15:00'),
  (200, 'PO-03-00200', 3, 3, '2026-09-09', '2026-10-21', 450, 'DISPONIBLE', NULL, '2026-09-09 08:15:00'),
  (201, 'PO-03-00201', 3, 3, '2026-09-11', '2026-10-23', 350, 'DISPONIBLE', NULL, '2026-09-11 08:15:00'),
  (202, 'PO-03-00202', 3, 3, '2026-09-29', '2026-11-10', 450, 'DISPONIBLE', NULL, '2026-09-29 08:15:00'),
  (203, 'PO-03-00203', 3, 3, '2026-09-20', '2026-11-01', 450, 'DISPONIBLE', NULL, '2026-09-20 08:15:00'),
  (204, 'PO-03-00204', 3, 3, '2026-09-28', '2026-11-09', 450, 'DISPONIBLE', NULL, '2026-09-28 08:15:00'),
  (205, 'PO-03-00205', 3, 3, '2026-09-13', '2026-10-25', 350, 'DISPONIBLE', NULL, '2026-09-13 08:15:00'),
  (206, 'PO-03-00206', 3, 3, '2026-09-22', '2026-11-03', 350, 'DISPONIBLE', NULL, '2026-09-22 08:15:00'),
  (207, 'PO-03-00207', 3, 4, '2026-09-17', '2026-10-29', 250, 'DISPONIBLE', NULL, '2026-09-17 08:15:00'),
  (208, 'PO-03-00208', 3, 5, '2026-09-05', '2026-10-17', 350, 'DISPONIBLE', NULL, '2026-09-05 08:15:00'),
  (209, 'PO-03-00209', 3, 5, '2026-09-01', '2026-10-13', 350, 'DISPONIBLE', NULL, '2026-09-01 08:15:00'),
  (210, 'PO-03-00210', 3, 5, '2026-09-05', '2026-10-17', 350, 'DISPONIBLE', NULL, '2026-09-05 08:15:00'),
  (211, 'PO-03-00211', 3, 5, '2026-09-10', '2026-10-22', 250, 'DISPONIBLE', NULL, '2026-09-10 08:15:00'),
  (212, 'PO-03-00212', 3, 6, '2026-09-25', '2026-11-06', 350, 'DISPONIBLE', NULL, '2026-09-25 08:15:00'),
  (213, 'PO-03-00213', 3, 6, '2026-09-27', '2026-11-08', 350, 'DISPONIBLE', NULL, '2026-09-27 08:15:00'),
  (214, 'PO-03-00214', 3, 6, '2026-09-28', '2026-11-09', 450, 'DISPONIBLE', NULL, '2026-09-28 08:15:00'),
  (215, 'PO-03-00215', 3, 7, '2026-09-13', '2026-10-25', 350, 'DISPONIBLE', NULL, '2026-09-13 08:15:00'),
  (216, 'PO-03-00216', 3, 7, '2026-09-23', '2026-11-04', 350, 'DISPONIBLE', NULL, '2026-09-23 08:15:00'),
  (217, 'PO-03-00217', 3, 7, '2026-09-02', '2026-10-14', 450, 'DISPONIBLE', NULL, '2026-09-02 08:15:00'),
  (218, 'PO-03-00218', 3, 7, '2026-09-01', '2026-10-13', 250, 'DISPONIBLE', NULL, '2026-09-01 08:15:00'),
  (219, 'PO-03-00219', 3, 7, '2026-09-15', '2026-10-27', 350, 'DISPONIBLE', NULL, '2026-09-15 08:15:00'),
  (220, 'PO-03-00220', 3, 7, '2026-09-23', '2026-11-04', 250, 'DISPONIBLE', NULL, '2026-09-23 08:15:00'),
  (221, 'PO-03-00221', 3, 7, '2026-09-13', '2026-10-25', 250, 'DISPONIBLE', NULL, '2026-09-13 08:15:00'),
  (222, 'PO-03-00222', 3, 7, '2026-09-18', '2026-10-30', 450, 'DISPONIBLE', NULL, '2026-09-18 08:15:00'),
  (223, 'PO-03-00223', 3, 8, '2026-09-26', '2026-11-07', 350, 'DISPONIBLE', NULL, '2026-09-26 08:15:00'),
  (224, 'PO-03-00224', 3, 8, '2026-09-29', '2026-11-10', 250, 'DISPONIBLE', NULL, '2026-09-29 08:15:00'),
  (225, 'PO-03-00225', 3, 8, '2026-09-04', '2026-10-16', 250, 'DISPONIBLE', NULL, '2026-09-04 08:15:00'),
  (226, 'PO-03-00226', 3, 8, '2026-09-27', '2026-11-08', 350, 'DISPONIBLE', NULL, '2026-09-27 08:15:00'),
  (227, 'PO-03-00227', 3, 8, '2026-09-23', '2026-11-04', 350, 'DISPONIBLE', NULL, '2026-09-23 08:15:00'),
  (228, 'PO-03-00228', 3, 8, '2026-09-20', '2026-11-01', 450, 'DISPONIBLE', NULL, '2026-09-20 08:15:00'),
  (229, 'PO-03-00229', 3, 8, '2026-09-26', '2026-11-07', 450, 'DISPONIBLE', NULL, '2026-09-26 08:15:00'),
  (230, 'PO-03-00230', 3, 8, '2026-09-26', '2026-11-07', 250, 'DISPONIBLE', NULL, '2026-09-26 08:15:00'),
  (231, 'PO-03-00231', 3, 8, '2026-09-20', '2026-11-01', 250, 'DISPONIBLE', NULL, '2026-09-20 08:15:00'),
  (232, 'PO-03-00232', 3, 8, '2026-09-23', '2026-11-04', 350, 'DISPONIBLE', NULL, '2026-09-23 08:15:00'),
  (233, 'PO-03-00233', 3, 8, '2026-09-21', '2026-11-02', 350, 'DISPONIBLE', NULL, '2026-09-21 08:15:00'),
  (234, 'PO-04-00234', 4, 1, '2026-09-06', '2026-10-18', 350, 'DISPONIBLE', NULL, '2026-09-06 08:15:00'),
  (235, 'PO-04-00235', 4, 1, '2026-09-20', '2026-11-01', 450, 'DISPONIBLE', NULL, '2026-09-20 08:15:00'),
  (236, 'PO-04-00236', 4, 1, '2026-09-09', '2026-10-21', 250, 'DISPONIBLE', NULL, '2026-09-09 08:15:00'),
  (237, 'PO-04-00237', 4, 1, '2026-09-20', '2026-11-01', 450, 'DISPONIBLE', NULL, '2026-09-20 08:15:00'),
  (238, 'PO-04-00238', 4, 2, '2026-09-17', '2026-10-29', 450, 'DISPONIBLE', NULL, '2026-09-17 08:15:00'),
  (239, 'PO-04-00239', 4, 3, '2026-09-14', '2026-10-26', 350, 'DISPONIBLE', NULL, '2026-09-14 08:15:00'),
  (240, 'PO-04-00240', 4, 3, '2026-09-20', '2026-11-01', 350, 'DISPONIBLE', NULL, '2026-09-20 08:15:00');
INSERT INTO `poche` (`id`, `code_poche`, `banque_id`, `groupe_sanguin_id`, `date_prelevement`, `date_peremption`, `volume_ml`, `statut`, `precommande_id`, `date_creation`) VALUES
  (241, 'PO-04-00241', 4, 3, '2026-09-07', '2026-10-19', 250, 'DISPONIBLE', NULL, '2026-09-07 08:15:00'),
  (242, 'PO-04-00242', 4, 3, '2026-09-26', '2026-11-07', 250, 'DISPONIBLE', NULL, '2026-09-26 08:15:00'),
  (243, 'PO-04-00243', 4, 4, '2026-09-23', '2026-11-04', 450, 'DISPONIBLE', NULL, '2026-09-23 08:15:00'),
  (244, 'PO-04-00244', 4, 4, '2026-09-03', '2026-10-15', 350, 'DISPONIBLE', NULL, '2026-09-03 08:15:00'),
  (245, 'PO-04-00245', 4, 5, '2026-09-08', '2026-10-20', 250, 'DISPONIBLE', NULL, '2026-09-08 08:15:00'),
  (246, 'PO-04-00246', 4, 5, '2026-09-12', '2026-10-24', 450, 'DISPONIBLE', NULL, '2026-09-12 08:15:00'),
  (247, 'PO-04-00247', 4, 5, '2026-09-02', '2026-10-14', 250, 'DISPONIBLE', NULL, '2026-09-02 08:15:00'),
  (248, 'PO-04-00248', 4, 5, '2026-09-09', '2026-10-21', 350, 'DISPONIBLE', NULL, '2026-09-09 08:15:00'),
  (249, 'PO-04-00249', 4, 5, '2026-09-14', '2026-10-26', 450, 'DISPONIBLE', NULL, '2026-09-14 08:15:00'),
  (250, 'PO-04-00250', 4, 5, '2026-09-04', '2026-10-16', 250, 'DISPONIBLE', NULL, '2026-09-04 08:15:00'),
  (251, 'PO-04-00251', 4, 6, '2026-09-08', '2026-10-20', 350, 'DISPONIBLE', NULL, '2026-09-08 08:15:00'),
  (252, 'PO-04-00252', 4, 6, '2026-09-08', '2026-10-20', 350, 'DISPONIBLE', NULL, '2026-09-08 08:15:00'),
  (253, 'PO-04-00253', 4, 6, '2026-09-25', '2026-11-06', 350, 'DISPONIBLE', NULL, '2026-09-25 08:15:00'),
  (254, 'PO-04-00254', 4, 6, '2026-09-26', '2026-11-07', 350, 'DISPONIBLE', NULL, '2026-09-26 08:15:00'),
  (255, 'PO-04-00255', 4, 7, '2026-09-23', '2026-11-04', 250, 'DISPONIBLE', NULL, '2026-09-23 08:15:00'),
  (256, 'PO-04-00256', 4, 7, '2026-09-26', '2026-11-07', 450, 'DISPONIBLE', NULL, '2026-09-26 08:15:00'),
  (257, 'PO-04-00257', 4, 7, '2026-09-26', '2026-11-07', 350, 'DISPONIBLE', NULL, '2026-09-26 08:15:00'),
  (258, 'PO-04-00258', 4, 7, '2026-09-22', '2026-11-03', 250, 'DISPONIBLE', NULL, '2026-09-22 08:15:00'),
  (259, 'PO-04-00259', 4, 7, '2026-09-04', '2026-10-16', 350, 'DISPONIBLE', NULL, '2026-09-04 08:15:00'),
  (260, 'PO-04-00260', 4, 8, '2026-09-21', '2026-11-02', 350, 'DISPONIBLE', NULL, '2026-09-21 08:15:00'),
  (261, 'PO-04-00261', 4, 8, '2026-09-30', '2026-11-11', 350, 'DISPONIBLE', NULL, '2026-09-30 08:15:00'),
  (262, 'PO-04-00262', 4, 8, '2026-09-08', '2026-10-20', 350, 'DISPONIBLE', NULL, '2026-09-08 08:15:00'),
  (263, 'PO-04-00263', 4, 8, '2026-09-19', '2026-10-31', 450, 'DISPONIBLE', NULL, '2026-09-19 08:15:00'),
  (264, 'PO-04-00264', 4, 8, '2026-09-28', '2026-11-09', 350, 'DISPONIBLE', NULL, '2026-09-28 08:15:00'),
  (265, 'PO-04-00265', 4, 8, '2026-09-19', '2026-10-31', 450, 'DISPONIBLE', NULL, '2026-09-19 08:15:00'),
  (266, 'PO-05-00266', 5, 1, '2026-09-11', '2026-10-23', 450, 'DISPONIBLE', NULL, '2026-09-11 08:15:00'),
  (267, 'PO-05-00267', 5, 1, '2026-09-14', '2026-10-26', 450, 'DISPONIBLE', NULL, '2026-09-14 08:15:00'),
  (268, 'PO-05-00268', 5, 1, '2026-09-28', '2026-11-09', 250, 'DISPONIBLE', NULL, '2026-09-28 08:15:00'),
  (269, 'PO-05-00269', 5, 1, '2026-09-15', '2026-10-27', 350, 'DISPONIBLE', NULL, '2026-09-15 08:15:00'),
  (270, 'PO-05-00270', 5, 1, '2026-09-13', '2026-10-25', 250, 'DISPONIBLE', NULL, '2026-09-13 08:15:00'),
  (271, 'PO-05-00271', 5, 2, '2026-09-25', '2026-11-06', 250, 'DISPONIBLE', NULL, '2026-09-25 08:15:00'),
  (272, 'PO-05-00272', 5, 2, '2026-09-06', '2026-10-18', 450, 'DISPONIBLE', NULL, '2026-09-06 08:15:00'),
  (273, 'PO-05-00273', 5, 2, '2026-09-20', '2026-11-01', 350, 'DISPONIBLE', NULL, '2026-09-20 08:15:00'),
  (274, 'PO-05-00274', 5, 2, '2026-09-02', '2026-10-14', 250, 'DISPONIBLE', NULL, '2026-09-02 08:15:00'),
  (275, 'PO-05-00275', 5, 2, '2026-09-20', '2026-11-01', 350, 'DISPONIBLE', NULL, '2026-09-20 08:15:00'),
  (276, 'PO-05-00276', 5, 2, '2026-09-08', '2026-10-20', 450, 'DISPONIBLE', NULL, '2026-09-08 08:15:00'),
  (277, 'PO-05-00277', 5, 2, '2026-09-21', '2026-11-02', 450, 'DISPONIBLE', NULL, '2026-09-21 08:15:00'),
  (278, 'PO-05-00278', 5, 2, '2026-09-30', '2026-11-11', 350, 'DISPONIBLE', NULL, '2026-09-30 08:15:00'),
  (279, 'PO-05-00279', 5, 2, '2026-09-30', '2026-11-11', 350, 'DISPONIBLE', NULL, '2026-09-30 08:15:00'),
  (280, 'PO-05-00280', 5, 2, '2026-09-02', '2026-10-14', 450, 'DISPONIBLE', NULL, '2026-09-02 08:15:00'),
  (281, 'PO-05-00281', 5, 2, '2026-09-09', '2026-10-21', 250, 'DISPONIBLE', NULL, '2026-09-09 08:15:00'),
  (282, 'PO-05-00282', 5, 3, '2026-09-14', '2026-10-26', 250, 'DISPONIBLE', NULL, '2026-09-14 08:15:00'),
  (283, 'PO-05-00283', 5, 3, '2026-09-06', '2026-10-18', 450, 'DISPONIBLE', NULL, '2026-09-06 08:15:00'),
  (284, 'PO-05-00284', 5, 3, '2026-09-29', '2026-11-10', 250, 'DISPONIBLE', NULL, '2026-09-29 08:15:00'),
  (285, 'PO-05-00285', 5, 3, '2026-09-04', '2026-10-16', 250, 'DISPONIBLE', NULL, '2026-09-04 08:15:00'),
  (286, 'PO-05-00286', 5, 3, '2026-09-13', '2026-10-25', 250, 'DISPONIBLE', NULL, '2026-09-13 08:15:00'),
  (287, 'PO-05-00287', 5, 3, '2026-09-11', '2026-10-23', 450, 'DISPONIBLE', NULL, '2026-09-11 08:15:00'),
  (288, 'PO-05-00288', 5, 4, '2026-09-02', '2026-10-14', 350, 'DISPONIBLE', NULL, '2026-09-02 08:15:00'),
  (289, 'PO-05-00289', 5, 5, '2026-09-09', '2026-10-21', 450, 'DISPONIBLE', NULL, '2026-09-09 08:15:00'),
  (290, 'PO-05-00290', 5, 5, '2026-09-08', '2026-10-20', 450, 'DISPONIBLE', NULL, '2026-09-08 08:15:00'),
  (291, 'PO-05-00291', 5, 5, '2026-09-22', '2026-11-03', 450, 'DISPONIBLE', NULL, '2026-09-22 08:15:00'),
  (292, 'PO-05-00292', 5, 5, '2026-09-24', '2026-11-05', 450, 'DISPONIBLE', NULL, '2026-09-24 08:15:00'),
  (293, 'PO-05-00293', 5, 5, '2026-09-12', '2026-10-24', 350, 'DISPONIBLE', NULL, '2026-09-12 08:15:00'),
  (294, 'PO-05-00294', 5, 7, '2026-09-06', '2026-10-18', 350, 'DISPONIBLE', NULL, '2026-09-06 08:15:00'),
  (295, 'PO-05-00295', 5, 7, '2026-09-09', '2026-10-21', 350, 'DISPONIBLE', NULL, '2026-09-09 08:15:00'),
  (296, 'PO-05-00296', 5, 7, '2026-09-03', '2026-10-15', 350, 'DISPONIBLE', NULL, '2026-09-03 08:15:00'),
  (297, 'PO-05-00297', 5, 7, '2026-09-05', '2026-10-17', 450, 'DISPONIBLE', NULL, '2026-09-05 08:15:00'),
  (298, 'PO-05-00298', 5, 7, '2026-09-24', '2026-11-05', 250, 'DISPONIBLE', NULL, '2026-09-24 08:15:00'),
  (299, 'PO-05-00299', 5, 7, '2026-09-04', '2026-10-16', 450, 'DISPONIBLE', NULL, '2026-09-04 08:15:00'),
  (300, 'PO-05-00300', 5, 7, '2026-09-23', '2026-11-04', 250, 'DISPONIBLE', NULL, '2026-09-23 08:15:00');
INSERT INTO `poche` (`id`, `code_poche`, `banque_id`, `groupe_sanguin_id`, `date_prelevement`, `date_peremption`, `volume_ml`, `statut`, `precommande_id`, `date_creation`) VALUES
  (301, 'PO-05-00301', 5, 7, '2026-09-21', '2026-11-02', 250, 'DISPONIBLE', NULL, '2026-09-21 08:15:00'),
  (302, 'PO-05-00302', 5, 7, '2026-09-17', '2026-10-29', 350, 'DISPONIBLE', NULL, '2026-09-17 08:15:00'),
  (303, 'PO-05-00303', 5, 7, '2026-09-20', '2026-11-01', 250, 'DISPONIBLE', NULL, '2026-09-20 08:15:00'),
  (304, 'PO-05-00304', 5, 8, '2026-09-12', '2026-10-24', 250, 'DISPONIBLE', NULL, '2026-09-12 08:15:00'),
  (305, 'PO-05-00305', 5, 8, '2026-09-06', '2026-10-18', 250, 'DISPONIBLE', NULL, '2026-09-06 08:15:00'),
  (306, 'PO-05-00306', 5, 8, '2026-09-21', '2026-11-02', 250, 'DISPONIBLE', NULL, '2026-09-21 08:15:00'),
  (307, 'PO-05-00307', 5, 8, '2026-09-09', '2026-10-21', 450, 'DISPONIBLE', NULL, '2026-09-09 08:15:00'),
  (308, 'PO-05-00308', 5, 8, '2026-09-09', '2026-10-21', 450, 'DISPONIBLE', NULL, '2026-09-09 08:15:00'),
  (309, 'PO-05-00309', 5, 8, '2026-09-07', '2026-10-19', 450, 'DISPONIBLE', NULL, '2026-09-07 08:15:00'),
  (310, 'PO-05-00310', 5, 8, '2026-09-01', '2026-10-13', 450, 'DISPONIBLE', NULL, '2026-09-01 08:15:00'),
  (311, 'PO-05-00311', 5, 8, '2026-09-26', '2026-11-07', 350, 'DISPONIBLE', NULL, '2026-09-26 08:15:00'),
  (312, 'PO-05-00312', 5, 8, '2026-09-01', '2026-10-13', 250, 'DISPONIBLE', NULL, '2026-09-01 08:15:00'),
  (313, 'PO-05-00313', 5, 8, '2026-09-15', '2026-10-27', 450, 'DISPONIBLE', NULL, '2026-09-15 08:15:00'),
  (314, 'PO-06-00314', 6, 1, '2026-09-09', '2026-10-21', 450, 'DISPONIBLE', NULL, '2026-09-09 08:15:00'),
  (315, 'PO-06-00315', 6, 1, '2026-09-17', '2026-10-29', 350, 'DISPONIBLE', NULL, '2026-09-17 08:15:00'),
  (316, 'PO-06-00316', 6, 1, '2026-09-22', '2026-11-03', 450, 'DISPONIBLE', NULL, '2026-09-22 08:15:00'),
  (317, 'PO-06-00317', 6, 1, '2026-09-16', '2026-10-28', 450, 'DISPONIBLE', NULL, '2026-09-16 08:15:00'),
  (318, 'PO-06-00318', 6, 1, '2026-09-18', '2026-10-30', 250, 'DISPONIBLE', NULL, '2026-09-18 08:15:00'),
  (319, 'PO-06-00319', 6, 1, '2026-09-15', '2026-10-27', 350, 'DISPONIBLE', NULL, '2026-09-15 08:15:00'),
  (320, 'PO-06-00320', 6, 1, '2026-09-24', '2026-11-05', 450, 'DISPONIBLE', NULL, '2026-09-24 08:15:00'),
  (321, 'PO-06-00321', 6, 1, '2026-09-02', '2026-10-14', 250, 'DISPONIBLE', NULL, '2026-09-02 08:15:00'),
  (322, 'PO-06-00322', 6, 1, '2026-09-19', '2026-10-31', 450, 'DISPONIBLE', NULL, '2026-09-19 08:15:00'),
  (323, 'PO-06-00323', 6, 1, '2026-09-29', '2026-11-10', 250, 'DISPONIBLE', NULL, '2026-09-29 08:15:00'),
  (324, 'PO-06-00324', 6, 1, '2026-09-08', '2026-10-20', 350, 'DISPONIBLE', NULL, '2026-09-08 08:15:00'),
  (325, 'PO-06-00325', 6, 1, '2026-09-22', '2026-11-03', 450, 'DISPONIBLE', NULL, '2026-09-22 08:15:00'),
  (326, 'PO-06-00326', 6, 1, '2026-09-11', '2026-10-23', 350, 'DISPONIBLE', NULL, '2026-09-11 08:15:00'),
  (327, 'PO-06-00327', 6, 1, '2026-09-27', '2026-11-08', 250, 'DISPONIBLE', NULL, '2026-09-27 08:15:00'),
  (328, 'PO-06-00328', 6, 2, '2026-09-02', '2026-10-14', 250, 'DISPONIBLE', NULL, '2026-09-02 08:15:00'),
  (329, 'PO-06-00329', 6, 2, '2026-09-10', '2026-10-22', 250, 'DISPONIBLE', NULL, '2026-09-10 08:15:00'),
  (330, 'PO-06-00330', 6, 2, '2026-09-04', '2026-10-16', 250, 'DISPONIBLE', NULL, '2026-09-04 08:15:00'),
  (331, 'PO-06-00331', 6, 2, '2026-09-30', '2026-11-11', 350, 'DISPONIBLE', NULL, '2026-09-30 08:15:00'),
  (332, 'PO-06-00332', 6, 2, '2026-09-14', '2026-10-26', 450, 'DISPONIBLE', NULL, '2026-09-14 08:15:00'),
  (333, 'PO-06-00333', 6, 2, '2026-09-04', '2026-10-16', 250, 'DISPONIBLE', NULL, '2026-09-04 08:15:00'),
  (334, 'PO-06-00334', 6, 2, '2026-09-01', '2026-10-13', 350, 'DISPONIBLE', NULL, '2026-09-01 08:15:00'),
  (335, 'PO-06-00335', 6, 3, '2026-09-29', '2026-11-10', 350, 'RESERVEE', 4, '2026-09-29 08:15:00'),
  (336, 'PO-06-00336', 6, 3, '2026-09-22', '2026-11-03', 350, 'RESERVEE', 4, '2026-09-22 08:15:00'),
  (337, 'PO-06-00337', 6, 3, '2026-09-29', '2026-11-10', 450, 'RESERVEE', 4, '2026-09-29 08:15:00'),
  (338, 'PO-06-00338', 6, 3, '2026-09-26', '2026-11-07', 350, 'RESERVEE', 4, '2026-09-26 08:15:00'),
  (339, 'PO-06-00339', 6, 3, '2026-09-22', '2026-11-03', 350, 'DISPONIBLE', NULL, '2026-09-22 08:15:00'),
  (340, 'PO-06-00340', 6, 3, '2026-09-28', '2026-11-09', 350, 'DISPONIBLE', NULL, '2026-09-28 08:15:00'),
  (341, 'PO-06-00341', 6, 3, '2026-09-05', '2026-10-17', 250, 'DISPONIBLE', NULL, '2026-09-05 08:15:00'),
  (342, 'PO-06-00342', 6, 3, '2026-09-24', '2026-11-05', 250, 'DISPONIBLE', NULL, '2026-09-24 08:15:00'),
  (343, 'PO-06-00343', 6, 3, '2026-09-15', '2026-10-27', 450, 'DISPONIBLE', NULL, '2026-09-15 08:15:00'),
  (344, 'PO-06-00344', 6, 3, '2026-09-19', '2026-10-31', 450, 'DISPONIBLE', NULL, '2026-09-19 08:15:00'),
  (345, 'PO-06-00345', 6, 3, '2026-09-05', '2026-10-17', 250, 'DISPONIBLE', NULL, '2026-09-05 08:15:00'),
  (346, 'PO-06-00346', 6, 3, '2026-09-28', '2026-11-09', 450, 'DISPONIBLE', NULL, '2026-09-28 08:15:00'),
  (347, 'PO-06-00347', 6, 3, '2026-09-16', '2026-10-28', 250, 'DISPONIBLE', NULL, '2026-09-16 08:15:00'),
  (348, 'PO-06-00348', 6, 3, '2026-09-01', '2026-10-13', 450, 'DISPONIBLE', NULL, '2026-09-01 08:15:00'),
  (349, 'PO-06-00349', 6, 3, '2026-09-13', '2026-10-25', 450, 'DISPONIBLE', NULL, '2026-09-13 08:15:00'),
  (350, 'PO-06-00350', 6, 3, '2026-09-17', '2026-10-29', 250, 'DISPONIBLE', NULL, '2026-09-17 08:15:00'),
  (351, 'PO-06-00351', 6, 3, '2026-09-02', '2026-10-14', 450, 'DISPONIBLE', NULL, '2026-09-02 08:15:00'),
  (352, 'PO-06-00352', 6, 3, '2026-09-17', '2026-10-29', 450, 'DISPONIBLE', NULL, '2026-09-17 08:15:00'),
  (353, 'PO-06-00353', 6, 3, '2026-09-17', '2026-10-29', 350, 'DISPONIBLE', NULL, '2026-09-17 08:15:00'),
  (354, 'PO-06-00354', 6, 4, '2026-09-05', '2026-10-17', 450, 'DISPONIBLE', NULL, '2026-09-05 08:15:00'),
  (355, 'PO-06-00355', 6, 4, '2026-09-28', '2026-11-09', 450, 'DISPONIBLE', NULL, '2026-09-28 08:15:00'),
  (356, 'PO-06-00356', 6, 4, '2026-09-23', '2026-11-04', 350, 'DISPONIBLE', NULL, '2026-09-23 08:15:00'),
  (357, 'PO-06-00357', 6, 4, '2026-09-19', '2026-10-31', 250, 'DISPONIBLE', NULL, '2026-09-19 08:15:00'),
  (358, 'PO-06-00358', 6, 4, '2026-09-24', '2026-11-05', 350, 'DISPONIBLE', NULL, '2026-09-24 08:15:00'),
  (359, 'PO-06-00359', 6, 4, '2026-09-12', '2026-10-24', 450, 'DISPONIBLE', NULL, '2026-09-12 08:15:00'),
  (360, 'PO-06-00360', 6, 4, '2026-09-09', '2026-10-21', 450, 'DISPONIBLE', NULL, '2026-09-09 08:15:00');
INSERT INTO `poche` (`id`, `code_poche`, `banque_id`, `groupe_sanguin_id`, `date_prelevement`, `date_peremption`, `volume_ml`, `statut`, `precommande_id`, `date_creation`) VALUES
  (361, 'PO-06-00361', 6, 4, '2026-09-28', '2026-11-09', 350, 'DISPONIBLE', NULL, '2026-09-28 08:15:00'),
  (362, 'PO-06-00362', 6, 4, '2026-09-12', '2026-10-24', 450, 'DISPONIBLE', NULL, '2026-09-12 08:15:00'),
  (363, 'PO-06-00363', 6, 5, '2026-09-21', '2026-11-02', 450, 'DISPONIBLE', NULL, '2026-09-21 08:15:00'),
  (364, 'PO-06-00364', 6, 5, '2026-09-12', '2026-10-24', 450, 'DISPONIBLE', NULL, '2026-09-12 08:15:00'),
  (365, 'PO-06-00365', 6, 5, '2026-09-17', '2026-10-29', 250, 'DISPONIBLE', NULL, '2026-09-17 08:15:00'),
  (366, 'PO-06-00366', 6, 5, '2026-09-02', '2026-10-14', 350, 'DISPONIBLE', NULL, '2026-09-02 08:15:00'),
  (367, 'PO-06-00367', 6, 5, '2026-09-16', '2026-10-28', 250, 'DISPONIBLE', NULL, '2026-09-16 08:15:00'),
  (368, 'PO-06-00368', 6, 5, '2026-09-27', '2026-11-08', 450, 'DISPONIBLE', NULL, '2026-09-27 08:15:00'),
  (369, 'PO-06-00369', 6, 5, '2026-09-20', '2026-11-01', 350, 'DISPONIBLE', NULL, '2026-09-20 08:15:00'),
  (370, 'PO-06-00370', 6, 5, '2026-09-27', '2026-11-08', 250, 'DISPONIBLE', NULL, '2026-09-27 08:15:00'),
  (371, 'PO-06-00371', 6, 5, '2026-09-13', '2026-10-25', 250, 'DISPONIBLE', NULL, '2026-09-13 08:15:00'),
  (372, 'PO-06-00372', 6, 5, '2026-09-08', '2026-10-20', 350, 'DISPONIBLE', NULL, '2026-09-08 08:15:00'),
  (373, 'PO-06-00373', 6, 5, '2026-09-14', '2026-10-26', 450, 'DISPONIBLE', NULL, '2026-09-14 08:15:00'),
  (374, 'PO-06-00374', 6, 5, '2026-09-02', '2026-10-14', 450, 'DISPONIBLE', NULL, '2026-09-02 08:15:00'),
  (375, 'PO-06-00375', 6, 7, '2026-09-25', '2026-11-06', 450, 'DISPONIBLE', NULL, '2026-09-25 08:15:00'),
  (376, 'PO-06-00376', 6, 7, '2026-09-06', '2026-10-18', 450, 'DISPONIBLE', NULL, '2026-09-06 08:15:00'),
  (377, 'PO-06-00377', 6, 7, '2026-09-15', '2026-10-27', 350, 'DISPONIBLE', NULL, '2026-09-15 08:15:00'),
  (378, 'PO-06-00378', 6, 7, '2026-09-10', '2026-10-22', 350, 'DISPONIBLE', NULL, '2026-09-10 08:15:00'),
  (379, 'PO-06-00379', 6, 7, '2026-09-28', '2026-11-09', 350, 'DISPONIBLE', NULL, '2026-09-28 08:15:00'),
  (380, 'PO-06-00380', 6, 7, '2026-09-18', '2026-10-30', 450, 'DISPONIBLE', NULL, '2026-09-18 08:15:00'),
  (381, 'PO-06-00381', 6, 7, '2026-09-01', '2026-10-13', 350, 'DISPONIBLE', NULL, '2026-09-01 08:15:00'),
  (382, 'PO-06-00382', 6, 7, '2026-09-07', '2026-10-19', 350, 'DISPONIBLE', NULL, '2026-09-07 08:15:00'),
  (383, 'PO-06-00383', 6, 7, '2026-09-01', '2026-10-13', 350, 'DISPONIBLE', NULL, '2026-09-01 08:15:00'),
  (384, 'PO-06-00384', 6, 8, '2026-09-21', '2026-11-02', 250, 'DISPONIBLE', NULL, '2026-09-21 08:15:00'),
  (385, 'PO-06-00385', 6, 8, '2026-09-25', '2026-11-06', 350, 'DISPONIBLE', NULL, '2026-09-25 08:15:00'),
  (386, 'PO-06-00386', 6, 8, '2026-09-28', '2026-11-09', 250, 'DISPONIBLE', NULL, '2026-09-28 08:15:00'),
  (387, 'PO-06-00387', 6, 8, '2026-09-03', '2026-10-15', 450, 'DISPONIBLE', NULL, '2026-09-03 08:15:00'),
  (388, 'PO-06-00388', 6, 8, '2026-09-06', '2026-10-18', 350, 'DISPONIBLE', NULL, '2026-09-06 08:15:00'),
  (389, 'PO-06-00389', 6, 8, '2026-09-02', '2026-10-14', 250, 'DISPONIBLE', NULL, '2026-09-02 08:15:00'),
  (390, 'PO-06-00390', 6, 8, '2026-09-19', '2026-10-31', 350, 'DISPONIBLE', NULL, '2026-09-19 08:15:00'),
  (391, 'PO-06-00391', 6, 8, '2026-09-27', '2026-11-08', 250, 'DISPONIBLE', NULL, '2026-09-27 08:15:00'),
  (392, 'PO-06-00392', 6, 8, '2026-09-05', '2026-10-17', 350, 'DISPONIBLE', NULL, '2026-09-05 08:15:00'),
  (393, 'PO-06-00393', 6, 8, '2026-09-10', '2026-10-22', 450, 'DISPONIBLE', NULL, '2026-09-10 08:15:00'),
  (394, 'PO-06-00394', 6, 8, '2026-09-23', '2026-11-04', 250, 'DISPONIBLE', NULL, '2026-09-23 08:15:00'),
  (395, 'PO-06-00395', 6, 8, '2026-09-17', '2026-10-29', 450, 'DISPONIBLE', NULL, '2026-09-17 08:15:00'),
  (396, 'PO-06-00396', 6, 8, '2026-09-28', '2026-11-09', 450, 'DISPONIBLE', NULL, '2026-09-28 08:15:00'),
  (397, 'PO-06-00397', 6, 8, '2026-09-28', '2026-11-09', 350, 'DISPONIBLE', NULL, '2026-09-28 08:15:00'),
  (398, 'PO-06-00398', 6, 8, '2026-09-13', '2026-10-25', 450, 'DISPONIBLE', NULL, '2026-09-13 08:15:00'),
  (399, 'PO-06-00399', 6, 8, '2026-09-04', '2026-10-16', 350, 'DISPONIBLE', NULL, '2026-09-04 08:15:00'),
  (400, 'PO-06-00400', 6, 8, '2026-09-18', '2026-10-30', 250, 'DISPONIBLE', NULL, '2026-09-18 08:15:00'),
  (401, 'PO-06-00401', 6, 8, '2026-09-21', '2026-11-02', 350, 'DISPONIBLE', NULL, '2026-09-21 08:15:00'),
  (402, 'PO-06-00402', 6, 8, '2026-09-05', '2026-10-17', 450, 'DISPONIBLE', NULL, '2026-09-05 08:15:00'),
  (403, 'PO-06-00403', 6, 8, '2026-09-05', '2026-10-17', 350, 'DISPONIBLE', NULL, '2026-09-05 08:15:00'),
  (404, 'PO-06-00404', 6, 8, '2026-09-27', '2026-11-08', 350, 'DISPONIBLE', NULL, '2026-09-27 08:15:00'),
  (405, 'PO-07-00405', 7, 1, '2026-09-01', '2026-10-13', 450, 'DISPONIBLE', NULL, '2026-09-01 08:15:00'),
  (406, 'PO-07-00406', 7, 1, '2026-09-06', '2026-10-18', 350, 'DISPONIBLE', NULL, '2026-09-06 08:15:00'),
  (407, 'PO-07-00407', 7, 1, '2026-09-08', '2026-10-20', 450, 'DISPONIBLE', NULL, '2026-09-08 08:15:00'),
  (408, 'PO-07-00408', 7, 2, '2026-09-24', '2026-11-05', 350, 'DISPONIBLE', NULL, '2026-09-24 08:15:00'),
  (409, 'PO-07-00409', 7, 2, '2026-09-16', '2026-10-28', 250, 'DISPONIBLE', NULL, '2026-09-16 08:15:00'),
  (410, 'PO-07-00410', 7, 2, '2026-09-30', '2026-11-11', 350, 'DISPONIBLE', NULL, '2026-09-30 08:15:00'),
  (411, 'PO-07-00411', 7, 2, '2026-09-01', '2026-10-13', 350, 'DISPONIBLE', NULL, '2026-09-01 08:15:00'),
  (412, 'PO-07-00412', 7, 3, '2026-09-24', '2026-11-05', 350, 'DISPONIBLE', NULL, '2026-09-24 08:15:00'),
  (413, 'PO-07-00413', 7, 3, '2026-09-27', '2026-11-08', 450, 'DISPONIBLE', NULL, '2026-09-27 08:15:00'),
  (414, 'PO-07-00414', 7, 3, '2026-09-21', '2026-11-02', 350, 'DISPONIBLE', NULL, '2026-09-21 08:15:00'),
  (415, 'PO-07-00415', 7, 4, '2026-09-30', '2026-11-11', 250, 'DISPONIBLE', NULL, '2026-09-30 08:15:00'),
  (416, 'PO-07-00416', 7, 4, '2026-09-21', '2026-11-02', 250, 'DISPONIBLE', NULL, '2026-09-21 08:15:00'),
  (417, 'PO-07-00417', 7, 5, '2026-09-16', '2026-10-28', 450, 'DISPONIBLE', NULL, '2026-09-16 08:15:00'),
  (418, 'PO-07-00418', 7, 5, '2026-09-10', '2026-10-22', 250, 'DISPONIBLE', NULL, '2026-09-10 08:15:00'),
  (419, 'PO-07-00419', 7, 5, '2026-09-27', '2026-11-08', 250, 'DISPONIBLE', NULL, '2026-09-27 08:15:00'),
  (420, 'PO-07-00420', 7, 5, '2026-09-27', '2026-11-08', 350, 'DISPONIBLE', NULL, '2026-09-27 08:15:00');
INSERT INTO `poche` (`id`, `code_poche`, `banque_id`, `groupe_sanguin_id`, `date_prelevement`, `date_peremption`, `volume_ml`, `statut`, `precommande_id`, `date_creation`) VALUES
  (421, 'PO-07-00421', 7, 5, '2026-09-11', '2026-10-23', 350, 'DISPONIBLE', NULL, '2026-09-11 08:15:00'),
  (422, 'PO-07-00422', 7, 6, '2026-09-16', '2026-10-28', 350, 'DISPONIBLE', NULL, '2026-09-16 08:15:00'),
  (423, 'PO-07-00423', 7, 6, '2026-09-18', '2026-10-30', 450, 'DISPONIBLE', NULL, '2026-09-18 08:15:00'),
  (424, 'PO-07-00424', 7, 6, '2026-09-19', '2026-10-31', 350, 'DISPONIBLE', NULL, '2026-09-19 08:15:00'),
  (425, 'PO-07-00425', 7, 6, '2026-09-17', '2026-10-29', 350, 'DISPONIBLE', NULL, '2026-09-17 08:15:00'),
  (426, 'PO-07-00426', 7, 6, '2026-09-06', '2026-10-18', 450, 'DISPONIBLE', NULL, '2026-09-06 08:15:00'),
  (427, 'PO-07-00427', 7, 6, '2026-09-11', '2026-10-23', 450, 'DISPONIBLE', NULL, '2026-09-11 08:15:00'),
  (428, 'PO-07-00428', 7, 7, '2026-09-16', '2026-10-28', 450, 'DISPONIBLE', NULL, '2026-09-16 08:15:00'),
  (429, 'PO-07-00429', 7, 7, '2026-09-26', '2026-11-07', 450, 'DISPONIBLE', NULL, '2026-09-26 08:15:00'),
  (430, 'PO-07-00430', 7, 7, '2026-09-05', '2026-10-17', 250, 'DISPONIBLE', NULL, '2026-09-05 08:15:00'),
  (431, 'PO-07-00431', 7, 7, '2026-09-23', '2026-11-04', 250, 'DISPONIBLE', NULL, '2026-09-23 08:15:00'),
  (432, 'PO-07-00432', 7, 8, '2026-09-01', '2026-10-13', 450, 'DISPONIBLE', NULL, '2026-09-01 08:15:00'),
  (433, 'PO-08-00433', 8, 1, '2026-09-07', '2026-10-19', 250, 'DISPONIBLE', NULL, '2026-09-07 08:15:00'),
  (434, 'PO-08-00434', 8, 1, '2026-09-22', '2026-11-03', 250, 'DISPONIBLE', NULL, '2026-09-22 08:15:00'),
  (435, 'PO-08-00435', 8, 1, '2026-09-17', '2026-10-29', 350, 'DISPONIBLE', NULL, '2026-09-17 08:15:00'),
  (436, 'PO-08-00436', 8, 2, '2026-09-14', '2026-10-26', 450, 'DISPONIBLE', NULL, '2026-09-14 08:15:00'),
  (437, 'PO-08-00437', 8, 2, '2026-09-27', '2026-11-08', 250, 'DISPONIBLE', NULL, '2026-09-27 08:15:00'),
  (438, 'PO-08-00438', 8, 2, '2026-09-28', '2026-11-09', 350, 'DISPONIBLE', NULL, '2026-09-28 08:15:00'),
  (439, 'PO-08-00439', 8, 2, '2026-09-26', '2026-11-07', 350, 'DISPONIBLE', NULL, '2026-09-26 08:15:00'),
  (440, 'PO-08-00440', 8, 2, '2026-09-08', '2026-10-20', 450, 'DISPONIBLE', NULL, '2026-09-08 08:15:00'),
  (441, 'PO-08-00441', 8, 2, '2026-09-29', '2026-11-10', 250, 'DISPONIBLE', NULL, '2026-09-29 08:15:00'),
  (442, 'PO-08-00442', 8, 2, '2026-09-19', '2026-10-31', 450, 'DISPONIBLE', NULL, '2026-09-19 08:15:00'),
  (443, 'PO-08-00443', 8, 2, '2026-09-14', '2026-10-26', 450, 'DISPONIBLE', NULL, '2026-09-14 08:15:00'),
  (444, 'PO-08-00444', 8, 2, '2026-09-29', '2026-11-10', 450, 'DISPONIBLE', NULL, '2026-09-29 08:15:00'),
  (445, 'PO-08-00445', 8, 2, '2026-09-17', '2026-10-29', 350, 'DISPONIBLE', NULL, '2026-09-17 08:15:00'),
  (446, 'PO-08-00446', 8, 2, '2026-09-04', '2026-10-16', 350, 'DISPONIBLE', NULL, '2026-09-04 08:15:00'),
  (447, 'PO-08-00447', 8, 3, '2026-09-04', '2026-10-16', 350, 'DISPONIBLE', NULL, '2026-09-04 08:15:00'),
  (448, 'PO-08-00448', 8, 3, '2026-09-15', '2026-10-27', 450, 'DISPONIBLE', NULL, '2026-09-15 08:15:00'),
  (449, 'PO-08-00449', 8, 5, '2026-09-21', '2026-11-02', 450, 'DISPONIBLE', NULL, '2026-09-21 08:15:00'),
  (450, 'PO-08-00450', 8, 5, '2026-09-15', '2026-10-27', 250, 'DISPONIBLE', NULL, '2026-09-15 08:15:00'),
  (451, 'PO-08-00451', 8, 5, '2026-09-11', '2026-10-23', 450, 'DISPONIBLE', NULL, '2026-09-11 08:15:00'),
  (452, 'PO-08-00452', 8, 5, '2026-09-30', '2026-11-11', 450, 'DISPONIBLE', NULL, '2026-09-30 08:15:00'),
  (453, 'PO-08-00453', 8, 5, '2026-09-29', '2026-11-10', 450, 'DISPONIBLE', NULL, '2026-09-29 08:15:00'),
  (454, 'PO-08-00454', 8, 7, '2026-09-07', '2026-10-19', 350, 'DISPONIBLE', NULL, '2026-09-07 08:15:00'),
  (455, 'PO-08-00455', 8, 7, '2026-09-26', '2026-11-07', 250, 'DISPONIBLE', NULL, '2026-09-26 08:15:00'),
  (456, 'PO-08-00456', 8, 7, '2026-09-11', '2026-10-23', 350, 'DISPONIBLE', NULL, '2026-09-11 08:15:00'),
  (457, 'PO-08-00457', 8, 7, '2026-09-14', '2026-10-26', 250, 'DISPONIBLE', NULL, '2026-09-14 08:15:00'),
  (458, 'PO-08-00458', 8, 7, '2026-09-10', '2026-10-22', 350, 'DISPONIBLE', NULL, '2026-09-10 08:15:00'),
  (459, 'PO-08-00459', 8, 7, '2026-09-19', '2026-10-31', 350, 'DISPONIBLE', NULL, '2026-09-19 08:15:00'),
  (460, 'PO-08-00460', 8, 7, '2026-09-27', '2026-11-08', 350, 'DISPONIBLE', NULL, '2026-09-27 08:15:00'),
  (461, 'PO-08-00461', 8, 7, '2026-09-17', '2026-10-29', 350, 'DISPONIBLE', NULL, '2026-09-17 08:15:00'),
  (462, 'PO-08-00462', 8, 7, '2026-09-27', '2026-11-08', 350, 'DISPONIBLE', NULL, '2026-09-27 08:15:00'),
  (463, 'PO-08-00463', 8, 7, '2026-09-16', '2026-10-28', 250, 'DISPONIBLE', NULL, '2026-09-16 08:15:00'),
  (464, 'PO-08-00464', 8, 8, '2026-09-01', '2026-10-13', 450, 'DISPONIBLE', NULL, '2026-09-01 08:15:00'),
  (465, 'PO-08-00465', 8, 8, '2026-09-16', '2026-10-28', 450, 'DISPONIBLE', NULL, '2026-09-16 08:15:00'),
  (466, 'PO-08-00466', 8, 8, '2026-09-19', '2026-10-31', 250, 'DISPONIBLE', NULL, '2026-09-19 08:15:00'),
  (467, 'PO-09-00467', 9, 1, '2026-09-29', '2026-11-10', 450, 'DISPONIBLE', NULL, '2026-09-29 08:15:00'),
  (468, 'PO-09-00468', 9, 1, '2026-09-05', '2026-10-17', 350, 'DISPONIBLE', NULL, '2026-09-05 08:15:00'),
  (469, 'PO-09-00469', 9, 1, '2026-09-05', '2026-10-17', 450, 'DISPONIBLE', NULL, '2026-09-05 08:15:00'),
  (470, 'PO-09-00470', 9, 1, '2026-09-23', '2026-11-04', 350, 'DISPONIBLE', NULL, '2026-09-23 08:15:00'),
  (471, 'PO-09-00471', 9, 2, '2026-09-12', '2026-10-24', 250, 'DISPONIBLE', NULL, '2026-09-12 08:15:00'),
  (472, 'PO-09-00472', 9, 2, '2026-09-23', '2026-11-04', 450, 'DISPONIBLE', NULL, '2026-09-23 08:15:00'),
  (473, 'PO-09-00473', 9, 2, '2026-09-26', '2026-11-07', 450, 'DISPONIBLE', NULL, '2026-09-26 08:15:00'),
  (474, 'PO-09-00474', 9, 2, '2026-09-17', '2026-10-29', 450, 'DISPONIBLE', NULL, '2026-09-17 08:15:00'),
  (475, 'PO-09-00475', 9, 2, '2026-09-19', '2026-10-31', 450, 'DISPONIBLE', NULL, '2026-09-19 08:15:00'),
  (476, 'PO-09-00476', 9, 2, '2026-09-15', '2026-10-27', 350, 'DISPONIBLE', NULL, '2026-09-15 08:15:00'),
  (477, 'PO-09-00477', 9, 3, '2026-09-21', '2026-11-02', 450, 'DISPONIBLE', NULL, '2026-09-21 08:15:00'),
  (478, 'PO-09-00478', 9, 3, '2026-09-20', '2026-11-01', 450, 'DISPONIBLE', NULL, '2026-09-20 08:15:00'),
  (479, 'PO-09-00479', 9, 4, '2026-09-04', '2026-10-16', 250, 'DISPONIBLE', NULL, '2026-09-04 08:15:00'),
  (480, 'PO-09-00480', 9, 4, '2026-09-10', '2026-10-22', 350, 'DISPONIBLE', NULL, '2026-09-10 08:15:00');
INSERT INTO `poche` (`id`, `code_poche`, `banque_id`, `groupe_sanguin_id`, `date_prelevement`, `date_peremption`, `volume_ml`, `statut`, `precommande_id`, `date_creation`) VALUES
  (481, 'PO-09-00481', 9, 5, '2026-09-15', '2026-10-27', 350, 'DISPONIBLE', NULL, '2026-09-15 08:15:00'),
  (482, 'PO-09-00482', 9, 5, '2026-09-12', '2026-10-24', 250, 'DISPONIBLE', NULL, '2026-09-12 08:15:00'),
  (483, 'PO-09-00483', 9, 5, '2026-09-22', '2026-11-03', 250, 'DISPONIBLE', NULL, '2026-09-22 08:15:00'),
  (484, 'PO-09-00484', 9, 5, '2026-09-07', '2026-10-19', 250, 'DISPONIBLE', NULL, '2026-09-07 08:15:00'),
  (485, 'PO-09-00485', 9, 5, '2026-09-12', '2026-10-24', 250, 'DISPONIBLE', NULL, '2026-09-12 08:15:00'),
  (486, 'PO-09-00486', 9, 6, '2026-09-11', '2026-10-23', 450, 'DISPONIBLE', NULL, '2026-09-11 08:15:00'),
  (487, 'PO-09-00487', 9, 6, '2026-09-28', '2026-11-09', 350, 'DISPONIBLE', NULL, '2026-09-28 08:15:00'),
  (488, 'PO-09-00488', 9, 6, '2026-09-30', '2026-11-11', 450, 'DISPONIBLE', NULL, '2026-09-30 08:15:00'),
  (489, 'PO-09-00489', 9, 6, '2026-09-14', '2026-10-26', 350, 'DISPONIBLE', NULL, '2026-09-14 08:15:00'),
  (490, 'PO-09-00490', 9, 6, '2026-09-19', '2026-10-31', 250, 'DISPONIBLE', NULL, '2026-09-19 08:15:00'),
  (491, 'PO-09-00491', 9, 6, '2026-09-11', '2026-10-23', 350, 'DISPONIBLE', NULL, '2026-09-11 08:15:00'),
  (492, 'PO-09-00492', 9, 6, '2026-09-19', '2026-10-31', 350, 'DISPONIBLE', NULL, '2026-09-19 08:15:00'),
  (493, 'PO-09-00493', 9, 6, '2026-09-02', '2026-10-14', 350, 'DISPONIBLE', NULL, '2026-09-02 08:15:00'),
  (494, 'PO-09-00494', 9, 6, '2026-09-08', '2026-10-20', 350, 'DISPONIBLE', NULL, '2026-09-08 08:15:00'),
  (495, 'PO-09-00495', 9, 6, '2026-09-04', '2026-10-16', 250, 'DISPONIBLE', NULL, '2026-09-04 08:15:00'),
  (496, 'PO-09-00496', 9, 7, '2026-09-08', '2026-10-20', 350, 'DISPONIBLE', NULL, '2026-09-08 08:15:00'),
  (497, 'PO-09-00497', 9, 7, '2026-09-08', '2026-10-20', 350, 'DISPONIBLE', NULL, '2026-09-08 08:15:00'),
  (498, 'PO-09-00498', 9, 8, '2026-09-24', '2026-11-05', 450, 'DISPONIBLE', NULL, '2026-09-24 08:15:00'),
  (499, 'PO-09-00499', 9, 8, '2026-09-03', '2026-10-15', 350, 'DISPONIBLE', NULL, '2026-09-03 08:15:00'),
  (500, 'PO-09-00500', 9, 8, '2026-09-26', '2026-11-07', 450, 'DISPONIBLE', NULL, '2026-09-26 08:15:00'),
  (501, 'PO-09-00501', 9, 8, '2026-09-30', '2026-11-11', 250, 'DISPONIBLE', NULL, '2026-09-30 08:15:00'),
  (502, 'PO-10-00502', 10, 1, '2026-09-11', '2026-10-23', 250, 'DISPONIBLE', NULL, '2026-09-11 08:15:00'),
  (503, 'PO-10-00503', 10, 1, '2026-09-06', '2026-10-18', 350, 'DISPONIBLE', NULL, '2026-09-06 08:15:00'),
  (504, 'PO-10-00504', 10, 1, '2026-09-25', '2026-11-06', 250, 'DISPONIBLE', NULL, '2026-09-25 08:15:00'),
  (505, 'PO-10-00505', 10, 1, '2026-09-08', '2026-10-20', 450, 'DISPONIBLE', NULL, '2026-09-08 08:15:00'),
  (506, 'PO-10-00506', 10, 1, '2026-09-13', '2026-10-25', 250, 'DISPONIBLE', NULL, '2026-09-13 08:15:00'),
  (507, 'PO-10-00507', 10, 1, '2026-09-25', '2026-11-06', 350, 'DISPONIBLE', NULL, '2026-09-25 08:15:00'),
  (508, 'PO-10-00508', 10, 2, '2026-09-25', '2026-11-06', 450, 'DISPONIBLE', NULL, '2026-09-25 08:15:00'),
  (509, 'PO-10-00509', 10, 2, '2026-09-24', '2026-11-05', 450, 'DISPONIBLE', NULL, '2026-09-24 08:15:00'),
  (510, 'PO-10-00510', 10, 2, '2026-09-21', '2026-11-02', 450, 'DISPONIBLE', NULL, '2026-09-21 08:15:00'),
  (511, 'PO-10-00511', 10, 2, '2026-09-02', '2026-10-14', 450, 'DISPONIBLE', NULL, '2026-09-02 08:15:00'),
  (512, 'PO-10-00512', 10, 2, '2026-09-13', '2026-10-25', 250, 'DISPONIBLE', NULL, '2026-09-13 08:15:00'),
  (513, 'PO-10-00513', 10, 2, '2026-09-21', '2026-11-02', 350, 'DISPONIBLE', NULL, '2026-09-21 08:15:00'),
  (514, 'PO-10-00514', 10, 3, '2026-09-16', '2026-10-28', 250, 'DISPONIBLE', NULL, '2026-09-16 08:15:00'),
  (515, 'PO-10-00515', 10, 3, '2026-09-20', '2026-11-01', 350, 'DISPONIBLE', NULL, '2026-09-20 08:15:00'),
  (516, 'PO-10-00516', 10, 3, '2026-09-12', '2026-10-24', 250, 'DISPONIBLE', NULL, '2026-09-12 08:15:00'),
  (517, 'PO-10-00517', 10, 3, '2026-09-26', '2026-11-07', 350, 'DISPONIBLE', NULL, '2026-09-26 08:15:00'),
  (518, 'PO-10-00518', 10, 4, '2026-09-07', '2026-10-19', 350, 'DISPONIBLE', NULL, '2026-09-07 08:15:00'),
  (519, 'PO-10-00519', 10, 4, '2026-09-27', '2026-11-08', 250, 'DISPONIBLE', NULL, '2026-09-27 08:15:00'),
  (520, 'PO-10-00520', 10, 4, '2026-09-10', '2026-10-22', 350, 'DISPONIBLE', NULL, '2026-09-10 08:15:00'),
  (521, 'PO-10-00521', 10, 4, '2026-09-15', '2026-10-27', 250, 'DISPONIBLE', NULL, '2026-09-15 08:15:00'),
  (522, 'PO-10-00522', 10, 4, '2026-09-07', '2026-10-19', 250, 'DISPONIBLE', NULL, '2026-09-07 08:15:00'),
  (523, 'PO-10-00523', 10, 5, '2026-09-08', '2026-10-20', 450, 'DISPONIBLE', NULL, '2026-09-08 08:15:00'),
  (524, 'PO-10-00524', 10, 5, '2026-09-17', '2026-10-29', 350, 'DISPONIBLE', NULL, '2026-09-17 08:15:00'),
  (525, 'PO-10-00525', 10, 7, '2026-09-27', '2026-11-08', 350, 'DISPONIBLE', NULL, '2026-09-27 08:15:00'),
  (526, 'PO-10-00526', 10, 7, '2026-09-07', '2026-10-19', 450, 'DISPONIBLE', NULL, '2026-09-07 08:15:00'),
  (527, 'PO-10-00527', 10, 7, '2026-09-11', '2026-10-23', 350, 'DISPONIBLE', NULL, '2026-09-11 08:15:00'),
  (528, 'PO-10-00528', 10, 7, '2026-09-23', '2026-11-04', 450, 'DISPONIBLE', NULL, '2026-09-23 08:15:00'),
  (529, 'PO-10-00529', 10, 7, '2026-09-21', '2026-11-02', 450, 'DISPONIBLE', NULL, '2026-09-21 08:15:00'),
  (530, 'PO-10-00530', 10, 7, '2026-09-03', '2026-10-15', 350, 'DISPONIBLE', NULL, '2026-09-03 08:15:00'),
  (531, 'PO-10-00531', 10, 8, '2026-09-12', '2026-10-24', 350, 'DISPONIBLE', NULL, '2026-09-12 08:15:00'),
  (532, 'PO-11-00532', 11, 1, '2026-09-06', '2026-10-18', 450, 'DISPONIBLE', NULL, '2026-09-06 08:15:00'),
  (533, 'PO-11-00533', 11, 1, '2026-09-11', '2026-10-23', 450, 'DISPONIBLE', NULL, '2026-09-11 08:15:00'),
  (534, 'PO-11-00534', 11, 1, '2026-09-13', '2026-10-25', 450, 'DISPONIBLE', NULL, '2026-09-13 08:15:00'),
  (535, 'PO-11-00535', 11, 1, '2026-09-04', '2026-10-16', 250, 'DISPONIBLE', NULL, '2026-09-04 08:15:00'),
  (536, 'PO-11-00536', 11, 2, '2026-09-22', '2026-11-03', 250, 'DISPONIBLE', NULL, '2026-09-22 08:15:00'),
  (537, 'PO-11-00537', 11, 2, '2026-09-29', '2026-11-10', 350, 'DISPONIBLE', NULL, '2026-09-29 08:15:00'),
  (538, 'PO-11-00538', 11, 2, '2026-09-12', '2026-10-24', 350, 'DISPONIBLE', NULL, '2026-09-12 08:15:00'),
  (539, 'PO-11-00539', 11, 2, '2026-09-28', '2026-11-09', 250, 'DISPONIBLE', NULL, '2026-09-28 08:15:00'),
  (540, 'PO-11-00540', 11, 2, '2026-09-14', '2026-10-26', 350, 'DISPONIBLE', NULL, '2026-09-14 08:15:00');
INSERT INTO `poche` (`id`, `code_poche`, `banque_id`, `groupe_sanguin_id`, `date_prelevement`, `date_peremption`, `volume_ml`, `statut`, `precommande_id`, `date_creation`) VALUES
  (541, 'PO-11-00541', 11, 3, '2026-09-04', '2026-10-16', 350, 'DISPONIBLE', NULL, '2026-09-04 08:15:00'),
  (542, 'PO-11-00542', 11, 3, '2026-09-15', '2026-10-27', 250, 'DISPONIBLE', NULL, '2026-09-15 08:15:00'),
  (543, 'PO-11-00543', 11, 3, '2026-09-19', '2026-10-31', 450, 'DISPONIBLE', NULL, '2026-09-19 08:15:00'),
  (544, 'PO-11-00544', 11, 4, '2026-09-27', '2026-11-08', 450, 'DISPONIBLE', NULL, '2026-09-27 08:15:00'),
  (545, 'PO-11-00545', 11, 4, '2026-09-29', '2026-11-10', 250, 'DISPONIBLE', NULL, '2026-09-29 08:15:00'),
  (546, 'PO-11-00546', 11, 4, '2026-09-28', '2026-11-09', 350, 'DISPONIBLE', NULL, '2026-09-28 08:15:00'),
  (547, 'PO-11-00547', 11, 6, '2026-09-16', '2026-10-28', 350, 'DISPONIBLE', NULL, '2026-09-16 08:15:00'),
  (548, 'PO-11-00548', 11, 6, '2026-09-02', '2026-10-14', 450, 'DISPONIBLE', NULL, '2026-09-02 08:15:00'),
  (549, 'PO-11-00549', 11, 7, '2026-09-23', '2026-11-04', 450, 'DISPONIBLE', NULL, '2026-09-23 08:15:00'),
  (550, 'PO-11-00550', 11, 7, '2026-09-05', '2026-10-17', 250, 'DISPONIBLE', NULL, '2026-09-05 08:15:00'),
  (551, 'PO-11-00551', 11, 7, '2026-09-06', '2026-10-18', 450, 'DISPONIBLE', NULL, '2026-09-06 08:15:00'),
  (552, 'PO-11-00552', 11, 7, '2026-09-04', '2026-10-16', 350, 'DISPONIBLE', NULL, '2026-09-04 08:15:00'),
  (553, 'PO-11-00553', 11, 8, '2026-09-02', '2026-10-14', 250, 'DISPONIBLE', NULL, '2026-09-02 08:15:00'),
  (554, 'PO-11-00554', 11, 8, '2026-09-17', '2026-10-29', 250, 'DISPONIBLE', NULL, '2026-09-17 08:15:00'),
  (555, 'PO-11-00555', 11, 8, '2026-09-01', '2026-10-13', 350, 'DISPONIBLE', NULL, '2026-09-01 08:15:00'),
  (556, 'PO-12-00556', 12, 1, '2026-09-15', '2026-10-27', 450, 'DISPONIBLE', NULL, '2026-09-15 08:15:00'),
  (557, 'PO-12-00557', 12, 1, '2026-09-09', '2026-10-21', 250, 'DISPONIBLE', NULL, '2026-09-09 08:15:00'),
  (558, 'PO-12-00558', 12, 1, '2026-09-13', '2026-10-25', 250, 'DISPONIBLE', NULL, '2026-09-13 08:15:00'),
  (559, 'PO-12-00559', 12, 1, '2026-09-23', '2026-11-04', 250, 'DISPONIBLE', NULL, '2026-09-23 08:15:00'),
  (560, 'PO-12-00560', 12, 1, '2026-09-17', '2026-10-29', 350, 'DISPONIBLE', NULL, '2026-09-17 08:15:00'),
  (561, 'PO-12-00561', 12, 1, '2026-09-03', '2026-10-15', 250, 'DISPONIBLE', NULL, '2026-09-03 08:15:00'),
  (562, 'PO-12-00562', 12, 1, '2026-09-05', '2026-10-17', 350, 'DISPONIBLE', NULL, '2026-09-05 08:15:00'),
  (563, 'PO-12-00563', 12, 1, '2026-09-25', '2026-11-06', 350, 'DISPONIBLE', NULL, '2026-09-25 08:15:00'),
  (564, 'PO-12-00564', 12, 1, '2026-09-23', '2026-11-04', 250, 'DISPONIBLE', NULL, '2026-09-23 08:15:00'),
  (565, 'PO-12-00565', 12, 1, '2026-09-18', '2026-10-30', 350, 'DISPONIBLE', NULL, '2026-09-18 08:15:00'),
  (566, 'PO-12-00566', 12, 2, '2026-09-23', '2026-11-04', 450, 'DISPONIBLE', NULL, '2026-09-23 08:15:00'),
  (567, 'PO-12-00567', 12, 2, '2026-09-15', '2026-10-27', 350, 'DISPONIBLE', NULL, '2026-09-15 08:15:00'),
  (568, 'PO-12-00568', 12, 2, '2026-09-19', '2026-10-31', 250, 'DISPONIBLE', NULL, '2026-09-19 08:15:00'),
  (569, 'PO-12-00569', 12, 2, '2026-09-15', '2026-10-27', 450, 'DISPONIBLE', NULL, '2026-09-15 08:15:00'),
  (570, 'PO-12-00570', 12, 2, '2026-09-05', '2026-10-17', 250, 'DISPONIBLE', NULL, '2026-09-05 08:15:00'),
  (571, 'PO-12-00571', 12, 2, '2026-09-28', '2026-11-09', 350, 'DISPONIBLE', NULL, '2026-09-28 08:15:00'),
  (572, 'PO-12-00572', 12, 2, '2026-09-21', '2026-11-02', 450, 'DISPONIBLE', NULL, '2026-09-21 08:15:00'),
  (573, 'PO-12-00573', 12, 3, '2026-09-03', '2026-10-15', 350, 'DISPONIBLE', NULL, '2026-09-03 08:15:00'),
  (574, 'PO-12-00574', 12, 3, '2026-09-10', '2026-10-22', 250, 'DISPONIBLE', NULL, '2026-09-10 08:15:00'),
  (575, 'PO-12-00575', 12, 3, '2026-09-14', '2026-10-26', 250, 'DISPONIBLE', NULL, '2026-09-14 08:15:00'),
  (576, 'PO-12-00576', 12, 3, '2026-09-17', '2026-10-29', 450, 'DISPONIBLE', NULL, '2026-09-17 08:15:00'),
  (577, 'PO-12-00577', 12, 3, '2026-09-02', '2026-10-14', 450, 'DISPONIBLE', NULL, '2026-09-02 08:15:00'),
  (578, 'PO-12-00578', 12, 3, '2026-09-16', '2026-10-28', 250, 'DISPONIBLE', NULL, '2026-09-16 08:15:00'),
  (579, 'PO-12-00579', 12, 4, '2026-09-13', '2026-10-25', 350, 'DISPONIBLE', NULL, '2026-09-13 08:15:00'),
  (580, 'PO-12-00580', 12, 5, '2026-09-30', '2026-11-11', 250, 'DISPONIBLE', NULL, '2026-09-30 08:15:00'),
  (581, 'PO-12-00581', 12, 5, '2026-09-02', '2026-10-14', 350, 'DISPONIBLE', NULL, '2026-09-02 08:15:00'),
  (582, 'PO-12-00582', 12, 5, '2026-09-29', '2026-11-10', 350, 'DISPONIBLE', NULL, '2026-09-29 08:15:00'),
  (583, 'PO-12-00583', 12, 6, '2026-09-14', '2026-10-26', 250, 'DISPONIBLE', NULL, '2026-09-14 08:15:00'),
  (584, 'PO-12-00584', 12, 6, '2026-09-02', '2026-10-14', 350, 'DISPONIBLE', NULL, '2026-09-02 08:15:00'),
  (585, 'PO-12-00585', 12, 6, '2026-09-04', '2026-10-16', 350, 'DISPONIBLE', NULL, '2026-09-04 08:15:00'),
  (586, 'PO-12-00586', 12, 6, '2026-09-09', '2026-10-21', 350, 'DISPONIBLE', NULL, '2026-09-09 08:15:00'),
  (587, 'PO-12-00587', 12, 6, '2026-09-17', '2026-10-29', 450, 'DISPONIBLE', NULL, '2026-09-17 08:15:00'),
  (588, 'PO-12-00588', 12, 6, '2026-09-19', '2026-10-31', 350, 'DISPONIBLE', NULL, '2026-09-19 08:15:00'),
  (589, 'PO-12-00589', 12, 6, '2026-09-02', '2026-10-14', 450, 'DISPONIBLE', NULL, '2026-09-02 08:15:00'),
  (590, 'PO-12-00590', 12, 6, '2026-09-24', '2026-11-05', 350, 'DISPONIBLE', NULL, '2026-09-24 08:15:00'),
  (591, 'PO-12-00591', 12, 6, '2026-09-25', '2026-11-06', 250, 'DISPONIBLE', NULL, '2026-09-25 08:15:00'),
  (592, 'PO-12-00592', 12, 7, '2026-09-17', '2026-10-29', 350, 'DISPONIBLE', NULL, '2026-09-17 08:15:00'),
  (593, 'PO-12-00593', 12, 7, '2026-09-19', '2026-10-31', 250, 'DISPONIBLE', NULL, '2026-09-19 08:15:00'),
  (594, 'PO-12-00594', 12, 7, '2026-09-06', '2026-10-18', 450, 'DISPONIBLE', NULL, '2026-09-06 08:15:00'),
  (595, 'PO-12-00595', 12, 7, '2026-09-01', '2026-10-13', 350, 'DISPONIBLE', NULL, '2026-09-01 08:15:00'),
  (596, 'PO-12-00596', 12, 7, '2026-09-25', '2026-11-06', 450, 'DISPONIBLE', NULL, '2026-09-25 08:15:00'),
  (597, 'PO-12-00597', 12, 7, '2026-09-28', '2026-11-09', 350, 'DISPONIBLE', NULL, '2026-09-28 08:15:00'),
  (598, 'PO-12-00598', 12, 7, '2026-09-10', '2026-10-22', 250, 'DISPONIBLE', NULL, '2026-09-10 08:15:00'),
  (599, 'PO-12-00599', 12, 7, '2026-09-04', '2026-10-16', 450, 'DISPONIBLE', NULL, '2026-09-04 08:15:00'),
  (600, 'PO-12-00600', 12, 8, '2026-09-17', '2026-10-29', 350, 'DISPONIBLE', NULL, '2026-09-17 08:15:00');
INSERT INTO `poche` (`id`, `code_poche`, `banque_id`, `groupe_sanguin_id`, `date_prelevement`, `date_peremption`, `volume_ml`, `statut`, `precommande_id`, `date_creation`) VALUES
  (601, 'PO-12-00601', 12, 8, '2026-09-01', '2026-10-13', 250, 'DISPONIBLE', NULL, '2026-09-01 08:15:00'),
  (602, 'PO-12-00602', 12, 8, '2026-09-27', '2026-11-08', 450, 'DISPONIBLE', NULL, '2026-09-27 08:15:00'),
  (603, 'PO-12-00603', 12, 8, '2026-09-09', '2026-10-21', 450, 'DISPONIBLE', NULL, '2026-09-09 08:15:00'),
  (604, 'PO-12-00604', 12, 8, '2026-09-04', '2026-10-16', 450, 'DISPONIBLE', NULL, '2026-09-04 08:15:00');

INSERT INTO `stock` (`id`, `banque_id`, `groupe_sanguin_id`, `quantite_disponible`, `quantite_reservee`, `seuil_alerte`, `etat`, `date_maj`) VALUES
  (1, 1, 1, 9, 0, 5, 'DISPONIBLE', '2026-10-01 05:48:00'),
  (2, 1, 2, 14, 0, 5, 'DISPONIBLE', '2026-10-01 03:46:00'),
  (3, 1, 3, 9, 0, 5, 'DISPONIBLE', '2026-09-30 20:40:00'),
  (4, 1, 4, 2, 0, 5, 'TENSION', '2026-09-30 23:16:00'),
  (5, 1, 5, 16, 5, 5, 'DISPONIBLE', '2026-10-01 06:30:00'),
  (6, 1, 6, 20, 0, 5, 'DISPONIBLE', '2026-10-01 07:00:00'),
  (7, 1, 7, 11, 0, 5, 'DISPONIBLE', '2026-09-30 18:46:00'),
  (8, 1, 8, 10, 0, 5, 'DISPONIBLE', '2026-10-01 01:39:00'),
  (9, 2, 1, 13, 0, 5, 'DISPONIBLE', '2026-09-30 21:29:00'),
  (10, 2, 2, 8, 3, 5, 'DISPONIBLE', '2026-09-30 18:36:00'),
  (11, 2, 3, 8, 2, 5, 'DISPONIBLE', '2026-09-30 23:18:00'),
  (12, 2, 4, 1, 0, 5, 'TENSION', '2026-10-01 03:46:00'),
  (13, 2, 5, 11, 0, 5, 'DISPONIBLE', '2026-10-01 06:37:00'),
  (14, 2, 6, 22, 0, 5, 'DISPONIBLE', '2026-10-01 07:48:00'),
  (15, 2, 7, 8, 0, 5, 'DISPONIBLE', '2026-10-01 02:47:00'),
  (16, 2, 8, 8, 0, 5, 'DISPONIBLE', '2026-09-30 19:15:00'),
  (17, 3, 1, 3, 0, 5, 'TENSION', '2026-09-30 21:37:00'),
  (18, 3, 2, 12, 0, 5, 'DISPONIBLE', '2026-09-30 21:38:00'),
  (19, 3, 3, 7, 0, 5, 'DISPONIBLE', '2026-09-30 23:03:00'),
  (20, 3, 4, 1, 0, 5, 'TENSION', '2026-10-01 05:51:00'),
  (21, 3, 5, 4, 0, 5, 'TENSION', '2026-09-30 23:56:00'),
  (22, 3, 6, 3, 0, 5, 'TENSION', '2026-09-30 21:06:00'),
  (23, 3, 7, 8, 0, 5, 'DISPONIBLE', '2026-10-01 07:07:00'),
  (24, 3, 8, 11, 0, 5, 'DISPONIBLE', '2026-10-01 02:30:00'),
  (25, 4, 1, 4, 0, 5, 'TENSION', '2026-10-01 03:15:00'),
  (26, 4, 2, 1, 0, 5, 'TENSION', '2026-10-01 05:50:00'),
  (27, 4, 3, 4, 0, 5, 'TENSION', '2026-10-01 06:33:00'),
  (28, 4, 4, 2, 0, 5, 'TENSION', '2026-10-01 04:04:00'),
  (29, 4, 5, 6, 0, 5, 'DISPONIBLE', '2026-09-30 18:44:00'),
  (30, 4, 6, 4, 0, 5, 'TENSION', '2026-09-30 18:44:00'),
  (31, 4, 7, 5, 0, 5, 'DISPONIBLE', '2026-09-30 23:35:00'),
  (32, 4, 8, 6, 0, 5, 'DISPONIBLE', '2026-10-01 04:44:00'),
  (33, 5, 1, 5, 0, 5, 'DISPONIBLE', '2026-10-01 01:47:00'),
  (34, 5, 2, 11, 0, 5, 'DISPONIBLE', '2026-10-01 08:24:00'),
  (35, 5, 3, 6, 0, 5, 'DISPONIBLE', '2026-09-30 18:44:00'),
  (36, 5, 4, 1, 0, 5, 'TENSION', '2026-10-01 07:00:00'),
  (37, 5, 5, 5, 0, 5, 'DISPONIBLE', '2026-09-30 22:40:00'),
  (38, 5, 6, 0, 0, 5, 'RUPTURE', '2026-10-01 00:08:00'),
  (39, 5, 7, 10, 0, 5, 'DISPONIBLE', '2026-09-30 23:48:00'),
  (40, 5, 8, 10, 0, 5, 'DISPONIBLE', '2026-10-01 02:29:00');
INSERT INTO `stock` (`id`, `banque_id`, `groupe_sanguin_id`, `quantite_disponible`, `quantite_reservee`, `seuil_alerte`, `etat`, `date_maj`) VALUES
  (41, 6, 1, 14, 0, 5, 'DISPONIBLE', '2026-10-01 08:54:00'),
  (42, 6, 2, 7, 0, 5, 'DISPONIBLE', '2026-10-01 01:31:00'),
  (43, 6, 3, 15, 4, 5, 'DISPONIBLE', '2026-10-01 07:35:00'),
  (44, 6, 4, 9, 0, 5, 'DISPONIBLE', '2026-10-01 08:46:00'),
  (45, 6, 5, 12, 0, 5, 'DISPONIBLE', '2026-10-01 02:04:00'),
  (46, 6, 6, 0, 0, 5, 'RUPTURE', '2026-10-01 01:22:00'),
  (47, 6, 7, 9, 0, 5, 'DISPONIBLE', '2026-09-30 23:05:00'),
  (48, 6, 8, 21, 0, 5, 'DISPONIBLE', '2026-09-30 19:26:00'),
  (49, 7, 1, 3, 0, 5, 'TENSION', '2026-09-30 21:40:00'),
  (50, 7, 2, 4, 0, 5, 'TENSION', '2026-10-01 06:22:00'),
  (51, 7, 3, 3, 0, 5, 'TENSION', '2026-10-01 02:11:00'),
  (52, 7, 4, 2, 0, 5, 'TENSION', '2026-09-30 20:57:00'),
  (53, 7, 5, 5, 0, 5, 'DISPONIBLE', '2026-09-30 20:04:00'),
  (54, 7, 6, 6, 0, 5, 'DISPONIBLE', '2026-10-01 05:08:00'),
  (55, 7, 7, 4, 0, 5, 'TENSION', '2026-10-01 03:40:00'),
  (56, 7, 8, 1, 0, 5, 'TENSION', '2026-10-01 00:26:00'),
  (57, 8, 1, 3, 0, 5, 'TENSION', '2026-09-30 20:40:00'),
  (58, 8, 2, 11, 0, 5, 'DISPONIBLE', '2026-10-01 01:29:00'),
  (59, 8, 3, 2, 0, 5, 'TENSION', '2026-10-01 02:12:00'),
  (60, 8, 4, 0, 0, 5, 'RUPTURE', '2026-09-30 22:54:00'),
  (61, 8, 5, 5, 0, 5, 'DISPONIBLE', '2026-10-01 01:00:00'),
  (62, 8, 6, 0, 0, 5, 'RUPTURE', '2026-09-30 22:26:00'),
  (63, 8, 7, 10, 0, 5, 'DISPONIBLE', '2026-09-30 22:44:00'),
  (64, 8, 8, 3, 0, 5, 'TENSION', '2026-10-01 08:09:00'),
  (65, 9, 1, 4, 0, 5, 'TENSION', '2026-10-01 07:24:00'),
  (66, 9, 2, 6, 0, 5, 'DISPONIBLE', '2026-09-30 23:27:00'),
  (67, 9, 3, 2, 0, 5, 'TENSION', '2026-09-30 22:46:00'),
  (68, 9, 4, 2, 0, 5, 'TENSION', '2026-10-01 05:49:00'),
  (69, 9, 5, 5, 0, 5, 'DISPONIBLE', '2026-10-01 00:12:00'),
  (70, 9, 6, 10, 0, 5, 'DISPONIBLE', '2026-09-30 19:16:00'),
  (71, 9, 7, 2, 0, 5, 'TENSION', '2026-09-30 20:01:00'),
  (72, 9, 8, 4, 0, 5, 'TENSION', '2026-09-30 19:48:00'),
  (73, 10, 1, 6, 0, 5, 'DISPONIBLE', '2026-10-01 05:41:00'),
  (74, 10, 2, 6, 0, 5, 'DISPONIBLE', '2026-10-01 05:47:00'),
  (75, 10, 3, 4, 0, 5, 'TENSION', '2026-09-30 21:12:00'),
  (76, 10, 4, 5, 0, 5, 'DISPONIBLE', '2026-10-01 05:27:00'),
  (77, 10, 5, 2, 0, 5, 'TENSION', '2026-10-01 08:41:00'),
  (78, 10, 6, 0, 0, 5, 'RUPTURE', '2026-10-01 09:00:00'),
  (79, 10, 7, 6, 0, 5, 'DISPONIBLE', '2026-09-30 23:20:00'),
  (80, 10, 8, 1, 0, 5, 'TENSION', '2026-09-30 20:29:00');
INSERT INTO `stock` (`id`, `banque_id`, `groupe_sanguin_id`, `quantite_disponible`, `quantite_reservee`, `seuil_alerte`, `etat`, `date_maj`) VALUES
  (81, 11, 1, 4, 0, 5, 'TENSION', '2026-10-01 02:18:00'),
  (82, 11, 2, 5, 0, 5, 'DISPONIBLE', '2026-10-01 09:07:00'),
  (83, 11, 3, 3, 0, 5, 'TENSION', '2026-09-30 22:58:00'),
  (84, 11, 4, 3, 0, 5, 'TENSION', '2026-09-30 19:35:00'),
  (85, 11, 5, 0, 0, 5, 'RUPTURE', '2026-10-01 06:51:00'),
  (86, 11, 6, 2, 0, 5, 'TENSION', '2026-09-30 23:17:00'),
  (87, 11, 7, 4, 0, 5, 'TENSION', '2026-10-01 03:12:00'),
  (88, 11, 8, 3, 0, 5, 'TENSION', '2026-09-30 19:48:00'),
  (89, 12, 1, 10, 0, 5, 'NON_COMMUNIQUE', '2026-09-29 06:30:00'),
  (90, 12, 2, 7, 0, 5, 'NON_COMMUNIQUE', '2026-09-29 06:30:00'),
  (91, 12, 3, 6, 0, 5, 'NON_COMMUNIQUE', '2026-09-29 06:30:00'),
  (92, 12, 4, 1, 0, 5, 'NON_COMMUNIQUE', '2026-09-29 06:30:00'),
  (93, 12, 5, 3, 0, 5, 'NON_COMMUNIQUE', '2026-09-29 06:30:00'),
  (94, 12, 6, 9, 0, 5, 'NON_COMMUNIQUE', '2026-09-29 06:30:00'),
  (95, 12, 7, 8, 0, 5, 'NON_COMMUNIQUE', '2026-09-29 06:30:00'),
  (96, 12, 8, 5, 0, 5, 'NON_COMMUNIQUE', '2026-09-29 06:30:00');

INSERT INTO `mouvement_stock` (`id`, `banque_id`, `groupe_sanguin_id`, `poche_id`, `type`, `quantite`, `quantite_apres`, `precommande_id`, `utilisateur_id`, `motif`, `date_mouvement`) VALUES
  (1, 1, 1, NULL, 'SORTIE', -4, 9, 1, 2, 'Précommande PRE-COT-2026-00001', '2026-09-25 09:30:00'),
  (2, 2, 2, NULL, 'RESERVATION', -3, 8, 2, 3, 'Précommande PRE-COT-2026-00002', '2026-09-29 05:30:00'),
  (3, 2, 3, NULL, 'RESERVATION', -2, 8, 2, 3, 'Précommande PRE-COT-2026-00002', '2026-09-29 05:30:00'),
  (4, 1, 5, NULL, 'RESERVATION', -5, 16, 3, 2, 'Précommande PRE-COT-2026-00003', '2026-09-30 06:30:00'),
  (5, 6, 3, NULL, 'RESERVATION', -4, 15, 4, 4, 'Précommande PRE-COT-2026-00004', '2026-09-30 10:30:00'),
  (6, 1, 1, NULL, 'ENTREE', 12, 9, NULL, 6, 'Collecte mobile — campagne de don', '2026-09-28 09:30:00'),
  (7, 1, 2, NULL, 'ENTREE', 4, 14, NULL, 6, 'Collecte mobile — campagne de don', '2026-09-28 09:30:00'),
  (8, 2, 3, NULL, 'ENTREE', 8, 8, NULL, 7, 'Collecte mobile — campagne de don', '2026-09-29 09:30:00'),
  (9, 6, 1, NULL, 'ENTREE', 10, 14, NULL, 7, 'Collecte mobile — campagne de don', '2026-09-29 09:30:00'),
  (10, 8, 5, NULL, 'ENTREE', 6, 5, NULL, 7, 'Collecte mobile — campagne de don', '2026-09-30 09:30:00'),
  (11, 5, 1, NULL, 'ENTREE', 7, 5, NULL, 7, 'Collecte mobile — campagne de don', '2026-09-30 09:30:00'),
  (12, 4, 6, NULL, 'PEREMPTION', -2, 0, NULL, 2, 'Poches périmées — destruction tracée', '2026-09-29 09:30:00'),
  (13, 1, 1, 7, 'ENTREE', 1, 9, NULL, 9, 'Qualification conforme du don DON-01-2026-0001', '2026-09-25 10:05:00'),
  (14, 1, 1, 8, 'ENTREE', 1, 9, NULL, 9, 'Qualification conforme du don DON-01-2026-0002', '2026-09-29 10:05:00'),
  (15, 1, 1, 9, 'ENTREE', 1, 9, NULL, 9, 'Qualification conforme du don DON-01-2026-0003', '2026-09-27 12:05:00'),
  (16, 1, 1, 12, 'ENTREE', 1, 9, NULL, 9, 'Qualification conforme du don DON-01-2026-0004', '2026-09-25 11:20:00'),
  (17, 1, 1, 13, 'ENTREE', 1, 9, NULL, 9, 'Qualification conforme du don DON-01-2026-0005', '2026-09-25 12:40:00'),
  (18, 1, 2, 25, 'ENTREE', 1, 14, NULL, 9, 'Qualification conforme du don DON-01-2026-0006', '2026-09-30 10:20:00'),
  (19, 2, 1, 109, 'ENTREE', 1, 13, NULL, 10, 'Qualification conforme du don DON-02-2026-0007', '2026-09-27 11:20:00'),
  (20, 2, 1, 112, 'ENTREE', 1, 13, NULL, 10, 'Qualification conforme du don DON-02-2026-0008', '2026-09-27 13:20:00'),
  (21, 2, 2, 118, 'ENTREE', 1, 8, NULL, 10, 'Qualification conforme du don DON-02-2026-0009', '2026-09-26 12:20:00'),
  (22, 2, 2, 121, 'ENTREE', 1, 8, NULL, 10, 'Qualification conforme du don DON-02-2026-0010', '2026-09-26 10:20:00'),
  (23, 2, 3, 128, 'ENTREE', 1, 8, NULL, 10, 'Qualification conforme du don DON-02-2026-0011', '2026-10-01 13:40:00'),
  (24, 2, 5, 138, 'ENTREE', 1, 11, NULL, 10, 'Qualification conforme du don DON-02-2026-0012', '2026-09-26 12:05:00');

INSERT INTO `notification_sms` (`id`, `destinataire`, `message`, `type`, `precommande_id`, `statut`, `tentatives`, `date_creation`, `date_envoi`, `erreur`) VALUES
  (1, '+229 97 20 30 40', 'HemoLink : votre precommande PRE-COT-2026-00001 est confirmee. Retrait sous 48h, presentez ce numero.', 'CONFIRMATION', 1, 'ENVOYE', 1, '2026-09-25 09:30:00', '2026-09-25 09:30:12', NULL),
  (2, '+229 96 51 62 73', 'HemoLink : votre precommande PRE-COT-2026-00002 est confirmee. Retrait sous 48h, presentez ce numero.', 'CONFIRMATION', 2, 'ENVOYE', 1, '2026-09-29 05:30:00', '2026-09-29 05:30:12', NULL),
  (3, '+229 96 51 62 73', 'HemoLink : les poches de la precommande PRE-COT-2026-00002 sont pretes au retrait.', 'RAPPEL', 2, 'ENVOYE', 1, '2026-09-29 06:30:00', '2026-09-29 06:30:09', NULL),
  (4, '+229 21 33 44 55', 'HemoLink : votre precommande PRE-COT-2026-00003 est confirmee. Retrait sous 48h, presentez ce numero.', 'CONFIRMATION', 3, 'ENVOYE', 1, '2026-09-30 06:30:00', '2026-09-30 06:30:12', NULL),
  (5, '+229 97 83 94 05', 'HemoLink : votre precommande PRE-COT-2026-00004 est confirmee. Retrait sous 48h, presentez ce numero.', 'CONFIRMATION', 4, 'ENVOYE', 1, '2026-09-30 10:30:00', '2026-09-30 10:30:12', NULL),
  (6, '+229 97 20 30 40', 'HemoLink : la precommande PRE-COT-2026-00007 a ete annulee.', 'AUTRE', 7, 'ECHEC', 3, '2026-09-27 12:30:00', NULL, 'Operateur injoignable (timeout 30s)'),
  (7, '+229 95 77 88 99', 'HemoLink : rupture de B- a CHUD Ouémé-Plateau. Stock a 0 poche, pensez a lancer une collecte.', 'ALERTE_RUPTURE', NULL, 'ENVOYE', 1, '2026-10-01 06:30:00', '2026-10-01 06:30:07', NULL),
  (8, '+229 97 31 76 20', 'HemoLink : rendez-vous de don RDV-2026-00017 le 05/10/2026 a 08:00 - CNTS. Venez avec une piece d identite.', 'RAPPEL', NULL, 'ENVOYE', 1, '2026-09-26 09:00:00', '2026-09-26 09:00:08', NULL),
  (9, '+229 96 44 08 15', 'HemoLink : rendez-vous de don RDV-2026-00018 le 07/10/2026 a 08:00 - CNHU-HKM. Venez avec une piece d identite.', 'RAPPEL', NULL, 'ENVOYE', 1, '2026-10-02 09:00:00', '2026-10-02 09:00:08', NULL),
  (10, '+229 94 76 55 12', 'HemoLink : rendez-vous de don RDV-2026-00019 le 03/10/2026 a 08:00 - CNHU-HKM. Venez avec une piece d identite.', 'RAPPEL', NULL, 'ENVOYE', 1, '2026-10-01 09:00:00', '2026-10-01 09:00:08', NULL),
  (11, '+229 96 31 90 44', 'HemoLink : rendez-vous de don RDV-2026-00020 le 09/10/2026 a 08:00 - CNHU-HKM. Venez avec une piece d identite.', 'RAPPEL', NULL, 'ENVOYE', 1, '2026-10-07 09:00:00', '2026-10-07 09:00:08', NULL),
  (12, '+229 97 45 83 06', 'HemoLink : rendez-vous de don RDV-2026-00021 le 12/10/2026 a 08:00 - CNHU-HKM. Venez avec une piece d identite.', 'RAPPEL', NULL, 'ENVOYE', 1, '2026-10-08 09:00:00', '2026-10-08 09:00:08', NULL);

INSERT INTO `compteur` (`cle`, `prefixe`, `annee`, `valeur`, `date_maj`) VALUES
  ('PRECOMMANDE-COT-2026', 'PRE-COT', 2026, 7, '2026-10-01 09:30:00'),
  ('POCHE-2026', 'PO', 2026, 604, '2026-10-01 09:30:00');

INSERT INTO `journal` (`id`, `utilisateur_id`, `action`, `entite`, `entite_id`, `details`, `adresse_ip`, `date_action`) VALUES
  (1, 1, 'CONNEXION', 'Utilisateur', 1, 'Connexion administrateur', '41.79.12.84', '2026-10-01 07:30:00'),
  (2, 3, 'MAJ_STOCK', 'Stock', 9, 'Mise à jour du stock O+ du CNHU-HKM : 10 -> 13 poches', '41.79.12.90', '2026-10-01 06:30:00'),
  (3, 3, 'CONFIRMATION', 'Precommande', 2, 'Précommande PRE-COT-2026-00002 confirmée', '41.79.12.90', '2026-09-29 09:30:00'),
  (4, 2, 'CREATION', 'Utilisateur', 6, 'Création du compte agent.cnts@hemolink.bj', '41.79.12.84', '2026-09-26 09:30:00'),
  (5, NULL, 'PRECOMMANDE', 'Precommande', 6, 'Précommande PRE-COT-2026-00006 déposée depuis le site public', '197.234.221.17', '2026-10-01 04:30:00'),
  (6, 1, 'SAUVEGARDE', 'Systeme', NULL, 'Sauvegarde quotidienne : hemolink_2026-09-30.sql', '127.0.0.1', '2026-10-01 00:30:00'),
  (7, 9, 'QUALIFICATION', 'Don', 13, 'Don DON-01-2026-0013 declare non conforme : aucune poche mise en stock', '41.79.12.91', '2026-09-27 12:30:00'),
  (8, 10, 'QUALIFICATION', 'Don', 14, 'Don DON-02-2026-0014 declare non conforme : aucune poche mise en stock', '41.79.12.92', '2026-09-29 12:30:00'),
  (9, 9, 'QUALIFICATION', 'Don', 1, '12 produits qualifies conformes et mis en stock', '41.79.12.91', '2026-10-01 03:30:00');


-- =====================================================================
--  MODULE DON : donneurs, creneaux, rendez-vous, dons, qualification
-- =====================================================================

INSERT INTO `donneur` (`id`, `utilisateur_id`, `groupe_sanguin_id`, `numero_donneur`, `nom`, `prenom`, `sexe`, `date_naissance`, `telephone`, `email`, `ville`, `dernier_don`, `nombre_dons`, `apte`, `motif_inaptitude`, `date_creation`) VALUES
  (1, 11, 1, 'DN-2026-00001', 'SOSSOU', 'Armand', 'M', '1991-03-14', '+229 97 31 76 20', 'donneur.sossou@hemolink.bj', 'Cotonou', '2026-09-28', 13, 1, NULL, '2025-10-05 09:30:00'),
  (2, 12, 3, 'DN-2026-00002', 'TOGNON', 'Mireille', 'F', '1996-11-02', '+229 96 44 08 15', 'donneur.tognon@hemolink.bj', 'Cotonou', '2026-09-30', 4, 1, NULL, '2025-08-25 09:30:00'),
  (3, NULL, 5, 'DN-2026-00003', 'ADJOVI', 'Cyrille', 'M', '1988-06-25', '+229 95 12 63 40', 'adjovi.cyrille@exemple.bj', 'Abomey-Calavi', '2026-09-26', 11, 1, NULL, '2025-03-12 09:30:00'),
  (4, NULL, 2, 'DN-2026-00004', 'DANSOU', 'Prudencia', 'F', '2001-01-30', '+229 94 76 55 12', 'dansou.prudencia@exemple.bj', 'Cotonou', '2026-09-29', 5, 1, NULL, '2024-06-18 09:30:00'),
  (5, NULL, 7, 'DN-2026-00005', 'KPADONOU', 'Serge', 'M', '1979-09-08', '+229 97 08 22 71', 'kpadonou.serge@exemple.bj', 'Porto-Novo', '2026-09-28', 12, 1, NULL, '2024-07-11 09:30:00'),
  (6, NULL, 4, 'DN-2026-00006', 'BIO SIKA', 'Nadège', 'F', '1994-04-17', '+229 96 31 90 44', 'biosika.nadège@exemple.bj', 'Parakou', NULL, 4, 1, NULL, '2024-04-25 09:30:00'),
  (7, NULL, NULL, 'DN-2026-00007', 'ZINSOU', 'Hervé', 'M', '1999-12-05', '+229 95 60 17 38', 'zinsou.hervé@exemple.bj', 'Porto-Novo', '2026-09-30', 1, 1, NULL, '2025-08-15 09:30:00'),
  (8, NULL, 1, 'DN-2026-00008', 'GANDONOU', 'Reine', 'F', '1985-07-21', '+229 97 45 83 06', 'gandonou.reine@exemple.bj', 'Cotonou', '2026-09-30', 8, 1, NULL, '2024-05-07 09:30:00');

INSERT INTO `creneau_don` (`id`, `banque_id`, `date_creneau`, `heure_debut`, `heure_fin`, `capacite`, `places_reservees`, `lieu`, `statut`, `date_creation`) VALUES
  (1, 1, '2026-09-24', '08:00:00', '10:00:00', 8, 1, NULL, 'CLOS', '2026-09-10 09:30:00'),
  (2, 1, '2026-09-24', '10:00:00', '12:00:00', 8, 1, NULL, 'CLOS', '2026-09-10 09:30:00'),
  (3, 1, '2026-09-24', '15:00:00', '17:00:00', 8, 1, NULL, 'CLOS', '2026-09-10 09:30:00'),
  (4, 1, '2026-09-25', '08:00:00', '10:00:00', 8, 0, NULL, 'CLOS', '2026-09-10 09:30:00'),
  (5, 1, '2026-09-25', '10:00:00', '12:00:00', 8, 0, NULL, 'CLOS', '2026-09-10 09:30:00'),
  (6, 1, '2026-09-25', '15:00:00', '17:00:00', 8, 0, NULL, 'CLOS', '2026-09-10 09:30:00'),
  (7, 1, '2026-09-26', '08:00:00', '10:00:00', 8, 2, NULL, 'CLOS', '2026-09-10 09:30:00'),
  (8, 1, '2026-09-26', '10:00:00', '12:00:00', 8, 0, NULL, 'CLOS', '2026-09-10 09:30:00'),
  (9, 1, '2026-09-28', '08:00:00', '10:00:00', 8, 1, NULL, 'CLOS', '2026-09-10 09:30:00'),
  (10, 1, '2026-09-28', '10:00:00', '12:00:00', 8, 0, NULL, 'CLOS', '2026-09-10 09:30:00'),
  (11, 1, '2026-09-28', '15:00:00', '17:00:00', 8, 0, NULL, 'CLOS', '2026-09-10 09:30:00'),
  (12, 1, '2026-09-29', '08:00:00', '10:00:00', 8, 1, NULL, 'CLOS', '2026-09-10 09:30:00'),
  (13, 1, '2026-09-29', '10:00:00', '12:00:00', 8, 0, NULL, 'CLOS', '2026-09-10 09:30:00'),
  (14, 1, '2026-09-29', '15:00:00', '17:00:00', 8, 0, NULL, 'CLOS', '2026-09-10 09:30:00'),
  (15, 1, '2026-09-30', '08:00:00', '10:00:00', 8, 1, NULL, 'CLOS', '2026-09-10 09:30:00'),
  (16, 1, '2026-09-30', '10:00:00', '12:00:00', 8, 0, NULL, 'CLOS', '2026-09-10 09:30:00'),
  (17, 1, '2026-09-30', '15:00:00', '17:00:00', 8, 0, NULL, 'CLOS', '2026-09-10 09:30:00'),
  (18, 1, '2026-10-01', '08:00:00', '10:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (19, 1, '2026-10-01', '10:00:00', '12:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (20, 1, '2026-10-01', '15:00:00', '17:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (21, 1, '2026-10-02', '08:00:00', '10:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (22, 1, '2026-10-02', '10:00:00', '12:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (23, 1, '2026-10-02', '15:00:00', '17:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (24, 1, '2026-10-03', '08:00:00', '10:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (25, 1, '2026-10-03', '10:00:00', '12:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (26, 1, '2026-10-05', '08:00:00', '10:00:00', 8, 1, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (27, 1, '2026-10-05', '10:00:00', '12:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (28, 1, '2026-10-05', '15:00:00', '17:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (29, 1, '2026-10-06', '08:00:00', '10:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (30, 1, '2026-10-06', '10:00:00', '12:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (31, 1, '2026-10-06', '15:00:00', '17:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (32, 1, '2026-10-07', '08:00:00', '10:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (33, 1, '2026-10-07', '10:00:00', '12:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (34, 1, '2026-10-07', '15:00:00', '17:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (35, 1, '2026-10-08', '08:00:00', '10:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (36, 1, '2026-10-08', '10:00:00', '12:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (37, 1, '2026-10-08', '15:00:00', '17:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (38, 1, '2026-10-09', '08:00:00', '10:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (39, 1, '2026-10-09', '10:00:00', '12:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (40, 1, '2026-10-09', '15:00:00', '17:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (41, 1, '2026-10-10', '08:00:00', '10:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (42, 1, '2026-10-10', '10:00:00', '12:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (43, 1, '2026-10-12', '08:00:00', '10:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (44, 1, '2026-10-12', '10:00:00', '12:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (45, 1, '2026-10-12', '15:00:00', '17:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (46, 1, '2026-10-13', '08:00:00', '10:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (47, 1, '2026-10-13', '10:00:00', '12:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (48, 1, '2026-10-13', '15:00:00', '17:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (49, 1, '2026-10-14', '08:00:00', '10:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (50, 1, '2026-10-14', '10:00:00', '12:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (51, 1, '2026-10-14', '15:00:00', '17:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (52, 1, '2026-10-15', '08:00:00', '10:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (53, 1, '2026-10-15', '10:00:00', '12:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (54, 1, '2026-10-15', '15:00:00', '17:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (55, 2, '2026-09-24', '08:00:00', '10:00:00', 8, 0, NULL, 'CLOS', '2026-09-10 09:30:00'),
  (56, 2, '2026-09-24', '10:00:00', '12:00:00', 8, 0, NULL, 'CLOS', '2026-09-10 09:30:00'),
  (57, 2, '2026-09-24', '15:00:00', '17:00:00', 8, 0, NULL, 'CLOS', '2026-09-10 09:30:00'),
  (58, 2, '2026-09-25', '08:00:00', '10:00:00', 8, 2, NULL, 'CLOS', '2026-09-10 09:30:00'),
  (59, 2, '2026-09-25', '10:00:00', '12:00:00', 8, 1, NULL, 'CLOS', '2026-09-10 09:30:00'),
  (60, 2, '2026-09-25', '15:00:00', '17:00:00', 8, 0, NULL, 'CLOS', '2026-09-10 09:30:00');
INSERT INTO `creneau_don` (`id`, `banque_id`, `date_creneau`, `heure_debut`, `heure_fin`, `capacite`, `places_reservees`, `lieu`, `statut`, `date_creation`) VALUES
  (61, 2, '2026-09-26', '08:00:00', '10:00:00', 8, 1, NULL, 'CLOS', '2026-09-10 09:30:00'),
  (62, 2, '2026-09-26', '10:00:00', '12:00:00', 8, 1, NULL, 'CLOS', '2026-09-10 09:30:00'),
  (63, 2, '2026-09-28', '08:00:00', '10:00:00', 8, 1, NULL, 'CLOS', '2026-09-10 09:30:00'),
  (64, 2, '2026-09-28', '10:00:00', '12:00:00', 8, 0, NULL, 'CLOS', '2026-09-10 09:30:00'),
  (65, 2, '2026-09-28', '15:00:00', '17:00:00', 8, 0, NULL, 'CLOS', '2026-09-10 09:30:00'),
  (66, 2, '2026-09-29', '08:00:00', '10:00:00', 8, 0, NULL, 'CLOS', '2026-09-10 09:30:00'),
  (67, 2, '2026-09-29', '10:00:00', '12:00:00', 8, 0, NULL, 'CLOS', '2026-09-10 09:30:00'),
  (68, 2, '2026-09-29', '15:00:00', '17:00:00', 8, 0, NULL, 'CLOS', '2026-09-10 09:30:00'),
  (69, 2, '2026-09-30', '08:00:00', '10:00:00', 8, 2, NULL, 'CLOS', '2026-09-10 09:30:00'),
  (70, 2, '2026-09-30', '10:00:00', '12:00:00', 8, 0, NULL, 'CLOS', '2026-09-10 09:30:00'),
  (71, 2, '2026-09-30', '15:00:00', '17:00:00', 8, 0, NULL, 'CLOS', '2026-09-10 09:30:00'),
  (72, 2, '2026-10-01', '08:00:00', '10:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (73, 2, '2026-10-01', '10:00:00', '12:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (74, 2, '2026-10-01', '15:00:00', '17:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (75, 2, '2026-10-02', '08:00:00', '10:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (76, 2, '2026-10-02', '10:00:00', '12:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (77, 2, '2026-10-02', '15:00:00', '17:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (78, 2, '2026-10-03', '08:00:00', '10:00:00', 8, 1, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (79, 2, '2026-10-03', '10:00:00', '12:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (80, 2, '2026-10-05', '08:00:00', '10:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (81, 2, '2026-10-05', '10:00:00', '12:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (82, 2, '2026-10-05', '15:00:00', '17:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (83, 2, '2026-10-06', '08:00:00', '10:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (84, 2, '2026-10-06', '10:00:00', '12:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (85, 2, '2026-10-06', '15:00:00', '17:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (86, 2, '2026-10-07', '08:00:00', '10:00:00', 8, 1, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (87, 2, '2026-10-07', '10:00:00', '12:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (88, 2, '2026-10-07', '15:00:00', '17:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (89, 2, '2026-10-08', '08:00:00', '10:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (90, 2, '2026-10-08', '10:00:00', '12:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (91, 2, '2026-10-08', '15:00:00', '17:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (92, 2, '2026-10-09', '08:00:00', '10:00:00', 8, 1, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (93, 2, '2026-10-09', '10:00:00', '12:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (94, 2, '2026-10-09', '15:00:00', '17:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (95, 2, '2026-10-10', '08:00:00', '10:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (96, 2, '2026-10-10', '10:00:00', '12:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (97, 2, '2026-10-12', '08:00:00', '10:00:00', 8, 1, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (98, 2, '2026-10-12', '10:00:00', '12:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (99, 2, '2026-10-12', '15:00:00', '17:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (100, 2, '2026-10-13', '08:00:00', '10:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (101, 2, '2026-10-13', '10:00:00', '12:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (102, 2, '2026-10-13', '15:00:00', '17:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (103, 2, '2026-10-14', '08:00:00', '10:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (104, 2, '2026-10-14', '10:00:00', '12:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (105, 2, '2026-10-14', '15:00:00', '17:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (106, 2, '2026-10-15', '08:00:00', '10:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (107, 2, '2026-10-15', '10:00:00', '12:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (108, 2, '2026-10-15', '15:00:00', '17:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (109, 6, '2026-09-24', '08:00:00', '10:00:00', 8, 0, NULL, 'CLOS', '2026-09-10 09:30:00'),
  (110, 6, '2026-09-24', '10:00:00', '12:00:00', 8, 0, NULL, 'CLOS', '2026-09-10 09:30:00'),
  (111, 6, '2026-09-24', '15:00:00', '17:00:00', 8, 0, NULL, 'CLOS', '2026-09-10 09:30:00'),
  (112, 6, '2026-09-25', '08:00:00', '10:00:00', 8, 0, NULL, 'CLOS', '2026-09-10 09:30:00'),
  (113, 6, '2026-09-25', '10:00:00', '12:00:00', 8, 0, NULL, 'CLOS', '2026-09-10 09:30:00'),
  (114, 6, '2026-09-25', '15:00:00', '17:00:00', 8, 0, NULL, 'CLOS', '2026-09-10 09:30:00'),
  (115, 6, '2026-09-26', '08:00:00', '10:00:00', 8, 0, NULL, 'CLOS', '2026-09-10 09:30:00'),
  (116, 6, '2026-09-26', '10:00:00', '12:00:00', 8, 0, NULL, 'CLOS', '2026-09-10 09:30:00'),
  (117, 6, '2026-09-28', '08:00:00', '10:00:00', 8, 0, NULL, 'CLOS', '2026-09-10 09:30:00'),
  (118, 6, '2026-09-28', '10:00:00', '12:00:00', 8, 0, NULL, 'CLOS', '2026-09-10 09:30:00'),
  (119, 6, '2026-09-28', '15:00:00', '17:00:00', 8, 0, NULL, 'CLOS', '2026-09-10 09:30:00'),
  (120, 6, '2026-09-29', '08:00:00', '10:00:00', 8, 0, NULL, 'CLOS', '2026-09-10 09:30:00');
INSERT INTO `creneau_don` (`id`, `banque_id`, `date_creneau`, `heure_debut`, `heure_fin`, `capacite`, `places_reservees`, `lieu`, `statut`, `date_creation`) VALUES
  (121, 6, '2026-09-29', '10:00:00', '12:00:00', 8, 0, NULL, 'CLOS', '2026-09-10 09:30:00'),
  (122, 6, '2026-09-29', '15:00:00', '17:00:00', 8, 0, NULL, 'CLOS', '2026-09-10 09:30:00'),
  (123, 6, '2026-09-30', '08:00:00', '10:00:00', 8, 0, NULL, 'CLOS', '2026-09-10 09:30:00'),
  (124, 6, '2026-09-30', '10:00:00', '12:00:00', 8, 0, NULL, 'CLOS', '2026-09-10 09:30:00'),
  (125, 6, '2026-09-30', '15:00:00', '17:00:00', 8, 0, NULL, 'CLOS', '2026-09-10 09:30:00'),
  (126, 6, '2026-10-01', '08:00:00', '10:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (127, 6, '2026-10-01', '10:00:00', '12:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (128, 6, '2026-10-01', '15:00:00', '17:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (129, 6, '2026-10-02', '08:00:00', '10:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (130, 6, '2026-10-02', '10:00:00', '12:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (131, 6, '2026-10-02', '15:00:00', '17:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (132, 6, '2026-10-03', '08:00:00', '10:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (133, 6, '2026-10-03', '10:00:00', '12:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (134, 6, '2026-10-05', '08:00:00', '10:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (135, 6, '2026-10-05', '10:00:00', '12:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (136, 6, '2026-10-05', '15:00:00', '17:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (137, 6, '2026-10-06', '08:00:00', '10:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (138, 6, '2026-10-06', '10:00:00', '12:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (139, 6, '2026-10-06', '15:00:00', '17:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (140, 6, '2026-10-07', '08:00:00', '10:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (141, 6, '2026-10-07', '10:00:00', '12:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (142, 6, '2026-10-07', '15:00:00', '17:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (143, 6, '2026-10-08', '08:00:00', '10:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (144, 6, '2026-10-08', '10:00:00', '12:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (145, 6, '2026-10-08', '15:00:00', '17:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (146, 6, '2026-10-09', '08:00:00', '10:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (147, 6, '2026-10-09', '10:00:00', '12:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (148, 6, '2026-10-09', '15:00:00', '17:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (149, 6, '2026-10-10', '08:00:00', '10:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (150, 6, '2026-10-10', '10:00:00', '12:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (151, 6, '2026-10-12', '08:00:00', '10:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (152, 6, '2026-10-12', '10:00:00', '12:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (153, 6, '2026-10-12', '15:00:00', '17:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (154, 6, '2026-10-13', '08:00:00', '10:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (155, 6, '2026-10-13', '10:00:00', '12:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (156, 6, '2026-10-13', '15:00:00', '17:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (157, 6, '2026-10-14', '08:00:00', '10:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (158, 6, '2026-10-14', '10:00:00', '12:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (159, 6, '2026-10-14', '15:00:00', '17:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (160, 6, '2026-10-15', '08:00:00', '10:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (161, 6, '2026-10-15', '10:00:00', '12:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (162, 6, '2026-10-15', '15:00:00', '17:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (163, 8, '2026-09-24', '08:00:00', '10:00:00', 8, 0, NULL, 'CLOS', '2026-09-10 09:30:00'),
  (164, 8, '2026-09-24', '10:00:00', '12:00:00', 8, 0, NULL, 'CLOS', '2026-09-10 09:30:00'),
  (165, 8, '2026-09-24', '15:00:00', '17:00:00', 8, 0, NULL, 'CLOS', '2026-09-10 09:30:00'),
  (166, 8, '2026-09-25', '08:00:00', '10:00:00', 8, 0, NULL, 'CLOS', '2026-09-10 09:30:00'),
  (167, 8, '2026-09-25', '10:00:00', '12:00:00', 8, 0, NULL, 'CLOS', '2026-09-10 09:30:00'),
  (168, 8, '2026-09-25', '15:00:00', '17:00:00', 8, 0, NULL, 'CLOS', '2026-09-10 09:30:00'),
  (169, 8, '2026-09-26', '08:00:00', '10:00:00', 8, 0, NULL, 'CLOS', '2026-09-10 09:30:00'),
  (170, 8, '2026-09-26', '10:00:00', '12:00:00', 8, 0, NULL, 'CLOS', '2026-09-10 09:30:00'),
  (171, 8, '2026-09-28', '08:00:00', '10:00:00', 8, 0, NULL, 'CLOS', '2026-09-10 09:30:00'),
  (172, 8, '2026-09-28', '10:00:00', '12:00:00', 8, 0, NULL, 'CLOS', '2026-09-10 09:30:00'),
  (173, 8, '2026-09-28', '15:00:00', '17:00:00', 8, 0, NULL, 'CLOS', '2026-09-10 09:30:00'),
  (174, 8, '2026-09-29', '08:00:00', '10:00:00', 8, 0, NULL, 'CLOS', '2026-09-10 09:30:00'),
  (175, 8, '2026-09-29', '10:00:00', '12:00:00', 8, 0, NULL, 'CLOS', '2026-09-10 09:30:00'),
  (176, 8, '2026-09-29', '15:00:00', '17:00:00', 8, 0, NULL, 'CLOS', '2026-09-10 09:30:00'),
  (177, 8, '2026-09-30', '08:00:00', '10:00:00', 8, 0, NULL, 'CLOS', '2026-09-10 09:30:00'),
  (178, 8, '2026-09-30', '10:00:00', '12:00:00', 8, 0, NULL, 'CLOS', '2026-09-10 09:30:00'),
  (179, 8, '2026-09-30', '15:00:00', '17:00:00', 8, 0, NULL, 'CLOS', '2026-09-10 09:30:00'),
  (180, 8, '2026-10-01', '08:00:00', '10:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00');
INSERT INTO `creneau_don` (`id`, `banque_id`, `date_creneau`, `heure_debut`, `heure_fin`, `capacite`, `places_reservees`, `lieu`, `statut`, `date_creation`) VALUES
  (181, 8, '2026-10-01', '10:00:00', '12:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (182, 8, '2026-10-01', '15:00:00', '17:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (183, 8, '2026-10-02', '08:00:00', '10:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (184, 8, '2026-10-02', '10:00:00', '12:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (185, 8, '2026-10-02', '15:00:00', '17:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (186, 8, '2026-10-03', '08:00:00', '10:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (187, 8, '2026-10-03', '10:00:00', '12:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (188, 8, '2026-10-05', '08:00:00', '10:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (189, 8, '2026-10-05', '10:00:00', '12:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (190, 8, '2026-10-05', '15:00:00', '17:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (191, 8, '2026-10-06', '08:00:00', '10:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (192, 8, '2026-10-06', '10:00:00', '12:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (193, 8, '2026-10-06', '15:00:00', '17:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (194, 8, '2026-10-07', '08:00:00', '10:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (195, 8, '2026-10-07', '10:00:00', '12:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (196, 8, '2026-10-07', '15:00:00', '17:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (197, 8, '2026-10-08', '08:00:00', '10:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (198, 8, '2026-10-08', '10:00:00', '12:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (199, 8, '2026-10-08', '15:00:00', '17:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (200, 8, '2026-10-09', '08:00:00', '10:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (201, 8, '2026-10-09', '10:00:00', '12:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (202, 8, '2026-10-09', '15:00:00', '17:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (203, 8, '2026-10-10', '08:00:00', '10:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (204, 8, '2026-10-10', '10:00:00', '12:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (205, 8, '2026-10-12', '08:00:00', '10:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (206, 8, '2026-10-12', '10:00:00', '12:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (207, 8, '2026-10-12', '15:00:00', '17:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (208, 8, '2026-10-13', '08:00:00', '10:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (209, 8, '2026-10-13', '10:00:00', '12:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (210, 8, '2026-10-13', '15:00:00', '17:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (211, 8, '2026-10-14', '08:00:00', '10:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (212, 8, '2026-10-14', '10:00:00', '12:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (213, 8, '2026-10-14', '15:00:00', '17:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (214, 8, '2026-10-15', '08:00:00', '10:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (215, 8, '2026-10-15', '10:00:00', '12:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00'),
  (216, 8, '2026-10-15', '15:00:00', '17:00:00', 8, 0, NULL, 'OUVERT', '2026-09-10 09:30:00');

INSERT INTO `rendez_vous_don` (`id`, `numero`, `donneur_id`, `creneau_id`, `statut`, `commentaire`, `date_creation`, `date_annulation`) VALUES
  (1, 'RDV-2026-00001', 1, 1, 'HONORE', NULL, '2026-09-15 09:00:00', NULL),
  (2, 'RDV-2026-00002', 1, 9, 'HONORE', NULL, '2026-09-24 09:00:00', NULL),
  (3, 'RDV-2026-00003', 1, 7, 'HONORE', NULL, '2026-09-20 09:00:00', NULL),
  (4, 'RDV-2026-00004', 1, 2, 'HONORE', NULL, '2026-09-22 09:00:00', NULL),
  (5, 'RDV-2026-00005', 1, 3, 'HONORE', NULL, '2026-09-17 09:00:00', NULL),
  (6, 'RDV-2026-00006', 4, 12, 'HONORE', NULL, '2026-09-22 09:00:00', NULL),
  (7, 'RDV-2026-00007', 1, 61, 'HONORE', NULL, '2026-09-19 09:00:00', NULL),
  (8, 'RDV-2026-00008', 1, 62, 'HONORE', NULL, '2026-09-23 09:00:00', NULL),
  (9, 'RDV-2026-00009', 4, 58, 'HONORE', NULL, '2026-09-16 09:00:00', NULL),
  (10, 'RDV-2026-00010', 4, 59, 'HONORE', NULL, '2026-09-16 09:00:00', NULL),
  (11, 'RDV-2026-00011', 2, 69, 'HONORE', NULL, '2026-09-24 09:00:00', NULL),
  (12, 'RDV-2026-00012', 3, 58, 'HONORE', NULL, '2026-09-19 09:00:00', NULL),
  (13, 'RDV-2026-00013', 3, 7, 'HONORE', NULL, '2026-09-21 09:00:00', NULL),
  (14, 'RDV-2026-00014', 5, 63, 'HONORE', NULL, '2026-09-22 09:00:00', NULL),
  (15, 'RDV-2026-00015', 8, 15, 'HONORE', NULL, '2026-09-27 09:00:00', NULL),
  (16, 'RDV-2026-00016', 7, 69, 'HONORE', NULL, '2026-09-24 09:00:00', NULL),
  (17, 'RDV-2026-00017', 1, 26, 'PLANIFIE', NULL, '2026-09-26 09:00:00', NULL),
  (18, 'RDV-2026-00018', 2, 86, 'PLANIFIE', NULL, '2026-10-02 09:00:00', NULL),
  (19, 'RDV-2026-00019', 4, 78, 'PLANIFIE', NULL, '2026-10-01 09:00:00', NULL),
  (20, 'RDV-2026-00020', 6, 92, 'PLANIFIE', NULL, '2026-10-07 09:00:00', NULL),
  (21, 'RDV-2026-00021', 8, 97, 'PLANIFIE', NULL, '2026-10-08 09:00:00', NULL),
  (22, 'RDV-2026-00022', 3, 29, 'ANNULE', NULL, '2026-10-02 09:00:00', '2026-09-30 09:30:00'),
  (23, 'RDV-2026-00023', 4, 61, 'ABSENT', NULL, '2026-09-23 09:00:00', NULL);

INSERT INTO `don` (`id`, `code_don`, `donneur_id`, `banque_id`, `rendez_vous_id`, `groupe_sanguin_id`, `date_don`, `volume_ml`, `hemoglobine`, `statut`, `utilisateur_id`, `observation`, `date_creation`) VALUES
  (1, 'DON-01-2026-0001', 1, 1, 1, 1, '2026-09-24 08:05:00', 450, 14.5, 'QUALIFIE', 6, NULL, '2026-09-24 08:05:00'),
  (2, 'DON-01-2026-0002', 1, 1, 2, 1, '2026-09-28 08:05:00', 450, 15.7, 'QUALIFIE', 6, NULL, '2026-09-28 08:05:00'),
  (3, 'DON-01-2026-0003', 1, 1, 3, 1, '2026-09-26 10:05:00', 250, 13.1, 'QUALIFIE', 6, NULL, '2026-09-26 10:05:00'),
  (4, 'DON-01-2026-0004', 1, 1, 4, 1, '2026-09-24 09:20:00', 350, 12.6, 'QUALIFIE', 6, NULL, '2026-09-24 09:20:00'),
  (5, 'DON-01-2026-0005', 1, 1, 5, 1, '2026-09-24 10:40:00', 450, 12.7, 'QUALIFIE', 6, NULL, '2026-09-24 10:40:00'),
  (6, 'DON-01-2026-0006', 4, 1, 6, 2, '2026-09-29 08:20:00', 350, 15.0, 'QUALIFIE', 6, NULL, '2026-09-29 08:20:00'),
  (7, 'DON-02-2026-0007', 1, 2, 7, 1, '2026-09-26 09:20:00', 450, 12.7, 'QUALIFIE', 7, NULL, '2026-09-26 09:20:00'),
  (8, 'DON-02-2026-0008', 1, 2, 8, 1, '2026-09-26 11:20:00', 250, 13.1, 'QUALIFIE', 7, NULL, '2026-09-26 11:20:00'),
  (9, 'DON-02-2026-0009', 4, 2, 9, 2, '2026-09-25 10:20:00', 350, 12.5, 'QUALIFIE', 7, NULL, '2026-09-25 10:20:00'),
  (10, 'DON-02-2026-0010', 4, 2, 10, 2, '2026-09-25 08:20:00', 250, 13.1, 'QUALIFIE', 7, NULL, '2026-09-25 08:20:00'),
  (11, 'DON-02-2026-0011', 2, 2, 11, 3, '2026-09-30 11:40:00', 450, 14.8, 'QUALIFIE', 7, NULL, '2026-09-30 11:40:00'),
  (12, 'DON-02-2026-0012', 3, 2, 12, 5, '2026-09-25 10:05:00', 250, 16.0, 'QUALIFIE', 7, NULL, '2026-09-25 10:05:00'),
  (13, 'DON-01-2026-0013', 3, 1, 13, NULL, '2026-09-26 09:30:00', 450, 13.9, 'REJETE', 6, 'Produit ecarte apres analyses', '2026-09-26 09:30:00'),
  (14, 'DON-02-2026-0014', 5, 2, 14, NULL, '2026-09-28 09:30:00', 450, 13.2, 'REJETE', 7, 'Produit ecarte apres analyses', '2026-09-28 09:30:00'),
  (15, 'DON-01-2026-0015', 8, 1, 15, 1, '2026-09-30 10:10:00', 450, 15.0, 'EN_QUALIFICATION', 6, 'Echantillons transmis au laboratoire', '2026-09-30 10:10:00'),
  (16, 'DON-02-2026-0016', 7, 2, 16, NULL, '2026-09-30 10:10:00', 450, 12.9, 'EN_QUALIFICATION', 7, 'Echantillons transmis au laboratoire', '2026-09-30 10:10:00');

INSERT INTO `qualification` (`id`, `don_id`, `poche_id`, `personnel_id`, `groupe_sanguin_id`, `resultat`, `vih`, `hepatite_b`, `hepatite_c`, `syphilis`, `motif_rejet`, `date_qualification`) VALUES
  (1, 1, 7, 9, 1, 'CONFORME', 'NEGATIF', 'NEGATIF', 'NEGATIF', 'NEGATIF', NULL, '2026-09-25 10:05:00'),
  (2, 2, 8, 9, 1, 'CONFORME', 'NEGATIF', 'NEGATIF', 'NEGATIF', 'NEGATIF', NULL, '2026-09-29 10:05:00'),
  (3, 3, 9, 9, 1, 'CONFORME', 'NEGATIF', 'NEGATIF', 'NEGATIF', 'NEGATIF', NULL, '2026-09-27 12:05:00'),
  (4, 4, 12, 9, 1, 'CONFORME', 'NEGATIF', 'NEGATIF', 'NEGATIF', 'NEGATIF', NULL, '2026-09-25 11:20:00'),
  (5, 5, 13, 9, 1, 'CONFORME', 'NEGATIF', 'NEGATIF', 'NEGATIF', 'NEGATIF', NULL, '2026-09-25 12:40:00'),
  (6, 6, 25, 9, 2, 'CONFORME', 'NEGATIF', 'NEGATIF', 'NEGATIF', 'NEGATIF', NULL, '2026-09-30 10:20:00'),
  (7, 7, 109, 10, 1, 'CONFORME', 'NEGATIF', 'NEGATIF', 'NEGATIF', 'NEGATIF', NULL, '2026-09-27 11:20:00'),
  (8, 8, 112, 10, 1, 'CONFORME', 'NEGATIF', 'NEGATIF', 'NEGATIF', 'NEGATIF', NULL, '2026-09-27 13:20:00'),
  (9, 9, 118, 10, 2, 'CONFORME', 'NEGATIF', 'NEGATIF', 'NEGATIF', 'NEGATIF', NULL, '2026-09-26 12:20:00'),
  (10, 10, 121, 10, 2, 'CONFORME', 'NEGATIF', 'NEGATIF', 'NEGATIF', 'NEGATIF', NULL, '2026-09-26 10:20:00'),
  (11, 11, 128, 10, 3, 'CONFORME', 'NEGATIF', 'NEGATIF', 'NEGATIF', 'NEGATIF', NULL, '2026-10-01 13:40:00'),
  (12, 12, 138, 10, 5, 'CONFORME', 'NEGATIF', 'NEGATIF', 'NEGATIF', 'NEGATIF', NULL, '2026-09-26 12:05:00'),
  (13, 13, NULL, 9, NULL, 'NON_CONFORME', 'NEGATIF', 'POSITIF', 'NEGATIF', 'NEGATIF', 'Serologie hepatite B positive : produit detruit, donneur oriente vers une consultation medicale', '2026-09-27 12:30:00'),
  (14, 14, NULL, 10, NULL, 'NON_CONFORME', 'NEGATIF', 'NEGATIF', 'NEGATIF', 'POSITIF', 'Serologie syphilis positive : produit detruit selon la procedure du laboratoire', '2026-09-29 12:30:00');


-- =====================================================================
--  VUES DE CONFORT (lecture seule, pratiques pour les écrans et les tests)
-- =====================================================================

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
WHERE b.`statut` = 'ACTIVE';

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
WHERE p.`statut` IN ('EN_ATTENTE','CONFIRMEE','PRETE');

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
  AND c.`places_reservees` < c.`capacite`;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
--  REQUETES DE VERIFICATION (a executer apres l'import)
--
--  -- Banques les plus proches de Cotonou centre ayant du O- disponible :
--  SELECT b.nom, s.quantite_disponible,
--         ROUND(6371 * ACOS(LEAST(1, COS(RADIANS(6.3703)) * COS(RADIANS(b.latitude))
--              * COS(RADIANS(b.longitude) - RADIANS(2.3912))
--              + SIN(RADIANS(6.3703)) * SIN(RADIANS(b.latitude)))), 2) AS distance_km
--  FROM banque b
--  JOIN stock s ON s.banque_id = b.id
--  JOIN groupe_sanguin g ON g.id = s.groupe_sanguin_id
--  WHERE g.code = 'O-' AND s.quantite_disponible > 0 AND b.statut = 'ACTIVE'
--  ORDER BY distance_km LIMIT 5;
--
--  -- Groupes compatibles avec un receveur A+ :
--  SELECT gd.code FROM compatibilite c
--  JOIN groupe_sanguin gr ON gr.id = c.groupe_receveur_id
--  JOIN groupe_sanguin gd ON gd.id = c.groupe_donneur_id
--  WHERE gr.code = 'A+';
-- =====================================================================
