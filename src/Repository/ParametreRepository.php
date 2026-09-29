<?php

namespace App\Repository;

use App\Entity\Parametre;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Parametre>
 */
class ParametreRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Parametre::class);
    }


    public function valeur(string $cle, string $defaut = ''): string
    {
        $parametre = $this->find($cle);

        return $parametre ? $parametre->getValeur() : $defaut;
    }

    public function entier(string $cle, int $defaut = 0): int
    {
        $valeur = $this->valeur($cle);

        return '' === $valeur ? $defaut : (int) $valeur;
    }
}
