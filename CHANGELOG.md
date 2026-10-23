# 📝 Changelog - sj4webcleaningdb

## [1.3.0] - 2026-10-22 — Suppression par lots, backup sans blocage, optimisation ciblée

### Fixed
- Le nettoyage ne s'arrête plus à la première erreur `1062 Duplicate entry` : la copie vers les tables `save_*` utilise
  désormais `INSERT IGNORE`. Une exécution interrompue entre la copie et la suppression bloquait auparavant toutes les
  exécutions suivantes (observé en production de août 2025 à octobre 2026 : plus aucune purge, tables de statistiques
  de plusieurs dizaines de Go).
- Une erreur sur une table n'interrompt plus le nettoyage des tables suivantes (`table_error` dans le log).

### Changed
- Suppressions par lots (`SJ4WEB_CLEANINGDB_BATCH_SIZE`, 5000 lignes par défaut) avec pause entre lots et temporisation
  automatique quand MariaDB est chargé (`Threads_running`) : plus de gros `DELETE ... NOT IN (SELECT ...)` en une seule requête.
- Durée maximale d'un passage (`SJ4WEB_CLEANINGDB_MAX_MINUTES`, 25 min par défaut) : arrêt propre puis reprise au passage suivant
  (`purge_time_limit` dans le log).
- Les sous-requêtes `NOT IN (SELECT DISTINCT ...)` sont remplacées par `NOT EXISTS`.
- Optimisation (`OPTIMIZE TABLE`) uniquement pour les tables ayant perdu au moins 100 000 lignes pendant le passage. Avant : reconstruction
  complète chaque nuit de toutes les tables activées (`OPTIMIZE` + `ALTER TABLE ENGINE=InnoDB` + `COUNT(*)`), soit plusieurs Go réécrits à 4 h.
  Le bouton « Optimize tables now » du BO continue d'optimiser toutes les tables activées.
- L'option « backup » (tables `save_*`) reste désactivable dans le BO ; elle est désactivée par défaut à l'installation.

### Added
- `cli/purge_stats_batches.php` : script autonome de purge par lots des tables `connections`, `connections_source` et `guest`
  (simulation par défaut, `--execute` pour supprimer, reprenable, verrou, temporisation).
- `cli/drop_save_tables.php` : script autonome de suppression des tables d'archive `save_*` (simulation par défaut, double confirmation,
  refuse si l'option de backup est encore active).
- `upgrade/upgrade-1.3.0.php` : initialise les nouvelles clés de configuration.

## [1.2.0] - 2025-05-30 — Ajout du mode CLI et gestion avancée des erreurs

### Added
- Ajout d’un script CLI (`cli/run_cleaning.php`) pour lancer le nettoyage via la ligne de commande.
- Ajout d’un système de verrouillage (`Sj4WebLockManager`) pour empêcher les exécutions concurrentes.
- Envoi automatique d’un mail d’alerte en cas d’échec du processus CLI (`mails/fr/en/alerte.html/.txt`).
- Génération d’un fichier log texte détaillé pour chaque exécution CLI (`logs/cli/*.log`).
- Ajout du dossier `dev-tools` (tests non inclus dans les releases).

### Fixed
- Correction du chemin d’accès à `config.inc.php` pour compatibilité avec tous les environnements (Windows/Linux).

### Changed
- Refactorisation et nettoyage du script CLI.

## [1.1.3] - Envoi automatique par email du rapport de nettoyage 
- Corrige l'affichage de la page des logs lorsqu'il n'y a pas de logs

## [1.1.2] - Envoi automatique par email du rapport de nettoyage

### ✅ Ajouté :
- Envoi automatique d’un **email récapitulatif** à la fin de chaque nettoyage :
  - **Option activable/désactivable** dans le BO
  - Destinataires configurables (**plusieurs emails**, séparés par virgule)
  - Si vide, envoi à **l’email de la boutique**
- Résumé du nettoyage dans l’email :
  - Liste des tables nettoyées
  - Nombre de lignes supprimées par table
- Utilisation du **système natif de mail PrestaShop**
- Ajout des templates email :
  - `cleaning_report.html` / `cleaning_report.txt`
  - Versions en **fr-FR** et **en-US**
  - Traductions via `{l s=... d=Modules.Sj4webcleaningdb.Admin}`
- Ajout de deux clés de configuration :
  - `cleaning_mail_enabled`
  - `cleaning_mail_recipients`
- Nouvelle méthode : `TableCleanerHelper::getCleaningReportEmails()`


## [1.1.1] - Lecture enrichie des logs + Résumé BO

### ✅ Ajouté :
- Lecture structurée des fichiers `.log` au format JSON
- Enrichissement automatique des entrées avec contexte, type, timestamp, et chaîne traduite
- Résumé dynamique par table dans le BO (lignes supprimées, optimisation, taille avant/après, gain)
- Retour de l’affichage brut du fichier log (contenu JSON brut scrollable)
- Traductions automatiques appliquées **à l’affichage BO uniquement** (le fichier `.log` reste en anglais)
- Refactorisation complète de la méthode `readLogLines()` pour fournir un tableau exploitable (plus de `preg_match`)
- Compatibilité renforcée PHP 7.4 : plus de `match`, code adapté proprement

### ♻️ Modifié :
- Le tableau résumé n'exploite plus les chaînes traduites, mais les données JSON directement
- Les fichiers `.log` sont désormais lus une seule fois et utilisés à la fois pour :
  - L’affichage humain (texte lisible, traduit)
  - Le résumé synthétique (structure par table)

### 🧼 Supprimé :
- Suppression des anciens fragments non utilisés (`entries`, index intermédiaires, etc.)

---

## [1.1.0] - Améliorations post-stabilisation

### ✅ Ajouté :
- Internationalisation complète de l’interface (BO, formulaires, intitulés, etc.)
- Traductions multilingues via fichiers `.xlf` (`fr-FR`, `en-US`) conformes aux règles PrestaShop 8+
- Nom du module dans le back-office désormais préfixé : `SJ4WEB -`
- Nettoyage visuel et factorisation du code dans la configuration BO
- Regroupement et homogénéisation des traductions via le domaine `Modules.Sj4webcleaningdb.Admin`

### ⚠️ Limitations toujours en place :
- Le module **ne prend pas en charge le multi-boutique**

---

## [1.0.0] - Première version stable

### ✅ Ajouté :
- Nettoyage automatisé des tables obsolètes (`cart`, `connections`, `guest`, `statssearch`, `pagenotfound`, `log`, `mail`)
- Optimisation des tables via `OPTIMIZE TABLE`
- Interface complète dans le Back Office avec configuration par table
- Exécution planifiable via CRON sécurisé (token)
- Journalisation des actions (suppression, optimisation, taille avant/après)
- Suppression automatique des fichiers logs trop anciens
- Interface de lecture des logs :
  - **Vue brute** (contenu JSON horodaté)
  - **Vue synthétique** (résumé par table)
- Système de traduction moderne PrestaShop 8+ (`trans()` + fichiers `.xlf` avec domaine `Modules.Sj4webcleaningdb.Admin`)
- Compatibilité : PrestaShop **1.7.8 à 8.x**, PHP **>= 7.4**

### ⚠️ Limitations connues :
- Le module **ne prend pas en charge le multi-boutique**
