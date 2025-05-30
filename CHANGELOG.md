# 📝 Changelog - sj4webcleaningdb

## [1.2.0-php73] - 2025-05-30 — Version compatible PHP 7.3

### 🎯 Spécifique à cette branche :
- Retrait de toutes les syntaxes non compatibles PHP 7.3 :
  - Suppression de l’opérateur `??=`.
  - Retrait des types de retour (`: string`, `: array`, etc.).
- Nettoyage pour assurer un fonctionnement stable sur des environnements PHP 7.3 (mutualisés basiques, anciens serveurs).

### 📝 Notes :
- Fonctionnalité identique à la branche principale **1.2.0**.
- Prévu pour rester rétro-compatible avec PrestaShop 1.7.8.x et 8.0.x.

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
- Compatibilité renforcée PHP 7.3 : plus de `match`, code adapté proprement

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
- Compatibilité : PrestaShop **1.7.8 à 8.x**, PHP **>= 7.3**

### ⚠️ Limitations connues :
- Le module **ne prend pas en charge le multi-boutique**
