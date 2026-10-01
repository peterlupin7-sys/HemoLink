<?php

namespace App\Tests\Service;

use App\Repository\BanqueRepository;
use App\Repository\ParametreRepository;
use App\Service\GeolocalisationService;
use PHPUnit\Framework\TestCase;

class GeolocalisationServiceTest extends TestCase
{
    private GeolocalisationService $service;

    protected function setUp(): void
    {
        $this->service = new GeolocalisationService(
            $this->createMock(BanqueRepository::class),
            $this->createMock(ParametreRepository::class),
            6.3654,
            2.4183,
        );
    }

    public function testDistanceNulleEntreUnPointEtLuiMeme(): void
    {
        self::assertSame(0.0, $this->service->distance(6.3654, 2.4183, 6.3654, 2.4183));
    }

    /**
     * CNTS Cotonou -> CNHU-HKM : deux établissements voisins, environ 3 km.
     */
    public function testDistanceEntreLeCntsEtLeCnhu(): void
    {
        $distance = $this->service->distance(6.3654, 2.4183, 6.3703, 2.3912);

        self::assertGreaterThan(2.5, $distance);
        self::assertLessThan(3.5, $distance);
    }

    /**
     * Cotonou -> Parakou : environ 330 km à vol d'oiseau.
     */
    public function testDistanceCotonouParakou(): void
    {
        $distance = $this->service->distance(6.3654, 2.4183, 9.3372, 2.6303);

        self::assertGreaterThan(300, $distance);
        self::assertLessThan(360, $distance);
    }

    public function testLaDistanceEstSymetrique(): void
    {
        self::assertSame(
            $this->service->distance(6.3654, 2.4183, 6.4969, 2.6036),
            $this->service->distance(6.4969, 2.6036, 6.3654, 2.4183),
        );
    }

    public function testLeCentreParDefautEstCotonou(): void
    {
        self::assertSame(
            ['latitude' => 6.3654, 'longitude' => 2.4183],
            $this->service->centreParDefaut(),
        );
    }
}
