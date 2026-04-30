# TP CRUD en PHP / MySQL — Gestion des étudiants

Application web simple permettant de gérer une liste d'étudiants (ajout, affichage, modification, suppression) en utilisant **PHP** et **MySQL** via **PDO**.

## Auteur

**Khalid MORJANE**

## Fonctionnalités

- Afficher la liste des étudiants
- Ajouter un nouvel étudiant
- Modifier les informations d'un étudiant existant
- Supprimer un étudiant
- Validation des champs côté serveur
- Protection contre les injections SQL (requêtes préparées PDO)
- Protection contre les attaques CSRF (jeton de session)
- Protection contre les attaques XSS (échappement HTML)

## Prérequis

- **XAMPP** (ou équivalent : Apache + PHP 7.4+ + MySQL/MariaDB)
- Un navigateur web

## Installation

### 1. Cloner le projet

Placer le dossier du projet dans le répertoire `htdocs` de XAMPP :

```
C:\xampp\htdocs\TP_CRUD_en_PHP_MySQL_MORJAN_KHALID
```

### 2. Démarrer les services

Lancer **Apache** et **MySQL** depuis le panneau de contrôle XAMPP.

### 3. Créer la base de données

Ouvrir [phpMyAdmin](http://localhost/phpmyadmin) puis exécuter :

```sql
CREATE DATABASE gestion_etudiants CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE gestion_etudiants;

CREATE TABLE etudiants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL,
    filieres VARCHAR(100) NOT NULL
);
```

### 4. Configurer la connexion

Les paramètres de connexion sont définis dans `connexion.php` :

| Paramètre | Valeur par défaut |
|-----------|-------------------|
| host      | localhost         |
| dbname    | gestion_etudiants |
| username  | root              |
| password  | *(vide)*          |

### 5. Accéder à l'application

Ouvrir dans le navigateur :

```
http://localhost/TP_CRUD_en_PHP_MySQL_MORJAN_KHALID/index.php
```

## Structure du projet

```
TP_CRUD_en_PHP_MySQL_MORJAN_KHALID/
├── connexion.php     # Connexion PDO à la base de données
├── index.php         # Page d'accueil — liste des étudiants
├── ajouter.php       # Formulaire d'ajout d'un étudiant
├── modifier.php      # Formulaire de modification d'un étudiant
├── supprimer.php     # Suppression d'un étudiant
└── README.md
```

## Sécurité

- **Requêtes préparées (PDO)** pour toutes les interactions avec la base
- **Jeton CSRF** pour les formulaires d'ajout, de modification et de suppression
- **Validation et nettoyage** des entrées (`trim`, `filter_var`, expressions régulières)
- **Échappement HTML** systématique avec `htmlspecialchars` pour prévenir le XSS
- **Confirmation utilisateur** avant suppression
