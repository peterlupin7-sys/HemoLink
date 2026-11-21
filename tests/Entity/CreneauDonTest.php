<?php

namespace App\Tests\Entity;

use App\Entity\CreneauDon;
use App\Entity\RendezVousDon;
use PHPUnit\Framework\TestCase;

class CreneauDonTest extends TestCase
{
    private function creneau(string $date, int $capacite = 8, int $reservees = 0): CreneauDon
    {
        return (new CreneauDon())
            ->setDateCreneau(new \DateTimeImmutable($date))
            ->setHeureDebut('08:00:00')
            ->setHeureFin('10:00:00')
            ->setCapacite($capacite)
            ->setPlacesReservees($reservees)
            ->setStatut(CreneauDon::OUVERT);
    }

    public function testLesPlacesRestantesSontLaDifferenceEntreCapaciteEtReservations(): void
    {
        self::assertSame(5, $this->creneau('+3 days', 8, 3)->getPlacesRestantes());
    }

    public function testUnCreneauCompletNOffrePlusAucunePlace(): void
    {
        $creneau = $this->creneau('+3 days', 8, 8);

        self::assertSame(0, $creneau->getPlacesRestantes());
        self::assertFalse($creneau->estDisponible());
    }

    public function testUnCreneauPasseNEstPlusDisponibleMemeSILResteDesPlaces(): void
    {
        $creneau = $this->creneau('-2 days', 8, 1);

        self::assertTrue($creneau->estPasse());
        self::assertFalse($creneau->estDisponible());
    }

    public function testUnCreneauAnnuleNEstPasDisponible(): void
    {
        $creneau = $this->creneau('+5 days')->setStatut(CreneauDon::ANNULE);

        self::assertFalse($creneau->estDisponible());
    }

    public function testLHoraireEstAffichePourUnEcranDeTelephone(): void
    {
        self::assertSame('08:00 - 10:00', $this->creneau('+1 day')->getHoraire());
    }

    public function testUnRendezVousNEstPlusModifiableDansLesDernieresHeures(): void
    {
        $creneau = $this->creneau('+1 day');
        $rendezVous = (new RendezVousDon())
            ->setCreneau($creneau)
            ->setStatut(RendezVousDon::PLANIFIE);

        // Le créneau est demain à 8 h : à 12 h de délai, on est encore dans les temps,
        // mais pas à 48 h.
        self::assertTrue($rendezVous->estModifiable(12,
            $creneau->getDebut()->modify('-20 hours')));
        self::assertFalse($rendezVous->estModifiable(12,
            $creneau->getDebut()->modify('-2 hours')));
    }

    public function testUnRendezVousDejaAnnuleNEstPlusModifiable(): void
    {
        $creneau = $this->creneau('+10 days');
        $rendezVous = (new RendezVousDon())
            ->setCreneau($creneau)
            ->setStatut(RendezVousDon::ANNULE);

        self::assertFalse($rendezVous->estModifiable(12));
        self::assertFalse($rendezVous->estActif());
    }
}
