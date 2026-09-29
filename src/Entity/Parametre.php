<?php

namespace App\Entity;

use App\Repository\ParametreRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Réglage modifiable sans redéploiement.
 */
#[ORM\Entity(repositoryClass: ParametreRepository::class)]
#[ORM\Table(name: 'parametre')]
class Parametre
{
    #[ORM\Id]
    #[ORM\Column(length: 60)]
    private ?string $cle = null;

    #[ORM\Column(length: 255)]
    private string $valeur;

    #[ORM\Column(length: 200)]
    private string $libelle;

    #[ORM\Column(length: 10)]
    private string $type = 'TEXTE';

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

    public function getValeur(): string
    {
        return $this->valeur;
    }

    public function setValeur(string $valeur): static
    {
        $this->valeur = $valeur;

        return $this;
    }

    public function getLibelle(): string
    {
        return $this->libelle;
    }

    public function setLibelle(string $libelle): static
    {
        $this->libelle = $libelle;

        return $this;
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
