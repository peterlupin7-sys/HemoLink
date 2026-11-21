<?php

namespace App\Entity;

use App\Repository\DonneurRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Personne qui donne son sang. Le groupe n'est connu qu'après la première qualification : avant, on n'affiche rien plutôt qu'une supposition.
 */
#[ORM\Entity(repositoryClass: DonneurRepository::class)]
#[ORM\Table(name: 'donneur')]
class Donneur
{
    public const SEXE_F = 'F';
    public const SEXE_M = 'M';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'numero_donneur', length: 20, unique: true)]
    private string $numeroDonneur;

    #[ORM\Column(length: 80)]
    private string $nom;

    #[ORM\Column(length: 80)]
    private string $prenom;

    #[ORM\Column(length: 1)]
    private string $sexe = 'F';

    #[ORM\Column(name: 'date_naissance', type: 'date')]
    private \DateTimeInterface $dateNaissance;

    #[ORM\Column(length: 30)]
    private string $telephone;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $ville = null;

    #[ORM\Column(name: 'dernier_don', type: 'date', nullable: true)]
    private ?\DateTimeInterface $dernierDon = null;

    #[ORM\Column(name: 'nombre_dons', type: 'smallint')]
    private int $nombreDons = 0;

    #[ORM\Column(type: 'boolean')]
    private bool $apte = true;

    #[ORM\Column(name: 'motif_inaptitude', length: 255, nullable: true)]
    private ?string $motifInaptitude = null;

    #[ORM\Column(name: 'date_creation', type: 'datetime')]
    private \DateTimeInterface $dateCreation;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(name: 'utilisateur_id', nullable: true)]
    private ?Utilisateur $utilisateur = null;

    #[ORM\ManyToOne(targetEntity: GroupeSanguin::class)]
    #[ORM\JoinColumn(name: 'groupe_sanguin_id', nullable: true)]
    private ?GroupeSanguin $groupeSanguin = null;

    #[ORM\OneToMany(mappedBy: 'donneur', targetEntity: RendezVousDon::class)]
    private Collection $rendezVous;

    #[ORM\OneToMany(mappedBy: 'donneur', targetEntity: Don::class)]
    private Collection $dons;

    public function __construct()
    {
        $this->rendezVous = new ArrayCollection();
        $this->dons = new ArrayCollection();
        $this->dateNaissance = new \DateTimeImmutable();
        $this->dateCreation = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNumeroDonneur(): string
    {
        return $this->numeroDonneur;
    }

    public function setNumeroDonneur(string $numeroDonneur): static
    {
        $this->numeroDonneur = $numeroDonneur;

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

    public function getPrenom(): string
    {
        return $this->prenom;
    }

    public function setPrenom(string $prenom): static
    {
        $this->prenom = $prenom;

        return $this;
    }

    public function getSexe(): string
    {
        return $this->sexe;
    }

    public function setSexe(string $sexe): static
    {
        $this->sexe = $sexe;

        return $this;
    }

    public function getDateNaissance(): \DateTimeInterface
    {
        return $this->dateNaissance;
    }

    public function setDateNaissance(\DateTimeInterface $dateNaissance): static
    {
        $this->dateNaissance = $dateNaissance;

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

    public function getVille(): ?string
    {
        return $this->ville;
    }

    public function setVille(?string $ville): static
    {
        $this->ville = $ville;

        return $this;
    }

    public function getDernierDon(): ?\DateTimeInterface
    {
        return $this->dernierDon;
    }

    public function setDernierDon(?\DateTimeInterface $dernierDon): static
    {
        $this->dernierDon = $dernierDon;

        return $this;
    }

    public function getNombreDons(): int
    {
        return $this->nombreDons;
    }

    public function setNombreDons(int $nombreDons): static
    {
        $this->nombreDons = $nombreDons;

        return $this;
    }

    public function isApte(): bool
    {
        return $this->apte;
    }

    public function setApte(bool $apte): static
    {
        $this->apte = $apte;

        return $this;
    }

    public function getMotifInaptitude(): ?string
    {
        return $this->motifInaptitude;
    }

    public function setMotifInaptitude(?string $motifInaptitude): static
    {
        $this->motifInaptitude = $motifInaptitude;

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

    public function getUtilisateur(): ?Utilisateur
    {
        return $this->utilisateur;
    }

    public function setUtilisateur(?Utilisateur $utilisateur): static
    {
        $this->utilisateur = $utilisateur;

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
            $rendezvous->setDonneur($this);
        }

        return $this;
    }

    public function removeRendezVous(RendezVousDon $rendezvous): static
    {
        if ($this->rendezVous->removeElement($rendezvous) && $rendezvous->getDonneur() === $this) {
            $rendezvous->setDonneur(null);
        }

        return $this;
    }

    /**
     * @return Collection<int, Don>
     */
    public function getDons(): Collection
    {
        return $this->dons;
    }

    public function addDon(Don $don): static
    {
        if (!$this->dons->contains($don)) {
            $this->dons->add($don);
            $don->setDonneur($this);
        }

        return $this;
    }

    public function removeDon(Don $don): static
    {
        if ($this->dons->removeElement($don) && $don->getDonneur() === $this) {
            $don->setDonneur(null);
        }

        return $this;
    }

    public function __toString(): string
    {
        return $this->getNomComplet();
    }

    public function getNomComplet(): string
    {
        return trim($this->nom.' '.$this->prenom);
    }

    public function getAge(?\DateTimeInterface $reference = null): int
    {
        $reference ??= new \DateTimeImmutable();

        return (int) $this->dateNaissance->diff($reference)->y;
    }

    /**
     * Le donneur peut-il donner aujourd'hui ?
     *
     * Trois conditions, dans cet ordre : être déclaré apte, avoir l'âge requis,
     * et avoir laissé passer le délai de récupération depuis le dernier don.
     */
    public function peutDonner(int $delaiJours = 90, int $ageMin = 18, int $ageMax = 65,
        ?\DateTimeInterface $reference = null): bool
    {
        if (!$this->apte) {
            return false;
        }

        $age = $this->getAge($reference);

        if ($age < $ageMin || $age > $ageMax) {
            return false;
        }

        return $this->joursAvantProchainDon($delaiJours, $reference) <= 0;
    }

    /**
     * Nombre de jours restant avant d'être à nouveau éligible (0 si éligible).
     */
    public function joursAvantProchainDon(int $delaiJours = 90,
        ?\DateTimeInterface $reference = null): int
    {
        if (null === $this->dernierDon) {
            return 0;
        }

        $reference ??= new \DateTimeImmutable();
        $prochain = (new \DateTimeImmutable($this->dernierDon->format('Y-m-d')))
            ->modify(sprintf('+%d days', $delaiJours));

        if ($prochain <= $reference) {
            return 0;
        }

        return (int) $reference->diff($prochain)->days;
    }
}
