<?php

namespace App\MessageHandler;

use App\Entity\NotificationSms;
use App\Message\EnvoyerSms;
use App\Repository\NotificationSmsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Envoie réellement le SMS.
 *
 * Deux modes :
 *  - simulation (par défaut) : rien ne part, le message est journalisé. C'est ce
 *    qui permet de démontrer la plateforme sans contrat opérateur.
 *  - passerelle : appel HTTP à l'API de l'opérateur, avec remontée d'erreur pour
 *    que Messenger rejoue le message plus tard.
 */
#[AsMessageHandler]
final class EnvoyerSmsHandler
{
    public function __construct(
        private readonly NotificationSmsRepository $notifications,
        private readonly EntityManagerInterface $em,
        private readonly HttpClientInterface $http,
        private readonly LoggerInterface $smsLogger,
        private readonly string $smsMode,
        private readonly string $smsUrl,
        private readonly string $smsCleApi,
        private readonly string $smsExpediteur,
    ) {
    }

    public function __invoke(EnvoyerSms $message): void
    {
        $notification = $this->notifications->find($message->getNotificationId());

        if (!$notification instanceof NotificationSms
            || NotificationSms::ENVOYE === $notification->getStatut()) {
            return;
        }

        $notification->setTentatives($notification->getTentatives() + 1);

        try {
            if ('simulation' === $this->smsMode || '' === $this->smsUrl) {
                $this->smsLogger->info('SMS simulé', [
                    'destinataire' => $notification->getDestinataire(),
                    'message' => $notification->getMessage(),
                ]);
            } else {
                $reponse = $this->http->request('POST', $this->smsUrl, [
                    'headers' => ['Authorization' => 'Bearer '.$this->smsCleApi],
                    'json' => [
                        'from' => $this->smsExpediteur,
                        'to' => $notification->getDestinataire(),
                        'text' => $notification->getMessage(),
                    ],
                    'timeout' => 15,
                ]);

                if ($reponse->getStatusCode() >= 300) {
                    throw new \RuntimeException('Passerelle SMS : code '.$reponse->getStatusCode());
                }
            }

            $notification
                ->setStatut(NotificationSms::ENVOYE)
                ->setDateEnvoi(new \DateTimeImmutable())
                ->setErreur(null);
        } catch (\Throwable $e) {
            $notification
                ->setStatut($notification->getTentatives() >= 5
                    ? NotificationSms::ECHEC
                    : NotificationSms::EN_ATTENTE)
                ->setErreur(mb_substr($e->getMessage(), 0, 255));

            $this->smsLogger->error('Échec d\'envoi du SMS', [
                'notification' => $notification->getId(),
                'erreur' => $e->getMessage(),
            ]);

            $this->em->flush();

            throw $e; // Messenger rejouera le message
        } finally {
            $this->em->flush();
        }
    }
}
