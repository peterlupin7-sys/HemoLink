<?php

namespace App\Tests\Entity;

use App\Entity\Qualification;
use PHPUnit\Framework\TestCase;

/**
 * Règle DON-05 / US31 : seul un produit qualifié conforme peut rejoindre le
 * stock utilisable. Ces tests vérifient la brique élémentaire qui permet au
 * service de trancher.
 */
class QualificationTest extends TestCase
{
    private function qualification(array $tests = []): Qualification
    {
        $qualification = (new Qualification())
            ->setVih($tests['vih'] ?? Qualification::NEGATIF)
            ->setHepatiteB($tests['hepatiteB'] ?? Qualification::NEGATIF)
            ->setHepatiteC($tests['hepatiteC'] ?? Qualification::NEGATIF)
            ->setSyphilis($tests['syphilis'] ?? Qualification::NEGATIF);

        return $qualification;
    }

    public function testAucunTestPositifQuandToutEstNegatif(): void
    {
        self::assertSame([], $this->qualification()->testsPositifs());
    }

    public function testUnSeulMarqueurPositifEstRemonte(): void
    {
        $qualification = $this->qualification(['hepatiteB' => Qualification::POSITIF]);

        self::assertSame(['Hépatite B'], $qualification->testsPositifs());
    }

    public function testPlusieursMarqueursPositifsSontTousRemontes(): void
    {
        $qualification = $this->qualification([
            'vih' => Qualification::POSITIF,
            'syphilis' => Qualification::POSITIF,
        ]);

        self::assertSame(['VIH', 'Syphilis'], $qualification->testsPositifs());
    }

    public function testUnTestNonFaitNEstPasUnTestPositif(): void
    {
        $qualification = $this->qualification(['hepatiteC' => Qualification::NON_FAIT]);

        self::assertSame([], $qualification->testsPositifs());
    }

    public function testUnResultatNonConformeNePorteAucunePoche(): void
    {
        $qualification = $this->qualification(['vih' => Qualification::POSITIF])
            ->setResultat(Qualification::NON_CONFORME)
            ->setMotifRejet('Analyse positive : VIH');

        self::assertFalse($qualification->estConforme());
        self::assertNull($qualification->getPoche());
    }

    public function testUnResultatConformeEstReconnuCommeTel(): void
    {
        $qualification = $this->qualification()->setResultat(Qualification::CONFORME);

        self::assertTrue($qualification->estConforme());
    }
}
