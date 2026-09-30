<?php

namespace App\Controller\Gestion;

use App\Entity\Utilisateur;
use App\Repository\GroupeSanguinRepository;
use App\Repository\StockRepository;
use App\Service\JournalService;
use App\Service\StockService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Saisie des stocks par l'agent.
 *
 * Écran pensé pour le téléphone : une ligne par groupe, un champ, un bouton.
 * Objectif du cahier des charges : mettre un stock à jour en moins d'une minute.
 */
#[Route('/gestion/stocks')]
#[IsGranted('ROLE_AGENT')]
class StockController extends AbstractController
{
    #[Route('', name: 'gestion_stocks', methods: ['GET'])]
    public function index(StockRepository $stocks, GroupeSanguinRepository $groupes): Response
    {
        /** @var Utilisateur $utilisateur */
        $utilisateur = $this->getUser();
        $banque = $utilisateur->getBanque();

        if (!$banque) {
            throw $this->createAccessDeniedException('Votre compte n’est rattaché à aucune banque.');
        }

        return $this->render('gestion/stocks.html.twig', [
            'banque' => $banque,
            'stocks' => $stocks->pourBanque($banque->getId()),
            'groupes' => $groupes->tousOrdonnes(),
        ]);
    }

    #[Route('/maj', name: 'gestion_stocks_maj', methods: ['POST'])]
    public function mettreAJour(
        Request $requete,
        GroupeSanguinRepository $groupes,
        StockService $stockService,
        JournalService $journal,
    ): Response {
        /** @var Utilisateur $utilisateur */
        $utilisateur = $this->getUser();
        $banque = $utilisateur->getBanque();

        if (!$banque) {
            throw $this->createAccessDeniedException('Votre compte n’est rattaché à aucune banque.');
        }

        if (!$this->isCsrfTokenValid('maj_stock', (string) $requete->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $quantites = $requete->request->all('quantite');
        $modifies = 0;

        foreach ($quantites as $groupeId => $valeur) {
            if ('' === $valeur || !is_numeric($valeur)) {
                continue;
            }

            $groupe = $groupes->find((int) $groupeId);
            if (!$groupe) {
                continue;
            }

            $stockService->ajuster($banque, $groupe, max(0, (int) $valeur), $utilisateur,
                'Saisie agent');
            ++$modifies;
        }

        $journal->tracer('MAJ_STOCK', 'Banque', $banque->getId(),
            sprintf('%d groupe(s) mis à jour', $modifies));

        $this->addFlash('succes', sprintf('Stock mis à jour pour %d groupe(s).', $modifies));

        return $this->redirectToRoute('gestion_stocks');
    }
}
