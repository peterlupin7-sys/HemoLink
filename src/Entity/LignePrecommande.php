<?php

namespace App\Entity;

use App\Repository\LignePrecommandeRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Détail d'une précommande pour un groupe sanguin.
 */
#[ORM\Entity(repositoryClass: LignePrecommandeRepository::class)]
#[ORM\Table(name: 'ligne_precommande')]
class LignePrecommande
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'quantite_demandee', type: 'smallint')]
    private int $quantiteDemandee;

    #[ORM\Column(name: 'quantite_confirmee', type: 'smallint', nullable: true)]
    private ?int $quantiteConfirmee = null;

    #[ORM\ManyToOne(targetEntity: Precommande::class, inversedBy: 'lignes')]
    #[ORM\JoinColumn(name: 'precommande_id', nullable: false)]
    private ?Precommande $precommande = null;

    #[ORM\ManyToOne(targetEntity: GroupeSanguin::class)]
    #[ORM\JoinColumn(name: 'groupe_sanguin_id', nullable: false)]
    private ?GroupeSanguin $groupeSanguin = null;

    #[ORM\ManyToOne(targetEntity: GroupeSanguin::class)]
    #[ORM\JoinColumn(name: 'groupe_substitue_id', nullable: true)]
    private ?GroupeSanguin $groupeSubstitue = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getQuantiteDemandee(): int
    {
        return $this->quantiteDemandee;
    }

    public function setQuantiteDemandee(int $quantiteDemandee): static
    {
        $this->quantiteDemandee = $quantiteDemandee;

        return $this;
    }

    public function getQuantiteConfirmee(): ?int
    {
        return $this->quantiteConfirmee;
    }

    public function setQuantiteConfirmee(?int $quantiteConfirmee): static
    {
        $this->quantiteConfirmee = $quantiteConfirmee;

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

    public function getGroupeSanguin(): ?GroupeSanguin
    {
        return $this->groupeSanguin;
    }

    public function setGroupeSanguin(?GroupeSanguin $groupeSanguin): static
    {
        $this->groupeSanguin = $groupeSanguin;

        return $this;
    }

    public function getGroupeSubstitue(): ?GroupeSanguin
    {
        return $this->groupeSubstitue;
    }

    public function setGroupeSubstitue(?GroupeSanguin $groupeSubstitue): static
    {
        $this->groupeSubstitue = $groupeSubstitue;

        return $this;
    }
}
