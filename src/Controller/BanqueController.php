<?php

namespace App\Controller;

use App\Entity\Banque;
use App\Repository\StockRepository;
use App\Service\CompatibiliteService;
use App\Service\GeolocalisationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Fiche publique d'une banque : coordonnées, horaires et stock par groupe.
 */
class BanqueController extends AbstractController
{
    #[Route('/banque/{id}', name: 'banque_fiche', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function fiche(
        Banque $banque,
        Request $requete,
        StockRepository $stocks,
        GeolocalisationService $geolocalisation,
        CompatibiliteService $compatibilite,
    ): Response {
        $distance = null;

        if ($requete->query->has('lat') && $requete->query->has('lon')) {
            $distance = $geolocalisation->distance(
                (float) $requete->query->get('lat'),
                (float) $requete->query->get('lon'),
                $banque->getLatitude(),
                $banque->getLongitude(),
            );
        }

        $lignes = [];
        foreach ($stocks->pourBanque($banque->getId()) as $stock) {
            $lignes[] = [
                'stock' => $stock,
                'substituts' => 0 === $stock->getQuantiteDisponible()
                    ? $compatibilite->substituts($stock->getGroupeSanguin())
                    : [],
            ];
        }

        return $this->render('banque/fiche.html.twig', [
            'banque' => $banque,
            'lignes' => $lignes,
            'distance' => $distance,
        ]);
    }
}
