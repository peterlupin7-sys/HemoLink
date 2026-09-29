<?php

namespace App\Repository;

use App\Entity\Stock;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Stock>
 */
class StockRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Stock::class);
    }


    /**
     * @return array<int, Stock>
     */
    public function pourBanque(int $banqueId): array
    {
        return $this->createQueryBuilder('s')
            ->join('s.groupeSanguin', 'g')
            ->andWhere('s.banque = :banque')
            ->setParameter('banque', $banqueId)
            ->orderBy('g.rang', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array<int, Stock>
     */
    public function enRupture(): array
    {
        return $this->createQueryBuilder('s')
            ->join('s.banque', 'b')
            ->andWhere("s.etat = 'RUPTURE'")
            ->andWhere("b.statut = 'ACTIVE'")
            ->orderBy('b.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array<string, int>
     */
    public function totauxParEtat(): array
    {
        $lignes = $this->createQueryBuilder('s')
            ->select('s.etat AS etat, COUNT(s.id) AS total')
            ->groupBy('s.etat')
            ->getQuery()
            ->getArrayResult();

        $totaux = [];
        foreach ($lignes as $ligne) {
            $totaux[$ligne['etat']] = (int) $ligne['total'];
        }

        return $totaux;
    }
}
