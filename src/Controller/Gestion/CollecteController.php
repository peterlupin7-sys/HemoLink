<?php

namespace App\Controller\Gestion;

use App\Entity\Banque;
use App\Entity\CreneauDon;
use App\Entity\Donneur;
use App\Entity\RendezVousDon;
use App\Entity\Utilisateur;
use App\Repository\CreneauDonRepository;
use App\Repository\DonneurRepository;
use App\Repository\DonRepository;
use App\Repository\GroupeSanguinRepository;
use App\Repository\ParametreRepository;
use App\Repository\RendezVousDonRepository;
use App\Service\DonService;
use App\Service\JournalService;
use App\Service\RendezVousService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Côté banque : ouverture des créneaux, accueil des donneurs, enregistrement
 * des prélèvements.
 *
 * L'agent travaille ici avec la liste du jour sous les yeux ; le gestionnaire
 * y ajoute l'ouverture de nouveaux créneaux.
 */
#[Route('/gestion/collecte')]
#[IsGranted('ROLE_AGENT')]
class CollecteController extends AbstractController
{
    #[Route('', name: 'gestion_collecte', methods: ['GET'])]
    public function index(
        Request $requete,
        CreneauDonRepository $creneaux,
        RendezVousDonRepository $rendezVous,
        DonRepository $dons,
        DonneurRepository $donneurs,
        GroupeSanguinRepository $groupes,
    ): Response {
        $banque = $this->banqueCourante();
        $recherche = trim((string) $requete->query->get('q'));

        return $this->render('gestion/collecte.html.twig', [
            'banque' => $banque,
            'creneaux' => $creneaux->pourBanque($banque, 2, 21),
            'creneaux_jour' => $creneaux->duJour($banque),
            'attendus' => $rendezVous->duJour($banque),
            'a_pointer' => $rendezVous->aPointer($banque),
            'dons_recents' => $dons->recents($banque, 12),
            'par_statut' => $dons->compterParStatut($banque),
            'dons_jour' => $dons->compterDepuis(new \DateTimeImmutable('today'), $banque),
            'resultats' => '' !== $recherche ? $donneurs->rechercher($recherche) : [],
            'recherche' => $recherche,
            'groupes' => $groupes->findBy([], ['code' => 'ASC']),
        ]);
    }

