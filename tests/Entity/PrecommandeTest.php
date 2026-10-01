<?php

namespace App\Tests\Entity;

use App\Entity\GroupeSanguin;
use App\Entity\LignePrecommande;
use App\Entity\Precommande;
use PHPUnit\Framework\TestCase;

class PrecommandeTest extends TestCase
{
    private function ligne(string $code, int $quantite): LignePrecommande
    {
        $groupe = (new GroupeSanguin())
            ->setCode($code)
            ->setSystemeAbo(rtrim($code, '+-'))
            ->setRhesus(str_ends_with($code, '+') ? 'POSITIF' : 'NEGATIF')
            ->setRang(1);

        return (new LignePrecommande())
            ->setGroupeSanguin($groupe)
            ->setQuantiteDemandee($quantite);
    }

    public function testLaQuantiteTotaleAdditionneLesLignes(): void
    {
        $precommande = new Precommande();
        $precommande->addLigne($this->ligne('O+', 2));
        $precommande->addLigne($this->ligne('A-', 3));

        self::assertCount(2, $precommande->getLignes());
        self::assertSame(5, $precommande->getQuantiteTotale());
    }

    public function testAjouterUneLigneLaRattacheALaPrecommande(): void
    {
        $precommande = new Precommande();
        $ligne = $this->ligne('B+', 1);
        $precommande->addLigne($ligne);

        self::assertSame($precommande, $ligne->getPrecommande());
    }

    public function testUnePrecommandeRetireeNEstPlusModifiable(): void
    {
        $precommande = (new Precommande())->setStatut(Precommande::EN_ATTENTE);
        self::assertTrue($precommande->estModifiable());

        $precommande->setStatut(Precommande::CONFIRMEE);
        self::assertTrue($precommande->estModifiable());

        $precommande->setStatut(Precommande::RETIREE);
        self::assertFalse($precommande->estModifiable());

        $precommande->setStatut(Precommande::ANNULEE);
        self::assertFalse($precommande->estModifiable());

        $precommande->setStatut(Precommande::EXPIREE);
        self::assertFalse($precommande->estModifiable());
    }

    public function testChaqueStatutPossedeUnLibelleEnFrancais(): void
    {
        foreach (array_keys(Precommande::LIBELLES) as $statut) {
            $precommande = (new Precommande())->setStatut($statut);

            self::assertNotSame($statut, $precommande->getLibelleStatut());
        }
    }
}
