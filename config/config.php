<?php
declare(strict_types=1);

// Guard against double-include
if (defined('APP_LOADED')) {
    return;
}
define('APP_LOADED', true);

// Database credentials
const DB_HOST    = 'localhost';
const DB_NAME    = 'gestion_etudiants';
const DB_USER    = 'root';
const DB_PASS    = '';
const DB_CHARSET = 'utf8mb4';

// Application settings
const APP_NAME = 'Gestion des étudiants';
const APP_ENV  = 'development'; // 'development' | 'production'
const BASE_URL = '/TP_CRUD_en_PHP_MySQL_MORJAN_KHALID/public/';

// Filesystem roots — every path uses __DIR__, no relative '../' guessing elsewhere
const APP_ROOT      = __DIR__ . '/..';
const INCLUDES_PATH = APP_ROOT . '/includes';
const PAGES_PATH    = APP_ROOT . '/pages';

// Configure error reporting based on environment
if (APP_ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
} else {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    ini_set('log_errors', '1');
}

/**
 * Returns a singleton PDO instance configured for the application.
 *
 * @return PDO
 */
function getPDO(): PDO {
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        return $pdo;
    } catch (PDOException $e) {
        error_log('Erreur de connexion BDD : ' . $e->getMessage());
        http_response_code(500);
        if (APP_ENV === 'development') {
            exit('Erreur de connexion : ' . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
        exit('Erreur interne du serveur. Veuillez réessayer plus tard.');
    }
}
