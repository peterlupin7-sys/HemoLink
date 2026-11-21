<?php

namespace App\Entity;

use App\Repository\BanqueRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Une banque de sang géolocalisée.
 */
#[ORM\Entity(repositoryClass: BanqueRepository::class)]
#[ORM\Table(name: 'banque')]
class Banque
{
    public const TYPES = ['CNTS', 'CHU', 'HOPITAL', 'CLINIQUE', 'CENTRE'];
    public const STATUTS = ['ACTIVE', 'SUSPENDUE', 'FERMEE'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20, unique: true)]
    private string $code;

    #[ORM\Column(length: 150)]
    private string $nom;

    #[ORM\Column(length: 20)]
    private string $type = 'HOPITAL';

    #[ORM\Column(length: 200)]
    private string $adresse;

    #[ORM\Column(length: 80)]
    private string $ville;

    #[ORM\Column(length: 80)]
    private string $departement;

    #[ORM\Column(type: 'float')]
    private float $latitude;

    #[ORM\Column(type: 'float')]
    private float $longitude;

    #[ORM\Column(length: 25)]
    private string $telephone;

    #[ORM\Column(name: 'telephone_urgence', length: 25, nullable: true)]
    private ?string $telephoneUrgence = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(length: 200, nullable: true)]
    private ?string $horaires = null;

    #[ORM\Column(name: 'capacite_stockage', type: 'smallint')]
    private int $capaciteStockage = 0;

    #[ORM\Column(length: 20)]
    private string $statut = 'ACTIVE';

    #[ORM\Column(name: 'date_creation', type: 'datetime')]
    private \DateTimeInterface $dateCreation;

    #[ORM\Column(name: 'date_maj', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $dateMaj = null;

    #[ORM\OneToMany(mappedBy: 'banque', targetEntity: Stock::class)]
    private Collection $stocks;

    #[ORM\OneToMany(mappedBy: 'banque', targetEntity: Poche::class)]
    private Collection $poches;

    #[ORM\OneToMany(mappedBy: 'banque', targetEntity: Utilisateur::class)]
    private Collection $utilisateurs;

    #[ORM\OneToMany(mappedBy: 'banque', targetEntity: Precommande::class)]
    private Collection $precommandes;

    #[ORM\OneToMany(mappedBy: 'banque', targetEntity: CreneauDon::class)]
    private Collection $creneaux;

    public function __construct()
    {
        $this->stocks = new ArrayCollection();
        $this->poches = new ArrayCollection();
        $this->utilisateurs = new ArrayCollection();
        $this->precommandes = new ArrayCollection();
        $this->creneaux = new ArrayCollection();
        $this->dateCreation = new \DateTimeImmutable();
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

    public function getNom(): string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

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

    public function getAdresse(): string
    {
        return $this->adresse;
    }

    public function setAdresse(string $adresse): static
    {
        $this->adresse = $adresse;

        return $this;
    }

    public function getVille(): string
    {
        return $this->ville;
    }

    public function setVille(string $ville): static
    {
        $this->ville = $ville;

        return $this;
    }

    public function getDepartement(): string
    {
        return $this->departement;
    }

    public function setDepartement(string $departement): static
    {
        $this->departement = $departement;

        return $this;
    }

    public function getLatitude(): float
    {
        return $this->latitude;
    }

    public function setLatitude(float $latitude): static
    {
        $this->latitude = $latitude;

        return $this;
    }

    public function getLongitude(): float
    {
        return $this->longitude;
    }

    public function setLongitude(float $longitude): static
    {
        $this->longitude = $longitude;

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

    public function getTelephoneUrgence(): ?string
    {
        return $this->telephoneUrgence;
    }

    public function setTelephoneUrgence(?string $telephoneUrgence): static
    {
        $this->telephoneUrgence = $telephoneUrgence;

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

    public function getHoraires(): ?string
    {
        return $this->horaires;
    }

    public function setHoraires(?string $horaires): static
    {
        $this->horaires = $horaires;

        return $this;
    }

    public function getCapaciteStockage(): int
    {
        return $this->capaciteStockage;
    }

    public function setCapaciteStockage(int $capaciteStockage): static
    {
        $this->capaciteStockage = $capaciteStockage;

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

    public function getDateMaj(): ?\DateTimeInterface
    {
        return $this->dateMaj;
    }

    public function setDateMaj(?\DateTimeInterface $dateMaj): static
    {
        $this->dateMaj = $dateMaj;

        return $this;
    }

    /**
     * @return Collection<int, Stock>
     */
    public function getStocks(): Collection
    {
        return $this->stocks;
    }

    public function addStock(Stock $stock): static
    {
        if (!$this->stocks->contains($stock)) {
            $this->stocks->add($stock);
            $stock->setBanque($this);
        }

        return $this;
    }

    public function removeStock(Stock $stock): static
    {
        if ($this->stocks->removeElement($stock) && $stock->getBanque() === $this) {
            $stock->setBanque(null);
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
            $poche->setBanque($this);
        }

        return $this;
    }

    public function removePoche(Poche $poche): static
    {
        if ($this->poches->removeElement($poche) && $poche->getBanque() === $this) {
            $poche->setBanque(null);
        }

        return $this;
    }

    /**
     * @return Collection<int, Utilisateur>
     */
    public function getUtilisateurs(): Collection
    {
        return $this->utilisateurs;
    }

    public function addUtilisateur(Utilisateur $utilisateur): static
    {
        if (!$this->utilisateurs->contains($utilisateur)) {
            $this->utilisateurs->add($utilisateur);
            $utilisateur->setBanque($this);
        }

        return $this;
    }

    public function removeUtilisateur(Utilisateur $utilisateur): static
    {
        if ($this->utilisateurs->removeElement($utilisateur) && $utilisateur->getBanque() === $this) {
            $utilisateur->setBanque(null);
        }

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
            $precommande->setBanque($this);
        }

        return $this;
    }

    public function removePrecommande(Precommande $precommande): static
    {
        if ($this->precommandes->removeElement($precommande) && $precommande->getBanque() === $this) {
            $precommande->setBanque(null);
        }

        return $this;
    }

    /**
     * @return Collection<int, CreneauDon>
     */
    public function getCreneaux(): Collection
    {
        return $this->creneaux;
    }

    public function addCreneaux(CreneauDon $creneaux): static
    {
        if (!$this->creneaux->contains($creneaux)) {
            $this->creneaux->add($creneaux);
            $creneaux->setBanque($this);
        }

        return $this;
    }

    public function removeCreneaux(CreneauDon $creneaux): static
    {
        if ($this->creneaux->removeElement($creneaux) && $creneaux->getBanque() === $this) {
            $creneaux->setBanque(null);
        }

        return $this;
    }

    public function __toString(): string
    {
        return (string) $this->nom;
    }

    public function estActive(): bool
    {
        return 'ACTIVE' === $this->statut;
    }

    /**
     * Stock de la banque pour un groupe donné, ou null s'il n'est pas suivi.
     */
    public function getStockPour(GroupeSanguin $groupe): ?Stock
    {
        foreach ($this->stocks as $stock) {
            if ($stock->getGroupeSanguin() === $groupe) {
                return $stock;
            }
        }

        return null;
    }
}
