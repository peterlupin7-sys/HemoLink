<?php

namespace App\Entity;

use App\Repository\NotificationSmsRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Message SMS en file d'attente, rejoué tant qu'il échoue.
 */
#[ORM\Entity(repositoryClass: NotificationSmsRepository::class)]
#[ORM\Table(name: 'notification_sms')]
class NotificationSms
{
    public const EN_ATTENTE = 'EN_ATTENTE';
    public const ENVOYE = 'ENVOYE';
    public const ECHEC = 'ECHEC';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 25)]
    private string $destinataire;

    #[ORM\Column(length: 480)]
    private string $message;

    #[ORM\Column(length: 25)]
    private string $type = 'AUTRE';

    #[ORM\Column(length: 20)]
    private string $statut = self::EN_ATTENTE;

    #[ORM\Column(type: 'smallint')]
    private int $tentatives = 0;

    #[ORM\Column(name: 'date_creation', type: 'datetime')]
    private \DateTimeInterface $dateCreation;

    #[ORM\Column(name: 'date_envoi', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $dateEnvoi = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $erreur = null;

    #[ORM\ManyToOne(targetEntity: Precommande::class)]
    #[ORM\JoinColumn(name: 'precommande_id', nullable: true)]
    private ?Precommande $precommande = null;

    public function __construct()
    {
        $this->dateCreation = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDestinataire(): string
    {
        return $this->destinataire;
    }

    public function setDestinataire(string $destinataire): static
    {
        $this->destinataire = $destinataire;

        return $this;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setMessage(string $message): static
    {
        $this->message = $message;

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

    public function getStatut(): string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = $statut;

        return $this;
    }

    public function getTentatives(): int
    {
        return $this->tentatives;
    }

    public function setTentatives(int $tentatives): static
    {
        $this->tentatives = $tentatives;

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

    public function getDateEnvoi(): ?\DateTimeInterface
    {
        return $this->dateEnvoi;
    }

    public function setDateEnvoi(?\DateTimeInterface $dateEnvoi): static
    {
        $this->dateEnvoi = $dateEnvoi;

        return $this;
    }

    public function getErreur(): ?string
    {
        return $this->erreur;
    }

    public function setErreur(?string $erreur): static
    {
        $this->erreur = $erreur;

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
}
