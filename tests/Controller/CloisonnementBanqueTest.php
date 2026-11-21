<?php

namespace App\Tests\Controller;

use App\Entity\Don;
use App\Entity\Utilisateur;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Chaque banque ne voit que ses propres dons.
 *
 * L'écran de qualification filtre bien la liste par banque, mais l'adresse
 * d'un dossier reste devinable : /qualification/63 ouvrait le dossier d'un don
 * prélevé dans une autre banque, analyses comprises. Ces tests ferment la
 * porte et doivent rester verts.
 *
 * Ils supposent la base de test chargée :
 *   php bin/console doctrine:migrations:migrate -e test --no-interaction
 *   php bin/console doctrine:fixtures:load -e test --no-interaction
 */
class CloisonnementBanqueTest extends WebTestCase
{
    private function connecter(KernelBrowser $client, string $email): Utilisateur
    {
        /** @var UtilisateurRepository $depot */
        $depot = static::getContainer()->get(UtilisateurRepository::class);
        $utilisateur = $depot->findOneBy(['email' => $email]);

        self::assertNotNull($utilisateur, sprintf(
            'Le compte %s est absent : chargez les fixtures sur la base de test.', $email));

        $client->loginUser($utilisateur);

        return $utilisateur;
    }

    /**
     * Un don prélevé dans une banque différente de celle du biologiste.
     */
    private function donDUneAutreBanque(Utilisateur $biologiste): Don
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $don = $em->createQueryBuilder()
            ->select('d')->from(Don::class, 'd')
            ->where('d.banque != :banque')
            ->setParameter('banque', $biologiste->getBanque())
            ->setMaxResults(1)
            ->getQuery()->getOneOrNullResult();

        self::assertNotNull($don, 'Le jeu de démonstration doit contenir des dons dans plusieurs banques.');

        return $don;
    }

    public function testUnBiologisteNOuvrePasLeDossierDUneAutreBanque(): void
    {
        $client = static::createClient();
        $biologiste = $this->connecter($client, 'biologiste.cnts@hemolink.bj');
        $don = $this->donDUneAutreBanque($biologiste);

        $client->request('GET', '/qualification/'.$don->getId());

        self::assertResponseStatusCodeSame(403,
            'Le dossier d’un don prélevé ailleurs ne doit pas s’ouvrir.');
    }

    public function testUnBiologisteNePrononcePasSurUneAutreBanque(): void
    {
        $client = static::createClient();
        $biologiste = $this->connecter($client, 'biologiste.cnts@hemolink.bj');
        $don = $this->donDUneAutreBanque($biologiste);
        $avant = $don->getStatut();

        $client->request('POST', '/qualification/'.$don->getId().'/prononcer', [
            'vih' => 'NEGATIF',
            'hepatiteB' => 'NEGATIF',
            'hepatiteC' => 'NEGATIF',
            'syphilis' => 'NEGATIF',
        ]);

        self::assertResponseStatusCodeSame(403);

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        $relu = $em->getRepository(Don::class)->find($don->getId());

        self::assertSame($avant, $relu?->getStatut(),
            'Le refus doit être total : le statut du don ne change pas.');
    }

    public function testUnDonneurNAccedePasALaQualification(): void
    {
        $client = static::createClient();
        $this->connecter($client, 'donneur.sossou@hemolink.bj');

        $client->request('GET', '/qualification');

        self::assertResponseStatusCodeSame(403);
    }

    public function testUnBiologisteNAccedePasALaGestionDeCollecte(): void
    {
        $client = static::createClient();
        $this->connecter($client, 'biologiste.cnts@hemolink.bj');

        $client->request('GET', '/gestion/collecte');

        self::assertResponseStatusCodeSame(403,
            'ROLE_PERSONNEL est volontairement hors de la hiérarchie des gestionnaires.');
    }
}
