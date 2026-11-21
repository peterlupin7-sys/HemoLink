<?php

namespace App\Entity;

use App\Repository\DonRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Prélèvement effectivement réalisé sur un donneur.
 */
#[ORM\Entity(repositoryClass: DonRepository::class)]
#[ORM\Table(name: 'don')]
class Don
{
    public const ENREGISTRE = 'ENREGISTRE';
    public const EN_QUALIFICATION = 'EN_QUALIFICATION';
    public const QUALIFIE = 'QUALIFIE';
    public const REJETE = 'REJETE';

    public const LIBELLES = [
        self::ENREGISTRE => 'Enregistré',
        self::EN_QUALIFICATION => 'En cours de qualification',
        self::QUALIFIE => 'Qualifié, mis en stock',
        self::REJETE => 'Écarté après analyses',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'code_don', length: 25, unique: true)]
    private string $codeDon;

    #[ORM\Column(name: 'date_don', type: 'datetime')]
    private \DateTimeInterface $dateDon;

    #[ORM\Column(name: 'volume_ml', type: 'smallint')]
    private int $volumeMl = 450;

    #[ORM\Column(type: 'decimal', precision: 4, scale: 1, nullable: true)]
    private ?string $hemoglobine = null;

    #[ORM\Column(length: 25)]
    private string $statut = self::ENREGISTRE;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $observation = null;

    #[ORM\Column(name: 'date_creation', type: 'datetime')]
    private \DateTimeInterface $dateCreation;

    #[ORM\ManyToOne(targetEntity: Donneur::class, inversedBy: 'dons')]
    #[ORM\JoinColumn(name: 'donneur_id', nullable: false)]
    private ?Donneur $donneur = null;

    #[ORM\ManyToOne(targetEntity: Banque::class)]
    #[ORM\JoinColumn(name: 'banque_id', nullable: false)]
    private ?Banque $banque = null;

    #[ORM\ManyToOne(targetEntity: RendezVousDon::class)]
    #[ORM\JoinColumn(name: 'rendez_vous_id', nullable: true)]
    private ?RendezVousDon $rendezVous = null;

    #[ORM\ManyToOne(targetEntity: GroupeSanguin::class)]
    #[ORM\JoinColumn(name: 'groupe_sanguin_id', nullable: true)]
    private ?GroupeSanguin $groupeSanguin = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(name: 'utilisateur_id', nullable: true)]
    private ?Utilisateur $utilisateur = null;

    #[ORM\OneToOne(mappedBy: 'don', targetEntity: Qualification::class)]
    private ?Qualification $qualification = null;

    public function __construct()
    {
        $this->dateDon = new \DateTimeImmutable();
        $this->dateCreation = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCodeDon(): string
    {
        return $this->codeDon;
    }

    public function setCodeDon(string $codeDon): static
    {
        $this->codeDon = $codeDon;

        return $this;
    }

    public function getDateDon(): \DateTimeInterface
    {
        return $this->dateDon;
    }

    public function setDateDon(\DateTimeInterface $dateDon): static
    {
        $this->dateDon = $dateDon;

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

    public function getHemoglobine(): ?string
    {
        return $this->hemoglobine;
    }

    public function setHemoglobine(?string $hemoglobine): static
    {
        $this->hemoglobine = $hemoglobine;

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

    public function getObservation(): ?string
    {
        return $this->observation;
    }

    public function setObservation(?string $observation): static
    {
        $this->observation = $observation;

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

    public function getDonneur(): ?Donneur
    {
        return $this->donneur;
    }

    public function setDonneur(?Donneur $donneur): static
    {
        $this->donneur = $donneur;

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

    public function getRendezVous(): ?RendezVousDon
    {
        return $this->rendezVous;
    }

    public function setRendezVous(?RendezVousDon $rendezVous): static
    {
        $this->rendezVous = $rendezVous;

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

    public function getUtilisateur(): ?Utilisateur
    {
        return $this->utilisateur;
    }

    public function setUtilisateur(?Utilisateur $utilisateur): static
    {
        $this->utilisateur = $utilisateur;

        return $this;
    }

    public function getQualification(): ?Qualification
    {
        return $this->qualification;
    }

    public function setQualification(?Qualification $qualification): static
    {
        $this->qualification = $qualification;

        return $this;
    }

    public function getLibelleStatut(): string
    {
        return self::LIBELLES[$this->statut] ?? $this->statut;
    }

    public function estQualifiable(): bool
    {
        return \in_array($this->statut, [self::ENREGISTRE, self::EN_QUALIFICATION], true);
    }

    public function __toString(): string
    {
        return (string) $this->codeDon;
    }
}
