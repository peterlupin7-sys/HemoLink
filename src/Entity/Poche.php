<?php

namespace App\Entity;

use App\Repository\PocheRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Unité physique de sang, tracée du prélèvement à la délivrance.
 */
#[ORM\Entity(repositoryClass: PocheRepository::class)]
#[ORM\Table(name: 'poche')]
class Poche
{
    public const DISPONIBLE = 'DISPONIBLE';
    public const RESERVEE = 'RESERVEE';
    public const DELIVREE = 'DELIVREE';
    public const PERIMEE = 'PERIMEE';
    public const DETRUITE = 'DETRUITE';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'code_poche', length: 25, unique: true)]
    private string $codePoche;

    #[ORM\Column(name: 'date_prelevement', type: 'date')]
    private \DateTimeInterface $datePrelevement;

    #[ORM\Column(name: 'date_peremption', type: 'date')]
    private \DateTimeInterface $datePeremption;

    #[ORM\Column(name: 'volume_ml', type: 'smallint')]
    private int $volumeMl = 450;

    #[ORM\Column(length: 20)]
    private string $statut = self::DISPONIBLE;

    #[ORM\Column(name: 'date_creation', type: 'datetime')]
    private \DateTimeInterface $dateCreation;

    #[ORM\ManyToOne(targetEntity: Banque::class, inversedBy: 'poches')]
    #[ORM\JoinColumn(name: 'banque_id', nullable: false)]
    private ?Banque $banque = null;

    #[ORM\ManyToOne(targetEntity: GroupeSanguin::class)]
    #[ORM\JoinColumn(name: 'groupe_sanguin_id', nullable: false)]
    private ?GroupeSanguin $groupeSanguin = null;

    #[ORM\ManyToOne(targetEntity: Precommande::class, inversedBy: 'poches')]
    #[ORM\JoinColumn(name: 'precommande_id', nullable: true)]
    private ?Precommande $precommande = null;

    public function __construct()
    {
        $this->datePrelevement = new \DateTimeImmutable();
        $this->datePeremption = new \DateTimeImmutable();
        $this->dateCreation = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCodePoche(): string
    {
        return $this->codePoche;
    }

    public function setCodePoche(string $codePoche): static
    {
        $this->codePoche = $codePoche;

        return $this;
    }

    public function getDatePrelevement(): \DateTimeInterface
    {
        return $this->datePrelevement;
    }

    public function setDatePrelevement(\DateTimeInterface $datePrelevement): static
    {
        $this->datePrelevement = $datePrelevement;

        return $this;
    }

    public function getDatePeremption(): \DateTimeInterface
    {
        return $this->datePeremption;
    }

    public function setDatePeremption(\DateTimeInterface $datePeremption): static
    {
        $this->datePeremption = $datePeremption;

        return $this;
    }

    public function getVolumeMl(): int
    {
        return $this->volumeMl;
    }

    public function setVolumeMl(int $volumeMl): static
    {
        $this->volumeMl = $volumeMl;

        return $this;
    }

    public function getStatut(): string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = $statut;

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

    public function getPrecommande(): ?Precommande
    {
        return $this->precommande;
    }

    public function setPrecommande(?Precommande $precommande): static
    {
        $this->precommande = $precommande;

        return $this;
    }

    public function estPerimee(): bool
    {
        return $this->datePeremption < new \DateTimeImmutable('today');
    }

    public function joursAvantPeremption(): int
    {
        return (int) (new \DateTimeImmutable('today'))
            ->diff($this->datePeremption)->format('%r%a');
    }
}
