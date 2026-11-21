<?php

namespace App\Tests\Entity;

use App\Entity\Donneur;
use PHPUnit\Framework\TestCase;

class DonneurTest extends TestCase
{
    private function donneur(?string $dernierDon, string $naissance = '1990-05-10'): Donneur
    {
        $donneur = (new Donneur())
            ->setNom('SOSSOU')
            ->setPrenom('Armand')
            ->setDateNaissance(new \DateTimeImmutable($naissance))
            ->setApte(true);

        if ($dernierDon) {
            $donneur->setDernierDon(new \DateTimeImmutable($dernierDon));
        }

        return $donneur;
    }

    public function testUnPremierDonneurEstImmediatementEligible(): void
    {
        $donneur = $this->donneur(null);

        self::assertSame(0, $donneur->joursAvantProchainDon());
        self::assertTrue($donneur->peutDonner());
    }

    public function testLeDelaiDeRecuperationBloqueUnDonTropRecent(): void
    {
        $donneur = $this->donneur('-30 days');

        self::assertSame(60, $donneur->joursAvantProchainDon(90));
        self::assertFalse($donneur->peutDonner(90));
    }

    public function testApresLeDelaiLeDonneurRedevientEligible(): void
    {
        $donneur = $this->donneur('-95 days');

        self::assertSame(0, $donneur->joursAvantProchainDon(90));
        self::assertTrue($donneur->peutDonner(90));
    }

    public function testUnDonneurTropJeuneOuTropAgeNePeutPasDonner(): void
    {
        $reference = new \DateTimeImmutable('2026-10-01');

        $mineur = $this->donneur(null, '2010-01-01');
        $aine = $this->donneur(null, '1950-01-01');

        self::assertFalse($mineur->peutDonner(90, 18, 65, $reference));
        self::assertFalse($aine->peutDonner(90, 18, 65, $reference));
    }

    public function testUneInaptitudeDeclareePrimeSurToutLeReste(): void
    {
        $donneur = $this->donneur('-400 days')
            ->setApte(false)
            ->setMotifInaptitude('Traitement en cours');

        self::assertFalse($donneur->peutDonner());
    }

    public function testLAgeEstCalculeALaDateDeReference(): void
    {
        $donneur = $this->donneur(null, '1991-03-14');

        self::assertSame(35, $donneur->getAge(new \DateTimeImmutable('2026-10-01')));
    }

    public function testLeNomCompletAssembleNomEtPrenom(): void
    {
        self::assertSame('SOSSOU Armand', $this->donneur(null)->getNomComplet());
    }
}
