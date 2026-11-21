<?php

namespace App\Entity;

use App\Repository\RendezVousDonRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Réservation d'une place sur un créneau de collecte.
 */
#[ORM\Entity(repositoryClass: RendezVousDonRepository::class)]
#[ORM\Table(name: 'rendez_vous_don')]
class RendezVousDon
{
    public const PLANIFIE = 'PLANIFIE';
    public const HONORE = 'HONORE';
    public const ANNULE = 'ANNULE';
    public const ABSENT = 'ABSENT';

    public const LIBELLES = [
        self::PLANIFIE => 'Planifié',
        self::HONORE => 'Honoré',
        self::ANNULE => 'Annulé',
        self::ABSENT => 'Absence',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 25, unique: true)]
    private string $numero;

    #[ORM\Column(length: 20)]
    private string $statut = self::PLANIFIE;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $commentaire = null;

    #[ORM\Column(name: 'date_creation', type: 'datetime')]
    private \DateTimeInterface $dateCreation;

    #[ORM\Column(name: 'date_annulation', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $dateAnnulation = null;

    #[ORM\ManyToOne(targetEntity: Donneur::class, inversedBy: 'rendezVous')]
    #[ORM\JoinColumn(name: 'donneur_id', nullable: false)]
    private ?Donneur $donneur = null;

    #[ORM\ManyToOne(targetEntity: CreneauDon::class, inversedBy: 'rendezVous')]
    #[ORM\JoinColumn(name: 'creneau_id', nullable: false)]
    private ?CreneauDon $creneau = null;

    public function __construct()
    {
        $this->dateCreation = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNumero(): string
    {
        return $this->numero;
    }

    public function setNumero(string $numero): static
    {
        $this->numero = $numero;

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

    public function getCommentaire(): ?string
    {
        return $this->commentaire;
    }

    public function setCommentaire(?string $commentaire): static
    {
        $this->commentaire = $commentaire;

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

    public function getDateAnnulation(): ?\DateTimeInterface
    {
        return $this->dateAnnulation;
    }

    public function setDateAnnulation(?\DateTimeInterface $dateAnnulation): static
    {
        $this->dateAnnulation = $dateAnnulation;

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

    public function getCreneau(): ?CreneauDon
    {
        return $this->creneau;
    }

    public function setCreneau(?CreneauDon $creneau): static
    {
        $this->creneau = $creneau;

        return $this;
    }

    public function getLibelleStatut(): string
    {
        return self::LIBELLES[$this->statut] ?? $this->statut;
    }

    public function estActif(): bool
    {
        return \in_array($this->statut, [self::PLANIFIE, self::HONORE], true);
    }

    /**
     * Un rendez-vous ne peut plus être modifié ni annulé trop près du créneau :
     * la banque a préparé le matériel et réservé le personnel.
     */
    public function estModifiable(int $delaiHeures = 12,
        ?\DateTimeInterface $reference = null): bool
    {
        if (self::PLANIFIE !== $this->statut) {
            return false;
        }

        $reference ??= new \DateTimeImmutable();
        $limite = $this->creneau->getDebut()->modify(sprintf('-%d hours', $delaiHeures));

        return $reference < $limite;
    }

    public function __toString(): string
    {
        return (string) $this->numero;
    }
}
