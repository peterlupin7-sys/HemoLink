<?php

namespace App\Controller;

use App\Entity\CreneauDon;
use App\Entity\Donneur;
use App\Entity\RendezVousDon;
use App\Entity\Utilisateur;
use App\Exception\RegleMetier;
use App\Repository\BanqueRepository;
use App\Repository\CreneauDonRepository;
use App\Repository\DonneurRepository;
use App\Repository\DonRepository;
use App\Repository\ParametreRepository;
use App\Repository\RendezVousDonRepository;
use App\Service\RendezVousService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Espace personnel du donneur : ses rendez-vous, et rien que les siens.
 *
 * Un donneur n'a aucune raison de voir les rendez-vous des autres, ni les
 * stocks, ni les précommandes. Chaque action vérifie donc que l'objet manipulé
 * lui appartient bien avant d'aller plus loin.
 */
#[Route('/espace-donneur')]
#[IsGranted('ROLE_DONNEUR')]
class EspaceDonneurController extends AbstractController
{
    #[Route('', name: 'espace_donneur', methods: ['GET'])]
    public function index(
        DonneurRepository $donneurs,
        RendezVousDonRepository $rendezVous,
        DonRepository $dons,
        ParametreRepository $parametres,
    ): Response {
        $donneur = $this->donneurCourant($donneurs);
        $delai = $parametres->entier('delai_entre_dons_jours', 90);
        $tous = $rendezVous->pourDonneur($donneur);

        $aVenir = array_filter($tous, static fn (RendezVousDon $r) => RendezVousDon::PLANIFIE === $r->getStatut()
            && !$r->getCreneau()->estPasse());

        return $this->render('donneur/index.html.twig', [
            'donneur' => $donneur,
            'rendez_vous_a_venir' => $aVenir,
            'historique' => array_filter($tous,
                static fn (RendezVousDon $r) => !\in_array($r, $aVenir, true)),
            'dons' => $dons->findBy(['donneur' => $donneur], ['dateDon' => 'DESC'], 10),
            'delai_entre_dons' => $delai,
            'jours_restants' => $donneur->joursAvantProchainDon($delai),
            'delai_annulation' => $parametres->entier('delai_annulation_rdv_h', 12),
        ]);
    }

    #[Route('/creneaux', name: 'espace_donneur_creneaux', methods: ['GET'])]
    public function creneaux(
        Request $requete,
        DonneurRepository $donneurs,
        CreneauDonRepository $creneaux,
        BanqueRepository $banques,
        RendezVousService $service,
        ParametreRepository $parametres,
    ): Response {
        $donneur = $this->donneurCourant($donneurs);
        $banqueId = $requete->query->getInt('banque');
        $banque = $banqueId ? $banques->find($banqueId) : null;

        // Le donneur voit les créneaux même s'il n'est pas encore éligible :
        // mieux vaut lui dire pourquoi il ne peut pas réserver que lui cacher
        // la page et le laisser deviner.
        $blocage = null;

        try {
            $service->verifierEligibilite($donneur);
        } catch (RegleMetier $e) {
            $blocage = $e->getMessage();
        }

        return $this->render('donneur/creneaux.html.twig', [
            'donneur' => $donneur,
            'creneaux' => $creneaux->disponibles($banque,
                $parametres->entier('horizon_creneaux_jours', 30)),
            'banques' => $banques->findBy(['statut' => 'ACTIVE'], ['nom' => 'ASC']),
            'banque_choisie' => $banque,
            'blocage' => $blocage,
        ]);
    }

    #[Route('/creneaux/{id}/reserver', name: 'espace_donneur_reserver', methods: ['POST'])]
    public function reserver(
        CreneauDon $creneau,
        Request $requete,
        DonneurRepository $donneurs,
        RendezVousService $service,
    ): Response {
        if (!$this->isCsrfTokenValid('reserver'.$creneau->getId(),
            (string) $requete->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton invalide.');
        }

        $donneur = $this->donneurCourant($donneurs);

        try {
            $rendezVous = $service->reserver($creneau, $donneur);
            $this->addFlash('succes', sprintf(
                'Rendez-vous %s confirmé le %s à %s. Un SMS vous a été envoyé.',
                $rendezVous->getNumero(),
                $creneau->getDateCreneau()->format('d/m/Y'),
                substr($creneau->getHeureDebut(), 0, 5)));
        } catch (RegleMetier $e) {
            $this->addFlash('erreur', $e->getMessage());

            return $this->redirectToRoute('espace_donneur_creneaux');
        }

        return $this->redirectToRoute('espace_donneur');
    }

    #[Route('/rendez-vous/{id}/annuler', name: 'espace_donneur_annuler', methods: ['POST'])]
    public function annuler(
        RendezVousDon $rendezVous,
        Request $requete,
        DonneurRepository $donneurs,
        RendezVousService $service,
    ): Response {
        $this->verifierProprietaire($rendezVous, $donneurs);

        if (!$this->isCsrfTokenValid('annuler'.$rendezVous->getId(),
            (string) $requete->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton invalide.');
        }

        try {
            $service->annuler($rendezVous, 'Annulé par le donneur');
            $this->addFlash('succes', 'Votre rendez-vous est annulé. '
                .'La place est rendue à un autre donneur.');
        } catch (RegleMetier $e) {
            $this->addFlash('erreur', $e->getMessage());
        }

        return $this->redirectToRoute('espace_donneur');
    }

    #[Route('/rendez-vous/{id}/deplacer', name: 'espace_donneur_deplacer', methods: ['POST'])]
    public function deplacer(
        RendezVousDon $rendezVous,
        Request $requete,
        DonneurRepository $donneurs,
        CreneauDonRepository $creneaux,
        RendezVousService $service,
    ): Response {
        $this->verifierProprietaire($rendezVous, $donneurs);

        if (!$this->isCsrfTokenValid('deplacer'.$rendezVous->getId(),
            (string) $requete->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton invalide.');
        }

        $nouveau = $creneaux->find($requete->request->getInt('creneau'));

        if (!$nouveau) {
            $this->addFlash('erreur', 'Choisissez un nouveau créneau.');

            return $this->redirectToRoute('espace_donneur');
        }

        try {
            $remplacant = $service->deplacer($rendezVous, $nouveau);
            $this->addFlash('succes', sprintf('Rendez-vous déplacé : %s le %s à %s.',
                $remplacant->getNumero(),
                $nouveau->getDateCreneau()->format('d/m/Y'),
                substr($nouveau->getHeureDebut(), 0, 5)));
        } catch (RegleMetier $e) {
            $this->addFlash('erreur', $e->getMessage());
        }

        return $this->redirectToRoute('espace_donneur');
    }

    /**
     * Le profil donneur rattaché au compte connecté.
     */
    private function donneurCourant(DonneurRepository $donneurs): Donneur
    {
        /** @var Utilisateur $utilisateur */
        $utilisateur = $this->getUser();
        $donneur = $donneurs->parUtilisateur($utilisateur);

        if (!$donneur) {
            throw $this->createNotFoundException(
                'Aucun profil de donneur n’est rattaché à ce compte. '
                .'Présentez-vous à une banque de sang pour l’activer.');
        }

        return $donneur;
    }

    private function verifierProprietaire(RendezVousDon $rendezVous,
        DonneurRepository $donneurs): void
    {
        if ($rendezVous->getDonneur() !== $this->donneurCourant($donneurs)) {
            throw $this->createAccessDeniedException(
                'Ce rendez-vous n’est pas le vôtre.');
        }
    }
}
