<?php

namespace App\Tests\Service;

use App\Entity\GroupeSanguin;
use App\Repository\GroupeSanguinRepository;
use App\Service\CompatibiliteService;
use PHPUnit\Framework\TestCase;

/**
 * La compatibilité transfusionnelle est la règle la plus sensible du projet :
 * une erreur ici n'est pas un défaut d'affichage, c'est un risque vital.
 */
class CompatibiliteServiceTest extends TestCase
{
    /** @var array<string, GroupeSanguin> */
    private array $groupes = [];

    protected function setUp(): void
    {
        $definitions = [
            'O+' => ['O', 'POSITIF', '38.50', 1],
            'O-' => ['O', 'NEGATIF', '4.30', 2],
            'A+' => ['A', 'POSITIF', '26.80', 3],
            'A-' => ['A', 'NEGATIF', '2.60', 4],
            'B+' => ['B', 'POSITIF', '20.40', 5],
            'B-' => ['B', 'NEGATIF', '1.70', 6],
            'AB+' => ['AB', 'POSITIF', '4.90', 7],
            'AB-' => ['AB', 'NEGATIF', '0.80', 8],
        ];

        foreach ($definitions as $code => [$abo, $rhesus, $part, $rang]) {
            $this->groupes[$code] = (new GroupeSanguin())
                ->setCode($code)
                ->setSystemeAbo($abo)
                ->setRhesus($rhesus)
                ->setPartPopulation($part)
                ->setRang($rang);
        }

        // Table de compatibilité des concentrés de globules rouges.
        $table = [
            'O+' => ['O+', 'O-'],
            'O-' => ['O-'],
            'A+' => ['A+', 'A-', 'O+', 'O-'],
            'A-' => ['A-', 'O-'],
            'B+' => ['B+', 'B-', 'O+', 'O-'],
            'B-' => ['B-', 'O-'],
            'AB+' => ['AB+', 'AB-', 'A+', 'A-', 'B+', 'B-', 'O+', 'O-'],
            'AB-' => ['AB-', 'A-', 'B-', 'O-'],
        ];

        foreach ($table as $receveur => $donneurs) {
            foreach ($donneurs as $donneur) {
                $this->groupes[$receveur]->addDonneursCompatible($this->groupes[$donneur]);
            }
        }
    }

    private function service(): CompatibiliteService
    {
        $depot = $this->createMock(GroupeSanguinRepository::class);
        $depot->method('tousOrdonnes')->willReturn(array_values($this->groupes));

        return new CompatibiliteService($depot);
    }

    public function testONegatifEstDonneurUniversel(): void
    {
        $service = $this->service();
        $oNeg = $this->groupes['O-'];

        foreach ($this->groupes as $code => $receveur) {
            self::assertTrue($service->estCompatible($receveur, $oNeg),
                sprintf('Un patient %s doit pouvoir recevoir du O-.', $code));
        }
    }

    public function testAbPositifEstReceveurUniversel(): void
    {
        $service = $this->service();
        $abPlus = $this->groupes['AB+'];

        foreach ($this->groupes as $code => $donneur) {
            self::assertTrue($service->estCompatible($abPlus, $donneur),
                sprintf('Un patient AB+ doit pouvoir recevoir du %s.', $code));
        }
    }

    public function testOPositifNeRecoitQueDuGroupeO(): void
    {
        $service = $this->service();
        $oPlus = $this->groupes['O+'];

        self::assertTrue($service->estCompatible($oPlus, $this->groupes['O+']));
        self::assertTrue($service->estCompatible($oPlus, $this->groupes['O-']));
        self::assertFalse($service->estCompatible($oPlus, $this->groupes['A+']),
            'Un patient O+ ne peut jamais recevoir de A+.');
        self::assertFalse($service->estCompatible($oPlus, $this->groupes['AB+']));
    }

    public function testUnRhesusNegatifNeRecoitJamaisDuPositif(): void
    {
        $service = $this->service();

        foreach (['O-', 'A-', 'B-', 'AB-'] as $code) {
            foreach (['O+', 'A+', 'B+', 'AB+'] as $positif) {
                self::assertFalse(
                    $service->estCompatible($this->groupes[$code], $this->groupes[$positif]),
                    sprintf('%s ne doit pas accepter du %s.', $code, $positif)
                );
            }
        }
    }

    public function testLesReceveursDuONegatifSontLesHuitGroupes(): void
    {
        self::assertCount(8, $this->service()->receveursPour($this->groupes['O-']));
    }

    public function testLesSubstitutsExcluentLeGroupeDemande(): void
    {
        $substituts = $this->service()->substituts($this->groupes['A+']);
        $codes = array_map(static fn (GroupeSanguin $g) => $g->getCode(), $substituts);

        self::assertNotContains('A+', $codes);
        self::assertContains('O-', $codes);
        self::assertSame('A-', $codes[0],
            'Le premier substitut proposé doit rester dans le même système ABO.');
    }
}
