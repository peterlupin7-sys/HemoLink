# Recette de HemoLink — exécution réelle

Ce document rend compte de la première exécution complète de l'application sur
une machine réelle : installation des dépendances, création de la base,
chargement des données, suite de tests et parcours utilisateur menés à la main.

Jusque-là, le code avait été relu et vérifié syntaxiquement, mais jamais lancé.
Dix défauts sont apparus dès les premières minutes d'exécution. Ils sont listés
ici sans rien retrancher, parce qu'ils disent quelque chose d'utile sur ce
qu'une relecture ne peut pas voir.

## Environnement

| Composant | Version |
| --- | --- |
| PHP | 8.4.28 (`pdo_mysql`, `intl`, `mbstring`, `zip`, `gd`, `xsl`) |
| Composer | 2.11.2 |
| MariaDB | 11.8.9 |
| Symfony | 6.4.50 |
| PHPUnit | 9.6.39 |

Bases utilisées : `hemolink` (développement) et `hemolink_test` (tests), en
utf8mb4 / `utf8mb4_unicode_ci`.

## Ce qui a été exécuté

1. `composer install` — 118 paquets, sans erreur.
2. `doctrine:migrations:migrate` — 3 migrations, 23 requêtes.
3. `doctrine:fixtures:load` — 4 jeux de fixtures.
4. Import de `base/hemolink.sql` dans une base vierge.
5. `vendor/bin/phpunit` — **61 tests, 186 assertions, aucun échec**.
6. `lint:twig`, `lint:yaml`, `lint:container`, `doctrine:schema:validate` — tous
   au vert.
7. Les cinq commandes `app:*`.
8. Les parcours des cinq rôles, à travers un serveur HTTP réel.

## Les dix défauts trouvés, et leur correction

### 1. Le formulaire public de précommande ne pouvait jamais aboutir

Le plus grave. La fonction principale du site — déposer une précommande sans
compte — renvoyait systématiquement un code 422, c'est-à-dire le formulaire
réaffiché. La validation automatique déduite du mapping Doctrine
(`auto_mapping`) réclamait trois choses impossibles :

- un `numero` non nul, alors qu'il est attribué par `NumerotationService`
  *après* la validation ;
- des `\DateTime` pour `dateSouhaitee` et `dateCreation`, alors que
  l'application manipule des `\DateTimeImmutable`.

Correction : `config/packages/validator.yaml` n'active plus `auto_mapping`. Les
règles métier sont déclarées explicitement, en français, dans `App\Form` — une
seule source de vérité, et des messages lisibles par un demandeur.

### 2. Le formulaire n'affichait aucune erreur

Corollaire du précédent, et tout aussi gênant : le gabarit n'appelait
`form_errors()` que sur la collection de lignes. Une erreur globale — jeton de
sécurité expiré, session perdue — renvoyait un formulaire identique et muet.
`templates/precommande/nouvelle.html.twig` affiche désormais un bloc d'alerte
dès que le formulaire est invalide.

### 3. Le demandeur saisi n'était jamais enregistré

Une fois la validation franchie, le dépôt échouait sur
« A new entity was found through the relationship `Precommande#demandeur` ».
`PrecommandeService::deposer()` rattache maintenant le demandeur : le numéro de
téléphone sert de clé, si bien qu'un hôpital qui commande une deuxième fois
retrouve sa fiche au lieu d'en créer une copie.

### 4. Un biologiste pouvait ouvrir le dossier d'une autre banque

`/qualification/{id}` ne vérifiait pas la banque du don. La liste était bien
filtrée, mais l'adresse restait devinable : le biologiste du CNTS ouvrait le
dossier d'un don prélevé au CNHU, analyses comprises, et pouvait le qualifier.
`QualificationController::verifierBanque()` ferme la porte sur la consultation
comme sur le prononcé. Quatre tests fonctionnels le vérifient désormais.

### 5. Collision de numéros au premier enregistrement

Les fixtures écrivaient 23 rendez-vous et 16 dons numérotés à la main, sans
jamais renseigner la table `compteur`. La première réservation faite depuis
l'application repartait de 1 et heurtait la contrainte d'unicité :
`Duplicate entry 'RDV-2026-00001'`. Le script SQL livré avait le même défaut.
Les deux positionnent maintenant les compteurs sur le dernier numéro écrit.

