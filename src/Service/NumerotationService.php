<?php

namespace App\Service;

use App\Entity\Banque;
use App\Entity\Compteur;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Produit les numéros de précommande : PRE-COT-2026-00001.
 *
 * Pourquoi un compteur en base plutôt que l'AUTO_INCREMENT ? Parce que le numéro
 * est lu au téléphone et imprimé sur un bon : il doit être court, lisible, et
 * repartir à 1 chaque année. Le verrou pessimiste garantit que deux précommandes
 * simultanées ne reçoivent jamais le même numéro.
 */
class NumerotationService
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function prochainNumeroPrecommande(Banque $banque, ?int $annee = null): string
    {
        $annee ??= (int) date('Y');
        $ville = $this->codeVille($banque);
        $prefixe = 'PRE-'.$ville;
        $cle = sprintf('PRECOMMANDE-%s-%d', $ville, $annee);

        $valeur = $this->incrementer($cle, $prefixe, $annee);

        return sprintf('%s-%d-%05d', $prefixe, $annee, $valeur);
    }

    /**
     * Incrémente un compteur sous verrou. La transaction est courte : on verrouille,
     * on incrémente, on relâche.
     */
    public function incrementer(string $cle, string $prefixe, int $annee): int
    {
        $connexion = $this->em->getConnection();
        $connexion->beginTransaction();

        try {
            $compteur = $this->em->find(Compteur::class, $cle, LockMode::PESSIMISTIC_WRITE);

            if (!$compteur) {
                $compteur = (new Compteur())
                    ->setCle($cle)
                    ->setPrefixe($prefixe)
                    ->setAnnee($annee)
                    ->setValeur(0)
                    ->setDateMaj(new \DateTimeImmutable());
                $this->em->persist($compteur);
            }

            $valeur = $compteur->getValeur() + 1;
            $compteur->setValeur($valeur)->setDateMaj(new \DateTimeImmutable());

            $this->em->flush();
            $connexion->commit();

            return $valeur;
        } catch (\Throwable $e) {
            $connexion->rollBack();

            throw $e;
        }
    }

    /**
     * Code à trois lettres de la ville, utilisé dans le numéro.
     */
    public function codeVille(Banque $banque): string
    {
        $ville = iconv('UTF-8', 'ASCII//TRANSLIT', (string) $banque->getVille());
        $ville = preg_replace('/[^A-Za-z]/', '', (string) $ville) ?: 'BEN';

        return strtoupper(substr($ville, 0, 3));
    }
}
