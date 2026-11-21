<?php

namespace App\Entity;

use App\Repository\CreneauDonRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Plage de collecte ouverte par une banque, avec un nombre de places limité.
 */
#[ORM\Entity(repositoryClass: CreneauDonRepository::class)]
#[ORM\Table(name: 'creneau_don')]
class CreneauDon
{
    public const OUVERT = 'OUVERT';
    public const COMPLET = 'COMPLET';
    public const ANNULE = 'ANNULE';
    public const CLOS = 'CLOS';

    public const LIBELLES = [
        self::OUVERT => 'Ouvert',
        self::COMPLET => 'Complet',
        self::ANNULE => 'Annulé',
        self::CLOS => 'Clos',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'date_creneau', type: 'date')]
    private \DateTimeInterface $dateCreneau;

    #[ORM\Column(name: 'heure_debut', length: 8)]
    private string $heureDebut;

    #[ORM\Column(name: 'heure_fin', length: 8)]
    private string $heureFin;

    #[ORM\Column(type: 'smallint')]
    private int $capacite = 8;

    #[ORM\Column(name: 'places_reservees', type: 'smallint')]
    private int $placesReservees = 0;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $lieu = null;

    #[ORM\Column(length: 20)]
    private string $statut = self::OUVERT;

    #[ORM\Column(name: 'date_creation', type: 'datetime')]
    private \DateTimeInterface $dateCreation;

    #[ORM\ManyToOne(targetEntity: Banque::class, inversedBy: 'creneaux')]
    #[ORM\JoinColumn(name: 'banque_id', nullable: false)]
    private ?Banque $banque = null;

    #[ORM\OneToMany(mappedBy: 'creneau', targetEntity: RendezVousDon::class)]
    private Collection $rendezVous;

    public function __construct()
    {
        $this->rendezVous = new ArrayCollection();
        $this->dateCreneau = new \DateTimeImmutable();
        $this->dateCreation = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDateCreneau(): \DateTimeInterface
    {
        return $this->dateCreneau;
    }

    public function setDateCreneau(\DateTimeInterface $dateCreneau): static
    {
        $this->dateCreneau = $dateCreneau;

        return $this;
    }

    public function getHeureDebut(): string
    {
        return $this->heureDebut;
    }

    public function setHeureDebut(string $heureDebut): static
    {
        $this->heureDebut = $heureDebut;

        return $this;
    }

    public function getHeureFin(): string
    {
        return $this->heureFin;
    }

    public function setHeureFin(string $heureFin): static
    {
        $this->heureFin = $heureFin;

        return $this;
    }

    public function getCapacite(): int
    {
        return $this->capacite;
    }

    public function setCapacite(int $capacite): static
    {
        $this->capacite = $capacite;

        return $this;
    }

    public function getPlacesReservees(): int
    {
        return $this->placesReservees;
    }

    public function setPlacesReservees(int $placesReservees): static
    {
        $this->placesReservees = $placesReservees;

        return $this;
    }

    public function getLieu(): ?string
    {
        return $this->lieu;
    }

    public function setLieu(?string $lieu): static
    {
        $this->lieu = $lieu;

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

    /**
     * @return Collection<int, RendezVousDon>
     */
    public function getRendezVous(): Collection
    {
        return $this->rendezVous;
    }

    public function addRendezVous(RendezVousDon $rendezvous): static
    {
        if (!$this->rendezVous->contains($rendezvous)) {
            $this->rendezVous->add($rendezvous);
            $rendezvous->setCreneau($this);
        }

        return $this;
    }

    public function removeRendezVous(RendezVousDon $rendezvous): static
    {
        if ($this->rendezVous->removeElement($rendezvous) && $rendezvous->getCreneau() === $this) {
            $rendezvous->setCreneau(null);
        }

        return $this;
    }

    public function getLibelleStatut(): string
    {
        return self::LIBELLES[$this->statut] ?? $this->statut;
    }

    public function getPlacesRestantes(): int
    {
        return max(0, $this->capacite - $this->placesReservees);
    }

    public function estDisponible(?\DateTimeInterface $reference = null): bool
    {
        return self::OUVERT === $this->statut
            && $this->getPlacesRestantes() > 0
            && !$this->estPasse($reference);
    }

    public function estPasse(?\DateTimeInterface $reference = null): bool
    {
        $reference ??= new \DateTimeImmutable();

        return $this->getDebut() < $reference;
    }

    public function getDebut(): \DateTimeImmutable
    {
        return new \DateTimeImmutable(
            $this->dateCreneau->format('Y-m-d').' '.$this->heureDebut
        );
    }

    public function getHoraire(): string
    {
        return substr($this->heureDebut, 0, 5).' - '.substr($this->heureFin, 0, 5);
    }

    public function __toString(): string
    {
        return $this->dateCreneau->format('d/m/Y').' '.$this->getHoraire();
    }
}
