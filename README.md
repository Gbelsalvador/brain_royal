# Battle Royal - Gestion de Tournois

Ce projet est une application web PHP permettant de gérer des tournois de type "Battle Royal". Il propose des fonctionnalités d'inscription, de gestion des utilisateurs, de création et gestion de tournois, ainsi que le suivi en temps réel des participants et des questions.

## Fonctionnalités principales
- **Inscription et connexion des utilisateurs**
- **Création, édition et gestion des tournois** (interface admin)
- **Gestion des questions et réponses**
- **Suivi des participants actifs et de leur activité**
- **Export des participants**
- **Rapports et statistiques**

## Structure du projet
- `index.php` : Page d'accueil
- `login.php`, `register.php`, `logout.php` : Authentification
- `tournament.php`, `tournaments.php` : Affichage des tournois
- `admin/` : Espace d'administration (gestion tournois, utilisateurs, questions, rapports)
  - `ajax/` : Endpoints AJAX pour données dynamiques
  - `includes/` : Fichiers partagés (sidebar, etc.)
- `api/` : Endpoints API pour interactions front-end (statut, participants, questions, réponses)
- `config/database.php` : Configuration de la base de données (PDO)
- `css/styles.css` : Feuilles de style
- `includes/` : Header et footer communs

## Installation
1. **Prérequis** :
   - Serveur web (Apache, Nginx, XAMPP, etc.)
   - PHP >= 7.0
   - MySQL/MariaDB
2. **Cloner le projet** dans le dossier web de votre serveur.
3. **Configurer la base de données** :
   - Créez une base `battle_royal` et importez la structure (voir fichier SQL si fourni).
   - Modifiez `config/database.php` si besoin (identifiants, hôte, etc.).
4. **Lancer le serveur** et accéder à `index.php` via votre navigateur.

## Sécurité
- Les accès à l'administration sont protégés.
- Utilisation de PDO pour la connexion à la base de données (protection contre les injections SQL).

## Auteurs
- [Votre nom]

## Licence
Ce projet est fourni à des fins éducatives. À adapter selon vos besoins.
