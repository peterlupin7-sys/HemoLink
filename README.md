<p align="center">
  <img src="docs/identite/logo-hemolink.svg" alt="HemoLink" height="64">
</p>

<h1 align="center">HemoLink</h1>

<p align="center">
  <strong>Géolocalisation des banques de sang et précommande de poches au Bénin.</strong><br>
  <em>Chaque poche sauve une vie.</em>
</p>

---

## Le problème

Quand une transfusion devient urgente, personne ne sait où se trouve la poche
disponible. Les familles font le tour des hôpitaux, téléphone à la main, pendant
que les banques de sang voisines jettent des poches périmées faute de demande.
L'information existe : elle n'est simplement reliée à personne.

**HemoLink** relie les deux bouts. Une carte publique montre, en temps réel,
quelle banque dispose de quel groupe sanguin. Un formulaire en trois étapes
permet de réserver les poches avant de se déplacer. Les banques, elles,
disposent enfin d'un outil de gestion de stock qui tient dans un téléphone.

## Ce que fait l'application

| Pour le public | Pour les banques de sang |
| --- | --- |
| Carte des banques les plus proches, triées par distance | Mise à jour du stock en une seule page, pensée pour le mobile |
| Disponibilité par groupe sanguin, avec l'âge de l'information | Traitement des précommandes : confirmer, préparer, remettre |
| Précommande sans créer de compte | Bon de retrait imprimable et signé des deux parties |
| Suivi par numéro et notification SMS | Alertes de péremption et tableau de bord |
| Groupes de substitution proposés en cas de rupture | Journal des actions et sauvegardes automatisées |

| Pour les donneurs | Pour le personnel habilité |
| --- | --- |
| Créneaux de collecte ouverts, banque par banque | File des dons en attente d'analyses |
| Prise de rendez-vous en ligne, une place bloquée à la confirmation | Saisie des quatre marqueurs et du groupe confirmé |
| Annulation ou déplacement, qui rend aussitôt la place | Entrée au stock déclenchée par la seule qualification conforme |
| Historique de ses dons et rappel SMS la veille | Traçabilité : qui a qualifié quoi, et quand |

Quatre règles de gestion gouvernent tout le reste :

1. **La quantité affichée est comptée, jamais saisie.** `stock.quantite_disponible`
   est le nombre exact de poches à l'état `DISPONIBLE`, et seul `StockService`
   peut l'écrire.
2. **L'état se calcule.** 0 poche → rupture ; 1 à 4 → tension ; 5 et plus →
   disponible ; information vieille de plus de 24 h → « non communiqué ». Un
   chiffre dont on ne répond pas n'est pas affiché comme fiable.
3. **Le numéro de précommande est atomique.** Il est tiré de la table `compteur`
   avec un `SELECT … FOR UPDATE` : deux demandes simultanées ne peuvent pas
   obtenir le même numéro. Le même verrou protège la dernière place d'un créneau
   de collecte.
4. **Seul un produit qualifié conforme entre au stock.** Un don prélevé n'est pas
   une poche : tant que le personnel habilité n'a pas rendu les analyses, rien
   n'est créé. Un produit écarté ne produit aucune poche, donc rien qu'une
   précommande puisse réserver.

## Pile technique

- **PHP 8.1+** et **Symfony 6.4 LTS**
- **Doctrine ORM 2.17**, **MySQL 8** ou **MariaDB 10.4** (InnoDB, `utf8mb4_unicode_ci`)
- **Twig** pour les gabarits, aucun framework JavaScript
- **Leaflet 1.9** servi en local, fond de carte OpenStreetMap
- **Messenger** (transport Doctrine) pour la file d'envoi des SMS
- **PHPUnit 9.6** pour les tests

Aucune dépendance à un CDN : polices, feuilles de style, cartes et icônes sont
servies par l'application. Un poste de l'hôpital derrière une connexion lente ou
filtrée affiche la même chose qu'un poste de bureau.

## Installation

### Prérequis

- PHP 8.1 ou supérieur avec les extensions `pdo_mysql`, `intl`, `ctype`, `iconv`
- Composer 2
- MySQL 8 ou MariaDB 10.4 (XAMPP, WAMP, Laragon ou un serveur dédié)

### En cinq commandes

