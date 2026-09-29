<?php

namespace App\Entity;

use App\Repository\StockRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * État agrégé du stock d'une banque pour un groupe sanguin.
 */
#[ORM\Entity(repositoryClass: StockRepository::class)]
#[ORM\Table(name: 'stock')]
class Stock
{
    public const DISPONIBLE = 'DISPONIBLE';
    public const TENSION = 'TENSION';
    public const RUPTURE = 'RUPTURE';
    public const NON_COMMUNIQUE = 'NON_COMMUNIQUE';

    public const LIBELLES = [
        self::DISPONIBLE => 'Disponible',
        self::TENSION => 'En tension',
        self::RUPTURE => 'Rupture',
        self::NON_COMMUNIQUE => 'Non communiqué',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'quantite_disponible', type: 'smallint')]
    private int $quantiteDisponible = 0;

    #[ORM\Column(name: 'quantite_reservee', type: 'smallint')]
    private int $quantiteReservee = 0;

    #[ORM\Column(name: 'seuil_alerte', type: 'smallint')]
    private int $seuilAlerte = 5;

    #[ORM\Column(length: 20)]
    private string $etat = self::NON_COMMUNIQUE;

    #[ORM\Column(name: 'date_maj', type: 'datetime')]
    private \DateTimeInterface $dateMaj;

    #[ORM\ManyToOne(targetEntity: Banque::class, inversedBy: 'stocks')]
    #[ORM\JoinColumn(name: 'banque_id', nullable: false)]
    private ?Banque $banque = null;

    #[ORM\ManyToOne(targetEntity: GroupeSanguin::class)]
    #[ORM\JoinColumn(name: 'groupe_sanguin_id', nullable: false)]
    private ?GroupeSanguin $groupeSanguin = null;

    public function __construct()
    {
        $this->dateMaj = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getQuantiteDisponible(): int
    {
        return $this->quantiteDisponible;
    }

    public function setQuantiteDisponible(int $quantiteDisponible): static
    {
        $this->quantiteDisponible = $quantiteDisponible;

        return $this;
    }

    public function getQuantiteReservee(): int
    {
        return $this->quantiteReservee;
    }

    public function setQuantiteReservee(int $quantiteReservee): static
    {
        $this->quantiteReservee = $quantiteReservee;

        return $this;
    }

    public function getSeuilAlerte(): int
    {
        return $this->seuilAlerte;
    }

    public function setSeuilAlerte(int $seuilAlerte): static
    {
        $this->seuilAlerte = $seuilAlerte;

        return $this;
    }

    public function getEtat(): string
    {
        return $this->etat;
    }

    public function setEtat(string $etat): static
    {
        $this->etat = $etat;

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

    public function getLibelleEtat(): string
    {
        return self::LIBELLES[$this->etat] ?? $this->etat;
    }

    public function estObsolete(int $heures = 24): bool
    {
        return $this->dateMaj < new \DateTimeImmutable(sprintf('-%d hours', $heures));
    }
}
