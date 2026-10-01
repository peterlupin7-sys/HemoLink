<?php

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Sauvegarde de la base, sans dépendre de mysqldump.
 *
 * Pourquoi ne pas appeler mysqldump ? Parce qu'il n'est pas toujours dans le PATH
 * sous XAMPP Windows, et qu'une sauvegarde qui échoue silencieusement ne sert à
 * rien. On écrit donc le script nous-mêmes, en SQL portable.
 */
#[AsCommand(
    name: 'app:sauvegarde',
    description: 'Exporte la base dans un fichier SQL horodaté.',
)]
class SauvegardeCommand extends Command
{
    public function __construct(
        private readonly Connection $connexion,
        private readonly string $racineProjet,
        private readonly string $dossierSauvegarde,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dossier', null, InputOption::VALUE_REQUIRED,
                'Dossier de destination', null)
            ->addOption('garder', null, InputOption::VALUE_REQUIRED,
                'Nombre de sauvegardes à conserver', '14');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $fs = new Filesystem();

        $dossier = $input->getOption('dossier')
            ?: $this->racineProjet.'/'.$this->dossierSauvegarde;
        $fs->mkdir($dossier);

        $fichier = sprintf('%s/hemolink_%s.sql', $dossier, date('Y-m-d_His'));
        $flux = fopen($fichier, 'wb');

        if (false === $flux) {
            $io->error('Impossible d’écrire dans '.$dossier);

            return Command::FAILURE;
        }

        fwrite($flux, "-- Sauvegarde HemoLink du ".date('d/m/Y H:i')."\n");
        fwrite($flux, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n");

        $tables = $this->connexion->createSchemaManager()->listTableNames();
        $total = 0;

        foreach ($tables as $table) {
            $creation = $this->connexion
                ->fetchAssociative(sprintf('SHOW CREATE TABLE `%s`', $table));

            fwrite($flux, sprintf("DROP TABLE IF EXISTS `%s`;\n", $table));
            fwrite($flux, ($creation['Create Table'] ?? '').";\n\n");

            $lignes = $this->connexion->iterateAssociative(sprintf('SELECT * FROM `%s`', $table));
            $paquet = [];

            foreach ($lignes as $ligne) {
                $valeurs = array_map(
                    fn ($v) => null === $v ? 'NULL' : $this->connexion->quote((string) $v),
                    $ligne
                );
                $paquet[] = '('.implode(', ', $valeurs).')';
                ++$total;

                if (\count($paquet) >= 100) {
                    $this->ecrireLot($flux, $table, array_keys($ligne), $paquet);
                    $paquet = [];
                }
            }

            if ($paquet) {
                $this->ecrireLot($flux, $table, array_keys($ligne ?? []), $paquet);
            }
        }

        fwrite($flux, "SET FOREIGN_KEY_CHECKS = 1;\n");
        fclose($flux);

        $this->purger($dossier, (int) $input->getOption('garder'));

        $io->success(sprintf('Sauvegarde écrite : %s (%d lignes, %s)',
            $fichier, $total, $this->taille($fichier)));

        return Command::SUCCESS;
    }

    /**
     * @param resource            $flux
     * @param array<int, string>  $colonnes
     * @param array<int, string>  $valeurs
     */
    private function ecrireLot($flux, string $table, array $colonnes, array $valeurs): void
    {
        if (!$colonnes || !$valeurs) {
            return;
        }

        fwrite($flux, sprintf("INSERT INTO `%s` (`%s`) VALUES\n%s;\n\n",
            $table, implode('`, `', $colonnes), implode(",\n", $valeurs)));
    }

    private function purger(string $dossier, int $garder): void
    {
        $fichiers = glob($dossier.'/hemolink_*.sql') ?: [];
        rsort($fichiers);

        foreach (\array_slice($fichiers, max(1, $garder)) as $vieux) {
            @unlink($vieux);
        }
    }

    private function taille(string $fichier): string
    {
        $octets = filesize($fichier) ?: 0;

        return $octets > 1048576
            ? round($octets / 1048576, 1).' Mo'
            : round($octets / 1024).' ko';
    }
}
