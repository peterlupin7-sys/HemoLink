<?php

namespace App\Controller;

use App\Entity\Precommande;
use App\Repository\PrecommandeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Suivi public d'une précommande à partir de son numéro, et bon de retrait
 * imprimable (le document que l'on présente au guichet de la banque).
 */
class SuiviController extends AbstractController
{
    #[Route('/suivi', name: 'suivi', methods: ['GET'])]
    public function recherche(Request $requete, PrecommandeRepository $precommandes): Response
    {
        $numero = trim((string) $requete->query->get('numero'));

        if ('' !== $numero) {
            $precommande = $precommandes->findOneBy(['numero' => strtoupper($numero)]);

            if ($precommande) {
                return $this->redirectToRoute('suivi_detail', ['numero' => $precommande->getNumero()]);
            }

            $this->addFlash('erreur', 'Aucune précommande ne porte ce numéro. Vérifiez la saisie.');
        }

        return $this->render('suivi/recherche.html.twig', ['numero' => $numero]);
    }

    #[Route('/suivi/{numero}', name: 'suivi_detail', methods: ['GET'])]
    public function detail(string $numero, PrecommandeRepository $precommandes): Response
    {
        $precommande = $precommandes->findOneBy(['numero' => strtoupper($numero)]);

        if (!$precommande) {
            throw $this->createNotFoundException('Précommande introuvable.');
        }

        return $this->render('suivi/detail.html.twig', ['precommande' => $precommande]);
    }

    #[Route('/suivi/{numero}/bon', name: 'suivi_bon', methods: ['GET'])]
    public function bon(string $numero, PrecommandeRepository $precommandes): Response
    {
        $precommande = $precommandes->findOneBy(['numero' => strtoupper($numero)]);

        if (!$precommande) {
            throw $this->createNotFoundException('Précommande introuvable.');
        }

        if (\in_array($precommande->getStatut(), [Precommande::EN_ATTENTE, Precommande::ANNULEE,
            Precommande::EXPIREE], true)) {
            $this->addFlash('erreur', 'Le bon de retrait n’est disponible qu’une fois la précommande confirmée.');

            return $this->redirectToRoute('suivi_detail', ['numero' => $precommande->getNumero()]);
        }

        return $this->render('suivi/bon.html.twig', ['precommande' => $precommande]);
    }
}
