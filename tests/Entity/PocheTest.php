<?php

namespace App\Tests\Entity;

use App\Entity\Poche;
use PHPUnit\Framework\TestCase;

class PocheTest extends TestCase
{
    public function testUnePocheDontLaDateEstPasseeEstPerimee(): void
    {
        $poche = (new Poche())->setDatePeremption(new \DateTimeImmutable('-1 day'));

        self::assertTrue($poche->estPerimee());
    }

    public function testUnePocheDuJourNEstPasEncorePerimee(): void
    {
        $poche = (new Poche())->setDatePeremption(new \DateTimeImmutable('+3 days'));

        self::assertFalse($poche->estPerimee());
        self::assertSame(3, $poche->joursAvantPeremption());
    }

    public function testLeNombreDeJoursDevientNegatifApresLaDate(): void
    {
        $poche = (new Poche())->setDatePeremption(new \DateTimeImmutable('-5 days'));

        self::assertLessThan(0, $poche->joursAvantPeremption());
    }
}
