<?php

namespace App\Service;

use App\Entity\NotificationSms;
use App\Entity\Precommande;
use App\Entity\Stock;
use App\Message\EnvoyerSms;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Compose les SMS et les met en file.
 *
 * Les messages sont volontairement sans accent et courts : certains téléphones
 * d'entrée de gamme affichent mal les accents, et un SMS dépasse vite 160
 * caractères — au-delà, l'opérateur facture deux messages.
 */
class SmsNotifier
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MessageBusInterface $bus,
        private readonly string $smsExpediteur,
    ) {
    }

    public function depot(Precommande $precommande): void
    {
        $this->mettreEnFile(
            $precommande->getDemandeur()->getTelephone(),
            sprintf('%s: precommande %s enregistree a %s. Suivez-la avec ce numero.',
                $this->smsExpediteur,
                $precommande->getNumero(),
                $this->abreger($precommande->getBanque()->getNom())),
            'CONFIRMATION',
            $precommande
        );
    }

    public function confirmation(Precommande $precommande): void
    {
        $this->mettreEnFile(
            $precommande->getDemandeur()->getTelephone(),
            sprintf('%s: precommande %s CONFIRMEE. %d poche(s) reservee(s) a %s.',
                $this->smsExpediteur,
                $precommande->getNumero(),
                $precommande->getQuantiteTotale(),
                $this->abreger($precommande->getBanque()->getNom())),
            'CONFIRMATION',
            $precommande
        );
    }

    public function prete(Precommande $precommande): void
    {
        $this->mettreEnFile(
            $precommande->getDemandeur()->getTelephone(),
            sprintf('%s: les poches de la precommande %s sont PRETES au retrait. '
                .'Presentez ce numero a la banque.',
                $this->smsExpediteur, $precommande->getNumero()),
            'RAPPEL',
            $precommande
        );
    }

    public function annulation(Precommande $precommande, string $raison): void
    {
        $this->mettreEnFile(
            $precommande->getDemandeur()->getTelephone(),
            sprintf('%s: precommande %s ANNULEE. Motif: %s',
                $this->smsExpediteur, $precommande->getNumero(), $this->abreger($raison, 60)),
            'AUTRE',
            $precommande
        );
    }

    public function alerteRupture(Stock $stock, string $telephone): void
    {
        $this->mettreEnFile(
            $telephone,
            sprintf('%s: RUPTURE de %s a %s. Stock a 0 poche.',
                $this->smsExpediteur,
                $stock->getGroupeSanguin()->getCode(),
                $this->abreger($stock->getBanque()->getNom())),
            'ALERTE_RUPTURE'
        );
    }

    public function alertePeremption(string $telephone, int $nombre, int $jours): void
    {
        $this->mettreEnFile(
            $telephone,
            sprintf('%s: %d poche(s) perimen(t) dans moins de %d jours. A utiliser en priorite.',
                $this->smsExpediteur, $nombre, $jours),
            'ALERTE_PEREMPTION'
        );
    }

    /**
     * Enregistre le message en base PUIS le pousse dans la file. L'ordre compte :
     * si le worker meurt, la trace existe déjà et le message sera rejoué.
     */
    public function mettreEnFile(
        string $destinataire,
        string $message,
        string $type = 'AUTRE',
        ?Precommande $precommande = null,
    ): NotificationSms {
        $notification = (new NotificationSms())
            ->setDestinataire($destinataire)
            ->setMessage($this->sansAccent($message))
            ->setType($type)
            ->setPrecommande($precommande)
            ->setStatut(NotificationSms::EN_ATTENTE)
            ->setTentatives(0)
            ->setDateCreation(new \DateTimeImmutable());

        $this->em->persist($notification);
        $this->em->flush();

        $this->bus->dispatch(new EnvoyerSms($notification->getId()));

        return $notification;
    }

    private function sansAccent(string $texte): string
    {
        $translittere = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texte);

        return false === $translittere ? $texte : $translittere;
    }

    private function abreger(string $texte, int $longueur = 40): string
    {
        $texte = trim(explode('—', $texte)[0]);

        return mb_strlen($texte) > $longueur ? mb_substr($texte, 0, $longueur - 1).'.' : $texte;
    }
}
