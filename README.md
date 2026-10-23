# sj4webcleaningdb

## 📦 Description

`sj4webcleaningdb` est un module PrestaShop destiné à nettoyer et optimiser automatiquement la base de données de votre boutique.  
Il permet de supprimer les anciennes données inutiles (paniers expirés, connexions, logs, etc.), d'optimiser les tables, et de conserver des performances optimales sur votre site.

---

## ✅ Fonctionnalités principales

- Nettoyage des tables volumineuses ou obsolètes (ex. `cart`, `connections`, `statssearch`, etc.)
- Définition des tables à nettoyer et du nombre de jours à conserver dans le Back Office
- Journalisation des nettoyages dans des fichiers log horodatés (`logs/YYYY-MM-DD-HHMMSS.log`)
- Lecture structurée des logs et affichage d’un résumé dynamique par table (suppressions, optimisation, taille avant/après)
- Suppression automatique des anciens fichiers logs (selon la durée choisie)
- Tâche CRON sécurisée pour automatiser le nettoyage
- Optimisation des tables (`OPTIMIZE TABLE`) après suppression
- Traductions multilingues via système moderne PrestaShop 8+
- Envoi automatique d’un **email récapitulatif** à la fin de chaque nettoyage
- **Option activable** depuis le back-office

---

## 🧩 Compatibilité

- PrestaShop **1.7.8** à **8.x**
- PHP **>= 7.4**

---

## ⚙️ Installation

1. Installer le module via le back office de PrestaShop ou déposer le dossier dans `/modules/`
2. Accéder à la page de configuration dans le menu Modules > sj4webcleaningdb
3. Cocher les tables à nettoyer et renseigner le nombre de jours à conserver
4. Copier l’URL CRON affichée pour l’ajouter à votre tâche planifiée (exécution automatique)

> 🎯 Vous pouvez relancer manuellement un nettoyage depuis la configuration du module.

---

## 🔄 Tâche CRON

Une URL CRON sécurisée est générée automatiquement.  
Elle permet de lancer le nettoyage sans intervention humaine.

**Exemple :**
```
https://votresite.com/modules/sj4webcleaningdb/cron.php?token=XXXXXXXX
```

Exécute cette URL régulièrement via un cron job (ex. chaque nuit à 4h).

---

## 💻 Exécution en ligne de commande (mode CLI)

Depuis la version 1.2.0, le module peut être exécuté directement via une commande CLI.

### ➤ Exemple :
```bash
php modules/sj4webcleaningdb/cli/run_cleaning.php
```

- Un mécanisme de verrouillage (`lock`) empêche l’exécution simultanée du processus.
- En cas d’erreur, un mail est automatiquement envoyé à l’adresse de la boutique (ou adresse configurée à venir).
- Un fichier de log texte est créé avec le détail de l'exécution (`logs/cli/YYYYMMDD_HHMMSS.log`).
- Le traitement s’appuie sur la même configuration que dans le BO.

### 📂 Emplacement du script :
`/modules/sj4webcleaningdb/cli/run_cleaning.php`

---

## 🧹 Purge manuelle des tables de statistiques (scripts autonomes)

Deux scripts CLI indépendants du module (ils n'utilisent que `app/config/parameters.php`) : ils fonctionnent même si le module est en panne.
Ils sont en **simulation par défaut** (aucune suppression sans `--execute`).

```bash
# 1. Simulation : combien de lignes seraient supprimées (rétention des connexions : 30 jours)
php modules/sj4webcleaningdb/cli/purge_stats_batches.php --days=30

# 2. Suppression réelle par lots (reprenable ; arrêt propre au bout de --max-minutes)
php modules/sj4webcleaningdb/cli/purge_stats_batches.php --days=30 --execute --max-minutes=30

# 3. (optionnel, hors heures de pointe) récupérer l'espace disque
php modules/sj4webcleaningdb/cli/purge_stats_batches.php --days=30 --execute --optimize
```

⚠️ Ne pas lancer entre 03h45 et 04h45 (cron du module à 04h00).

Suppression des tables d'archive `save_*` (inutiles) — désactiver d'abord l'option « backup » dans le BO :

```bash
php modules/sj4webcleaningdb/cli/drop_save_tables.php                                   # simulation
php modules/sj4webcleaningdb/cli/drop_save_tables.php --execute --confirm=DROP-SAVE-TABLES
```

### Réglages de la suppression par lots (table `configuration`)

| Clé | Défaut | Rôle |
|---|---|---|
| `SJ4WEB_CLEANINGDB_BATCH_SIZE` | 5000 | lignes examinées par lot |
| `SJ4WEB_CLEANINGDB_MAX_MINUTES` | 25 | durée maximale d'un passage (reprise au passage suivant) |
| `SJ4WEB_CLEANINGDB_ENABLE_BACKUP` | 0 | copie les lignes supprimées dans `save_*` (BO : option « backup ») |

---

## 📁 Dossier dev-tools

Un dossier `dev-tools` est présent pour effectuer des tests en local (par exemple : tests de verrouillage parallèle).
**⚠️ Ce dossier ne doit pas être inclus dans les versions de production.**

---

## 📁 Logs

- Les logs sont enregistrés dans `/modules/sj4webcleaningdb/logs/`
- Deux vues sont disponibles dans le Back Office :
  - **Vue brute** du fichier log
  - **Synthèse lisible**, par table et action

> ℹ️ Les fichiers `.log` restent en anglais pour garantir la stabilité des données,  
> mais l’affichage dans le Back Office est **entièrement traduit** grâce à la structure enrichie.

---

## 🌐 Traduction

Le module utilise le **nouveau système de traduction PrestaShop 8+**.  
Toutes les chaînes sont déclarées via la méthode `trans()` avec un domaine spécifique :

- **Domaine utilisé :** `Modules.Sj4webcleaningdb.Admin`
- Fichiers de traduction au format **XLF** : `/translations/fr-FR/modules.sj4webcleaningdb.admin.xlf`, etc.

Pour traduire les libellés du back-office :

1. Accédez à **International > Traductions**
2. Choisissez :
  - Type de traduction : *Modules installés*
  - Sélectionnez le module : *sj4webcleaningdb*
  - Choisissez la langue : *Français (ou autre)*
3. Traduisez les chaînes selon vos besoins.

---

## 🛠 Configuration recommandée

| Table          | Jours à conserver | Remarques                         |
|----------------|-------------------|-----------------------------------|
| `cart`         | 180               | Supprime les paniers abandonnés   |
| `connections`  | 30                | Réduit les historiques trop longs |
| `guest`        | 90                | Supprime les guests inutiles      |
| `statssearch`  | 90                | Allège la table des recherches    |
| `pagenotfound` | 60                | Nettoie les erreurs 404 anciennes |

---

## ✍️ Auteur

Développé par **SJ4WEB.FR**  
Contact : [https://sj4web.fr](https://sj4web.fr)

---

## 📌 Licence

Module propriétaire – Usage réservé au client final. Reproduction ou diffusion interdite sans autorisation.
