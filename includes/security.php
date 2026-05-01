<?php
declare(strict_types=1);

/**
 * Send hardened HTTP security headers.
 * Must be called before any output.
 */
function send_security_headers(): void {
    $nonce = base64_encode(random_bytes(16));
    $_SERVER['CSP_NONCE'] = $nonce;

    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    header("Content-Security-Policy: " .
        "default-src 'self'; " .
        "script-src 'self' 'nonce-{$nonce}' https://cdn.jsdelivr.net; " .
        "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net " .
            "https://cdnjs.cloudflare.com https://fonts.googleapis.com; " .
        "font-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com " .
            "https://fonts.gstatic.com; " .
        "img-src 'self' data:; " .
        "connect-src 'self'; " .
        "frame-ancestors 'none'; " .
        "base-uri 'self'; " .
        "form-action 'self';"
    );
    header_remove('X-Powered-By');
}

/**
 * Harden PHP session configuration.
 * Must be called BEFORE session_start().
 */
function configure_session(): void {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_path', '/');
    ini_set('session.gc_maxlifetime', '7200');
    ini_set('session.cookie_lifetime', '7200');
    ini_set('session.sid_length', '48');
    ini_set('session.sid_bits_per_character', '6');

    if (defined('APP_ENV') && APP_ENV === 'production') {
        ini_set('session.cookie_secure', '1');
    }
}

/**
 * Prevent session fixation.
 * Call after session_start() on first visit.
 */
function prevent_session_fixation(): void {
    if (!isset($_SESSION['_initiated'])) {
        session_regenerate_id(true);
        $_SESSION['_initiated'] = true;
        $_SESSION['_created']   = time();
        $_SESSION['_ip']        = $_SERVER['REMOTE_ADDR'] ?? '';
        $_SESSION['_ua']        = $_SERVER['HTTP_USER_AGENT'] ?? '';
    }
}

/**
 * Validate session integrity on every request.
 * Destroys session if user agent changed.
 */
function validate_session_integrity(): void {
    if (!isset($_SESSION['_initiated'])) {
        return;
    }

    // Regenerate session ID every 30 minutes
    $age = time() - ($_SESSION['_created'] ?? time());
    if ($age > 1800) {
        session_regenerate_id(true);
        $_SESSION['_created'] = time();
    }

    // Destroy on user agent change (likely hijack)
    $ua_changed = isset($_SESSION['_ua']) &&
                  $_SESSION['_ua'] !== ($_SERVER['HTTP_USER_AGENT'] ?? '');
    if ($ua_changed) {
        session_unset();
        session_destroy();
        http_response_code(403);
        exit('Session invalide. Veuillez recharger la page.');
    }
}

/**
 * Abort with a clean error page.
 */
function abort(int $code, string $message = ''): never {
    http_response_code($code);
    $defaults = [
        400 => 'Requête invalide.',
        403 => 'Accès refusé.',
        404 => 'Page introuvable.',
        429 => 'Trop de requêtes. Veuillez patienter.',
        500 => 'Erreur interne du serveur.',
    ];
    $msg = $message ?: ($defaults[$code] ?? 'Erreur.');
    echo '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8">';
    echo '<title>Erreur ' . $code . '</title>';
    echo '<style>body{font-family:sans-serif;display:flex;align-items:center;';
    echo 'justify-content:center;height:100vh;margin:0;background:#f8f7ff;}';
    echo '.box{text-align:center;padding:2rem;}';
    echo 'h1{font-size:4rem;color:#4f46e5;margin:0;}';
    echo 'p{color:#6b7280;margin-top:0.5rem;}';
    echo 'a{color:#4f46e5;text-decoration:none;font-weight:500;}';
    echo '</style></head><body><div class="box">';
    echo '<h1>' . $code . '</h1>';
    echo '<p>' . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . '</p>';
    echo '<a href="javascript:history.back()">← Retour</a>';
    echo '</div></body></html>';
    exit;
}