    #[Route('/creneaux/nouveau', name: 'gestion_creneau_nouveau', methods: ['POST'])]
    #[IsGranted('ROLE_GESTIONNAIRE')]
    public function ouvrirCreneau(
        Request $requete,
        EntityManagerInterface $em,
        ParametreRepository $parametres,
        JournalService $journal,
    ): Response {
        if (!$this->isCsrfTokenValid('creneau_nouveau',
            (string) $requete->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton invalide.');
        }

        $banque = $this->banqueCourante();
        $date = (string) $requete->request->get('date_creneau');
        $debut = (string) $requete->request->get('heure_debut');
        $fin = (string) $requete->request->get('heure_fin');

        if (!$date || !$debut || !$fin) {
            $this->addFlash('erreur', 'Date et horaires sont obligatoires.');

            return $this->redirectToRoute('gestion_collecte');
        }

        $creneau = (new CreneauDon())
            ->setBanque($banque)
            ->setDateCreneau(new \DateTimeImmutable($date))
            ->setHeureDebut($this->normaliserHeure($debut))
            ->setHeureFin($this->normaliserHeure($fin))
            ->setCapacite(max(1, $requete->request->getInt('capacite',
                $parametres->entier('capacite_creneau_defaut', 8))))
            ->setPlacesReservees(0)
            ->setLieu(trim((string) $requete->request->get('lieu')) ?: null)
            ->setStatut(CreneauDon::OUVERT)
            ->setDateCreation(new \DateTimeImmutable());

        $em->persist($creneau);
        $em->flush();

        $journal->tracer('CRENEAU_OUVERT', 'CreneauDon', $creneau->getId(),
            sprintf('Créneau %s (%d places)', (string) $creneau, $creneau->getCapacite()));

        $this->addFlash('succes', sprintf('Créneau du %s ouvert : %d places.',
            $creneau->getDateCreneau()->format('d/m/Y'), $creneau->getCapacite()));

        return $this->redirectToRoute('gestion_collecte');
    }

    #[Route('/creneaux/{id}/fermer', name: 'gestion_creneau_fermer', methods: ['POST'])]
    #[IsGranted('ROLE_GESTIONNAIRE')]
    public function fermerCreneau(
        CreneauDon $creneau,
        Request $requete,
        EntityManagerInterface $em,
        RendezVousService $service,
        JournalService $journal,
    ): Response {
        $this->verifierBanque($creneau->getBanque());

        if (!$this->isCsrfTokenValid('creneau'.$creneau->getId(),
            (string) $requete->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton invalide.');
        }

        /** @var Utilisateur $utilisateur */
        $utilisateur = $this->getUser();
        $annules = 0;

        // Annuler le créneau sans prévenir les donneurs serait les envoyer
        // devant une porte fermée : chaque rendez-vous est annulé proprement,
        // ce qui déclenche son SMS.
        foreach ($creneau->getRendezVous() as $rendezVous) {
            if (RendezVousDon::PLANIFIE === $rendezVous->getStatut()) {
                $service->annuler($rendezVous, 'Créneau annulé par la banque', $utilisateur);
                ++$annules;
            }
        }

        $creneau->setStatut(CreneauDon::ANNULE);
        $em->flush();

        $journal->tracer('CRENEAU_ANNULE', 'CreneauDon', $creneau->getId(),
            sprintf('Créneau %s annulé, %d rendez-vous reportés', (string) $creneau, $annules));

        $this->addFlash('succes', sprintf(
            'Créneau annulé. %d donneur(s) ont été prévenus par SMS.', $annules));

        return $this->redirectToRoute('gestion_collecte');
    }

    #[Route('/rendez-vous/{id}/{action}', name: 'gestion_rdv_pointer', methods: ['POST'],
        requirements: ['action' => 'present|absent'])]
    public function pointer(
        RendezVousDon $rendezVous,
        string $action,
        Request $requete,
        RendezVousService $service,
    ): Response {
        $this->verifierBanque($rendezVous->getCreneau()?->getBanque());

        if (!$this->isCsrfTokenValid('pointer'.$rendezVous->getId(),
            (string) $requete->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton invalide.');
        }

        /** @var Utilisateur $utilisateur */
        $utilisateur = $this->getUser();

        if ('present' === $action) {
            $service->marquerHonore($rendezVous, $utilisateur);
            $this->addFlash('succes', sprintf('%s est pointé présent.',
                $rendezVous->getDonneur()?->getNomComplet() ?? 'Le donneur'));
        } else {
            $service->marquerAbsent($rendezVous, $utilisateur);
            $this->addFlash('succes', 'Absence enregistrée, la place est libérée.');
        }

        return $this->redirectToRoute('gestion_collecte');
    }

    #[Route('/dons/nouveau', name: 'gestion_don_nouveau', methods: ['POST'])]
    public function enregistrerDon(
        Request $requete,
        DonneurRepository $donneurs,
        RendezVousDonRepository $rendezVousDepot,
        GroupeSanguinRepository $groupes,
        DonService $service,
    ): Response {
        if (!$this->isCsrfTokenValid('don_nouveau',
            (string) $requete->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton invalide.');
        }

        $banque = $this->banqueCourante();
        $donneur = $donneurs->find($requete->request->getInt('donneur'));

        if (!$donneur instanceof Donneur) {
            $this->addFlash('erreur', 'Sélectionnez le donneur avant d’enregistrer le don.');

            return $this->redirectToRoute('gestion_collecte');
        }

        $rendezVous = null;
        if ($id = $requete->request->getInt('rendez_vous')) {
            $rendezVous = $rendezVousDepot->find($id);
        }

        /** @var Utilisateur $utilisateur */
        $utilisateur = $this->getUser();
        $hemoglobine = (string) $requete->request->get('hemoglobine');

        $don = $service->enregistrer(
            $donneur,
            $banque,
            $rendezVous,
            $groupes->find($requete->request->getInt('groupe')),
            '' !== $hemoglobine ? (float) str_replace(',', '.', $hemoglobine) : null,
            max(200, $requete->request->getInt('volume', 450)),
            $utilisateur,
            trim((string) $requete->request->get('observation')) ?: null,
        );

        $this->addFlash('succes', sprintf(
            'Don %s enregistré. Il reste en attente de qualification : aucune poche '
            .'n’entrera au stock avant la validation du laboratoire.', $don->getCodeDon()));

        return $this->redirectToRoute('gestion_collecte');
    }

    private function banqueCourante(): Banque
    {
        /** @var Utilisateur $utilisateur */
        $utilisateur = $this->getUser();
        $banque = $utilisateur->getBanque();

        if (!$banque) {
            throw $this->createAccessDeniedException(
                'Votre compte n’est rattaché à aucune banque de sang.');
        }

        return $banque;
    }

    private function verifierBanque(?Banque $banque): void
    {
        if (!$banque || $banque->getId() !== $this->banqueCourante()->getId()) {
            throw $this->createAccessDeniedException(
                'Cet élément appartient à une autre banque de sang.');
        }
    }

    private function normaliserHeure(string $heure): string
    {
        return 5 === \strlen($heure) ? $heure.':00' : $heure;
    }
}
