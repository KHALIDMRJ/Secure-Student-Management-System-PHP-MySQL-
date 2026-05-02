<?php
/**
 * Logout endpoint. POST only — prevents drive-by logouts via <img src> /
 * malicious links / GET redirects. Requires a valid CSRF token.
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/auth.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    configure_session();
    session_start();
}

// 1. Reject GET / HEAD / anything that isn't POST.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('?page=index');
}

// 2. CSRF check — without this, an attacker could embed a hidden form
//    that POSTs to ?page=logout from another origin.
if (!csrf_token_verify()) {
    abort(403, 'Jeton CSRF invalide.');
}

// 3. Tear down the session and redirect (never returns).
logout_admin();
