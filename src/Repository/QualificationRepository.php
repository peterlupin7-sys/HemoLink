<?php

namespace App\Repository;

use App\Entity\Banque;
use App\Entity\Qualification;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Qualification>
 */
class QualificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Qualification::class);
    }


    /**
     * @return array<string, int> resultat => nombre
     */
    public function compterParResultat(?Banque $banque = null): array
    {
        $qb = $this->createQueryBuilder('q')
            ->select('q.resultat AS resultat, COUNT(q.id) AS total')
            ->join('q.don', 'd')
            ->groupBy('q.resultat');

        if ($banque) {
            $qb->where('d.banque = :banque')->setParameter('banque', $banque);
        }

        $totaux = [];
        foreach ($qb->getQuery()->getResult() as $ligne) {
            $totaux[$ligne['resultat']] = (int) $ligne['total'];
        }

        return $totaux;
    }

    /**
     * Dernières qualifications prononcées, pour la traçabilité.
     *
     * @return array<int, Qualification>
     */
    public function dernieres(?Banque $banque = null, int $limite = 20): array
    {
        $qb = $this->createQueryBuilder('q')
            ->join('q.don', 'd')->addSelect('d')
            ->join('q.personnel', 'p')->addSelect('p')
            ->orderBy('q.dateQualification', 'DESC')
            ->setMaxResults($limite);

        if ($banque) {
            $qb->where('d.banque = :banque')->setParameter('banque', $banque);
        }

        return $qb->getQuery()->getResult();
    }
}