```bash
git clone https://github.com/<votre-compte>/hemolink.git
cd hemolink
composer install
cp .env .env.local          # puis ajustez DATABASE_URL dans .env.local
php bin/console doctrine:database:create --if-not-exists
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console doctrine:fixtures:load --no-interaction
symfony serve                # ou : php -S 0.0.0.0:8000 -t public
```

L'application répond alors sur <http://localhost:8000>.

### Variante phpMyAdmin / XAMPP

Si vous préférez partir de la base déjà remplie plutôt que des migrations :

1. Démarrez Apache et MySQL depuis le panneau XAMPP.
2. Ouvrez <http://localhost/phpmyadmin>, onglet **Importer**.
3. Choisissez `base/hemolink.sql`, puis **Exécuter**.

Le script crée la base, les 14 tables, les 2 vues et un jeu de démonstration
complet (12 banques, 604 poches, 7 précommandes). Il commence par supprimer une
éventuelle base `hemolink` existante : ne le lancez pas sur des données réelles.

Détail de la base, des règles et des requêtes types :
[`docs/base_de_donnees.md`](docs/base_de_donnees.md).

## Comptes de démonstration

| Rôle | Identifiant | Mot de passe |
| --- | --- | --- |
| Administrateur national | `admin@hemolink.bj` | `hemolink2026` |
| Gestionnaire CNTS | `gestionnaire.cnts@hemolink.bj` | `hemolink2026` |
| Gestionnaire CNHU-HKM | `gestionnaire.cnhu@hemolink.bj` | `hemolink2026` |
| Gestionnaire CHUD Ouémé | `gestionnaire.chud-oueme@hemolink.bj` | `hemolink2026` |
| Gestionnaire CHUD Borgou | `gestionnaire.chud-borgou@hemolink.bj` | `hemolink2026` |
| Agent CNTS | `agent.cnts@hemolink.bj` | `agent2026` |
| Agent CNHU-HKM | `agent.cnhu@hemolink.bj` | `agent2026` |
| Agent CHUD Ouémé | `agent.chud-oueme@hemolink.bj` | `agent2026` |
| Personnel habilité CNTS | `biologiste.cnts@hemolink.bj` | `hemolink2026` |
| Personnel habilité CNHU-HKM | `biologiste.cnhu@hemolink.bj` | `hemolink2026` |
| Donneur (Armand SOSSOU) | `donneur.sossou@hemolink.bj` | `agent2026` |
| Donneur (Mireille TOGNON) | `donneur.tognon@hemolink.bj` | `agent2026` |

> Ces comptes n'existent que pour la démonstration. Avant toute mise en service,
> supprimez-les et créez les vrais comptes avec
> `php bin/console app:creer-utilisateur`.

## Organisation du dépôt

```
hemolink/
├── base/            script SQL complet, importable dans phpMyAdmin
├── bin/console      point d'entrée des commandes
├── config/          configuration Symfony et des paquets
├── docs/            cahier des charges, charte graphique, schémas, base
├── migrations/      schéma initial et module don (Doctrine Migrations)
├── public/          racine web : index.php, CSS, JS, images, polices, Leaflet
├── src/
│   ├── Command/     tâches planifiées et outils d'exploitation
│   ├── Controller/  carte, précommande, suivi, gestion, collecte, qualification,
│   │                 espace donneur, administration
│   ├── DataFixtures/données de référence et jeu de démonstration
│   ├── Entity/      18 entités Doctrine
│   ├── Form/        formulaires de précommande, de banque, de demandeur
│   ├── Repository/  requêtes métier (Haversine, FIFO péremption, compteurs)
│   ├── Security/    contrôle d'accès aux précommandes
│   ├── Service/     géolocalisation, stock, compatibilité, numérotation, SMS
│   └── Twig/        filtres d'affichage (états, distances, téléphones)
├── templates/       22 gabarits Twig
└── tests/           tests unitaires PHPUnit
```

## Commandes utiles

