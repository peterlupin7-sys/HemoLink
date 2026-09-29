<?php

namespace App\Message;

/**
 * Demande d'envoi d'un SMS déjà enregistré en base.
 *
 * On ne transporte que l'identifiant : le contenu reste en base, donc un message
 * resté en file après un redémarrage est toujours lisible et rejouable.
 */
final class EnvoyerSms
{
    public function __construct(private readonly int $notificationId)
    {
    }

    public function getNotificationId(): int
    {
        return $this->notificationId;
    }
}
