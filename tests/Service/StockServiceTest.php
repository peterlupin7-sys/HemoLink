<?php

namespace App\Tests\Service;

use App\Entity\Stock;
use App\Repository\ParametreRepository;
use App\Repository\PocheRepository;
use App\Repository\StockRepository;
use App\Service\StockService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Règle de gestion n°2 : l'état d'un stock n'est jamais saisi à la main,
 * il se déduit de la quantité et de la fraîcheur de la mise à jour.
 */
class StockServiceTest extends TestCase
{
    private StockService $service;

    protected function setUp(): void
    {
        $parametres = $this->createMock(ParametreRepository::class);
        $parametres->method('entier')->willReturnCallback(
            static fn (string $cle, int $defaut = 0) => match ($cle) {
                'seuil_tension' => 5,
                'delai_stock_obsolete_h' => 24,
                default => $defaut,
            }
        );

        $this->service = new StockService(
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(StockRepository::class),
            $this->createMock(PocheRepository::class),
            $parametres,
        );
    }

    private function maintenant(): \DateTimeImmutable
    {
        return new \DateTimeImmutable();
    }

    public function testZeroPocheDonneUneRupture(): void
    {
        self::assertSame(Stock::RUPTURE, $this->service->calculerEtat(0, $this->maintenant()));
    }

    public function testEnDessousDuSeuilLeStockEstEnTension(): void
    {
        self::assertSame(Stock::TENSION, $this->service->calculerEtat(1, $this->maintenant()));
        self::assertSame(Stock::TENSION, $this->service->calculerEtat(4, $this->maintenant()));
    }

    public function testAuSeuilLeStockEstDisponible(): void
    {
        self::assertSame(Stock::DISPONIBLE, $this->service->calculerEtat(5, $this->maintenant()));
        self::assertSame(Stock::DISPONIBLE, $this->service->calculerEtat(42, $this->maintenant()));
    }

    public function testUneDonneeTropAncienneEstMarqueeNonCommuniquee(): void
    {
        $etat = $this->service->calculerEtat(30, new \DateTimeImmutable('-30 hours'));

        self::assertSame(Stock::NON_COMMUNIQUE, $etat,
            'Un chiffre vieux de plus de 24 h ne doit pas être présenté comme fiable.');
    }

    public function testEstObsoleteSuitLeMemeDelai(): void
    {
        $stock = (new Stock())
            ->setQuantiteDisponible(12)
            ->setDateMaj(new \DateTimeImmutable('-30 hours'));

        self::assertTrue($stock->estObsolete(24));

        $stock->setDateMaj(new \DateTimeImmutable('-2 hours'));

        self::assertFalse($stock->estObsolete(24));
    }

    public function testChaqueEtatPossedeUnLibelleLisible(): void
    {
        foreach (array_keys(Stock::LIBELLES) as $etat) {
            $stock = (new Stock())->setEtat($etat);

            self::assertNotSame($etat, $stock->getLibelleEtat());
        }
    }
}
