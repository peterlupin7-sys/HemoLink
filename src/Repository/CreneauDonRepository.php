<?php

namespace App\Repository;

use App\Entity\Banque;
use App\Entity\CreneauDon;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CreneauDon>
 */
class CreneauDonRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CreneauDon::class);
    }


    /**
     * Créneaux encore réservables, du plus proche au plus lointain.
     *
     * @return array<int, CreneauDon>
     */
    public function disponibles(?Banque $banque = null, int $joursAVenir = 30): array
    {
        $qb = $this->createQueryBuilder('c')
            ->join('c.banque', 'b')
            ->where('c.statut = :ouvert')
            ->andWhere('c.placesReservees < c.capacite')
            ->andWhere('c.dateCreneau >= :aujourdhui')
            ->andWhere('c.dateCreneau <= :fin')
            // Un créneau du jour dont l'heure est passée ne doit plus être
            // proposé : sinon le donneur voit un bouton « Réserver » qui le
            // renvoie systématiquement sur « Ce créneau est déjà passé ».
            ->andWhere('c.dateCreneau > :aujourdhui OR c.heureDebut > :maintenant')
            ->andWhere("b.statut = 'ACTIVE'")
            ->setParameter('ouvert', CreneauDon::OUVERT)
            ->setParameter('aujourdhui', new \DateTimeImmutable('today'))
            ->setParameter('maintenant', (new \DateTimeImmutable())->format('H:i:s'))
            ->setParameter('fin', (new \DateTimeImmutable('today'))
                ->modify(sprintf('+%d days', $joursAVenir)))
            ->orderBy('c.dateCreneau', 'ASC')
            ->addOrderBy('c.heureDebut', 'ASC');

        if ($banque) {
            $qb->andWhere('c.banque = :banque')->setParameter('banque', $banque);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Tous les créneaux d'une banque sur une période, passés compris.
     *
     * @return array<int, CreneauDon>
     */
    public function pourBanque(Banque $banque, int $joursAvant = 7, int $joursApres = 30): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.banque = :banque')
            ->andWhere('c.dateCreneau BETWEEN :debut AND :fin')
            ->setParameter('banque', $banque)
            ->setParameter('debut', (new \DateTimeImmutable('today'))
                ->modify(sprintf('-%d days', $joursAvant)))
            ->setParameter('fin', (new \DateTimeImmutable('today'))
                ->modify(sprintf('+%d days', $joursApres)))
            ->orderBy('c.dateCreneau', 'ASC')
            ->addOrderBy('c.heureDebut', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Créneaux du jour, pour l'accueil des donneurs à la banque.
     *
     * @return array<int, CreneauDon>
     */
    public function duJour(Banque $banque): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.banque = :banque')
            ->andWhere('c.dateCreneau = :jour')
            ->setParameter('banque', $banque)
            ->setParameter('jour', new \DateTimeImmutable('today'))
            ->orderBy('c.heureDebut', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
