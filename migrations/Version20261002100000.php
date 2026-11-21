<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * File d'attente des SMS (transport Doctrine de Symfony Messenger).
 *
 * Le manque s'est vu au premier essai reel : `SmsNotifier` enregistre la
 * notification en base puis la pousse dans la file. Sans cette table, toute
 * action qui previent un demandeur ou un donneur echoue. Le transport est
 * declare avec `auto_setup=0` : la table fait donc partie du schema livre,
 * elle n'est pas creee a la volee en production.
 */
final class Version20261002100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'File des SMS : table messenger_messages.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
            'HemoLink utilise MySQL 8 ou MariaDB 10.4.'
        );

        $this->addSql(<<<'SQL'
            CREATE TABLE `messenger_messages` (
              `id`           BIGINT NOT NULL AUTO_INCREMENT,
              `body`         LONGTEXT NOT NULL,
              `headers`      LONGTEXT NOT NULL,
              `queue_name`   VARCHAR(190) NOT NULL,
              `created_at`   DATETIME NOT NULL,
              `available_at` DATETIME NOT NULL,
              `delivered_at` DATETIME DEFAULT NULL,
              PRIMARY KEY (`id`),
              KEY `idx_messenger_queue` (`queue_name`),
              KEY `idx_messenger_available` (`available_at`),
              KEY `idx_messenger_delivered` (`delivered_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

        $this->write('  File des SMS creee : messenger_messages.');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS `messenger_messages`');
    }
}
