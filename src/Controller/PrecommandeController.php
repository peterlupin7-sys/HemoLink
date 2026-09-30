<?php

namespace App\Controller;

use App\Entity\Banque;
use App\Entity\Precommande;
use App\Form\PrecommandeType;
use App\Service\PrecommandeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Dépôt d'une précommande par un établissement ou un particulier.
 *
 * Aucun compte n'est demandé : dans l'urgence, créer un compte est un obstacle
 * de trop. Le numéro de précommande sert de clé de suivi.
 */
class PrecommandeController extends AbstractController
{
    #[Route('/banque/{id}/precommande', name: 'precommande_nouvelle',
        requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function nouvelle(
        Banque $banque,
        Request $requete,
        PrecommandeService $precommandes,
    ): Response {
        if (!$banque->estActive()) {
            $this->addFlash('erreur', 'Cette banque n’accepte pas de précommande pour le moment.');

            return $this->redirectToRoute('banque_fiche', ['id' => $banque->getId()]);
        }

        $precommande = (new Precommande())
            ->setBanque($banque)
            ->setDateSouhaitee(new \DateTimeImmutable('tomorrow'));

        $formulaire = $this->createForm(PrecommandeType::class, $precommande);
        $formulaire->handleRequest($requete);

        if ($formulaire->isSubmitted() && $formulaire->isValid()) {
            $precommandes->deposer($precommande);

            $this->addFlash('succes', sprintf(
                'Précommande enregistrée sous le numéro %s. Notez-le : il suffit à suivre votre demande.',
                $precommande->getNumero()
            ));

            return $this->redirectToRoute('suivi_detail', ['numero' => $precommande->getNumero()]);
        }

        return $this->render('precommande/nouvelle.html.twig', [
            'banque' => $banque,
            'formulaire' => $formulaire,
        ]);
    }
}
