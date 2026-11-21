<?php

namespace App\Repository;

use App\Entity\Banque;
use App\Entity\Don;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Don>
 */
class DonRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Don::class);
    }


    /**
     * Dons en attente de qualification : la file de travail du laboratoire.
     *
     * @return array<int, Don>
     */
    public function aQualifier(?Banque $banque = null): array
    {
        $qb = $this->createQueryBuilder('d')
            ->join('d.donneur', 'do')->addSelect('do')
            ->where('d.statut IN (:attente)')
            ->setParameter('attente', [Don::ENREGISTRE, Don::EN_QUALIFICATION])
            ->orderBy('d.dateDon', 'ASC');

        if ($banque) {
            $qb->andWhere('d.banque = :banque')->setParameter('banque', $banque);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return array<int, Don>
     */
    public function recents(Banque $banque, int $limite = 20): array
    {
        return $this->createQueryBuilder('d')
            ->join('d.donneur', 'do')->addSelect('do')
            ->where('d.banque = :banque')
            ->setParameter('banque', $banque)
            ->orderBy('d.dateDon', 'DESC')
            ->setMaxResults($limite)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array<string, int> statut => nombre
     */
    public function compterParStatut(?Banque $banque = null): array
    {
        $qb = $this->createQueryBuilder('d')
            ->select('d.statut AS statut, COUNT(d.id) AS total')
            ->groupBy('d.statut');

        if ($banque) {
            $qb->where('d.banque = :banque')->setParameter('banque', $banque);
        }

        $totaux = [];
        foreach ($qb->getQuery()->getResult() as $ligne) {
            $totaux[$ligne['statut']] = (int) $ligne['total'];
        }

        return $totaux;
    }

    public function compterDepuis(\DateTimeInterface $depuis, ?Banque $banque = null): int
    {
        $qb = $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->where('d.dateDon >= :depuis')
            ->setParameter('depuis', $depuis);

        if ($banque) {
            $qb->andWhere('d.banque = :banque')->setParameter('banque', $banque);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}
