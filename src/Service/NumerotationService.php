<?php

namespace App\Service;

use App\Entity\Banque;
use App\Entity\Compteur;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Produit les numéros de précommande : PRE-COT-2026-00001.
 *
 * Pourquoi un compteur en base plutôt que l'AUTO_INCREMENT ? Parce que le numéro
 * est lu au téléphone et imprimé sur un bon : il doit être court, lisible, et
 * repartir à 1 chaque année.
 */
class NumerotationService
{
    /** Nombre de tentatives avant d'abandonner un incrément disputé. */
    private const ESSAIS = 5;

    /**
     * Erreurs que la base nous demande de rejouer plutôt que de signaler :
     * 1020 ligne modifiée depuis la lecture, 1205 attente de verrou expirée,
     * 1213 interblocage.
     */
    private const CONFLITS = [1020, 1205, 1213];

    /** Connexion réservée au compteur, ouverte à la première demande. */
    private ?Connection $connexionCompteur = null;

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
     * Attribue le numéro suivant, de façon sûre même si plusieurs demandes
     * arrivent au même instant.
     *
     * Ce que dix réservations lancées en parallèle ont appris, par étapes :
     *
     *  1. la première version ouvrait sa propre transaction, imbriquée dans
     *     celle de l'appelant (`RendezVousService::reserver()` en ouvre une).
     *     Un `rollBack()` interne condamnait la transaction externe entière ;
     *  2. elle lisait ensuite la ligne avec `SELECT ... FOR UPDATE` avant
     *     d'ajouter un. Sous MariaDB, en REPEATABLE READ, toucher une ligne
     *     modifiée et validée par une autre transaction depuis le début de la
     *     nôtre lève l'erreur 1020, « Record has changed since last read » ;
     *  3. remplacer la lecture par un incrément atomique n'a pas suffi : tant
     *     que l'instruction s'exécutait dans la transaction de l'appelant,
     *     elle restait prisonnière de l'instantané ouvert quelques
     *     millisecondes plus tôt, et l'erreur 1020 revenait. Pire, elle
     *     laissait la transaction déjà défaite, si bien que le `rollBack()` de
     *     l'appelant levait « There is no active transaction » et masquait la
     *     cause réelle.
     *
     * D'où le choix retenu : le compteur vit sur une connexion à lui, en
     * dehors de toute transaction métier. Chaque incrément est une instruction
     * isolée, validée immédiatement, avec son propre instantané — la ligne la
     * plus disputée de la base cesse d'être un point de contention, et les
     * réservations ne peuvent plus se gêner.
     *
     * Contrepartie assumée : un numéro attribué à une réservation qui échoue
     * ensuite n'est pas rendu, et la série peut comporter un trou. C'est le
     * comportement de n'importe quelle séquence, et il est préférable à deux
     * demandeurs recevant le même numéro.
     */
    public function incrementer(string $cle, string $prefixe, int $annee): int
    {
        $maintenant = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        // Cas courant : le compteur existe déjà, une seule instruction suffit.
        $valeur = $this->tenterIncrement($cle, $maintenant);

        if (null === $valeur) {
            // Compteur encore inconnu : on le crée, puis on retente. Le passage
            // par `ON DUPLICATE KEY` absorbe le cas où un autre processus vient
            // de le créer entre nos deux instructions.
            $this->connexionCompteur()->executeStatement(
                'INSERT INTO compteur (cle, prefixe, annee, valeur, date_maj)
                 VALUES (:cle, :prefixe, :annee, 0, :maintenant)
                 ON DUPLICATE KEY UPDATE cle = cle',
                ['cle' => $cle, 'prefixe' => $prefixe, 'annee' => $annee,
                    'maintenant' => $maintenant]
            );

            $valeur = $this->tenterIncrement($cle, $maintenant);
        }

        if (null === $valeur || $valeur < 1) {
            throw new \RuntimeException(
                sprintf('Le compteur « %s » n\'a pas pu être incrémenté.', $cle));
        }

        // L'écriture est passée par une autre connexion : si l'entité était
        // déjà chargée, elle porte encore l'ancienne valeur.
        $charge = $this->em->getUnitOfWork()->tryGetById($cle, Compteur::class);

        if ($charge instanceof Compteur) {
            $charge->setValeur($valeur);
        }

        return $valeur;
    }

    /**
     * Incrémente la ligne en une instruction et rend la valeur attribuée.
     * Rend `null` si le compteur n'existe pas encore (aucune ligne touchée).
     *
     * `LAST_INSERT_ID(expr)` mémorise la valeur pour la connexion courante, et
     * elle seule : deux connexions simultanées ne peuvent pas se voler leur
     * numéro. MySQL 8 et MariaDB comprennent l'instruction de la même façon.
     */
    private function tenterIncrement(string $cle, string $maintenant): ?int
    {
        $connexion = $this->connexionCompteur();
        $derniere = null;

        for ($essai = 1; $essai <= self::ESSAIS; ++$essai) {
            try {
                $touchees = $connexion->executeStatement(
                    'UPDATE compteur
                        SET valeur = LAST_INSERT_ID(valeur + 1), date_maj = :maintenant
                      WHERE cle = :cle',
                    ['cle' => $cle, 'maintenant' => $maintenant]
                );

                if (0 === $touchees) {
                    return null;
                }

                return (int) $connexion->fetchOne('SELECT LAST_INSERT_ID()');
            } catch (DriverException $e) {
                if (!\in_array((int) $e->getCode(), self::CONFLITS, true)) {
                    throw $e;
                }

                $derniere = $e;

                // Attente croissante et irrégulière : deux processus repartis
                // au même instant ne doivent pas se heurter de nouveau.
                usleep($essai * 20_000 + random_int(0, 10_000));
            }
        }

        throw new \RuntimeException(sprintf(
            'Numérotation « %s » : trop de demandes simultanées, réessayez.', $cle),
            0, $derniere);
    }

    /**
     * Ouvre, à la première demande, une connexion dédiée au compteur.
     *
     * Elle reprend les paramètres de la connexion principale : une seule
     * configuration à tenir, et la bascule vers la base de test suit
     * automatiquement.
     */
    private function connexionCompteur(): Connection
    {
        if (null === $this->connexionCompteur || !$this->connexionCompteur->isConnected()) {
            $principale = $this->em->getConnection();

            // La configuration est reprise telle quelle, sans quoi Doctrine
            // signale une dépréciation sur la fabrique de gestionnaires de
            // schéma et l'on s'écarterait des réglages du projet.
            $this->connexionCompteur = DriverManager::getConnection(
                $principale->getParams(), $principale->getConfiguration());
        }

        return $this->connexionCompteur;
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
