<?php

namespace App\Service;

use App\Entity\Precommande;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Cycle de vie d'une précommande.
 *
 * En attente -> Confirmée -> Prête -> Retirée
 *            -> Annulée / Expirée (les poches réservées repartent au stock)
 */
class PrecommandeService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly NumerotationService $numerotation,
        private readonly StockService $stocks,
        private readonly SmsNotifier $sms,
        private readonly JournalService $journal,
    ) {
    }

    /**
     * Enregistre une précommande déposée depuis le site public.
     */
    public function deposer(Precommande $precommande): Precommande
    {
        $precommande
            ->setNumero($this->numerotation->prochainNumeroPrecommande($precommande->getBanque()))
            ->setStatut(Precommande::EN_ATTENTE)
            ->setDateCreation(new \DateTimeImmutable());

        $this->em->persist($precommande);
        $this->em->flush();

        $this->sms->depot($precommande);
        $this->journal->tracer('PRECOMMANDE', 'Precommande', $precommande->getId(),
            'Précommande '.$precommande->getNumero().' déposée');

        return $precommande;
    }

    /**
     * Confirmation par la banque : les poches sont réservées pour de bon.
     *
     * @throws \RuntimeException si le stock ne suffit pas
     */
    public function confirmer(Precommande $precommande, ?Utilisateur $agent = null): Precommande
    {
        if (Precommande::EN_ATTENTE !== $precommande->getStatut()) {
            throw new \LogicException('Seule une précommande en attente peut être confirmée.');
        }

        foreach ($precommande->getLignes() as $ligne) {
            $quantite = $ligne->getQuantiteConfirmee() ?? $ligne->getQuantiteDemandee();
            $groupe = $ligne->getGroupeSubstitue() ?? $ligne->getGroupeSanguin();

            $this->stocks->reserver($precommande, $groupe, $quantite, $agent);
            $ligne->setQuantiteConfirmee($quantite);
        }

        $precommande
            ->setStatut(Precommande::CONFIRMEE)
            ->setUtilisateurTraitant($agent)
            ->setDateConfirmation(new \DateTimeImmutable());

        $this->em->flush();

        $this->sms->confirmation($precommande);
        $this->journal->tracer('CONFIRMATION', 'Precommande', $precommande->getId(),
            'Précommande '.$precommande->getNumero().' confirmée');

        return $precommande;
    }

    public function marquerPrete(Precommande $precommande, ?Utilisateur $agent = null): Precommande
    {
        if (Precommande::CONFIRMEE !== $precommande->getStatut()) {
            throw new \LogicException('Seule une précommande confirmée peut être déclarée prête.');
        }

        $precommande->setStatut(Precommande::PRETE)->setUtilisateurTraitant($agent);
        $this->em->flush();

        $this->sms->prete($precommande);
        $this->journal->tracer('PRETE', 'Precommande', $precommande->getId(),
            'Précommande '.$precommande->getNumero().' prête au retrait');

        return $precommande;
    }

    public function retirer(Precommande $precommande, ?Utilisateur $agent = null): Precommande
    {
        if (!\in_array($precommande->getStatut(), [Precommande::CONFIRMEE, Precommande::PRETE], true)) {
            throw new \LogicException('Cette précommande ne peut pas être retirée.');
        }

        $this->stocks->delivrer($precommande, $agent);

        $precommande
            ->setStatut(Precommande::RETIREE)
            ->setUtilisateurTraitant($agent)
            ->setDateRetrait(new \DateTimeImmutable());

        $this->em->flush();

        $this->journal->tracer('RETRAIT', 'Precommande', $precommande->getId(),
            'Précommande '.$precommande->getNumero().' retirée');

        return $precommande;
    }

    public function annuler(Precommande $precommande, string $raison, ?Utilisateur $agent = null): Precommande
    {
        if (Precommande::RETIREE === $precommande->getStatut()) {
            throw new \LogicException('Une précommande retirée ne peut plus être annulée.');
        }

        $this->stocks->liberer($precommande, $agent);

        $precommande
            ->setStatut(Precommande::ANNULEE)
            ->setCommentaire($raison)
            ->setDateAnnulation(new \DateTimeImmutable());

        $this->em->flush();

        $this->sms->annulation($precommande, $raison);
        $this->journal->tracer('ANNULATION', 'Precommande', $precommande->getId(),
            'Précommande '.$precommande->getNumero().' annulée : '.$raison);

        return $precommande;
    }

    /**
     * Expire les précommandes jamais retirées : le sang ne doit pas rester bloqué.
     */
    public function expirer(Precommande $precommande): Precommande
    {
        $this->stocks->liberer($precommande);

        $precommande
            ->setStatut(Precommande::EXPIREE)
            ->setDateAnnulation(new \DateTimeImmutable());

        $this->em->flush();

        $this->journal->tracer('EXPIRATION', 'Precommande', $precommande->getId(),
            'Précommande '.$precommande->getNumero().' expirée automatiquement');

        return $precommande;
    }
}
