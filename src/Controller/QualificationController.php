<?php

namespace App\Controller;

use App\Entity\Don;
use App\Entity\Qualification;
use App\Entity\Utilisateur;
use App\Repository\DonRepository;
use App\Repository\GroupeSanguinRepository;
use App\Repository\QualificationRepository;
use App\Service\DonService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Espace du personnel habilité : qualification des produits sanguins.
 *
 * Volontairement séparé de /gestion. Le gestionnaire pilote un stock, le
 * personnel habilité engage sa responsabilité sur un résultat d'analyse : ce
 * ne sont pas les mêmes gestes, ce ne sont pas les mêmes écrans.
 */
#[Route('/qualification')]
#[IsGranted('ROLE_PERSONNEL')]
class QualificationController extends AbstractController
{
    #[Route('', name: 'qualification', methods: ['GET'])]
    public function index(
        DonRepository $dons,
        QualificationRepository $qualifications,
        GroupeSanguinRepository $groupes,
    ): Response {
        /** @var Utilisateur $utilisateur */
        $utilisateur = $this->getUser();
        $banque = $utilisateur->getBanque();

        return $this->render('qualification/index.html.twig', [
            'banque' => $banque,
            'file' => $dons->aQualifier($banque),
            'groupes' => $groupes->findBy([], ['code' => 'ASC']),
            'dernieres' => $qualifications->dernieres($banque, 15),
            'totaux' => $qualifications->compterParResultat($banque),
            'tests' => Qualification::TESTS,
        ]);
    }

    #[Route('/{id}', name: 'qualification_don', methods: ['GET'])]
    public function don(Don $don, GroupeSanguinRepository $groupes): Response
    {
        $this->verifierBanque($don);

        return $this->render('qualification/don.html.twig', [
            'don' => $don,
            'groupes' => $groupes->findBy([], ['code' => 'ASC']),
            'tests' => Qualification::TESTS,
        ]);
    }

    #[Route('/{id}/prononcer', name: 'qualification_prononcer', methods: ['POST'])]
    public function prononcer(
        Don $don,
        Request $requete,
        DonService $service,
        GroupeSanguinRepository $groupes,
    ): Response {
        $this->verifierBanque($don);

        if (!$this->isCsrfTokenValid('qualifier'.$don->getId(),
            (string) $requete->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton invalide.');
        }

        /** @var Utilisateur $personnel */
        $personnel = $this->getUser();

        $resultats = [];
        foreach (array_keys(Qualification::TESTS) as $test) {
            $valeur = (string) $requete->request->get($test, Qualification::NON_FAIT);
            $resultats[$test] = \in_array($valeur,
                [Qualification::NEGATIF, Qualification::POSITIF, Qualification::NON_FAIT], true)
                    ? $valeur : Qualification::NON_FAIT;
        }

        $groupe = $groupes->find($requete->request->getInt('groupe')) ?? $don->getGroupeSanguin();
        $motif = trim((string) $requete->request->get('motif_rejet')) ?: null;

        try {
            $qualification = $service->qualifier($don, $personnel, $resultats, $groupe, $motif);
        } catch (\RuntimeException $e) {
            $this->addFlash('erreur', $e->getMessage());

            return $this->redirectToRoute('qualification');
        }

        if ($qualification->estConforme()) {
            $this->addFlash('succes', sprintf(
                'Don %s qualifié conforme : la poche %s rejoint le stock %s.',
                $don->getCodeDon(),
                $qualification->getPoche()?->getCodePoche() ?? '',
                $qualification->getGroupeSanguin()?->getCode() ?? ''));
        } else {
            $this->addFlash('succes', sprintf(
                'Don %s écarté (%s). Aucune poche n’a été ajoutée au stock.',
                $don->getCodeDon(), $qualification->getMotifRejet()));
        }

        return $this->redirectToRoute('qualification');
    }

    /**
     * Un biologiste ne qualifie que les dons de sa propre banque.
     *
     * La liste filtre déjà par banque, mais l'adresse d'un don reste devinable :
     * sans ce contrôle, /qualification/63 ouvrait le dossier d'un don prélevé
     * ailleurs. L'administration nationale, qui n'est rattachée à aucune
     * banque, conserve l'accès complet.
     */
    private function verifierBanque(Don $don): void
    {
        /** @var Utilisateur $utilisateur */
        $utilisateur = $this->getUser();
        $banque = $utilisateur->getBanque();

        if (!$banque) {
            if ($this->isGranted('ROLE_ADMIN')) {
                return;
            }

            throw $this->createAccessDeniedException(
                'Votre compte n\'est rattaché à aucune banque de sang.');
        }

        if ($don->getBanque()?->getId() !== $banque->getId()) {
            throw $this->createAccessDeniedException(
                'Ce don a été prélevé dans une autre banque de sang.');
        }
    }
}
