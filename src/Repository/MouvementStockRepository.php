<?php

namespace App\Repository;

use App\Entity\MouvementStock;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MouvementStock>
 */
class MouvementStockRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MouvementStock::class);
    }


    /**
     * @return array<int, MouvementStock>
     */
    public function derniers(int $banqueId, int $limite = 20): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.banque = :banque')
            ->setParameter('banque', $banqueId)
            ->orderBy('m.dateMouvement', 'DESC')
            ->setMaxResults($limite)
            ->getQuery()
            ->getResult();
    }
}
