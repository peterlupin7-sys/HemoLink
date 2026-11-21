# Recette de HemoLink — exécution réelle

Ce document rend compte de la première exécution complète de l'application sur
une machine réelle : installation des dépendances, création de la base,
chargement des données, suite de tests et parcours utilisateur menés à la main.

Jusque-là, le code avait été relu et vérifié syntaxiquement, mais jamais lancé.
Dix défauts sont apparus dès les premières minutes d'exécution. Deux autres,
plus profonds, n'ont été révélés qu'en lançant plusieurs réservations au même
instant. Les douze sont listés ici sans rien retrancher, parce qu'ils disent
quelque chose d'utile sur ce qu'une relecture ne peut pas voir.

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

## Les douze défauts trouvés, et leur correction

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

### 11. Deux réservations simultanées sur deux créneaux différents s'empêchaient

Les dix premiers défauts ont été trouvés en utilisant l'application une action
après l'autre. Restait une question sans réponse : que se passe-t-il quand
plusieurs donneurs cliquent au même instant ? Une banque qui ouvre ses créneaux
du mois à 8 h connaît exactement cette situation.

Le dispositif : dix processus lancés en parallèle, chacun réservant une place,
tous synchronisés sur un horodatage commun pour qu'ils se disputent réellement
la ressource. Sans cette synchronisation, les processus se sérialisent et le
test ne prouve rien.

Trois campagnes, dont les deux premières rassurantes :

| Campagne | Dispositif | Résultat |
|---|---|---|
| 1 | un créneau de **3 places**, 10 concurrents | 3 réservations, 7 refus clairs, aucun surbooking |
| 2 | un créneau d'**1 place**, 10 concurrents | 1 réservation, 9 refus, la dernière place n'a pas été vendue deux fois |
| 3 | **10 créneaux différents**, 10 concurrents | **5 échecs sur 10** |

La troisième campagne est celle qui compte. Les donneurs ne se disputaient plus
rien : dix créneaux distincts, dix places libres. Pourtant la moitié des
tentatives échouait, et le donneur recevait ceci à l'écran :

```
SQLSTATE[HY000]: General error: 1020 Record has changed since last read
in table 'compteur'
```

La ligne `compteur`, qui attribue les numéros de rendez-vous, est traversée par
**toutes** les réservations, quel que soit le créneau. C'était le véritable
point de contention, et il était invisible tant qu'on testait une seule
ressource partagée.

La correction a demandé trois tentatives, chacune instructive :

1. le service ouvrait sa propre transaction, imbriquée dans celle de
   `RendezVousService::reserver()`. Un `rollBack()` interne condamnait la
   transaction externe tout entière. **Une transaction ne s'ouvre pas dans un
   service appelé par un service qui en a déjà ouvert une** ;
2. il lisait ensuite la ligne avec `SELECT ... FOR UPDATE` avant d'ajouter un.
   Sous MariaDB en REPEATABLE READ, toucher une ligne qu'une autre transaction
   a modifiée et validée depuis le début de la nôtre lève l'erreur 1020.
   Remplacer la lecture par un incrément atomique
   (`UPDATE compteur SET valeur = LAST_INSERT_ID(valeur + 1)`) était
   nécessaire, mais pas suffisant ;
3. tant que cette instruction s'exécutait **dans** la transaction de
   l'appelant, elle restait prisonnière de l'instantané ouvert quelques
   millisecondes plus tôt, et l'erreur revenait.

Le compteur vit désormais sur une **connexion qui lui est propre**, hors de
toute transaction métier : chaque incrément est une instruction isolée, validée
immédiatement, avec son propre instantané. Contrepartie assumée et documentée :
un numéro attribué à une réservation qui échoue ensuite n'est pas rendu, et la
série peut comporter un trou. C'est le comportement de n'importe quelle
séquence, et il vaut mieux qu'un trou que deux donneurs portant le même numéro.

Après correction, la troisième campagne donne **10 réservations sur 10**,
numéros uniques et consécutifs, et les deux premières restent inchangées
(3 sur 3, 1 sur 1, aucun surbooking).

### 12. Une erreur technique s'affichait au donneur comme un refus métier

Le message SQL brut ci-dessus n'aurait jamais dû sortir de l'application. S'il
est arrivé jusqu'à l'écran, c'est pour une raison que la relecture ne pouvait
pas voir : **`Doctrine\DBAL\Exception` hérite de `\RuntimeException`**.

Les contrôleurs écrivaient :

```php
catch (\RuntimeException $e) {
    $this->addFlash('erreur', $e->getMessage());
}
```

L'intention était d'attraper les refus métier — « ce créneau est complet »,
« vous avez déjà un rendez-vous » — et de les afficher tels quels. Mais ce
`catch` ramassait aussi les pannes techniques et les présentait au donneur avec
la même mise en forme qu'une règle de gestion.

Une classe dédiée, `App\Exception\RegleMetier`, sépare désormais les deux
familles. Les dix-neuf `throw` métier des quatre services l'utilisent, et les
six `catch` des contrôleurs ne retiennent plus qu'elle. Toute anomalie
technique repart en page d'erreur, où elle est journalisée au lieu d'être
maquillée en règle de gestion.

Un troisième point est apparu au passage, plus discret et plus pernicieux :
`RendezVousService` appelait `rollBack()` sans vérifier qu'une transaction
était encore ouverte. Quand la base avait déjà défait la transaction de son
côté, ce `rollBack()` levait « There is no active transaction », **remplaçant
la cause réelle** dans les journaux. Pendant un temps, l'erreur affichée n'avait
plus aucun rapport avec le problème. Le `rollBack()` est maintenant conditionné
à `isTransactionActive()`.

### Un test qui aurait attrapé les trois

`tests/Service/CompteurSousContentionTest.php` (5 tests) rejoue ces situations
sans avoir besoin de dix processus : il ouvre une transaction comme le fait
`reserver()`, fait modifier le compteur par une autre connexion pendant ce
temps, et vérifie que l'incrément passe, que la transaction de l'appelant
survit, et qu'aucun numéro n'est attribué deux fois.

## Ce que la vérification manuelle avait laissé passer

Les contrôles faits sans exécuter — `php -l`, relecture des signatures,
équilibrage des balises Twig — n'ont attrapé aucun de ces douze défauts. Ils
portaient tous sur le comportement : une configuration qui bloque, une requête
qui ne filtre pas assez, un objet jamais persisté, un contrôle d'accès absent.

Les défauts 11 et 12 vont plus loin encore : ils ne se voient ni à la relecture,
ni en utilisant l'application normalement. Il a fallu la mettre sous charge
pour qu'ils apparaissent — et surtout, il a fallu la mettre sous **la bonne**
charge. Les deux premières campagnes, pourtant menées sur la ressource qui
semblait la plus disputée, concluaient que tout allait bien.

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
| 10 réservations simultanées, 1 créneau de 3 places | 3 acceptées, 7 refusées, aucun surbooking |
| 10 réservations simultanées, 1 créneau d'1 place | 1 acceptée, 9 refusées |
| 10 réservations simultanées, 10 créneaux distincts | 10 acceptées, numéros uniques et consécutifs |

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
