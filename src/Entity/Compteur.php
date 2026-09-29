<?php

namespace App\Entity;

use App\Repository\CompteurRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Numérotation séquentielle sans doublon (verrou pessimiste).
 */
#[ORM\Entity(repositoryClass: CompteurRepository::class)]
#[ORM\Table(name: 'compteur')]
class Compteur
{
    #[ORM\Id]
    #[ORM\Column(length: 60)]
    private ?string $cle = null;

    #[ORM\Column(length: 20)]
    private string $prefixe;

    #[ORM\Column(type: 'smallint')]
    private int $annee;

    #[ORM\Column(type: 'integer')]
    private int $valeur = 0;

    #[ORM\Column(name: 'date_maj', type: 'datetime')]
    private \DateTimeInterface $dateMaj;

    public function __construct()
    {
        $this->dateMaj = new \DateTimeImmutable();
    }

    public function getCle(): ?string
    {
        return $this->cle;
    }

    public function setCle(string $cle): static
    {
        $this->cle = $cle;

        return $this;
    }

    public function getPrefixe(): string
    {
        return $this->prefixe;
    }

    public function setPrefixe(string $prefixe): static
    {
        $this->prefixe = $prefixe;

        return $this;
    }

    public function getAnnee(): int
    {
        return $this->annee;
    }

    public function setAnnee(int $annee): static
    {
        $this->annee = $annee;

        return $this;
    }

    public function getValeur(): int
    {
        return $this->valeur;
    }

    public function setValeur(int $valeur): static
    {
        $this->valeur = $valeur;

        return $this;
    }

    public function getDateMaj(): \DateTimeInterface
    {
        return $this->dateMaj;
    }

    public function setDateMaj(\DateTimeInterface $dateMaj): static
    {
        $this->dateMaj = $dateMaj;

        return $this;
    }
}
