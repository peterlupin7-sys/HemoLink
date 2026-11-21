<?php

namespace App\Entity;

use App\Repository\UtilisateurRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Compte du personnel : administrateur CNTS, gestionnaire ou agent.
 */
#[ORM\Entity(repositoryClass: UtilisateurRepository::class)]
#[ORM\Table(name: 'utilisateur')]
class Utilisateur implements UserInterface, PasswordAuthenticatedUserInterface
{
    public const ROLE_ADMIN = 'ROLE_ADMIN';
    public const ROLE_GESTIONNAIRE = 'ROLE_GESTIONNAIRE';
    public const ROLE_AGENT = 'ROLE_AGENT';

    /**
     * Personnel habilité : il qualifie et valide les produits sanguins.
     * Rôle volontairement distinct du gestionnaire — qualifier n'est pas gérer
     * un stock, et un gestionnaire n'a aucune raison de signer une analyse.
     */
    public const ROLE_PERSONNEL = 'ROLE_PERSONNEL';

    /** Donneur disposant d'un compte pour prendre ses rendez-vous. */
    public const ROLE_DONNEUR = 'ROLE_DONNEUR';

    public const LIBELLES_ROLES = [
        self::ROLE_ADMIN => 'Administrateur',
        self::ROLE_GESTIONNAIRE => 'Gestionnaire de banque',
        self::ROLE_AGENT => 'Agent de stock',
        self::ROLE_PERSONNEL => 'Personnel habilité',
        self::ROLE_DONNEUR => 'Donneur',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    private string $email;

    #[ORM\Column(name: 'mot_de_passe', length: 255)]
    private string $motDePasse;

    #[ORM\Column(type: 'json')]
    private array $roles = [];

    #[ORM\Column(length: 80)]
    private string $nom;

    #[ORM\Column(length: 80)]
    private string $prenom;

    #[ORM\Column(length: 25, nullable: true)]
    private ?string $telephone = null;

    #[ORM\Column(type: 'boolean')]
    private bool $actif = true;

    #[ORM\Column(name: 'derniere_connexion', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $derniereConnexion = null;

    #[ORM\Column(name: 'date_creation', type: 'datetime')]
    private \DateTimeInterface $dateCreation;

    #[ORM\ManyToOne(targetEntity: Banque::class, inversedBy: 'utilisateurs')]
    #[ORM\JoinColumn(name: 'banque_id', nullable: true)]
    private ?Banque $banque = null;

    public function __construct()
    {
        $this->dateCreation = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getMotDePasse(): string
    {
        return $this->motDePasse;
    }

    public function setMotDePasse(string $motDePasse): static
    {
        $this->motDePasse = $motDePasse;

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';

        return array_values(array_unique($roles));
    }

    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

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

    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    public function setTelephone(?string $telephone): static
    {
        $this->telephone = $telephone;

        return $this;
    }

    public function isActif(): bool
    {
        return $this->actif;
    }

    public function setActif(bool $actif): static
    {
        $this->actif = $actif;

        return $this;
    }

    public function getDerniereConnexion(): ?\DateTimeInterface
    {
        return $this->derniereConnexion;
    }

    public function setDerniereConnexion(?\DateTimeInterface $derniereConnexion): static
    {
        $this->derniereConnexion = $derniereConnexion;

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

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    public function getPassword(): string
    {
        return $this->motDePasse;
    }

    public function eraseCredentials(): void
    {
        // aucune donnée sensible temporaire n'est conservée
    }

    public function getNomComplet(): string
    {
        return $this->prenom.' '.$this->nom;
    }

    public function __toString(): string
    {
        return $this->getNomComplet();
    }
}
