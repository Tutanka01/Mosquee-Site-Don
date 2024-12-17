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

## Captures d'Écran

![Formulaire de Contribution](screenshots/formulaire_contribution.png)
![Tableau de Bord](screenshots/tableau_de_bord.png)

## Auteurs

- [Votre Nom](https://github.com/votre-utilisateur)

## Licence

Ce projet est sous licence MIT. Voir le fichier LICENSE pour plus de détails.