<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    #[Route('/connexion', name: 'connexion', methods: ['GET', 'POST'])]
    public function connexion(AuthenticationUtils $utils): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('apres_connexion');
        }

        return $this->render('securite/connexion.html.twig', [
            'dernier_email' => $utils->getLastUsername(),
            'erreur' => $utils->getLastAuthenticationError(),
        ]);
    }

    /**
     * Aiguillage après connexion.
     *
     * Les comptes n'ont pas le même métier : un donneur n'a rien à faire sur le
     * tableau de bord d'une banque, et le laboratoire veut sa file de dons, pas
     * la liste des précommandes. On envoie donc chacun chez lui.
     */
    #[Route('/apres-connexion', name: 'apres_connexion', methods: ['GET'])]
    public function apresConnexion(): Response
    {
        if ($this->isGranted('ROLE_AGENT')) {
            return $this->redirectToRoute('tableau_bord');
        }

        if ($this->isGranted('ROLE_PERSONNEL')) {
            return $this->redirectToRoute('qualification');
        }

        if ($this->isGranted('ROLE_DONNEUR')) {
            return $this->redirectToRoute('espace_donneur');
        }

        return $this->redirectToRoute('carte');
    }

    #[Route('/deconnexion', name: 'deconnexion', methods: ['GET'])]
    public function deconnexion(): never
    {
        throw new \LogicException('Cette méthode est interceptée par le pare-feu de Symfony.');
    }
}
