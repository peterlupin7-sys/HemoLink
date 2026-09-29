<?php

namespace App\Repository;

use App\Entity\Journal;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Journal>
 */
class JournalRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Journal::class);
    }


    /**
     * @return array<int, Journal>
     */
    public function derniers(int $limite = 50): array
    {
        return $this->createQueryBuilder('j')
            ->orderBy('j.dateAction', 'DESC')
            ->setMaxResults($limite)
            ->getQuery()
            ->getResult();
    }
}
