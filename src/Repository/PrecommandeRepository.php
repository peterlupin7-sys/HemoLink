<?php

namespace App\Repository;

use App\Entity\Precommande;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Precommande>
 */
class PrecommandeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Precommande::class);
    }


    /**
     * @return array<int, Precommande>
     */
    public function pourBanque(int $banqueId, ?string $statut = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->andWhere('p.banque = :banque')
            ->setParameter('banque', $banqueId)
            ->orderBy('p.dateCreation', 'DESC');

        if ($statut) {
            $qb->andWhere('p.statut = :statut')->setParameter('statut', $statut);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return array<int, Precommande>
     */
    public function aExpirer(int $heures = 48): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere("p.statut IN ('EN_ATTENTE', 'CONFIRMEE', 'PRETE')")
            ->andWhere('p.dateCreation < :limite')
            ->setParameter('limite', new \DateTimeImmutable(sprintf('-%d hours', $heures)))
            ->getQuery()
            ->getResult();
    }

    public function compterParStatut(?int $banqueId = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->select('p.statut AS statut, COUNT(p.id) AS total')
            ->groupBy('p.statut');

        if ($banqueId) {
            $qb->andWhere('p.banque = :banque')->setParameter('banque', $banqueId);
        }

        $totaux = [];
        foreach ($qb->getQuery()->getArrayResult() as $ligne) {
            $totaux[$ligne['statut']] = (int) $ligne['total'];
        }

        return $totaux;
    }

    public function compterDepuis(\DateTimeInterface $depuis, ?int $banqueId = null): int
    {
        $qb = $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.dateCreation >= :depuis')
            ->setParameter('depuis', $depuis);

        if ($banqueId) {
            $qb->andWhere('p.banque = :banque')->setParameter('banque', $banqueId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}
