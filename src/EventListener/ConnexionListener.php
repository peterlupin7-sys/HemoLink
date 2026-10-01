<?php

namespace App\EventListener;

use App\Entity\Utilisateur;
use App\Service\JournalService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * Enregistre la dernière connexion et la trace dans le journal.
 */
#[AsEventListener(event: LoginSuccessEvent::class)]
class ConnexionListener
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly JournalService $journal,
    ) {
    }

    public function __invoke(LoginSuccessEvent $evenement): void
    {
        $utilisateur = $evenement->getUser();

        if (!$utilisateur instanceof Utilisateur) {
            return;
        }

        $utilisateur->setDerniereConnexion(new \DateTimeImmutable());
        $this->em->flush();

        $this->journal->tracer('CONNEXION', 'Utilisateur', $utilisateur->getId(),
            'Connexion de '.$utilisateur->getEmail(), $utilisateur);
    }
}
