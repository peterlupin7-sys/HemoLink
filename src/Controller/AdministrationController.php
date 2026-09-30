<?php

namespace App\Controller;

use App\Entity\Banque;
use App\Form\BanqueType;
use App\Repository\BanqueRepository;
use App\Repository\JournalRepository;
use App\Repository\PrecommandeRepository;
use App\Repository\StockRepository;
use App\Repository\UtilisateurRepository;
use App\Service\JournalService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Administration nationale (CNTS) : vue d'ensemble, banques, comptes, journal.
 */
#[Route('/administration')]
#[IsGranted('ROLE_ADMIN')]
class AdministrationController extends AbstractController
{
    #[Route('', name: 'administration', methods: ['GET'])]
    public function index(
        BanqueRepository $banques,
        StockRepository $stocks,
        PrecommandeRepository $precommandes,
        UtilisateurRepository $utilisateurs,
    ): Response {
        return $this->render('administration/index.html.twig', [
            'banques' => $banques->toutesActives(),
            'totaux_etat' => $stocks->totauxParEtat(),
            'ruptures' => $stocks->enRupture(),
            'par_statut' => $precommandes->compterParStatut(),
            'precommandes_jour' => $precommandes->compterDepuis(new \DateTimeImmutable('today')),
            'nb_utilisateurs' => \count($utilisateurs->findBy(['actif' => true])),
        ]);
    }

    #[Route('/banques', name: 'administration_banques', methods: ['GET'])]
    public function banques(BanqueRepository $banques): Response
    {
        return $this->render('administration/banques.html.twig', [
            'banques' => $banques->findBy([], ['ville' => 'ASC', 'nom' => 'ASC']),
        ]);
    }

    #[Route('/banques/nouvelle', name: 'administration_banque_nouvelle', methods: ['GET', 'POST'])]
    #[Route('/banques/{id}/modifier', name: 'administration_banque_modifier',
        requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function editerBanque(
        Request $requete,
        EntityManagerInterface $em,
        JournalService $journal,
        ?Banque $banque = null,
    ): Response {
        $creation = null === $banque;
        $banque ??= (new Banque())->setDateCreation(new \DateTimeImmutable());

        $formulaire = $this->createForm(BanqueType::class, $banque);
        $formulaire->handleRequest($requete);

        if ($formulaire->isSubmitted() && $formulaire->isValid()) {
            $banque->setDateMaj(new \DateTimeImmutable());

            if ($creation) {
                $em->persist($banque);
            }

            $em->flush();

            $journal->tracer($creation ? 'CREATION' : 'MODIFICATION', 'Banque',
                $banque->getId(), $banque->getNom());

            $this->addFlash('succes', $creation ? 'Banque créée.' : 'Banque mise à jour.');

            return $this->redirectToRoute('administration_banques');
        }

        return $this->render('administration/banque_formulaire.html.twig', [
            'formulaire' => $formulaire,
            'banque' => $banque,
            'creation' => $creation,
        ]);
    }

    #[Route('/utilisateurs', name: 'administration_utilisateurs', methods: ['GET'])]
    public function utilisateurs(UtilisateurRepository $utilisateurs): Response
    {
        return $this->render('administration/utilisateurs.html.twig', [
            'utilisateurs' => $utilisateurs->findBy([], ['nom' => 'ASC']),
        ]);
    }

    #[Route('/journal', name: 'administration_journal', methods: ['GET'])]
    public function journal(JournalRepository $journal): Response
    {
        return $this->render('administration/journal.html.twig', [
            'entrees' => $journal->derniers(100),
        ]);
    }
}
