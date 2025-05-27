# 📝 Changelog - sj4webcleaningdb

## [1.1.0] - Améliorations post-stabilisation

### ✅ Ajouté :
- Internationalisation complète de l’interface (BO, formulaires, intitulés, etc.)
- Traductions multilingues via fichiers `.xlf` (`fr-FR`, `en-US`) conformes aux règles PrestaShop 8+
- Nom du module dans le back-office désormais préfixé : `SJ4WEB -`
- Nettoyage visuel et factorisation du code dans la configuration BO
- Regroupement et homogénéisation des traductions via le domaine `Modules.Sj4webcleaningdb.Admin`

### ⚠️ Limitations toujours en place :
- Le module **ne prend pas en charge le multi-boutique**
- La **lecture/écriture des logs n’est pas encore internationalisée** (sera traitée dans une prochaine version)

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

### ⚠️ Limites connues :
- Le module **ne prend pas en charge le multi-boutique**
- La **partie lecture/écriture des logs n’est pas encore internationalisée** (sera corrigée dans une version ultérieure)
