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
