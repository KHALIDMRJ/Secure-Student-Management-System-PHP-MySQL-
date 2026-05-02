<?php
/**
 * One-shot CLI installer for the authentication system.
 *
 * Usage:
 *   php database/install_auth.php
 *
 * Creates the `admins` table and seeds a default admin if none exist.
 * Default credentials are PRINTED to stdout — change them after first login.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(1);
}

require_once __DIR__ . '/../config/config.php';

$pdo = getPDO();

// 1. Create the admins table.
$pdo->exec("
    CREATE TABLE IF NOT EXISTS admins (
        id            INT AUTO_INCREMENT PRIMARY KEY,
        username      VARCHAR(60)  NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        full_name     VARCHAR(100) NOT NULL DEFAULT '',
        last_login    DATETIME     NULL,
        created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// 2. Seed a default admin if the table is empty.
$count = (int)$pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn();

if ($count === 0) {
    $defaultPassword = 'admin123';
    $hash = password_hash($defaultPassword, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare(
        "INSERT INTO admins (username, password_hash, full_name)
         VALUES ('admin', :h, 'Administrator')"
    );
    $stmt->bindValue(':h', $hash, PDO::PARAM_STR);
    $stmt->execute();

    echo "OK admins table created\n";
    echo "OK default admin created\n";
    echo "    Username: admin\n";
    echo "    Password: {$defaultPassword}\n";
    echo "    !! Change this password after first login.\n";
} else {
    echo "OK admins table already exists ({$count} admin(s))\n";
}
