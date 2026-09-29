<?php

namespace App\Service;

use App\Entity\Banque;
use App\Repository\BanqueRepository;
use App\Repository\ParametreRepository;

/**
 * Recherche des banques les plus proches.
 *
 * Le tri par distance est délégué à MySQL (formule de Haversine en SQL natif) :
 * charger toutes les banques en PHP pour les trier ensuite ne tiendrait pas
 * la charge et ferait ramer les téléphones.
 */
class GeolocalisationService
{
    public const RAYON_TERRE_KM = 6371.0;

    public function __construct(
        private readonly BanqueRepository $banques,
        private readonly ParametreRepository $parametres,
        private readonly float $carteLatitude,
        private readonly float $carteLongitude,
    ) {
    }

    /**
     * @return array<int, array{banque: Banque, distance: float, quantite: int, etat: string}>
     */
    public function rechercher(
        ?float $latitude,
        ?float $longitude,
        ?int $groupeSanguinId = null,
        ?float $rayonKm = null,
    ): array {
        $latitude ??= $this->carteLatitude;
        $longitude ??= $this->carteLongitude;
        $rayonKm ??= (float) $this->parametres->entier('rayon_recherche_km', 25);

        return $this->banques->trouverProches($latitude, $longitude, $groupeSanguinId, $rayonKm);
    }

    /**
     * Distance en kilomètres entre deux points (calcul de secours côté PHP,
     * utilisé par les tests et par l'affichage d'une fiche isolée).
     */
    public function distance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return round(self::RAYON_TERRE_KM * 2 * asin(min(1.0, sqrt($a))), 2);
    }

    /**
     * Coordonnées par défaut de la carte (Cotonou), quand le navigateur refuse
     * la géolocalisation ou qu'elle n'est pas disponible.
     *
     * @return array{latitude: float, longitude: float}
     */
    public function centreParDefaut(): array
    {
        return ['latitude' => $this->carteLatitude, 'longitude' => $this->carteLongitude];
    }
}
