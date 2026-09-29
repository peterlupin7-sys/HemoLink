<?php

namespace App\Service;

use App\Entity\Journal;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Journal des actions sensibles : qui a fait quoi, quand, depuis quelle adresse.
 *
 * Sans cette table, un stock qui change sans explication reste un mystère et la
 * plateforme perd la confiance des banques.
 */
class JournalService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RequestStack $requetes,
        private readonly Security $securite,
    ) {
    }

    public function tracer(
        string $action,
        ?string $entite = null,
        ?int $entiteId = null,
        ?string $details = null,
        ?Utilisateur $auteur = null,
    ): Journal {
        $utilisateur = $auteur ?? $this->utilisateurCourant();

        $journal = (new Journal())
            ->setUtilisateur($utilisateur)
            ->setAction($action)
            ->setEntite($entite)
            ->setEntiteId($entiteId)
            ->setDetails($details ? mb_substr($details, 0, 255) : null)
            ->setAdresseIp($this->requetes->getCurrentRequest()?->getClientIp())
            ->setDateAction(new \DateTimeImmutable());

        $this->em->persist($journal);
        $this->em->flush();

        return $journal;
    }

    private function utilisateurCourant(): ?Utilisateur
    {
        $utilisateur = $this->securite->getUser();

        return $utilisateur instanceof Utilisateur ? $utilisateur : null;
    }
}
