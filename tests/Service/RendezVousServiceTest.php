<?php

namespace App\Tests\Service;

use App\Entity\Donneur;
use App\Repository\ParametreRepository;
use App\Repository\RendezVousDonRepository;
use App\Service\JournalService;
use App\Service\NumerotationService;
use App\Service\RendezVousService;
use App\Service\SmsNotifier;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * DON-01 : on ne réserve un créneau que si le donneur est réellement éligible.
 *
 * Les messages d'erreur sont vérifiés eux aussi : un donneur refusé doit
 * comprendre pourquoi sans appeler la banque.
 */
class RendezVousServiceTest extends TestCase
{
    private function service(int $rendezVousActifs = 0): RendezVousService
    {
        $parametres = $this->createMock(ParametreRepository::class);
        $parametres->method('entier')->willReturnCallback(
            static fn (string $cle, int $defaut = 0) => match ($cle) {
                'delai_entre_dons_jours' => 90,
                'age_min_don' => 18,
                'age_max_don' => 65,
                'delai_annulation_rdv_h' => 12,
                default => $defaut,
            }
        );

        $rendezVous = $this->createMock(RendezVousDonRepository::class);
        $rendezVous->method('compterActifsPour')->willReturn($rendezVousActifs);

        return new RendezVousService(
            $this->createMock(EntityManagerInterface::class),
            $rendezVous,
            $parametres,
            $this->createMock(NumerotationService::class),
            $this->createMock(JournalService::class),
            $this->createMock(SmsNotifier::class),
        );
    }

    private function donneur(string $naissance = '1991-03-14', ?string $dernierDon = null): Donneur
    {
        $donneur = (new Donneur())
            ->setNom('SOSSOU')
            ->setPrenom('Armand')
            ->setDateNaissance(new \DateTimeImmutable($naissance))
            ->setTelephone('+229 97 31 76 20')
            ->setApte(true);

        if ($dernierDon) {
            $donneur->setDernierDon(new \DateTimeImmutable($dernierDon));
        }

        return $donneur;
    }

    public function testUnDonneurEligiblePasseSansErreur(): void
    {
        $this->expectNotToPerformAssertions();

        $this->service()->verifierEligibilite($this->donneur('1991-03-14', '-120 days'));
    }

    public function testUnDonneurDeclareInapteEstRefuseAvecSonMotif(): void
    {
        $donneur = $this->donneur()
            ->setApte(false)
            ->setMotifInaptitude('Traitement antibiotique en cours');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Traitement antibiotique en cours');

        $this->service()->verifierEligibilite($donneur);
    }

    public function testUnMineurEstRefuse(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('18 à 65 ans');

        $this->service()->verifierEligibilite($this->donneur('2012-01-01'));
    }

    public function testUnDonTropRecentEstRefuseAvecLeNombreDeJours(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('dans 60 jours');

        $this->service()->verifierEligibilite($this->donneur('1991-03-14', '-30 days'));
    }

    public function testOnNeCumulePasDeuxRendezVousAVenir(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('déjà un rendez-vous à venir');

        $this->service(1)->verifierEligibilite($this->donneur('1991-03-14', '-200 days'));
    }
}
