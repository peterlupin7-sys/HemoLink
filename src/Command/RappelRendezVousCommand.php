<?php

namespace App\Command;

use App\Entity\CreneauDon;
use App\Entity\RendezVousDon;
use App\Repository\CreneauDonRepository;
use App\Service\SmsNotifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Rappel SMS des rendez-vous de don du lendemain.
 *
 * Sur le terrain, l'absence au rendez-vous est le premier gaspillage d'une
 * collecte : le personnel et le matériel sont mobilisés pour rien. Un SMS la
 * veille suffit à réduire fortement ces absences, et laisse au donneur le
 * temps d'annuler pour rendre sa place.
 *
 * À planifier une fois par jour, en fin d'après-midi.
 */
#[AsCommand(
    name: 'app:rappel-rendez-vous',
    description: 'Envoie un SMS de rappel aux donneurs attendus le lendemain.',
)]
class RappelRendezVousCommand extends Command
{
    public function __construct(
        private readonly CreneauDonRepository $creneaux,
        private readonly SmsNotifier $sms,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('jours', null, InputOption::VALUE_REQUIRED,
                'Nombre de jours avant le rendez-vous', 1)
            ->addOption('simulation', null, InputOption::VALUE_NONE,
                'Affiche les rappels sans envoyer de SMS');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $jours = max(0, (int) $input->getOption('jours'));
        $simulation = (bool) $input->getOption('simulation');

        $cible = (new \DateTimeImmutable('today'))->modify(sprintf('+%d days', $jours));

        $creneaux = $this->em->getRepository(CreneauDon::class)->findBy([
            'dateCreneau' => $cible,
            'statut' => [CreneauDon::OUVERT, CreneauDon::COMPLET],
        ]);

        $envoyes = 0;
        $lignes = [];

        foreach ($creneaux as $creneau) {
            foreach ($creneau->getRendezVous() as $rendezVous) {
                if (RendezVousDon::PLANIFIE !== $rendezVous->getStatut()) {
                    continue;
                }

                $donneur = $rendezVous->getDonneur();

                if (!$donneur) {
                    continue;
                }

                $lignes[] = [
                    $rendezVous->getNumero(),
                    $donneur->getNomComplet(),
                    $creneau->getHoraire(),
                    $creneau->getBanque()?->getNom() ?? '',
                ];

                if (!$simulation) {
                    $this->sms->mettreEnFile($donneur->getTelephone(), sprintf(
                        'HemoLink : rappel de votre don demain %s a %s - %s. Mangez avant de '
                        .'venir et apportez une piece d identite. Empeche ? Annulez sur '
                        .'hemolink.bj pour liberer la place.',
                        $creneau->getDateCreneau()->format('d/m'),
                        substr($creneau->getHeureDebut(), 0, 5),
                        $creneau->getBanque()?->getNom() ?? ''
                    ), 'RAPPEL');
                    ++$envoyes;
                }
            }
        }

        if (!$lignes) {
            $io->success(sprintf('Aucun rendez-vous le %s : rien à rappeler.',
                $cible->format('d/m/Y')));

            return Command::SUCCESS;
        }

        $io->table(['Référence', 'Donneur', 'Horaire', 'Banque'], $lignes);

        if ($simulation) {
            $io->note(sprintf('Simulation : %d rappel(s) auraient été envoyés.',
                \count($lignes)));

            return Command::SUCCESS;
        }

        $io->success(sprintf('%d rappel(s) mis en file pour le %s.',
            $envoyes, $cible->format('d/m/Y')));

        return Command::SUCCESS;
    }
}
