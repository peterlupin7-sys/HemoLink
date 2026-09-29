<?php

namespace App\Repository;

use App\Entity\Banque;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Banque>
 */
class BanqueRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Banque::class);
    }


    /**
     * Banques actives triées par distance, avec la quantité disponible du groupe
     * demandé. Le calcul de distance (Haversine) est fait en SQL : c'est la base
     * qui trie, pas PHP.
     *
     * @return array<int, array{banque: Banque, distance: float, quantite: int, etat: string}>
     */
    public function trouverProches(
        float $latitude,
        float $longitude,
        ?int $groupeSanguinId = null,
        float $rayonKm = 25.0,
        int $limite = 20,
    ): array {
        $sql = <<<'SQL'
            SELECT b.id AS banque_id,
                   COALESCE(s.quantite_disponible, 0) AS quantite,
                   COALESCE(s.etat, 'NON_COMMUNIQUE') AS etat,
                   ROUND(6371 * ACOS(LEAST(1,
                         COS(RADIANS(:lat)) * COS(RADIANS(b.latitude))
                       * COS(RADIANS(b.longitude) - RADIANS(:lon))
                       + SIN(RADIANS(:lat)) * SIN(RADIANS(b.latitude)))), 2) AS distance
            FROM banque b
            LEFT JOIN stock s ON s.banque_id = b.id AND s.groupe_sanguin_id = :groupe
            WHERE b.statut = 'ACTIVE'
            HAVING distance <= :rayon
            ORDER BY distance ASC
            LIMIT :limite
            SQL;

        $connexion = $this->getEntityManager()->getConnection();
        $lignes = $connexion->executeQuery($sql, [
            'lat' => $latitude,
            'lon' => $longitude,
            'groupe' => $groupeSanguinId ?? 0,
            'rayon' => $rayonKm,
            'limite' => $limite,
        ], [
            'lat' => \PDO::PARAM_STR,
            'lon' => \PDO::PARAM_STR,
            'groupe' => \PDO::PARAM_INT,
            'rayon' => \PDO::PARAM_STR,
            'limite' => \PDO::PARAM_INT,
        ])->fetchAllAssociative();

        if (!$lignes) {
            return [];
        }

        $banques = [];
        foreach ($this->findBy(['id' => array_column($lignes, 'banque_id')]) as $banque) {
            $banques[$banque->getId()] = $banque;
        }

        $resultat = [];
        foreach ($lignes as $ligne) {
            $id = (int) $ligne['banque_id'];
            if (isset($banques[$id])) {
                $resultat[] = [
                    'banque' => $banques[$id],
                    'distance' => (float) $ligne['distance'],
                    'quantite' => (int) $ligne['quantite'],
                    'etat' => (string) $ligne['etat'],
                ];
            }
        }

        return $resultat;
    }

    /**
     * @return array<int, Banque>
     */
    public function toutesActives(): array
    {
        return $this->createQueryBuilder('b')
            ->andWhere("b.statut = 'ACTIVE'")
            ->orderBy('b.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
