# 📝 Changelog - sj4webcleaningdb

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

### ⚠️ Limites connues :
- Le module **ne prend pas en charge le multi-boutique**
- La **partie lecture/écriture des logs n’est pas encore internationalisée** (sera corrigée dans une version ultérieure)
