<?php

namespace App\Tests\Service;

use PHPUnit\Framework\TestCase;

/**
 * Le numéro de précommande est le seul identifiant que le demandeur retient :
 * son format doit rester stable et lisible au téléphone.
 */
class NumerotationServiceTest extends TestCase
{
    public function testLeFormatSuitLeModeleDuCahierDesCharges(): void
    {
        $numero = sprintf('PRE-%s-%d-%05d', 'COT', 2026, 7);

        self::assertSame('PRE-COT-2026-00007', $numero);
        self::assertMatchesRegularExpression('/^PRE-[A-Z]{3}-\d{4}-\d{5}$/', $numero);
    }

    public function testLeCompteurNeRepasseJamaisParUnNumeroDejaUtilise(): void
    {
        $vus = [];

        for ($i = 1; $i <= 50; ++$i) {
            $numero = sprintf('PRE-COT-2026-%05d', $i);

            self::assertNotContains($numero, $vus);
            $vus[] = $numero;
        }

        self::assertCount(50, $vus);
    }
}
