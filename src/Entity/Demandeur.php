<?php

namespace App\Entity;

use App\Repository\DemandeurRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Établissement de soins ou particulier qui précommande des poches.
 */
#[ORM\Entity(repositoryClass: DemandeurRepository::class)]
#[ORM\Table(name: 'demandeur')]
class Demandeur
{
    public const TYPES = ['ETABLISSEMENT', 'PARTICULIER'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20)]
    private string $type = 'ETABLISSEMENT';

    #[ORM\Column(length: 150)]
    private string $nom;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $responsable = null;

    #[ORM\Column(length: 25)]
    private string $telephone;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(length: 200, nullable: true)]
    private ?string $adresse = null;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $ville = null;

    #[ORM\Column(name: 'numero_agrement', length: 40, nullable: true)]
    private ?string $numeroAgrement = null;

    #[ORM\Column(name: 'date_creation', type: 'datetime')]
    private \DateTimeInterface $dateCreation;

    #[ORM\OneToMany(mappedBy: 'demandeur', targetEntity: Precommande::class)]
    private Collection $precommandes;

    public function __construct()
    {
        $this->precommandes = new ArrayCollection();
        $this->dateCreation = new \DateTimeImmutable();
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

    public function getNom(): string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    public function getResponsable(): ?string
    {
        return $this->responsable;
    }

    public function setResponsable(?string $responsable): static
    {
        $this->responsable = $responsable;

        return $this;
    }

    public function getTelephone(): string
    {
        return $this->telephone;
    }

    public function setTelephone(string $telephone): static
    {
        $this->telephone = $telephone;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getAdresse(): ?string
    {
        return $this->adresse;
    }

    public function setAdresse(?string $adresse): static
    {
        $this->adresse = $adresse;

        return $this;
    }

    public function getVille(): ?string
    {
        return $this->ville;
    }

    public function setVille(?string $ville): static
    {
        $this->ville = $ville;

        return $this;
    }

    public function getNumeroAgrement(): ?string
    {
        return $this->numeroAgrement;
    }

    public function setNumeroAgrement(?string $numeroAgrement): static
    {
        $this->numeroAgrement = $numeroAgrement;

        return $this;
    }

    public function getDateCreation(): \DateTimeInterface
    {
        return $this->dateCreation;
    }

    public function setDateCreation(\DateTimeInterface $dateCreation): static
    {
        $this->dateCreation = $dateCreation;

        return $this;
    }

    /**
     * @return Collection<int, Precommande>
     */
    public function getPrecommandes(): Collection
    {
        return $this->precommandes;
    }

    public function addPrecommande(Precommande $precommande): static
    {
        if (!$this->precommandes->contains($precommande)) {
            $this->precommandes->add($precommande);
            $precommande->setDemandeur($this);
        }

        return $this;
    }

    public function removePrecommande(Precommande $precommande): static
    {
        if ($this->precommandes->removeElement($precommande) && $precommande->getDemandeur() === $this) {
            $precommande->setDemandeur(null);
        }

        return $this;
    }

    public function __toString(): string
    {
        return (string) $this->nom;
    }
}
