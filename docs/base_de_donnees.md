# HemoLink — Base de données

**Fichier** : `hemolink.sql` · **Base** : `hemolink` · **Version** 1.0 — Octobre 2026
**Cible** : MySQL 8.x ou MariaDB 10.4+ (XAMPP) · InnoDB · `utf8mb4_unicode_ci`

---

## 1. Installation

**Avec phpMyAdmin**
1. Démarrer Apache et MySQL dans XAMPP.
2. Ouvrir `http://localhost/phpmyadmin` → onglet **Importer**.
3. Choisir `hemolink.sql` → **Exécuter**. La base est créée, remplie et prête.

**En ligne de commande**
```bash
mysql -u root -p < hemolink.sql
```

Le script est **ré-exécutable** : il supprime les tables existantes avant de les recréer.
Attention, cela efface donc les données saisies depuis le dernier import.

**Connexion depuis Symfony** — dans `.env.local` :
```
DATABASE_URL="mysql://root:@127.0.0.1:3306/hemolink?serverVersion=10.4.32-MariaDB&charset=utf8mb4"
```

---

## 2. Comptes de démonstration

| Adresse | Mot de passe | Rôle | Portée |
|---|---|---|---|
| `admin@hemolink.bj` | `hemolink2026` | `ROLE_ADMIN` | Toutes les banques (CNTS) |
| `gestionnaire.cnts@hemolink.bj` | `hemolink2026` | `ROLE_GESTIONNAIRE` | CNTS Cotonou |
| `gestionnaire.cnhu@hemolink.bj` | `hemolink2026` | `ROLE_GESTIONNAIRE` | CNHU-HKM |
| `gestionnaire.chud-oueme@hemolink.bj` | `hemolink2026` | `ROLE_GESTIONNAIRE` | CHUD Ouémé |
| `gestionnaire.chud-borgou@hemolink.bj` | `hemolink2026` | `ROLE_GESTIONNAIRE` | CHUD Borgou |
| `agent.cnts@hemolink.bj` | `agent2026` | `ROLE_AGENT` | Saisie des stocks CNTS |
| `agent.cnhu@hemolink.bj` | `agent2026` | `ROLE_AGENT` | Saisie des stocks CNHU |
| `agent.chud-oueme@hemolink.bj` | `agent2026` | `ROLE_AGENT` | Saisie des stocks CHUD Ouémé |
| `biologiste.cnts@hemolink.bj` | `hemolink2026` | `ROLE_PERSONNEL` | Qualification des dons CNTS |
| `biologiste.cnhu@hemolink.bj` | `hemolink2026` | `ROLE_PERSONNEL` | Qualification des dons CNHU |
| `donneur.sossou@hemolink.bj` | `agent2026` | `ROLE_DONNEUR` | Ses propres rendez-vous |
| `donneur.tognon@hemolink.bj` | `agent2026` | `ROLE_DONNEUR` | Ses propres rendez-vous |

Les mots de passe sont hachés en **bcrypt coût 12**, au format `$2y$` attendu par Symfony.
**À supprimer avant toute mise en service réelle.**

---

## 3. Les 19 tables

| # | Table | Rôle | Clés et colonnes notables |
|---|---|---|---|
| 1 | `groupe_sanguin` | Les 8 groupes ABO/Rhésus | `code` unique, `part_population`, `rang` |
| 2 | `compatibilite` | Qui peut recevoir de qui | PK composite receveur + donneur, `priorite` |
| 3 | `banque` | Banques de sang géolocalisées | `code` unique, `latitude`/`longitude` indexés, `statut` |
| 4 | `utilisateur` | Comptes du personnel | `email` unique, `mot_de_passe` bcrypt, `roles` JSON, `banque_id` |
| 5 | `stock` | État agrégé lu par la carte | unique (`banque_id`, `groupe_sanguin_id`), `etat`, `date_maj` |
| 6 | `demandeur` | Établissements et particuliers | `type`, `telephone` indexé, `numero_agrement` |
| 7 | `precommande` | La réservation | `numero` unique, `statut`, `urgence`, dates du cycle de vie |
| 8 | `ligne_precommande` | Détail par groupe | unique (`precommande_id`, `groupe_sanguin_id`), `groupe_substitue_id` |
| 9 | `poche` | L'unité physique tracée | `code_poche` unique, `date_peremption` indexée, `statut` |
| 10 | `mouvement_stock` | Historique des variations | `type`, `quantite` signée, `quantite_apres` |
| 11 | `notification_sms` | File d'envoi des SMS | `statut`, `tentatives`, `erreur` |
| 12 | `compteur` | Numérotation sans doublon | PK `cle`, `prefixe`, `annee`, `valeur` |
| 13 | `journal` | Traçabilité des actions | `action`, `entite`, `adresse_ip`, `date_action` |
| 14 | `parametre` | Réglages sans redéploiement | PK `cle`, `valeur`, `type` |
| 15 | `donneur` | Personnes qui donnent leur sang | `numero_donneur` unique, `utilisateur_id` unique et facultatif, `apte` |
| 16 | `creneau_don` | Plages de collecte ouvertes | unique (`banque_id`, `date_creneau`, `heure_debut`), `capacite`, `places_reservees` |
| 17 | `rendez_vous_don` | Réservation d'une place | `numero` unique, unique (`donneur_id`, `creneau_id`), `statut` |
| 18 | `don` | Prélèvement réalisé | `code_don` unique, `rendez_vous_id` unique et facultatif, `hemoglobine` |
| 19 | `qualification` | Analyses et validation | unique sur `don_id` **et** sur `poche_id`, `resultat`, 4 marqueurs |

