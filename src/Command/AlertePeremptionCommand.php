<?php

namespace App\Command;

use App\Repository\ParametreRepository;
use App\Repository\PocheRepository;
use App\Service\SmsNotifier;
use App\Service\StockService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * À lancer une fois par jour (tâche planifiée Windows ou cron).
 *
 * Deux effets : on sort du stock les poches périmées, et on prévient les
 * gestionnaires des poches qui périment bientôt pour qu'elles soient utilisées
 * en priorité. Du sang jeté est un don perdu.
 */
#[AsCommand(
    name: 'app:alerte-peremption',
    description: 'Marque les poches périmées et alerte sur celles qui vont périmer.',
)]
class AlertePeremptionCommand extends Command
{
    public function __construct(
        private readonly PocheRepository $poches,
        private readonly StockService $stocks,
        private readonly SmsNotifier $sms,
        private readonly ParametreRepository $parametres,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('jours', null, InputOption::VALUE_REQUIRED,
                'Fenêtre d’alerte en jours', null)
            ->addOption('sans-sms', null, InputOption::VALUE_NONE,
                'Ne pas envoyer les SMS (simulation)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $jours = (int) ($input->getOption('jours')
            ?: $this->parametres->entier('alerte_peremption_jours', 7));

        $perimees = $this->stocks->marquerPerimees();

        if ($perimees > 0) {
            $io->warning(sprintf('%d poche(s) périmée(s) sortie(s) du stock.', $perimees));
        }

        $bientot = $this->poches->bientotPerimees($jours);

        if (!$bientot) {
            $io->success(sprintf('Aucune poche ne périme dans les %d prochains jours.', $jours));

            return Command::SUCCESS;
        }

        $parBanque = [];
        foreach ($bientot as $poche) {
            $parBanque[$poche->getBanque()->getId()]['banque'] = $poche->getBanque();
            $parBanque[$poche->getBanque()->getId()]['poches'][] = $poche;
        }

        $lignes = [];
        foreach ($parBanque as $entree) {
            $banque = $entree['banque'];
            $nombre = \count($entree['poches']);
            $lignes[] = [$banque->getNom(), $nombre,
                $entree['poches'][0]->getDatePeremption()->format('d/m/Y')];

            if (!$input->getOption('sans-sms')) {
                foreach ($banque->getUtilisateurs() as $utilisateur) {
                    if ($utilisateur->getTelephone() && $utilisateur->isActif()) {
                        $this->sms->alertePeremption($utilisateur->getTelephone(), $nombre, $jours);
                        break;
                    }
                }
            }
        }

        $io->table(['Banque', 'Poches concernées', 'Première péremption'], $lignes);
        $io->success(sprintf('%d poche(s) à utiliser en priorité dans %d banque(s).',
            \count($bientot), \count($parBanque)));

        return Command::SUCCESS;
    }
}
