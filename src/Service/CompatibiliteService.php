<?php

namespace App\Service;

use App\Entity\GroupeSanguin;
use App\Repository\GroupeSanguinRepository;

/**
 * Compatibilité transfusionnelle (concentrés de globules rouges).
 *
 * La table `compatibilite` fait foi : ces règles ne doivent jamais être écrites
 * en dur dans un contrôleur. On les lit, on ne les devine pas.
 */
class CompatibiliteService
{
    public function __construct(private readonly GroupeSanguinRepository $groupes)
    {
    }

    /**
     * Groupes dont le sang peut être transfusé à ce receveur.
     *
     * @return array<int, GroupeSanguin>
     */
    public function donneursPour(GroupeSanguin $receveur): array
    {
        return $receveur->getDonneursCompatibles()->toArray();
    }

    /**
     * Groupes qui peuvent recevoir le sang de ce donneur.
     *
     * @return array<int, GroupeSanguin>
     */
    public function receveursPour(GroupeSanguin $donneur): array
    {
        $receveurs = [];

        foreach ($this->groupes->tousOrdonnes() as $groupe) {
            if ($groupe->peutRecevoirDe($donneur)) {
                $receveurs[] = $groupe;
            }
        }

        return $receveurs;
    }

    public function estCompatible(GroupeSanguin $receveur, GroupeSanguin $donneur): bool
    {
        return $receveur->peutRecevoirDe($donneur);
    }

    /**
     * Substituts proposés quand le groupe demandé est en rupture, du plus proche
     * au plus éloigné : on propose d'abord un groupe de même système ABO.
     *
     * @return array<int, GroupeSanguin>
     */
    public function substituts(GroupeSanguin $receveur): array
    {
        // On exclut le groupe demandé lui-même en comparant les codes : ils sont
        // uniques, alors que l'identifiant est nul tant que l'entité n'a pas été
        // enregistrée. Comparer les identifiants ferait disparaître tous les
        // substituts dans ce cas.
        $substituts = array_filter(
            $this->donneursPour($receveur),
            static fn (GroupeSanguin $g) => $g->getCode() !== $receveur->getCode()
        );

        usort($substituts, static function (GroupeSanguin $a, GroupeSanguin $b) use ($receveur) {
            $memeAbo = static fn (GroupeSanguin $g) => $g->getSystemeAbo() === $receveur->getSystemeAbo() ? 0 : 1;

            return [$memeAbo($a), -$a->getPartPopulation()] <=> [$memeAbo($b), -$b->getPartPopulation()];
        });

        return array_values($substituts);
    }
}
