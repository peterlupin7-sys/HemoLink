<?php

namespace App\Repository;

use App\Entity\Donneur;
use App\Entity\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Donneur>
 */
class DonneurRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Donneur::class);
    }


    public function parNumero(string $numero): ?Donneur
    {
        return $this->findOneBy(['numeroDonneur' => $numero]);
    }

    public function parUtilisateur(Utilisateur $utilisateur): ?Donneur
    {
        return $this->findOneBy(['utilisateur' => $utilisateur]);
    }

    /**
     * Recherche libre sur le nom, le prénom, le numéro ou le téléphone.
     *
     * @return array<int, Donneur>
     */
    public function rechercher(string $termes, int $limite = 25): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.nom LIKE :t OR d.prenom LIKE :t OR d.numeroDonneur LIKE :t '
                .'OR d.telephone LIKE :t')
            ->setParameter('t', '%'.trim($termes).'%')
            ->orderBy('d.nom', 'ASC')
            ->setMaxResults($limite)
            ->getQuery()
            ->getResult();
    }

    /**
     * Donneurs à nouveau éligibles, pour relancer une collecte.
     *
     * @return array<int, Donneur>
     */
    public function aRelancer(int $delaiJours = 90, int $limite = 50): array
    {
        $limiteDate = (new \DateTimeImmutable())->modify(sprintf('-%d days', $delaiJours));

        return $this->createQueryBuilder('d')
            ->where('d.apte = true')
            ->andWhere('d.dernierDon IS NULL OR d.dernierDon <= :limite')
            ->setParameter('limite', $limiteDate)
            ->orderBy('d.dernierDon', 'ASC')
            ->setMaxResults($limite)
            ->getQuery()
            ->getResult();
    }
}
