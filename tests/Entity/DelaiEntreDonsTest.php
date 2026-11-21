<?php

namespace App\Tests\Entity;

use App\Entity\Donneur;
use PHPUnit\Framework\TestCase;

/**
 * Le compte à rebours entre deux dons, vérifié aux heures qui posaient
 * problème.
 *
 * Le calcul comparait une date à minuit avec l'instant courant. Un donneur qui
 * consultait son espace en fin de journée lisait « 59 jours » là où il lui en
 * restait 60 : le reste d'une journée entamée était perdu par la troncature.
 * Les cas ci-dessous fixent le comportement attendu à toute heure.
 */
class DelaiEntreDonsTest extends TestCase
{
    private function donneurAyantDonne(string $ilYA): Donneur
    {
        return (new Donneur())
            ->setNumeroDonneur('DN-2026-09999')
            ->setNom('TEST')
            ->setPrenom('Delai')
            ->setSexe('M')
            ->setDateNaissance(new \DateTimeImmutable('-30 years'))
            ->setTelephone('+229 97 00 00 00')
            ->setVille('Cotonou')
            ->setDernierDon(new \DateTimeImmutable($ilYA));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function heuresDeLaJournee(): array
    {
        return [
            'juste après minuit' => ['00:05'],
            'milieu de matinée' => ['09:30'],
            'début d’après-midi' => ['14:00'],
            'juste avant minuit' => ['23:55'],
        ];
    }

    /**
     * @dataProvider heuresDeLaJournee
     */
    public function testLeResteAAttendreNeDependPasDeLHeureDeConsultation(string $heure): void
    {
        $jour = (new \DateTimeImmutable('2026-10-01'))->modify($heure);
        $donneur = (new Donneur())
            ->setNumeroDonneur('DN-2026-09998')
            ->setNom('TEST')
            ->setPrenom('Heure')
            ->setSexe('F')
            ->setDateNaissance(new \DateTimeImmutable('1995-01-01'))
            ->setTelephone('+229 97 00 00 01')
            ->setVille('Cotonou')
            ->setDernierDon(new \DateTimeImmutable('2026-09-01'));

        self::assertSame(60, $donneur->joursAvantProchainDon(90, $jour),
            sprintf('Consulté à %s, le délai doit rester de 60 jours.', $heure));
    }

    public function testLeDernierJourDAttenteCompteEncore(): void
    {
        $donneur = $this->donneurAyantDonne('-89 days');

        self::assertSame(1, $donneur->joursAvantProchainDon(90));
        self::assertFalse($donneur->peutDonner(90));
    }

    public function testLeJourAnniversaireDuDelaiLeDonneurRedevientEligible(): void
    {
        $donneur = $this->donneurAyantDonne('-90 days');

        self::assertSame(0, $donneur->joursAvantProchainDon(90));
        self::assertTrue($donneur->peutDonner(90));
    }
}
