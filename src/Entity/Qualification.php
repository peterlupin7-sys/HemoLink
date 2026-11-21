<?php

namespace App\Entity;

use App\Repository\QualificationRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Résultat des analyses prononcé par un personnel habilité.
 *
 * Règle intangible du projet : seule une qualification CONFORME peut
 * créer une poche utilisable. Un produit non conforme ne rejoint jamais
 * le stock, et la poche n'est donc pas créée du tout.
 */
#[ORM\Entity(repositoryClass: QualificationRepository::class)]
#[ORM\Table(name: 'qualification')]
class Qualification
{
    public const CONFORME = 'CONFORME';
    public const NON_CONFORME = 'NON_CONFORME';

    public const NEGATIF = 'NEGATIF';
    public const POSITIF = 'POSITIF';
    public const NON_FAIT = 'NON_FAIT';

    public const TESTS = ['vih' => 'VIH', 'hepatiteB' => 'Hépatite B',
        'hepatiteC' => 'Hépatite C', 'syphilis' => 'Syphilis'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20)]
    private string $resultat = self::CONFORME;

    #[ORM\Column(length: 10)]
    private string $vih = self::NON_FAIT;

    #[ORM\Column(name: 'hepatite_b', length: 10)]
    private string $hepatiteB = self::NON_FAIT;

    #[ORM\Column(name: 'hepatite_c', length: 10)]
    private string $hepatiteC = self::NON_FAIT;

    #[ORM\Column(length: 10)]
    private string $syphilis = self::NON_FAIT;

    #[ORM\Column(name: 'motif_rejet', length: 255, nullable: true)]
    private ?string $motifRejet = null;

    #[ORM\Column(name: 'date_qualification', type: 'datetime')]
    private \DateTimeInterface $dateQualification;

    #[ORM\OneToOne(targetEntity: Don::class, inversedBy: 'qualification')]
    #[ORM\JoinColumn(name: 'don_id', nullable: false)]
    private ?Don $don = null;

    #[ORM\ManyToOne(targetEntity: Poche::class)]
    #[ORM\JoinColumn(name: 'poche_id', nullable: true)]
    private ?Poche $poche = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(name: 'personnel_id', nullable: false)]
    private ?Utilisateur $personnel = null;

    #[ORM\ManyToOne(targetEntity: GroupeSanguin::class)]
    #[ORM\JoinColumn(name: 'groupe_sanguin_id', nullable: true)]
    private ?GroupeSanguin $groupeSanguin = null;

    public function __construct()
    {
        $this->dateQualification = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getResultat(): string
    {
        return $this->resultat;
    }

    public function setResultat(string $resultat): static
    {
        $this->resultat = $resultat;

        return $this;
    }

    public function getVih(): string
    {
        return $this->vih;
    }

    public function setVih(string $vih): static
    {
        $this->vih = $vih;

        return $this;
    }

    public function getHepatiteB(): string
    {
        return $this->hepatiteB;
    }

    public function setHepatiteB(string $hepatiteB): static
    {
        $this->hepatiteB = $hepatiteB;

        return $this;
    }

    public function getHepatiteC(): string
    {
        return $this->hepatiteC;
    }

    public function setHepatiteC(string $hepatiteC): static
    {
        $this->hepatiteC = $hepatiteC;

        return $this;
    }

    public function getSyphilis(): string
    {
        return $this->syphilis;
    }

    public function setSyphilis(string $syphilis): static
    {
        $this->syphilis = $syphilis;

        return $this;
    }

    public function getMotifRejet(): ?string
    {
        return $this->motifRejet;
    }

    public function setMotifRejet(?string $motifRejet): static
    {
        $this->motifRejet = $motifRejet;

        return $this;
    }

    public function getDateQualification(): \DateTimeInterface
    {
        return $this->dateQualification;
    }

    public function setDateQualification(\DateTimeInterface $dateQualification): static
    {
        $this->dateQualification = $dateQualification;

        return $this;
    }

    public function getDon(): ?Don
    {
        return $this->don;
    }

    public function setDon(?Don $don): static
    {
        $this->don = $don;

        return $this;
    }

    public function getPoche(): ?Poche
    {
        return $this->poche;
    }

    public function setPoche(?Poche $poche): static
    {
        $this->poche = $poche;

        return $this;
    }

    public function getPersonnel(): ?Utilisateur
    {
        return $this->personnel;
    }

    public function setPersonnel(?Utilisateur $personnel): static
    {
        $this->personnel = $personnel;

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

    public function estConforme(): bool
    {
        return self::CONFORME === $this->resultat;
    }

    /**
     * Un seul test positif suffit à écarter le produit.
     *
     * @return array<int, string> libellés des tests positifs
     */
    public function testsPositifs(): array
    {
        $positifs = [];

        foreach (self::TESTS as $propriete => $libelle) {
            if (self::POSITIF === $this->{$propriete}) {
                $positifs[] = $libelle;
            }
        }

        return $positifs;
    }
}
