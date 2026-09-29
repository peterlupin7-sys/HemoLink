<?php

namespace App\Repository;

use App\Entity\Poche;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Poche>
 */
class PocheRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Poche::class);
    }


    /**
     * Poches disponibles d'un groupe dans une banque, les plus anciennes d'abord :
     * on sort toujours la poche qui périme le plus tôt.
     *
     * @return array<int, Poche>
     */
    public function disponibles(int $banqueId, int $groupeId, ?int $limite = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->andWhere('p.banque = :banque')
            ->andWhere('p.groupeSanguin = :groupe')
            ->andWhere("p.statut = 'DISPONIBLE'")
            ->setParameter('banque', $banqueId)
            ->setParameter('groupe', $groupeId)
            ->orderBy('p.datePeremption', 'ASC');

        if (null !== $limite) {
            $qb->setMaxResults($limite);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return array<int, Poche>
     */
    public function bientotPerimees(int $jours = 7): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere("p.statut = 'DISPONIBLE'")
            ->andWhere('p.datePeremption <= :limite')
            ->setParameter('limite', new \DateTimeImmutable(sprintf('+%d days', $jours)))
            ->orderBy('p.datePeremption', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
