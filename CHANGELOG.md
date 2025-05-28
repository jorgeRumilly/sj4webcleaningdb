# 📝 Changelog - sj4webcleaningdb

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
