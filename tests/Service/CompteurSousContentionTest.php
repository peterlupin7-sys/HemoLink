<?php

namespace App\Tests\Service;

use App\Entity\Compteur;
use App\Service\NumerotationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Le compteur de numéros est la ligne la plus disputée de la base : chaque
 * réservation, chaque don et chaque précommande passe par elle.
 *
 * Dix réservations lancées en parallèle en ont tiré trois défauts, qu'aucun
 * test unitaire ne pouvait voir parce qu'ils n'apparaissent qu'avec une vraie
 * base et une vraie transaction ouverte :
 *
 *  1. le service ouvrait sa propre transaction, imbriquée dans celle de
 *     l'appelant ; son `rollBack()` condamnait la transaction externe ;
 *  2. il lisait la ligne sous verrou avant de l'incrémenter, ce qui lève
 *     l'erreur 1020 de MariaDB quand un autre processus l'a modifiée depuis ;
 *  3. l'erreur laissait la transaction défaite, si bien que le `rollBack()`
 *     de l'appelant levait à son tour « There is no active transaction » et
 *     effaçait la cause réelle des journaux.
 *
 * Ces tests tiennent les trois points fermés. Ils supposent la base de test
 * chargée :
 *   php bin/console doctrine:migrations:migrate -e test --no-interaction
 *   php bin/console doctrine:fixtures:load -e test --no-interaction
 */
class CompteurSousContentionTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private NumerotationService $numerotation;

    protected function setUp(): void
    {
        self::bootKernel();
        $conteneur = static::getContainer();
        $this->em = $conteneur->get(EntityManagerInterface::class);
        $this->numerotation = $conteneur->get(NumerotationService::class);
    }

    protected function tearDown(): void
    {
        $connexion = $this->em->getConnection();

        while ($connexion->isTransactionActive()) {
            $connexion->rollBack();
        }

        $connexion->executeStatement(
            "DELETE FROM compteur WHERE cle LIKE 'TEST-CONTENTION-%'");

        parent::tearDown();
    }

    /**
     * Le cas nominal : deux appels de suite rendent deux valeurs qui se
     * suivent, et la ligne est créée au premier passage.
     */
    public function testLesNumerosSeSuivent(): void
    {
        $cle = $this->cle();

        self::assertSame(1, $this->numerotation->incrementer($cle, 'TST', 2026));
        self::assertSame(2, $this->numerotation->incrementer($cle, 'TST', 2026));
        self::assertSame(3, $this->numerotation->incrementer($cle, 'TST', 2026));
    }

    /**
     * Le défaut n° 1. L'appelant ouvre une transaction, comme le fait
     * `RendezVousService::reserver()` : le service de numérotation ne doit ni
     * la valider, ni la défaire, ni en ouvrir une autre par-dessus.
     */
    public function testLaTransactionDeLAppelantSurvitALAppel(): void
    {
        $connexion = $this->em->getConnection();
        $cle = $this->cle();

        $connexion->beginTransaction();
        $niveauAvant = $connexion->getTransactionNestingLevel();

        $this->numerotation->incrementer($cle, 'TST', 2026);

        self::assertTrue($connexion->isTransactionActive(),
            'La numérotation a refermé la transaction de l’appelant.');
        self::assertSame($niveauAvant, $connexion->getTransactionNestingLevel(),
            'La numérotation a ouvert une transaction imbriquée.');

        // L'appelant reste maître de son sort : il doit pouvoir défaire.
        $connexion->rollBack();

        self::assertFalse($connexion->isTransactionActive());
    }

    /**
     * Les défauts n° 2 et 3. Une autre connexion modifie le compteur pendant
     * que notre transaction est ouverte — exactement ce que faisaient dix
     * processus simultanés. L'incrément doit passer malgré tout.
     */
    public function testUnCompteurModifieAilleursNInterrompPasLaTransaction(): void
    {
        $connexion = $this->em->getConnection();
        $cle = $this->cle();

        // La ligne existe et a déjà servi avant que notre transaction commence.
        $this->numerotation->incrementer($cle, 'TST', 2026);

        $connexion->beginTransaction();

        // Notre transaction fige son instantané en lisant la ligne.
        $connexion->fetchOne('SELECT valeur FROM compteur WHERE cle = ?', [$cle]);

        // Pendant ce temps, un autre processus consomme deux numéros.
        $ailleurs = $this->connexionSeparee();
        $ailleurs->executeStatement(
            'UPDATE compteur SET valeur = valeur + 2 WHERE cle = ?', [$cle]);
        $ailleurs->close();

        // Avant correction, cette ligne levait l'erreur 1020 et laissait la
        // transaction défaite.
        $valeur = $this->numerotation->incrementer($cle, 'TST', 2026);

        self::assertSame(4, $valeur,
            'Le numéro doit repartir de la valeur réellement en base.');
        self::assertTrue($connexion->isTransactionActive(),
            'La transaction de l’appelant a été défaite par la numérotation.');

        $connexion->rollBack();
    }

    /**
     * Conséquence assumée du choix de conception : le numéro est attribué hors
     * de la transaction métier. Si celle-ci échoue, le numéro est perdu — un
     * trou dans la série — mais jamais réattribué à quelqu'un d'autre.
     *
     * Ce test documente ce compromis autant qu'il le vérifie.
     */
    public function testUnNumeroAttribueNEstPasRendueApresUnEchec(): void
    {
        $connexion = $this->em->getConnection();
        $cle = $this->cle();

        $connexion->beginTransaction();
        $attribue = $this->numerotation->incrementer($cle, 'TST', 2026);
        $connexion->rollBack();

        $suivant = $this->numerotation->incrementer($cle, 'TST', 2026);

        self::assertSame($attribue + 1, $suivant,
            'Deux demandeurs ne doivent jamais recevoir le même numéro.');
    }

    /**
     * L'entité déjà chargée par Doctrine doit refléter la valeur écrite par
     * l'autre connexion, sinon un écran afficherait un numéro périmé.
     */
    public function testLEntiteDejaChargeeEstRafraichie(): void
    {
        $cle = $this->cle();
        $this->numerotation->incrementer($cle, 'TST', 2026);

        $compteur = $this->em->find(Compteur::class, $cle);
        self::assertInstanceOf(Compteur::class, $compteur);
        self::assertSame(1, $compteur->getValeur());

        $this->numerotation->incrementer($cle, 'TST', 2026);

        self::assertSame(2, $compteur->getValeur(),
            'L’entité en mémoire porte encore l’ancienne valeur.');
    }

    /** Une clé par test, pour qu'ils ne se gênent pas entre eux. */
    private function cle(): string
    {
        return 'TEST-CONTENTION-'.bin2hex(random_bytes(6));
    }

    private function connexionSeparee(): \Doctrine\DBAL\Connection
    {
        $principale = $this->em->getConnection();

        return \Doctrine\DBAL\DriverManager::getConnection(
            $principale->getParams(), $principale->getConfiguration());
    }
}
