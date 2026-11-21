<?php

namespace App\Exception;

/**
 * Règle de gestion non respectée : le message est destiné à l'utilisateur.
 *
 * Cette classe existe pour une raison précise, découverte en lançant dix
 * réservations simultanées. Les services signalaient leurs refus métier avec
 * `\RuntimeException`, et les contrôleurs affichaient le message attrapé tel
 * quel. Or `Doctrine\DBAL\Exception` hérite elle aussi de `\RuntimeException` :
 * une panne technique passait donc par le même chemin, et le donneur lisait
 *
 *   « An exception occurred while executing a query: SQLSTATE[HY000]:
 *     General error: 1020 Record has changed since last read... »
 *
 * là où il aurait fallu lui dire que le créneau venait d'être pris.
 *
 * La distinction est désormais explicite : tout ce qui porte cette classe est
 * un refus prévu, formulé en français et montrable. Tout le reste est une
 * anomalie, qui doit remonter, être journalisée et donner une page d'erreur —
 * jamais un message incompréhensible glissé dans l'interface.
 */
class RegleMetier extends \RuntimeException
{
}
