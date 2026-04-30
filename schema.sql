-- -----------------------------------------------------------------------------
-- Gestion des étudiants — Schéma de base de données
-- -----------------------------------------------------------------------------

CREATE DATABASE IF NOT EXISTS gestion_etudiants
    DEFAULT CHARACTER SET utf8mb4
    DEFAULT COLLATE utf8mb4_unicode_ci;

USE gestion_etudiants;

CREATE TABLE IF NOT EXISTS etudiants (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    nom        VARCHAR(100) NOT NULL,
    prenom     VARCHAR(100) NOT NULL,
    email      VARCHAR(150) NOT NULL UNIQUE,
    filieres   VARCHAR(100) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Données de démonstration
INSERT INTO etudiants (nom, prenom, email, filieres) VALUES
    ('El Amrani', 'Yassine', 'yassine.elamrani@example.ma', 'Génie Informatique'),
    ('Bennani',   'Salma',   'salma.bennani@example.ma',   'Génie Civil'),
    ('Chakir',    'Mehdi',   'mehdi.chakir@example.ma',    'Génie Mécanique'),
    ('Tazi',      'Hajar',   'hajar.tazi@example.ma',      'Génie Industriel'),
    ('Idrissi',   'Karim',   'karim.idrissi@example.ma',   'Génie Électrique');
