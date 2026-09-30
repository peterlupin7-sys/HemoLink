<?php

namespace App\Controller\Gestion;

use App\Entity\Precommande;
use App\Entity\Utilisateur;
use App\Repository\MouvementStockRepository;
use App\Repository\PocheRepository;
use App\Repository\PrecommandeRepository;
use App\Repository\StockRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Tableau de bord de la banque : les huit indicateurs du cahier des charges.
 */
#[Route('/gestion')]
#[IsGranted('ROLE_AGENT')]
class TableauBordController extends AbstractController
{
    #[Route('', name: 'tableau_bord', methods: ['GET'])]
    public function index(
        StockRepository $stocks,
        PrecommandeRepository $precommandes,
        PocheRepository $poches,
        MouvementStockRepository $mouvements,
    ): Response {
        /** @var Utilisateur $utilisateur */
        $utilisateur = $this->getUser();
        $banque = $utilisateur->getBanque();

        if (!$banque) {
            return $this->redirectToRoute('administration_banques');
        }

        $lignesStock = $stocks->pourBanque($banque->getId());
        $totalPoches = 0;
        $groupesEnRupture = 0;

        foreach ($lignesStock as $stock) {
            $totalPoches += $stock->getQuantiteDisponible();
            if (0 === $stock->getQuantiteDisponible()) {
                ++$groupesEnRupture;
            }
        }

        $perimeesBientot = array_filter(
            $poches->bientotPerimees(7),
            static fn ($poche) => $poche->getBanque()->getId() === $banque->getId()
        );

        return $this->render('gestion/tableau_bord.html.twig', [
            'banque' => $banque,
            'stocks' => $lignesStock,
            'total_poches' => $totalPoches,
            'groupes_en_rupture' => $groupesEnRupture,
            'par_statut' => $precommandes->compterParStatut($banque->getId()),
            'precommandes_jour' => $precommandes->compterDepuis(
                new \DateTimeImmutable('today'), $banque->getId()
            ),
            'a_traiter' => $precommandes->pourBanque($banque->getId(), Precommande::EN_ATTENTE),
            'peremptions' => $perimeesBientot,
            'mouvements' => $mouvements->derniers($banque->getId(), 10),
        ]);
    }
}
