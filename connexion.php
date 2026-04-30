<?php
function connexion(): PDO {
    $host     = 'localhost';
    $dbname   = 'gestion_etudiants';
    $username = 'root';
    $password = '';

    // et pour  utf8mb4 parce que  c'est le charset le plus complet et il supporte tous les caracteres et les guillemets simples n'interpolent pas les variables et $host , $dbname , $username , $password sont des variables et doivent etre interpoler pour qu'ils soient reconnus comme des variables et pas comme des chaines de caracteres
    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false, // vraies requêtes préparées (anti SQL injection)
    ];

    try {
        return new PDO($dsn, $username, $password, $options);
    } catch (PDOException $e) {
        // On loggue le détail mais on n'expose rien à l'utilisateur
        error_log("Erreur de connexion BDD : " . $e->getMessage());
        die("Erreur de connexion à la base de données.");
    }
}