Au passage, deux règles de numérotation coexistaient : les fixtures tiraient le
code ville du code de la banque (`PNO`), le service le tire du nom de la ville
(`POR`). Les deux jeux de données passent désormais par
`NumerotationService::codeVille()`.

### 6. Des créneaux déjà passés étaient proposés à la réservation

La requête filtrait sur la date mais pas sur l'heure. En fin de journée, le
donneur voyait des créneaux du matin avec un bouton « Réserver » qui le renvoyait
invariablement sur « Ce créneau est déjà passé ».

### 7. Le compte à rebours entre deux dons perdait un jour

`joursAvantProchainDon()` comparait une date à minuit avec l'instant courant.
Consulté à 23 h, un donneur lisait « 59 jours » là où il lui en restait 60. Le
délai se compte maintenant de minuit à minuit, et six tests couvrent les heures
qui posaient problème.

### 8. Une page d'administration renvoyait une erreur 500

`{% for role in utilisateur.roles if role != 'ROLE_USER' %}` : cette syntaxe a
disparu avec Twig 3. La page des utilisateurs était inaccessible. Le `if` est
remplacé par un `|filter`. À noter : `lint:twig` détecte cette faute en une
seconde — elle n'aurait jamais dû survivre à un commit.

### 9. Les dates s'affichaient en anglais

« Saturday 21 november 2026 » sur l'espace donneur d'une application
francophone. `default_locale: fr` était absent, et le filtre `|date` de Twig
ignore la locale. Les deux dates en toutes lettres passent par `format_date`.

### 10. Trois incohérences dans le jeu de démonstration

Invisibles sans exécution, puisqu'elles ne se voient qu'une fois les données
écrites :

- quatre dons conformes sans poche rattachée, en violation de la règle DON-05 ;
- trois lignes de stock fausses, les précommandes réservant des poches après le
  calcul des quantités ;
- deux listes de paramètres divergentes entre les fixtures et le script SQL,
  dont un `type` absent de l'ENUM qui faisait échouer le chargement.

La table `messenger_messages` manquait également au schéma : le premier SMS
aurait planté en production.

## Ce que la vérification manuelle avait laissé passer

Les contrôles faits sans exécuter — `php -l`, relecture des signatures,
équilibrage des balises Twig — n'ont attrapé aucun de ces dix défauts. Ils
portaient tous sur le comportement : une configuration qui bloque, une requête
qui ne filtre pas assez, un objet jamais persisté, un contrôle d'accès absent.

Trois outils auraient suffi à en détecter une bonne partie avant tout
lancement : `lint:twig` (défaut 8), `lint:container`, et surtout le chargement
des fixtures, qui a révélé à lui seul les défauts 5, 10 et une partie du 1.

## Parcours vérifiés de bout en bout

| Parcours | Résultat |
| --- | --- |
| Dépôt d'une précommande anonyme | `PRE-POR-2026-00003`, SMS en file, redirection vers le suivi |
| Second dépôt, même téléphone | Fiche demandeur réutilisée, pas de doublon |
| Confirmer → Prête → Retirée | Stock 14 → 12 disponibles, 2 réservées puis délivrées |
| Réservation d'un créneau | `RDV-2026-00024`, SMS de rappel en file |
| Pointage « présent » | Rendez-vous `HONORE` |
| Enregistrement du prélèvement | `DON-COT-2026-0017`, statut `EN_QUALIFICATION` |
| Qualification conforme | Poche `PO-37-3A7E39CA` créée, stock O+ 32 → 33 |
| Qualification non conforme (VIH+) | Don `REJETE`, stock et nombre de poches inchangés |
| Cloisonnement entre banques | 403 sur la consultation comme sur le prononcé |
| Cloisonnement entre rôles | Donneur et biologiste refusés sur `/gestion/collecte` |

La règle DON-05 — aucune poche au stock sans qualification conforme — a été
vérifiée dans les deux sens, sur des données réelles.

## Contrôles d'intégrité après chargement

Sept requêtes passées sur la base chargée, toutes à zéro :

- qualification non conforme portant une poche ;
- qualification conforme sans poche ;
- poche rattachée à deux qualifications ;
- quantité de stock différente du comptage des poches disponibles ;
- quantité réservée différente du comptage des poches réservées ;
- état de stock incohérent avec la quantité ;
- qualification prononcée par un compte sans `ROLE_PERSONNEL`.
