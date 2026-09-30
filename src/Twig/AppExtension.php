<?php

namespace App\Twig;

use App\Entity\Precommande;
use App\Entity\Stock;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Filtres d'affichage conformes à la charte graphique.
 *
 * Règle d'accessibilité du projet : un état n'est jamais signalé par la seule
 * couleur. Ces fonctions renvoient donc toujours un libellé avec la classe CSS.
 */
class AppExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('classe_etat', [$this, 'classeEtat']),
            new TwigFilter('libelle_etat', [$this, 'libelleEtat']),
            new TwigFilter('classe_statut', [$this, 'classeStatut']),
            new TwigFilter('distance', [$this, 'distance']),
            new TwigFilter('telephone', [$this, 'telephone']),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('marqueur', [$this, 'marqueur']),
        ];
    }

    public function classeEtat(string $etat): string
    {
        return match ($etat) {
            Stock::DISPONIBLE => 'etat-disponible',
            Stock::TENSION => 'etat-tension',
            Stock::RUPTURE => 'etat-rupture',
            default => 'etat-inconnu',
        };
    }

    public function libelleEtat(string $etat): string
    {
        return Stock::LIBELLES[$etat] ?? 'Non communiqué';
    }

    public function classeStatut(string $statut): string
    {
        return match ($statut) {
            Precommande::EN_ATTENTE => 'statut-attente',
            Precommande::CONFIRMEE => 'statut-confirmee',
            Precommande::PRETE => 'statut-prete',
            Precommande::RETIREE => 'statut-retiree',
            default => 'statut-annulee',
        };
    }

    /**
     * Les distances courtes se lisent mieux en mètres.
     */
    public function distance(?float $km): string
    {
        if (null === $km) {
            return '';
        }

        return $km < 1
            ? sprintf('%d m', (int) round($km * 1000, -1))
            : sprintf('%s km', number_format($km, 1, ',', ' '));
    }

    public function telephone(?string $numero): string
    {
        if (!$numero) {
            return '';
        }

        $chiffres = preg_replace('/\D+/', '', $numero) ?? '';

        if (str_starts_with($chiffres, '229') && 11 === \strlen($chiffres)) {
            return sprintf('+229 %s %s %s %s',
                substr($chiffres, 3, 2), substr($chiffres, 5, 2),
                substr($chiffres, 7, 2), substr($chiffres, 9, 2));
        }

        return $numero;
    }

    /**
     * Fichier de marqueur de carte correspondant à l'état du stock.
     */
    public function marqueur(string $etat): string
    {
        return match ($etat) {
            Stock::DISPONIBLE => 'images/marqueur-carte.svg',
            Stock::TENSION => 'images/marqueur-carte-tension.svg',
            default => 'images/marqueur-carte-rupture.svg',
        };
    }
}
