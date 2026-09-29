<?php

namespace App\Entity;

use App\Repository\MouvementStockRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Trace de toute variation de stock.
 */
#[ORM\Entity(repositoryClass: MouvementStockRepository::class)]
#[ORM\Table(name: 'mouvement_stock')]
class MouvementStock
{
    public const ENTREE = 'ENTREE';
    public const SORTIE = 'SORTIE';
    public const RESERVATION = 'RESERVATION';
    public const LIBERATION = 'LIBERATION';
    public const PEREMPTION = 'PEREMPTION';
    public const AJUSTEMENT = 'AJUSTEMENT';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20)]
    private string $type;

    #[ORM\Column(type: 'smallint')]
    private int $quantite;

    #[ORM\Column(name: 'quantite_apres', type: 'smallint')]
    private int $quantiteApres;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $motif = null;

    #[ORM\Column(name: 'date_mouvement', type: 'datetime')]
    private \DateTimeInterface $dateMouvement;

    #[ORM\ManyToOne(targetEntity: Banque::class)]
    #[ORM\JoinColumn(name: 'banque_id', nullable: false)]
    private ?Banque $banque = null;

    #[ORM\ManyToOne(targetEntity: GroupeSanguin::class)]
    #[ORM\JoinColumn(name: 'groupe_sanguin_id', nullable: false)]
    private ?GroupeSanguin $groupeSanguin = null;

    #[ORM\ManyToOne(targetEntity: Poche::class)]
    #[ORM\JoinColumn(name: 'poche_id', nullable: true)]
    private ?Poche $poche = null;

    #[ORM\ManyToOne(targetEntity: Precommande::class)]
    #[ORM\JoinColumn(name: 'precommande_id', nullable: true)]
    private ?Precommande $precommande = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(name: 'utilisateur_id', nullable: true)]
    private ?Utilisateur $utilisateur = null;

    public function __construct()
    {
        $this->dateMouvement = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getQuantite(): int
    {
        return $this->quantite;
    }

    public function setQuantite(int $quantite): static
    {
        $this->quantite = $quantite;

        return $this;
    }

    public function getQuantiteApres(): int
    {
        return $this->quantiteApres;
    }

    public function setQuantiteApres(int $quantiteApres): static
    {
        $this->quantiteApres = $quantiteApres;

        return $this;
    }

    public function getMotif(): ?string
    {
        return $this->motif;
    }

    public function setMotif(?string $motif): static
    {
        $this->motif = $motif;

        return $this;
    }

    public function getDateMouvement(): \DateTimeInterface
    {
        return $this->dateMouvement;
    }

    public function setDateMouvement(\DateTimeInterface $dateMouvement): static
    {
        $this->dateMouvement = $dateMouvement;

        return $this;
    }

    public function getBanque(): ?Banque
    {
        return $this->banque;
    }

    public function setBanque(?Banque $banque): static
    {
        $this->banque = $banque;

        return $this;
    }

    public function getGroupeSanguin(): ?GroupeSanguin
    {
        return $this->groupeSanguin;
    }

    public function setGroupeSanguin(?GroupeSanguin $groupeSanguin): static
    {
        $this->groupeSanguin = $groupeSanguin;

        return $this;
    }

    public function getPoche(): ?Poche
    {
        return $this->poche;
    }

    public function setPoche(?Poche $poche): static
    {
        $this->poche = $poche;

        return $this;
    }

    public function getPrecommande(): ?Precommande
    {
        return $this->precommande;
    }

    public function setPrecommande(?Precommande $precommande): static
    {
        $this->precommande = $precommande;

        return $this;
    }

    public function getUtilisateur(): ?Utilisateur
    {
        return $this->utilisateur;
    }

    public function setUtilisateur(?Utilisateur $utilisateur): static
    {
        $this->utilisateur = $utilisateur;

        return $this;
    }
}