**34 clés étrangères**, toutes nommées (`fk_…`), avec la bonne politique de suppression :
`CASCADE` quand l'enfant n'a pas de sens sans le parent (les stocks d'une banque),
`RESTRICT` quand la suppression doit être empêchée (un groupe sanguin référencé),
`SET NULL` quand le lien est facultatif (l'utilisateur qui a traité une précommande).

Trois vues de confort sont fournies : `vue_stock_banque`, `vue_precommande_en_cours` et
`vue_creneau_disponible` (créneaux encore réservables, avec leurs places restantes).

---

## 4. Les quatre règles de gestion portées par le schéma

**1. Le stock agrégé et les poches disent la même chose.**
`stock.quantite_disponible` = nombre de poches `DISPONIBLE` de la banque pour ce groupe.
`stock.quantite_reservee` = nombre de poches `RESERVEE`. La table `stock` existe pour que
la carte réponde vite ; `poche` reste la vérité unitaire. **Toute écriture passe par
`StockService`, jamais par un UPDATE direct.**

**2. L'état se calcule, il ne se saisit pas.**
`quantite_disponible = 0` → `RUPTURE` · `1 à 4` → `TENSION` · `≥ 5` → `DISPONIBLE` ·
`date_maj` de plus de 24 h → `NON_COMMUNIQUE`. Le seuil est dans `parametre.seuil_tension`,
pas en dur dans le code.

**3. Le numéro de précommande vient du compteur, jamais de l'`AUTO_INCREMENT`.**
`NumerotationService` verrouille la ligne `compteur` (`SELECT … FOR UPDATE`), incrémente,
puis forme `PRE-COT-2026-00001`. C'est ce qui empêche deux précommandes simultanées de
recevoir le même numéro.

**4. Un don ne devient du stock qu'après une qualification conforme (DON-05).**
`qualification.poche_id` n'est renseigné que lorsque `resultat = 'CONFORME'`. Un produit
écarté n'a **aucune poche** : elle n'est pas créée, pas même avec un statut d'attente.
C'est ce qui rend impossible la réservation d'un produit non qualifié, puisqu'une
précommande ne peut réserver que des poches `DISPONIBLE`. La contrainte `UNIQUE` sur
`qualification.poche_id` interdit par ailleurs qu'une même poche soit issue de deux dons.

---

## 5. Jeu de démonstration livré

| Contenu | Volume |
|---|---|
| Banques réelles (Cotonou, Abomey-Calavi, Porto-Novo, Parakou, Abomey, Lokossa, Natitingou, Tanguiéta) | 12 |
| Comptes utilisateurs (1 admin, 4 gestionnaires, 3 agents, 2 personnels habilités, 2 donneurs) | 12 |
| Groupes sanguins et règles de compatibilité | 8 + 27 |
| Poches individuelles avec dates de prélèvement et de péremption | 604 |
| Lignes de stock (12 banques × 8 groupes) | 96 |
| Précommandes couvrant tous les statuts | 7 |
| Mouvements de stock, SMS, entrées de journal, paramètres | 24 + 12 + 9 + 18 |
| Donneurs, dont deux disposant d'un compte en ligne | 8 |
| Créneaux de collecte (4 banques, J-7 → J+14, dont 1 collecte mobile) | 216 |
| Rendez-vous (5 planifiés, 16 honorés, 1 annulé, 1 absence) | 23 |
| Dons (12 qualifiés, 2 écartés, 2 en cours d'analyse) | 16 |
| Qualifications (12 conformes, 2 non conformes) | 14 |

Les états sont volontairement variés : **47 disponibles, 35 en tension, 6 ruptures,
8 non communiqués** (une banque n'a pas mis son stock à jour depuis plus de 24 h). La carte
montre donc les quatre couleurs de la charte dès le premier lancement.

Côté collecte, **148 créneaux restent ouverts et 1 179 places sont libres** : l'espace
donneur n'est jamais vide à la démonstration. Les dons conformes sont rattachés à des
poches **déjà comptées** dans le stock — aucune poche n'est ajoutée par le module don,
pour que les quantités affichées restent exactes.

> Les coordonnées GPS sont réalistes mais **à vérifier banque par banque** avant une
> utilisation réelle, de même que les numéros de téléphone, qui sont fictifs.

---

## 6. Requêtes utiles

**Banques les plus proches ayant du O− disponible** (formule de Haversine, celle que
`GeolocalisationService` exécutera en SQL natif) :

```sql
SELECT b.nom, s.quantite_disponible,
       ROUND(6371 * ACOS(LEAST(1,
             COS(RADIANS(:lat)) * COS(RADIANS(b.latitude))
           * COS(RADIANS(b.longitude) - RADIANS(:lon))
           + SIN(RADIANS(:lat)) * SIN(RADIANS(b.latitude)))), 2) AS distance_km
FROM banque b
JOIN stock s          ON s.banque_id = b.id
JOIN groupe_sanguin g ON g.id = s.groupe_sanguin_id
WHERE g.code = 'O-' AND s.quantite_disponible > 0 AND b.statut = 'ACTIVE'
HAVING distance_km <= :rayon
ORDER BY distance_km
LIMIT 10;
```

**Groupes compatibles avec un receveur donné** :

```sql
SELECT gd.code
FROM compatibilite c
JOIN groupe_sanguin gr ON gr.id = c.groupe_receveur_id
JOIN groupe_sanguin gd ON gd.id = c.groupe_donneur_id
WHERE gr.code = :groupe
ORDER BY c.priorite, gd.id;
```

**Poches à surveiller (péremption sous 7 jours)** — requête de `app:alerte-peremption` :

```sql
SELECT b.nom, g.code, p.code_poche, p.date_peremption
FROM poche p
JOIN banque b         ON b.id = p.banque_id
JOIN groupe_sanguin g ON g.id = p.groupe_sanguin_id
WHERE p.statut = 'DISPONIBLE'
  AND p.date_peremption <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
ORDER BY p.date_peremption;
```

---

## 7. Correspondance avec les entités Doctrine

| Table | Entité | Relations principales |
|---|---|---|
| `banque` | `Banque` | OneToMany `Stock`, `Poche`, `Utilisateur`, `Precommande` |
| `utilisateur` | `Utilisateur` (`UserInterface`) | ManyToOne `Banque` |
| `groupe_sanguin` | `GroupeSanguin` | ManyToMany `GroupeSanguin` (compatibilité) |
| `stock` | `Stock` | ManyToOne `Banque`, `GroupeSanguin` |
| `poche` | `Poche` | ManyToOne `Banque`, `GroupeSanguin`, `Precommande` |
| `demandeur` | `Demandeur` | OneToMany `Precommande` |
| `precommande` | `Precommande` | OneToMany `LignePrecommande`, ManyToOne `Banque`, `Demandeur` |
| `ligne_precommande` | `LignePrecommande` | ManyToOne `Precommande`, `GroupeSanguin` |
| `mouvement_stock` | `MouvementStock` | ManyToOne `Banque`, `GroupeSanguin`, `Utilisateur` |
| `notification_sms` | `NotificationSms` | ManyToOne `Precommande` |
| `compteur`, `journal`, `parametre` | `Compteur`, `Journal`, `Parametre` | — |
| `donneur` | `Donneur` | ManyToOne `Utilisateur`, `GroupeSanguin` ; OneToMany `RendezVousDon`, `Don` |
| `creneau_don` | `CreneauDon` | ManyToOne `Banque` ; OneToMany `RendezVousDon` |
| `rendez_vous_don` | `RendezVousDon` | ManyToOne `Donneur`, `CreneauDon` |
| `don` | `Don` | ManyToOne `Donneur`, `Banque`, `RendezVousDon`, `GroupeSanguin`, `Utilisateur` |
| `qualification` | `Qualification` | OneToOne `Don` ; ManyToOne `Poche`, `Utilisateur`, `GroupeSanguin` |

Deux manières de passer à Symfony :
- **Rétro-ingénierie** : `php bin/console doctrine:mapping:import` puis
  `make:entity --regenerate` — rapide, mais le code généré demande du nettoyage.
- **Écriture des entités à la main** à partir de ce schéma, puis
  `make:migration` — plus long, mais c'est le chemin que le professeur attend d'un
  projet Symfony, et les migrations deviennent la référence.

---

## 8. Vérifier la base après import

Le script `outils/valide_sql.py` rejoue l'intégralité de `hemolink.sql` dans une base
SQLite temporaire et contrôle **neuf règles métier** avant toute mise en ligne :

1. intégrité référentielle (`PRAGMA foreign_key_check`) ;
2. `stock.quantite_disponible` = comptage réel des poches `DISPONIBLE` ;
3. `stock.etat` déduit de la quantité, jamais saisi ;
4. aucune qualification `NON_CONFORME` ne porte de poche ;
5. toute qualification `CONFORME` en porte une ;
6. le statut du don est cohérent avec sa qualification ;
7. `creneau_don.places_reservees` = rendez-vous `PLANIFIE` + `HONORE` ;
8. aucun créneau en surbooking, aucun don rattaché à un rendez-vous non honoré ;
9. toute qualification est signée par un compte `ROLE_PERSONNEL`.

```bash
python3 outils/gen_sql.py     # régénère base/hemolink.sql
python3 outils/valide_sql.py  # doit afficher « TOUT EST COHERENT »
```
