<?php

namespace App\Controller\Gestion;

use App\Entity\Precommande;
use App\Entity\Utilisateur;
use App\Exception\RegleMetier;
use App\Repository\PrecommandeRepository;
use App\Service\PrecommandeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Traitement des précommandes par la banque.
 */
#[Route('/gestion/precommandes')]
#[IsGranted('ROLE_AGENT')]
class PrecommandeGestionController extends AbstractController
{
    public function __construct(private readonly PrecommandeService $precommandes)
    {
    }

    #[Route('', name: 'gestion_precommandes', methods: ['GET'])]
    public function liste(Request $requete, PrecommandeRepository $depot): Response
    {
        /** @var Utilisateur $utilisateur */
        $utilisateur = $this->getUser();
        $banque = $utilisateur->getBanque();

        if (!$banque) {
            throw $this->createAccessDeniedException('Votre compte n’est rattaché à aucune banque.');
        }

        $statut = $requete->query->get('statut');

        return $this->render('gestion/precommandes.html.twig', [
            'banque' => $banque,
            'precommandes' => $depot->pourBanque($banque->getId(), $statut),
            'statut' => $statut,
            'par_statut' => $depot->compterParStatut($banque->getId()),
        ]);
    }

    #[Route('/{id}', name: 'gestion_precommande', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function detail(Precommande $precommande): Response
    {
        $this->denyAccessUnlessGranted('PRECOMMANDE_TRAITER', $precommande);

        return $this->render('gestion/precommande_detail.html.twig', [
            'precommande' => $precommande,
        ]);
    }

    #[Route('/{id}/{action}', name: 'gestion_precommande_action',
        requirements: ['id' => '\d+', 'action' => 'confirmer|prete|retirer|annuler'],
        methods: ['POST'])]
    public function action(Precommande $precommande, string $action, Request $requete): Response
    {
        $this->denyAccessUnlessGranted('PRECOMMANDE_TRAITER', $precommande);

        if (!$this->isCsrfTokenValid('precommande_'.$precommande->getId(),
            (string) $requete->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        /** @var Utilisateur $agent */
        $agent = $this->getUser();

        try {
            match ($action) {
                'confirmer' => $this->precommandes->confirmer($precommande, $agent),
                'prete' => $this->precommandes->marquerPrete($precommande, $agent),
                'retirer' => $this->precommandes->retirer($precommande, $agent),
                'annuler' => $this->precommandes->annuler(
                    $precommande,
                    trim((string) $requete->request->get('raison')) ?: 'Annulée par la banque',
                    $agent
                ),
            };

            $this->addFlash('succes', sprintf('Précommande %s : %s.',
                $precommande->getNumero(), strtolower($precommande->getLibelleStatut())));
        } catch (RegleMetier $e) {
            $this->addFlash('erreur', $e->getMessage());
        }

        return $this->redirectToRoute('gestion_precommande', ['id' => $precommande->getId()]);
    }
}