```bash
# Expirer les précommandes non retirées au-delà du délai (à planifier chaque heure)
php bin/console app:expirer-precommandes

# Alerter les banques dont des poches approchent de la péremption (une fois par jour)
php bin/console app:alerte-peremption --jours=7

# Sauvegarder la base dans var/sauvegardes, en conservant les 14 dernières
php bin/console app:sauvegarde --garder=14

# Créer un compte réel (le mot de passe est demandé, jamais écrit dans l'historique)
php bin/console app:creer-utilisateur agent@chu.bj AGENT --banque=BQ-COT-001

# Créer un compte de personnel habilité (qualification) ou de donneur
php bin/console app:creer-utilisateur labo@cnts.bj PERSONNEL --banque=BQ-COT-001
php bin/console app:creer-utilisateur donneur@exemple.bj DONNEUR

# Rappeler par SMS les donneurs attendus demain (une fois par jour, en fin d'après-midi)
php bin/console app:rappel-rendez-vous --jours=1

# Vider la file des SMS en attente
php bin/console messenger:consume async -vv
```

Exemple de planification (crontab) :

```cron
0 * * * *  cd /var/www/hemolink && php bin/console app:expirer-precommandes
30 6 * * * cd /var/www/hemolink && php bin/console app:alerte-peremption
0 2 * * *  cd /var/www/hemolink && php bin/console app:sauvegarde --garder=14
0 17 * * * cd /var/www/hemolink && php bin/console app:rappel-rendez-vous
```

## Tests

```bash
composer test          # ou : vendor/bin/phpunit
```

Les tests couvrent ce qui ne doit jamais se tromper : la compatibilité
transfusionnelle (un rhésus négatif ne reçoit jamais de positif, O− est donneur
universel, AB+ est receveur universel), le calcul des distances, le calcul des
états de stock et le cycle de vie d'une précommande. Le module don y ajoute
l'éligibilité du donneur (délai de récupération, bornes d'âge, inaptitude
déclarée, un seul rendez-vous à la fois), la disponibilité d'un créneau et la
lecture des marqueurs infectieux, où un seul résultat positif écarte le
produit.

## Accessibilité

L'information d'état n'est jamais portée par la couleur seule : chaque stock
affiche une pastille, un mot et un nombre. Les contrastes de la charte ont été
vérifiés (19 combinaisons, niveau AA), les cibles tactiles font 44 pixels, la
page offre un lien d'évitement et les formulaires restent utilisables sans
JavaScript — la carte se dégrade en liste.

## Envoi des SMS

Par défaut, `SMS_MODE=simulation` : les messages sont enregistrés en base et
dans les journaux, sans être envoyés. Pour brancher une passerelle, renseignez
`SMS_URL` et `SMS_CLE_API` dans `.env.local`, puis passez `SMS_MODE=reel`. Les
messages sont volontairement écrits sans accent et limités à 160 caractères,
pour passer sur n'importe quel terminal.

## Documentation

| Document | Fichier |
| --- | --- |
| Cahier des charges v1.0 (32 pages) | `docs/Cahier_des_charges_HemoLink_v1.0.pdf` |
| Charte graphique « Pouls » v2.0 | `docs/Charte_graphique_HemoLink.pdf` |
| Présentation de l'identité visuelle | `docs/Presentation_Identite_HemoLink.pdf` |
| Base de données (tables, règles, requêtes) | `docs/base_de_donnees.md` |
| Schémas UML, MCD, MLD, architecture | `docs/figures/` |

## Projet académique

Institut universitaire **« Les Cours Sonou »** — Licence 3, Systèmes
informatiques et logiciels (SIL). **Groupe 1**, année académique 2025–2026,
sous la supervision de **M. Briand SAIZONOU**.

| Membre | Lot de travail |
| --- | --- |
| BALLARABE Amrath | Carte, géolocalisation et calcul des distances |
| HONFO Jennyfer | Entités Doctrine, migrations, fixtures et base de données |
| HOUKONNOU Daniel | Précommandes, numérotation séquentielle et transactions |
| KOKOYE Alida | Sécurité, rôles et droits d'accès ; gabarits Twig et accessibilité |
| YANSOUNNOU Géovanni | Notifications SMS, sauvegardes, tests PHPUnit et documentation |

Les rôles Scrum (Product Owner, Scrum Master, équipe de développement) sont
tenus par les membres du groupe et tournent d'un sprint à l'autre.

## Licence

Code distribué sous licence MIT (voir `LICENSE`). L'identité visuelle « Pouls »
— logotype, symbole et déclinaisons — reste propre au projet HemoLink.
