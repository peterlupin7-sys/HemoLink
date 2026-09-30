<?php

namespace App\Controller;

use App\Repository\GroupeSanguinRepository;
use App\Service\GeolocalisationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * La carte publique : trouver une banque qui a le bon groupe, tout de suite.
 *
 * Accessible sans compte — c'est le cœur de l'utilité de la plateforme.
 */
class CarteController extends AbstractController
{
    public function __construct(
        private readonly GeolocalisationService $geolocalisation,
        private readonly GroupeSanguinRepository $groupes,
    ) {
    }

    #[Route('/', name: 'carte', methods: ['GET'])]
    public function carte(Request $requete): Response
    {
        $groupe = $requete->query->get('groupe');
        $groupeSanguin = $groupe ? $this->groupes->parCode($groupe) : null;

        $latitude = $requete->query->has('lat') ? (float) $requete->query->get('lat') : null;
        $longitude = $requete->query->has('lon') ? (float) $requete->query->get('lon') : null;
        $rayon = $requete->query->has('rayon') ? (float) $requete->query->get('rayon') : null;

        $resultats = $this->geolocalisation->rechercher(
            $latitude, $longitude, $groupeSanguin?->getId(), $rayon
        );

        return $this->render('carte/index.html.twig', [
            'resultats' => $resultats,
            'groupes' => $this->groupes->tousOrdonnes(),
            'groupeChoisi' => $groupeSanguin,
            'rayon' => $rayon ?? 25,
            'centre' => $this->geolocalisation->centreParDefaut(),
        ]);
    }

    /**
     * Même recherche, en JSON : c'est ce que la carte Leaflet appelle quand
     * l'utilisateur déplace la carte ou change de groupe, sans recharger la page.
     */
    #[Route('/api/banques', name: 'api_banques', methods: ['GET'])]
    public function api(Request $requete): JsonResponse
    {
        $groupe = $requete->query->get('groupe');
        $groupeSanguin = $groupe ? $this->groupes->parCode($groupe) : null;

        $resultats = $this->geolocalisation->rechercher(
            $requete->query->has('lat') ? (float) $requete->query->get('lat') : null,
            $requete->query->has('lon') ? (float) $requete->query->get('lon') : null,
            $groupeSanguin?->getId(),
            $requete->query->has('rayon') ? (float) $requete->query->get('rayon') : null,
        );

        $donnees = array_map(static function (array $ligne): array {
            $banque = $ligne['banque'];

            return [
                'id' => $banque->getId(),
                'nom' => $banque->getNom(),
                'ville' => $banque->getVille(),
                'adresse' => $banque->getAdresse(),
                'telephone' => $banque->getTelephone(),
                'latitude' => $banque->getLatitude(),
                'longitude' => $banque->getLongitude(),
                'distance' => $ligne['distance'],
                'quantite' => $ligne['quantite'],
                'etat' => $ligne['etat'],
                'url' => '/banque/'.$banque->getId(),
            ];
        }, $resultats);

        return $this->json(['banques' => $donnees, 'total' => \count($donnees)]);
    }
}
