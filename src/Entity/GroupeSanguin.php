<?php

namespace App\Entity;

use App\Repository\GroupeSanguinRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Les huit groupes du système ABO / Rhésus.
 */
#[ORM\Entity(repositoryClass: GroupeSanguinRepository::class)]
#[ORM\Table(name: 'groupe_sanguin')]
class GroupeSanguin
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 3, unique: true)]
    private string $code;

    #[ORM\Column(name: 'systeme_abo', length: 2)]
    private string $systemeAbo;

    #[ORM\Column(length: 8)]
    private string $rhesus;

    #[ORM\Column(name: 'part_population', type: 'decimal', precision: 5, scale: 2)]
    private string $partPopulation = '0.00';

    #[ORM\Column(type: 'smallint')]
    private int $rang = 0;

    #[ORM\ManyToMany(targetEntity: GroupeSanguin::class)]
    #[ORM\JoinTable(name: 'compatibilite')]
    #[ORM\JoinColumn(name: 'groupe_receveur_id', referencedColumnName: 'id')]
    #[ORM\InverseJoinColumn(name: 'groupe_donneur_id', referencedColumnName: 'id')]
    private Collection $donneursCompatibles;

    public function __construct()
    {
        $this->donneursCompatibles = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;

        return $this;
    }

    public function getSystemeAbo(): string
    {
        return $this->systemeAbo;
    }

    public function setSystemeAbo(string $systemeAbo): static
    {
        $this->systemeAbo = $systemeAbo;

        return $this;
    }

    public function getRhesus(): string
    {
        return $this->rhesus;
    }

    public function setRhesus(string $rhesus): static
    {
        $this->rhesus = $rhesus;

        return $this;
    }

    public function getPartPopulation(): string
    {
        return $this->partPopulation;
    }

    public function setPartPopulation(string $partPopulation): static
    {
        $this->partPopulation = $partPopulation;

        return $this;
    }

    public function getRang(): int
    {
        return $this->rang;
    }

    public function setRang(int $rang): static
    {
        $this->rang = $rang;

        return $this;
    }

    /**
     * @return Collection<int, GroupeSanguin>
     */
    public function getDonneursCompatibles(): Collection
    {
        return $this->donneursCompatibles;
    }

    public function addDonneursCompatible(GroupeSanguin $donneurscompatible): static
    {
        if (!$this->donneursCompatibles->contains($donneurscompatible)) {
            $this->donneursCompatibles->add($donneurscompatible);
        }

        return $this;
    }

    public function removeDonneursCompatible(GroupeSanguin $donneurscompatible): static
    {
        $this->donneursCompatibles->removeElement($donneurscompatible);

        return $this;
    }

    public function __toString(): string
    {
        return (string) $this->code;
    }

    /**
     * Vrai si ce groupe peut recevoir du sang du groupe donné.
     */
    public function peutRecevoirDe(self $donneur): bool
    {
        return $this->donneursCompatibles->contains($donneur);
    }
}
