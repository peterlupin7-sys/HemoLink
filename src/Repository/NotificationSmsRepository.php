<?php

namespace App\Repository;

use App\Entity\NotificationSms;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<NotificationSms>
 */
class NotificationSmsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NotificationSms::class);
    }


    /**
     * @return array<int, NotificationSms>
     */
    public function aEnvoyer(int $limite = 50): array
    {
        return $this->createQueryBuilder('n')
            ->andWhere("n.statut = 'EN_ATTENTE'")
            ->andWhere('n.tentatives < 5')
            ->orderBy('n.dateCreation', 'ASC')
            ->setMaxResults($limite)
            ->getQuery()
            ->getResult();
    }
}
