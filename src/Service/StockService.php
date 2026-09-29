<?php

namespace App\Service;

use App\Entity\Banque;
use App\Entity\GroupeSanguin;
use App\Entity\MouvementStock;
use App\Entity\Poche;
use App\Entity\Precommande;
use App\Entity\Stock;
use App\Entity\Utilisateur;
use App\Repository\ParametreRepository;
use App\Repository\PocheRepository;
use App\Repository\StockRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Point de passage obligé de toute variation de stock.
 *
 * Règle du projet : aucun contrôleur, aucune commande n'écrit directement dans
 * la table `stock`. Tout passe ici, pour que l'historique et l'état restent vrais.
 */
class StockService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly StockRepository $stocks,
        private readonly PocheRepository $poches,
        private readonly ParametreRepository $parametres,
    ) {
    }

    /**
     * Calcule l'état affiché par la carte à partir de la quantité et de la fraîcheur.
     */
    public function calculerEtat(int $quantite, \DateTimeInterface $dateMaj): string
    {
        $delai = $this->parametres->entier('delai_stock_obsolete_h', 24);
        $limite = new \DateTimeImmutable(sprintf('-%d hours', $delai));

        if ($dateMaj < $limite) {
            return Stock::NON_COMMUNIQUE;
        }

        $seuil = $this->parametres->entier('seuil_tension', 5);

        if (0 === $quantite) {
            return Stock::RUPTURE;
        }

        return $quantite < $seuil ? Stock::TENSION : Stock::DISPONIBLE;
    }

    public function stockDe(Banque $banque, GroupeSanguin $groupe): Stock
    {
        $stock = $this->stocks->findOneBy(['banque' => $banque, 'groupeSanguin' => $groupe]);

        if (!$stock) {
            $stock = (new Stock())
                ->setBanque($banque)
                ->setGroupeSanguin($groupe)
                ->setDateMaj(new \DateTimeImmutable());
            $this->em->persist($stock);
        }

        return $stock;
    }

    /**
     * Entrée de poches après une collecte.
     *
     * @return array<int, Poche> les poches créées
     */
    public function enregistrerEntree(
        Banque $banque,
        GroupeSanguin $groupe,
        int $quantite,
        \DateTimeInterface $datePrelevement,
        ?Utilisateur $auteur = null,
        string $motif = 'Collecte',
        int $volumeMl = 450,
    ): array {
        if ($quantite < 1) {
            throw new \InvalidArgumentException('La quantité doit être positive.');
        }

        $dureeConservation = $this->parametres->entier('duree_conservation_jours', 42);
        $peremption = (new \DateTimeImmutable($datePrelevement->format('Y-m-d')))
            ->modify(sprintf('+%d days', $dureeConservation));

        $creees = [];
        for ($i = 0; $i < $quantite; ++$i) {
            $poche = (new Poche())
                ->setCodePoche($this->codePoche($banque))
                ->setBanque($banque)
                ->setGroupeSanguin($groupe)
                ->setDatePrelevement($datePrelevement)
                ->setDatePeremption($peremption)
                ->setVolumeMl($volumeMl)
                ->setStatut(Poche::DISPONIBLE)
                ->setDateCreation(new \DateTimeImmutable());
            $this->em->persist($poche);
            $creees[] = $poche;
        }

        $this->recalculer($banque, $groupe, MouvementStock::ENTREE, $quantite, $auteur, $motif);

        return $creees;
    }

    /**
     * Ajustement manuel par un agent : on vise une quantité, le service fait la
     * différence et trace le mouvement.
     */
    public function ajuster(
        Banque $banque,
        GroupeSanguin $groupe,
        int $quantiteVoulue,
        ?Utilisateur $auteur = null,
        string $motif = 'Ajustement manuel',
    ): Stock {
        $stock = $this->stockDe($banque, $groupe);
        $actuelle = $stock->getQuantiteDisponible();
        $ecart = $quantiteVoulue - $actuelle;

        if ($ecart > 0) {
            $this->enregistrerEntree($banque, $groupe, $ecart, new \DateTimeImmutable(),
                $auteur, $motif);

            return $this->stockDe($banque, $groupe);
        }

        if ($ecart < 0) {
            foreach ($this->poches->disponibles($banque->getId(), $groupe->getId(), -$ecart) as $poche) {
                $poche->setStatut(Poche::DETRUITE);
            }
        }

        $this->recalculer($banque, $groupe, MouvementStock::AJUSTEMENT, $ecart, $auteur, $motif);

        return $stock;
    }

    /**
     * Réserve des poches pour une précommande confirmée.
     *
     * @return array<int, Poche>
     */
    public function reserver(
        Precommande $precommande,
        GroupeSanguin $groupe,
        int $quantite,
        ?Utilisateur $auteur = null,
    ): array {
        $banque = $precommande->getBanque();
        $disponibles = $this->poches->disponibles($banque->getId(), $groupe->getId(), $quantite);

        if (\count($disponibles) < $quantite) {
            throw new \RuntimeException(sprintf(
                'Stock insuffisant : %d poche(s) de %s disponible(s) sur %d demandée(s).',
                \count($disponibles), $groupe->getCode(), $quantite
            ));
        }

        foreach ($disponibles as $poche) {
            $poche->setStatut(Poche::RESERVEE)->setPrecommande($precommande);
        }

        $this->recalculer($banque, $groupe, MouvementStock::RESERVATION, -$quantite, $auteur,
            'Précommande '.$precommande->getNumero(), $precommande);

        return $disponibles;
    }

    /**
     * Libère les poches d'une précommande annulée ou expirée.
     */
    public function liberer(Precommande $precommande, ?Utilisateur $auteur = null): int
    {
        $liberees = 0;
        $parGroupe = [];

        foreach ($precommande->getPoches() as $poche) {
            if (Poche::RESERVEE !== $poche->getStatut()) {
                continue;
            }
            $poche->setStatut(Poche::DISPONIBLE)->setPrecommande(null);
            $code = $poche->getGroupeSanguin()->getCode();
            $parGroupe[$code] ??= ['groupe' => $poche->getGroupeSanguin(), 'n' => 0];
            ++$parGroupe[$code]['n'];
            ++$liberees;
        }

        foreach ($parGroupe as $entree) {
            $this->recalculer($precommande->getBanque(), $entree['groupe'],
                MouvementStock::LIBERATION, $entree['n'], $auteur,
                'Libération '.$precommande->getNumero(), $precommande);
        }

        return $liberees;
    }

    /**
     * Sortie définitive : les poches réservées sont délivrées au demandeur.
     */
    public function delivrer(Precommande $precommande, ?Utilisateur $auteur = null): int
    {
        $delivrees = 0;
        $parGroupe = [];

        foreach ($precommande->getPoches() as $poche) {
            if (Poche::RESERVEE !== $poche->getStatut()) {
                continue;
            }
            $poche->setStatut(Poche::DELIVREE);
            $code = $poche->getGroupeSanguin()->getCode();
            $parGroupe[$code] ??= ['groupe' => $poche->getGroupeSanguin(), 'n' => 0];
            ++$parGroupe[$code]['n'];
            ++$delivrees;
        }

        foreach ($parGroupe as $entree) {
            $this->recalculer($precommande->getBanque(), $entree['groupe'],
                MouvementStock::SORTIE, 0, $auteur,
                'Retrait '.$precommande->getNumero(), $precommande);
        }

        return $delivrees;
    }

    /**
     * Marque les poches périmées et met les stocks à jour.
     */
    public function marquerPerimees(): int
    {
        $total = 0;
        $parCle = [];

        foreach ($this->poches->bientotPerimees(0) as $poche) {
            if (!$poche->estPerimee()) {
                continue;
            }
            $poche->setStatut(Poche::PERIMEE);
            $cle = $poche->getBanque()->getId().'-'.$poche->getGroupeSanguin()->getId();
            $parCle[$cle] ??= ['banque' => $poche->getBanque(),
                'groupe' => $poche->getGroupeSanguin(), 'n' => 0];
            ++$parCle[$cle]['n'];
            ++$total;
        }

        foreach ($parCle as $entree) {
            $this->recalculer($entree['banque'], $entree['groupe'], MouvementStock::PEREMPTION,
                -$entree['n'], null, 'Péremption automatique');
        }

        return $total;
    }

    /**
     * Recompte les poches et réécrit la ligne de stock. C'est la seule méthode
     * qui touche aux quantités : elles sont toujours déduites du réel.
     */
    public function recalculer(
        Banque $banque,
        GroupeSanguin $groupe,
        string $typeMouvement,
        int $quantiteMouvement,
        ?Utilisateur $auteur = null,
        string $motif = '',
        ?Precommande $precommande = null,
    ): Stock {
        $this->em->flush();

        $disponibles = \count($this->poches->disponibles($banque->getId(), $groupe->getId()));
        $reservees = $this->poches->count([
            'banque' => $banque, 'groupeSanguin' => $groupe, 'statut' => Poche::RESERVEE,
        ]);

        $maintenant = new \DateTimeImmutable();
        $stock = $this->stockDe($banque, $groupe)
            ->setQuantiteDisponible($disponibles)
            ->setQuantiteReservee($reservees)
            ->setDateMaj($maintenant);
        $stock->setEtat($this->calculerEtat($disponibles, $maintenant));

        $mouvement = (new MouvementStock())
            ->setBanque($banque)
            ->setGroupeSanguin($groupe)
            ->setType($typeMouvement)
            ->setQuantite($quantiteMouvement)
            ->setQuantiteApres($disponibles)
            ->setUtilisateur($auteur)
            ->setPrecommande($precommande)
            ->setMotif($motif ?: null)
            ->setDateMouvement($maintenant);

        $this->em->persist($mouvement);
        $this->em->flush();

        return $stock;
    }

    private function codePoche(Banque $banque): string
    {
        return sprintf('PO-%02d-%s', $banque->getId(), strtoupper(bin2hex(random_bytes(4))));
    }
}
