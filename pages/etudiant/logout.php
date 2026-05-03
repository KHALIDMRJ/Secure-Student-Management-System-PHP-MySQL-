<?php
/**
 * Student logout endpoint. POST + CSRF only.
 */
declare(strict_types=1);

require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/etudiant_auth.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    configure_session();
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('?page=etudiant/login');
}

if (!csrf_token_verify()) {
    abort(403, 'Jeton CSRF invalide.');
}

logout_etudiant(); // never returns
