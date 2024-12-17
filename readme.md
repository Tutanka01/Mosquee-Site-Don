# Gestion des Contributions - Mosquée Errahma

Ce projet a pour but de mettre en place un système de gestion des dons, cotisations et projets pour la Mosquée Errahma. Il permet de suivre les contributions des adhérents et donateurs, de gérer les cotisations mensuelles et de visualiser les statistiques des contributions.

## Fonctionnalités

- Gestion des adhérents et donateurs
- Suivi des cotisations mensuelles
- Gestion des dons et projets
- Tableau de bord avec statistiques et graphiques
- Recherche d'adhérents et historique des contributions

## Prérequis

- Docker
- Docker Compose

## Installation

1. Clonez le dépôt :
    ```sh
    git clone https://github.com/votre-utilisateur/votre-repo.git
    cd votre-repo
    ```

2. Lancez les services avec Docker Compose :
    ```sh
    docker-compose up -d
    ```

3. Accédez à l'application dans votre navigateur à l'adresse [http://localhost:8080](http://localhost:8080).

## Structure du Projet

- [compose.yaml](http://_vscodecontentref_/1) : Configuration Docker Compose
- [src](http://_vscodecontentref_/2) : Contient les fichiers source PHP et SQL
- [scripts](http://_vscodecontentref_/3) : Contient les scripts nécessaires
- [taches.md](http://_vscodecontentref_/4) : Liste des tâches à réaliser

## Exemple d'Utilisation

### Ajouter un Adhérent

1. Accédez à la page d'accueil.
2. Cliquez sur "Ajouter un nouvel adhérent".
3. Remplissez le formulaire et soumettez-le.

### Enregistrer une Contribution

1. Accédez à la page d'accueil.
2. Remplissez le formulaire de contribution.
3. Cliquez sur "Enregistrer".

### Visualiser les Statistiques

1. Accédez au tableau de bord.
2. Consultez les différentes sections pour voir les statistiques globales, les cotisations, les dons et les projets.

## Auteurs

- [El Akhal El Bouzidi Mohamad](https://github.com/tutanka01)

## Workflow de Contribution

### Création d’un nouvel adhérent
- L’utilisateur ouvre la fenêtre modale "Ajouter un nouvel adhérent".
- Il remplit les champs obligatoires (nom, prénom, email, téléphone) et optionnels (montant mensuel, dates d’adhésion).
- Le formulaire est soumis (envoi AJAX vers `insert_adherent.php`).
- Côté serveur :  
  - Vérification de l’unicité de l’email.  
  - Insertion de l’adhérent dans la table `Adherents`.  
  - Si `monthly_fee` > 0, génération des entrées `Cotisation_Months` pour les mois applicables (paid_amount = 0).
- Retour JSON succès/erreur.
- Côté client :  
  - Si succès, l’adhérent est ajouté dans la liste déroulante, informations préremplies.  
  - Si erreur, affichage du message d’erreur.

### Ajout d’une contribution (cotisation, don, projet)
- L’utilisateur sélectionne le type de contribution dans `index.php` (cotisation, don, projet).
- Selon le type, les champs affichés diffèrent (adhérent obligatoire pour cotisation, anonyme possible pour don/projet).
- L’utilisateur saisit le montant et le mode de paiement, puis soumet.
- Côté serveur (`insert.php`) :  
  - Vérification des données (montant, type paiement, si cotisation alors adhérent non-anonyme obligatoire).  
  - Insertion dans la table `Contributions`.  
  - Si cotisation : récupération du `monthly_fee` et répartition du paiement sur les mois `Cotisation_Months` (paid_amount) de manière séquentielle.  
- Retour message succès/erreur.
- Côté client : affichage d’un message "Contribution enregistrée avec succès".

### Paiement partiel d’une cotisation
- L’utilisateur effectue une cotisation avec un montant insuffisant pour couvrir le mois en entier (ou un lot de mois).
- Côté serveur lors de l’insertion :  
  - On calcule le `reste` à payer pour chaque mois non entièrement payé (monthly_fee - paid_amount).  
  - On applique le montant reçu pour réduire le `paid_amount` du premier mois partiellement ou totalement.  
  - S’il reste de l’argent, on passe au mois suivant, sinon on s’arrête.
- Côté client, rien de spécial, juste un message de succès. Le tableau mensuel reflètera ce changement (partiel ou payés).

### Recherche d’un adhérent
- L’utilisateur va dans l’onglet "Recherche Adhérent" du dashboard.
- Saisit une chaîne de recherche (nom/email).
- Clique sur "Rechercher" ⇒ envoi AJAX vers `fetch_adherent_search.php`.
- Côté serveur :  
  - SELECT sur `Adherents` avec un LIKE sur nom/prenom/email.  
  - Retourne une liste JSON d’adhérents correspondants.
- Côté client :  
  - Affiche la liste des résultats sous forme de liens.  
  - Au clic sur un adhérent, envoi AJAX vers `fetch_adherent_history.php` pour récupérer l’historique.
- Affichage dans le dashboard de l’historique des contributions de cet adhérent.

### Affichage du dashboard (onglets)
- L’utilisateur se rend sur `dashboard.php`.
- Par défaut, l’onglet "Statistiques Globales" est affiché :  
  - Graphiques générés (line chart pour les montants mensuels, pie chart pour la répartition).
  - Tableau top donateurs, stats globales.
- L’utilisateur peut cliquer sur "Cotisations", "Dons", "Projets" pour voir les listes filtrées.
- L’onglet "Liste des Contributions" affiche toutes les contributions paginées.
- L’onglet "Cotisations Mensuelles" affiche un tableau par adhérent et par mois :  
  - "N/A" si non applicable.  
  - "Non Payé" si paid_amount=0.  
  - "Partiel (X/Y)" si 0<paid_amount<monthly_fee.  
  - "Payé" si paid_amount=monthly_fee.

### Mise à jour automatique des données
- Après chaque insertion d’adhérent ou de contribution, la liste des adhérents (sur `index.php`) ou la liste des contributions (`dashboard.php`) peut être actualisée en rafraîchissant la page ou via AJAX (si implémenté).