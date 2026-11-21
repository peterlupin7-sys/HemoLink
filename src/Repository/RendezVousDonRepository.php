<?php

namespace App\Repository;

use App\Entity\Banque;
use App\Entity\Donneur;
use App\Entity\RendezVousDon;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RendezVousDon>
 */
class RendezVousDonRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RendezVousDon::class);
    }


    public function parNumero(string $numero): ?RendezVousDon
    {
        return $this->createQueryBuilder('r')
            ->join('r.creneau', 'c')->addSelect('c')
            ->join('r.donneur', 'd')->addSelect('d')
            ->where('r.numero = :numero')
            ->setParameter('numero', $numero)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Rendez-vous d'un donneur, les plus récents d'abord.
     *
     * @return array<int, RendezVousDon>
     */
    public function pourDonneur(Donneur $donneur): array
    {
        return $this->createQueryBuilder('r')
            ->join('r.creneau', 'c')->addSelect('c')
            ->where('r.donneur = :donneur')
            ->setParameter('donneur', $donneur)
            ->orderBy('c.dateCreneau', 'DESC')
            ->addOrderBy('c.heureDebut', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Rendez-vous attendus aujourd'hui dans une banque.
     *
     * @return array<int, RendezVousDon>
     */
    public function duJour(Banque $banque): array
    {
        return $this->createQueryBuilder('r')
            ->join('r.creneau', 'c')->addSelect('c')
            ->join('r.donneur', 'd')->addSelect('d')
            ->where('c.banque = :banque')
            ->andWhere('c.dateCreneau = :jour')
            ->andWhere('r.statut IN (:actifs)')
            ->setParameter('banque', $banque)
            ->setParameter('jour', new \DateTimeImmutable('today'))
            ->setParameter('actifs', [RendezVousDon::PLANIFIE, RendezVousDon::HONORE])
            ->orderBy('c.heureDebut', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Rendez-vous planifiés dont le créneau est déjà passé : ils attendent
     * d'être pointés présents ou absents.
     *
     * @return array<int, RendezVousDon>
     */
    public function aPointer(?Banque $banque = null): array
    {
        $qb = $this->createQueryBuilder('r')
            ->join('r.creneau', 'c')->addSelect('c')
            ->join('r.donneur', 'd')->addSelect('d')
            ->where('r.statut = :planifie')
            ->andWhere('c.dateCreneau < :jour')
            ->setParameter('planifie', RendezVousDon::PLANIFIE)
            ->setParameter('jour', new \DateTimeImmutable('today'))
            ->orderBy('c.dateCreneau', 'ASC');

        if ($banque) {
            $qb->andWhere('c.banque = :banque')->setParameter('banque', $banque);
        }

        return $qb->getQuery()->getResult();
    }

    public function compterActifsPour(Donneur $donneur): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->join('r.creneau', 'c')
            ->where('r.donneur = :donneur')
            ->andWhere('r.statut = :planifie')
            ->andWhere('c.dateCreneau >= :jour')
            ->setParameter('donneur', $donneur)
            ->setParameter('planifie', RendezVousDon::PLANIFIE)
            ->setParameter('jour', new \DateTimeImmutable('today'))
            ->getQuery()
            ->getSingleScalarResult();
    }
}
