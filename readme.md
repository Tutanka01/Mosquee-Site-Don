# Gestion des Contributions - Mosquée Errahma

Ce projet vise à mettre en place un système complet de gestion des contributions, dons, cotisations, et projets pour la Mosquée Errahma. Il permet de suivre les contributions des adhérents et donateurs, de gérer les cotisations mensuelles, et de visualiser les statistiques grâce à un tableau de bord interactif.

## Schema global du projet

![schema.png](images/schema.png)

> Par https://gitdiagram.com

## Fonctionnalités

- **Gestion des adhérents et donateurs** :
  - Ajout, modification et suppression d'adhérents.
  - Gestion des cotisations mensuelles avec historique.
  - Recherche avancée d'adhérents.

- **Contributions** :
  - Gestion des cotisations, dons, et projets.
  - Paiements partiels ou complets.

- **Tableau de bord interactif** :
  - Visualisation des statistiques (montants collectés, évolution mensuelle, répartition par type de contribution).
  - Suivi des cotisations mensuelles avec affichage des mois "payés", "partiels", ou "non payés".

- **Affichage dynamique** :
  - Tableau public dans le hall d'entrée montrant les cotisations mensuelles des membres.

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

- **`compose.yaml`** : Configuration Docker Compose pour le serveur PHP.
- **`src/`** : Contient les fichiers source PHP, SQL et CSS.
  - **`index.php`** : Point d'entrée principal.
  - **`dashboard.php`** : Tableau de bord pour l'administration.
  - **`public_display.php`** : Affichage public des cotisations.
  - **`db.php`** : Gestion de la connexion à la base de données SQLite.
  - **Scripts PHP** : Insertion, mise à jour, recherche, suppression des données.
  - **`style.css`** : Styles de l'application.
  - **`schema.sql`** : Schéma de la base de données SQLite.

- **`taches.md`** : Liste des tâches fonctionnelles et remarques.

## Base de Données

Le système utilise SQLite avec trois tables principales :

1. **Adherents** :
    - Informations sur les adhérents (nom, prénom, email, montant mensuel, etc.).
2. **Contributions** :
    - Détails des contributions (type, montant, mode de paiement, etc.).
3. **Cotisation_Months** :
    - Suivi des cotisations mensuelles par adhérent et par mois.

## Exemple d'Utilisation

### Ajouter un Adhérent
1. Accédez à la page d'accueil.
2. Cliquez sur "Ajouter un nouvel adhérent".
3. Remplissez le formulaire et soumettez-le.

### Enregistrer une Contribution
1. Accédez à la page d'accueil.
2. Sélectionnez le type de contribution (cotisation, don, projet).
3. Remplissez le formulaire et soumettez-le.

### Visualiser les Statistiques
1. Accédez au tableau de bord.
2. Consultez les différents onglets pour voir les statistiques globales, les cotisations, les dons et les projets.

## Logique Applicative

### Gestion des Cotisations Mensuelles
- Les adhérents ont un montant mensuel personnalisé.
- Lors d'une contribution, le système calcule automatiquement les mois "payés", "partiels" ou "non payés".
- Les cotisations peuvent couvrir plusieurs mois en fonction du montant.

### Paiements Partiels
- Si une cotisation est insuffisante pour couvrir un mois complet, le système applique la somme aux mois partiels.

### Recherche et Historique
- Recherche avancée des adhérents par nom, email ou téléphone.
- Historique complet des contributions d'un adhérent avec affichage des détails.

### Tableau de Bord
- **Statistiques Globales** : Montants collectés, évolution mensuelle, répartition par type de contribution.
- **Cotisations Mensuelles** : Affichage des mois "payés", "partiels" ou "non payés".

### Affichage Public
- Tableau récapitulatif des cotisations visibles dans le hall d'entrée.
- Affichage dynamique avec un design clair et lisible.

## Workflow de Contribution

### 1. Ajout d'un Adhérent
1. L'utilisateur ouvre la fenêtre modale "Ajouter un nouvel adhérent".
2. Le formulaire est soumis via AJAX (éléments vérifiés : email unique, montant mensuel, etc.).
3. Les informations sont ajoutées à la table `Adherents`.
4. Si une cotisation mensuelle est prévue, les entrées correspondantes sont générées dans `Cotisation_Months`.

### 2. Enregistrement d'une Contribution
1. L'utilisateur sélectionne le type de contribution (cotisation, don, projet).
2. Le montant est soumis et vérifié.
3. Les données sont insérées dans la table `Contributions`.
4. Pour les cotisations, le montant est réparti sur les mois applicables.

## Auteurs

- [El Akhal El Bouzidi Mohamad](https://github.com/tutanka01)