<?php

namespace App\Command;

use App\Entity\Utilisateur;
use App\Repository\BanqueRepository;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Création d'un compte en ligne de commande.
 *
 * Volontairement hors de l'interface web : le mot de passe est saisi à l'écran
 * par la personne concernée, jamais envoyé par SMS ni noté sur un papier.
 */
#[AsCommand(
    name: 'app:creer-utilisateur',
    description: 'Crée un compte (admin, gestionnaire, agent, personnel habilité ou donneur).',
)]
class CreerUtilisateurCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UtilisateurRepository $utilisateurs,
        private readonly BanqueRepository $banques,
        private readonly UserPasswordHasherInterface $hacheur,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Adresse électronique')
            ->addArgument('role', InputArgument::REQUIRED,
                'ADMIN, GESTIONNAIRE, AGENT, PERSONNEL ou DONNEUR')
            ->addOption('nom', null, InputOption::VALUE_REQUIRED, 'Nom de famille')
            ->addOption('prenom', null, InputOption::VALUE_REQUIRED, 'Prénom')
            ->addOption('telephone', null, InputOption::VALUE_REQUIRED, 'Téléphone')
            ->addOption('banque', null, InputOption::VALUE_REQUIRED,
                'Code de la banque (obligatoire sauf pour ADMIN et DONNEUR)')
            ->addOption('mot-de-passe', null, InputOption::VALUE_REQUIRED,
                'Mot de passe (sinon demandé de façon masquée)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $email = mb_strtolower(trim((string) $input->getArgument('email')));
        $role = 'ROLE_'.strtoupper((string) $input->getArgument('role'));

        if (!\in_array($role, [Utilisateur::ROLE_ADMIN, Utilisateur::ROLE_GESTIONNAIRE,
            Utilisateur::ROLE_AGENT, Utilisateur::ROLE_PERSONNEL,
            Utilisateur::ROLE_DONNEUR], true)) {
            $io->error('Rôle inconnu : utilisez ADMIN, GESTIONNAIRE, AGENT, PERSONNEL ou DONNEUR.');

            return Command::INVALID;
        }

        if ($this->utilisateurs->parEmail($email)) {
            $io->error('Un compte existe déjà avec cette adresse.');

            return Command::FAILURE;
        }

        // L'administrateur est national, le donneur n'appartient à personne :
        // ni l'un ni l'autre n'est rattaché à une banque.
        $banque = null;
        if (!\in_array($role, [Utilisateur::ROLE_ADMIN, Utilisateur::ROLE_DONNEUR], true)) {
            $code = $input->getOption('banque');

            if (!$code) {
                $io->error('Un gestionnaire, un agent ou un personnel habilité doit être '
                    .'rattaché à une banque (--banque=BQ-COT-001).');

                return Command::INVALID;
            }

            $banque = $this->banques->findOneBy(['code' => $code]);

            if (!$banque) {
                $io->error('Aucune banque ne porte le code '.$code);

                return Command::FAILURE;
            }
        }

        $motDePasse = $input->getOption('mot-de-passe');

        if (!$motDePasse) {
            $question = (new Question('Mot de passe : '))->setHidden(true);
            $motDePasse = (string) $io->askQuestion($question);
        }

        if (mb_strlen($motDePasse) < 10) {
            $io->error('Le mot de passe doit faire au moins 10 caractères.');

            return Command::INVALID;
        }

        $utilisateur = (new Utilisateur())
            ->setEmail($email)
            ->setRoles([$role])
            ->setNom(strtoupper((string) ($input->getOption('nom') ?? 'À COMPLÉTER')))
            ->setPrenom((string) ($input->getOption('prenom') ?? 'À compléter'))
            ->setTelephone($input->getOption('telephone'))
            ->setBanque($banque)
            ->setActif(true)
            ->setDateCreation(new \DateTimeImmutable());

        $utilisateur->setMotDePasse($this->hacheur->hashPassword($utilisateur, $motDePasse));

        $this->em->persist($utilisateur);
        $this->em->flush();

        $io->success(sprintf('Compte créé : %s (%s)%s', $email, $role,
            $banque ? ' — '.$banque->getNom() : ''));

        return Command::SUCCESS;
    }
}
