<?php

namespace App\Security\Voter;

use App\Entity\Precommande;
use App\Entity\Utilisateur;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Un gestionnaire ou un agent ne traite que les précommandes de SA banque.
 *
 * C'est la règle de sécurité la plus importante du projet : sans elle, n'importe
 * quel agent pourrait réserver le sang d'un autre établissement.
 */
class PrecommandeVoter extends Voter
{
    public const TRAITER = 'PRECOMMANDE_TRAITER';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::TRAITER === $attribute && $subject instanceof Precommande;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $utilisateur = $token->getUser();

        if (!$utilisateur instanceof Utilisateur || !$utilisateur->isActif()) {
            return false;
        }

        // L'administrateur national voit tout.
        if (\in_array(Utilisateur::ROLE_ADMIN, $utilisateur->getRoles(), true)) {
            return true;
        }

        $banque = $utilisateur->getBanque();

        /** @var Precommande $subject */
        return null !== $banque && $subject->getBanque()?->getId() === $banque->getId();
    }
}
