
# sj4webcleaningdb

## 📦 Description

`sj4webcleaningdb` est un module PrestaShop destiné à nettoyer et optimiser automatiquement la base de données de votre boutique.  
Il permet de supprimer les anciennes données inutiles (paniers expirés, connexions, logs, etc.), d'optimiser les tables, et de conserver des performances optimales sur votre site.

---

## ✅ Fonctionnalités principales

- Nettoyage des tables volumineuses ou obsolètes (ex. `cart`, `connections`, `statssearch`, etc.)
- Définition des tables à nettoyer et du nombre de jours à conserver dans le Back Office
- Journalisation des nettoyages dans des fichiers log horodatés (`logs/YYYY-MM-DD-HHMMSS.log`)
- Affichage d’un résumé lisible des actions effectuées (suppressions, taille avant/après)
- Suppression automatique des anciens fichiers logs (selon la durée choisie)
- Tâche CRON sécurisée pour automatiser le nettoyage
- Optimisation des tables (`OPTIMIZE TABLE`) après suppression

---

## ⚙️ Installation

1. Installer le module via le back office de PrestaShop ou déposer le dossier dans `/modules/`
2. Accéder à la page de configuration dans le menu Modules > sj4webcleaningdb
3. Cocher les tables à nettoyer et renseigner le nombre de jours à conserver
4. Copier l’URL CRON affichée pour l’ajouter à votre tâche planifiée (exécution automatique)

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

## 📁 Logs

- Les logs sont enregistrés dans `/modules/sj4webcleaningdb/logs/`
- Deux vues sont disponibles dans le BO :
  - **Vue brute** du fichier log
  - **Synthèse lisible**, par table et action

---

## 🛠 Configuration recommandée

| Table          | Jours à conserver | Remarques                         |
|----------------|-------------------|-----------------------------------|
| `cart`         | 30                | Supprime les paniers abandonnés   |
| `connections`  | 15                | Réduit les historiques trop longs |
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
