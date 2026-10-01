<?php

namespace App\Command;

use App\Repository\ParametreRepository;
use App\Repository\PrecommandeRepository;
use App\Service\PrecommandeService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Libère les poches des précommandes jamais retirées.
 *
 * Sans cette commande, une précommande oubliée immobilise du sang que personne
 * ne viendra chercher, et la carte ment à ceux qui en ont besoin.
 */
#[AsCommand(
    name: 'app:expirer-precommandes',
    description: 'Expire les précommandes dépassées et remet leurs poches en stock.',
)]
class ExpirerPrecommandesCommand extends Command
{
    public function __construct(
        private readonly PrecommandeRepository $precommandes,
        private readonly PrecommandeService $service,
        private readonly ParametreRepository $parametres,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('heures', null, InputOption::VALUE_REQUIRED,
                'Délai de validité en heures', null)
            ->addOption('simulation', null, InputOption::VALUE_NONE,
                'Afficher sans rien modifier');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $heures = (int) ($input->getOption('heures')
            ?: $this->parametres->entier('duree_validite_precommande_h', 48));

        $concernees = $this->precommandes->aExpirer($heures);

        if (!$concernees) {
            $io->success('Aucune précommande à expirer.');

            return Command::SUCCESS;
        }

        $lignes = [];
        foreach ($concernees as $precommande) {
            $lignes[] = [
                $precommande->getNumero(),
                $precommande->getBanque()->getNom(),
                $precommande->getLibelleStatut(),
                $precommande->getDateCreation()->format('d/m/Y H:i'),
                $precommande->getQuantiteTotale(),
            ];

            if (!$input->getOption('simulation')) {
                $this->service->expirer($precommande);
            }
        }

        $io->table(['Numéro', 'Banque', 'Statut', 'Déposée le', 'Poches'], $lignes);

        if ($input->getOption('simulation')) {
            $io->note(sprintf('%d précommande(s) seraient expirées (simulation).',
                \count($concernees)));

            return Command::SUCCESS;
        }

        $io->success(sprintf('%d précommande(s) expirée(s), poches remises en stock.',
            \count($concernees)));

        return Command::SUCCESS;
    }
}
