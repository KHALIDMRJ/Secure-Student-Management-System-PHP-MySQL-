<?php
function connexion(): PDO {
    $host     = 'localhost';
    $dbname   = 'gestion_etudiants';
    $username = 'root';
    $password = '';

    
    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false, 
    ];

    try {
        return new PDO($dsn, $username, $password, $options);
    } catch (PDOException $e) {
        error_log("Erreur de connexion BDD : " . $e->getMessage());
        die("Erreur de connexion à la base de données.");
    }
}
