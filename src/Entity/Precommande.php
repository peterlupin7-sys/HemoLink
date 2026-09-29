<?php

namespace App\Entity;

use App\Repository\PrecommandeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Réservation de poches auprès d'une banque.
 */
#[ORM\Entity(repositoryClass: PrecommandeRepository::class)]
#[ORM\Table(name: 'precommande')]
class Precommande
{
    public const EN_ATTENTE = 'EN_ATTENTE';
    public const CONFIRMEE = 'CONFIRMEE';
    public const PRETE = 'PRETE';
    public const RETIREE = 'RETIREE';
    public const ANNULEE = 'ANNULEE';
    public const EXPIREE = 'EXPIREE';

    public const LIBELLES = [
        self::EN_ATTENTE => 'En attente',
        self::CONFIRMEE => 'Confirmée',
        self::PRETE => 'Prête au retrait',
        self::RETIREE => 'Retirée',
        self::ANNULEE => 'Annulée',
        self::EXPIREE => 'Expirée',
    ];

    public const URGENCES = ['NORMALE', 'URGENTE', 'VITALE'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 25, unique: true)]
    private string $numero;

    #[ORM\Column(length: 20)]
    private string $statut = self::EN_ATTENTE;

    #[ORM\Column(length: 10)]
    private string $urgence = 'NORMALE';

    #[ORM\Column(name: 'date_souhaitee', type: 'date')]
    private \DateTimeInterface $dateSouhaitee;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $motif = null;

    #[ORM\Column(name: 'reference_patient', length: 40, nullable: true)]
    private ?string $referencePatient = null;

    #[ORM\Column(name: 'date_creation', type: 'datetime')]
    private \DateTimeInterface $dateCreation;

    #[ORM\Column(name: 'date_confirmation', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $dateConfirmation = null;

    #[ORM\Column(name: 'date_retrait', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $dateRetrait = null;

    #[ORM\Column(name: 'date_annulation', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $dateAnnulation = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $commentaire = null;

    #[ORM\ManyToOne(targetEntity: Banque::class, inversedBy: 'precommandes')]
    #[ORM\JoinColumn(name: 'banque_id', nullable: false)]
    private ?Banque $banque = null;

    #[ORM\ManyToOne(targetEntity: Demandeur::class, inversedBy: 'precommandes')]
    #[ORM\JoinColumn(name: 'demandeur_id', nullable: false)]
    private ?Demandeur $demandeur = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(name: 'utilisateur_traitant_id', nullable: true)]
    private ?Utilisateur $utilisateurTraitant = null;

    #[ORM\OneToMany(mappedBy: 'precommande', targetEntity: LignePrecommande::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $lignes;

    #[ORM\OneToMany(mappedBy: 'precommande', targetEntity: Poche::class)]
    private Collection $poches;

    public function __construct()
    {
        $this->lignes = new ArrayCollection();
        $this->poches = new ArrayCollection();
        $this->dateSouhaitee = new \DateTimeImmutable();
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

    public function getUrgence(): string
    {
        return $this->urgence;
    }

    public function setUrgence(string $urgence): static
    {
        $this->urgence = $urgence;

        return $this;
    }

    public function getDateSouhaitee(): \DateTimeInterface
    {
        return $this->dateSouhaitee;
    }

    public function setDateSouhaitee(\DateTimeInterface $dateSouhaitee): static
    {
        $this->dateSouhaitee = $dateSouhaitee;

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

    public function getReferencePatient(): ?string
    {
        return $this->referencePatient;
    }

    public function setReferencePatient(?string $referencePatient): static
    {
        $this->referencePatient = $referencePatient;

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

    public function getDateConfirmation(): ?\DateTimeInterface
    {
        return $this->dateConfirmation;
    }

    public function setDateConfirmation(?\DateTimeInterface $dateConfirmation): static
    {
        $this->dateConfirmation = $dateConfirmation;

        return $this;
    }

    public function getDateRetrait(): ?\DateTimeInterface
    {
        return $this->dateRetrait;
    }

    public function setDateRetrait(?\DateTimeInterface $dateRetrait): static
    {
        $this->dateRetrait = $dateRetrait;

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

    public function getCommentaire(): ?string
    {
        return $this->commentaire;
    }

    public function setCommentaire(?string $commentaire): static
    {
        $this->commentaire = $commentaire;

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

    public function getDemandeur(): ?Demandeur
    {
        return $this->demandeur;
    }

    public function setDemandeur(?Demandeur $demandeur): static
    {
        $this->demandeur = $demandeur;

        return $this;
    }

    public function getUtilisateurTraitant(): ?Utilisateur
    {
        return $this->utilisateurTraitant;
    }

    public function setUtilisateurTraitant(?Utilisateur $utilisateurTraitant): static
    {
        $this->utilisateurTraitant = $utilisateurTraitant;

        return $this;
    }

    /**
     * @return Collection<int, LignePrecommande>
     */
    public function getLignes(): Collection
    {
        return $this->lignes;
    }

    public function addLigne(LignePrecommande $ligne): static
    {
        if (!$this->lignes->contains($ligne)) {
            $this->lignes->add($ligne);
            $ligne->setPrecommande($this);
        }

        return $this;
    }

    public function removeLigne(LignePrecommande $ligne): static
    {
        if ($this->lignes->removeElement($ligne) && $ligne->getPrecommande() === $this) {
            $ligne->setPrecommande(null);
        }

        return $this;
    }

    /**
     * @return Collection<int, Poche>
     */
    public function getPoches(): Collection
    {
        return $this->poches;
    }

    public function addPoche(Poche $poche): static
    {
        if (!$this->poches->contains($poche)) {
            $this->poches->add($poche);
            $poche->setPrecommande($this);
        }

        return $this;
    }

    public function removePoche(Poche $poche): static
    {
        if ($this->poches->removeElement($poche) && $poche->getPrecommande() === $this) {
            $poche->setPrecommande(null);
        }

        return $this;
    }

    public function getLibelleStatut(): string
    {
        return self::LIBELLES[$this->statut] ?? $this->statut;
    }

    public function estModifiable(): bool
    {
        return \in_array($this->statut, [self::EN_ATTENTE, self::CONFIRMEE], true);
    }

    public function getQuantiteTotale(): int
    {
        $total = 0;
        foreach ($this->lignes as $ligne) {
            $total += $ligne->getQuantiteDemandee();
        }

        return $total;
    }

    public function __toString(): string
    {
        return (string) $this->numero;
    }
}
