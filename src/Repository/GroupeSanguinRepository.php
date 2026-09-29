<?php

namespace App\Repository;

use App\Entity\GroupeSanguin;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GroupeSanguin>
 */
class GroupeSanguinRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GroupeSanguin::class);
    }


    public function parCode(string $code): ?GroupeSanguin
    {
        return $this->findOneBy(['code' => $code]);
    }

    /**
     * @return array<int, GroupeSanguin>
     */
    public function tousOrdonnes(): array
    {
        return $this->createQueryBuilder('g')
            ->orderBy('g.rang', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
