<?php
/**
 * Security module — HTTP headers, session hardening, abort helper.
 *
 * Loaded by the front controller AND by every page that handles POST.
 * No HTML output here; functions only emit headers or set $_SERVER state.
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

/**
 * Send all security HTTP headers. Call once per request, before any output.
 *
 * Generates a fresh CSP nonce per request and stashes it in
 * $_SERVER['CSP_NONCE'] so views can apply it to inline <script> tags.
 *
 * @return void
 */
function send_security_headers(): void {
    // Clickjacking
    header('X-Frame-Options: DENY');

    // MIME sniffing
    header('X-Content-Type-Options: nosniff');

    // Legacy XSS auditor
    header('X-XSS-Protection: 1; mode=block');

    // Referrer policy
    header('Referrer-Policy: strict-origin-when-cross-origin');

    // Disable unused browser features
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

    // Per-request CSP nonce, exposed to views via $_SERVER
    $nonce = base64_encode(random_bytes(16));
    $_SERVER['CSP_NONCE'] = $nonce;

    // Content Security Policy
    // - script-src: self + nonce + Bootstrap CDN
    // - style-src:  self + 'unsafe-inline' (Bootstrap utility classes inject style attrs) + CDNs + Google Fonts
    // - font-src:   self + Google Fonts + cdnjs
    // - img-src:    self + data: (for inline SVG/icons)
    // - frame-ancestors 'none' is the modern equivalent of X-Frame-Options: DENY
    header("Content-Security-Policy: " .
        "default-src 'self'; " .
        "script-src 'self' 'nonce-{$nonce}' https://cdn.jsdelivr.net; " .
        "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net " .
            "https://cdnjs.cloudflare.com https://fonts.googleapis.com; " .
        "font-src 'self' https://cdnjs.cloudflare.com https://fonts.gstatic.com; " .
        "img-src 'self' data:; " .
        "connect-src 'self'; " .
        "frame-ancestors 'none'; " .
        "base-uri 'self'; " .
        "form-action 'self';"
    );

    // Hide PHP version
    header_remove('X-Powered-By');
}

/**
 * Harden PHP session configuration. MUST be called BEFORE session_start().
 *
 * Idempotent — safe to call multiple times in the same request.
 *
 * @return void
 */
function configure_session(): void {
    // Cookie security flags
    ini_set('session.cookie_httponly', '1');     // no JS access to session cookie
    ini_set('session.cookie_samesite', 'Strict'); // CSRF defence-in-depth
    ini_set('session.use_strict_mode', '1');     // reject uninitialised session IDs
    ini_set('session.use_only_cookies', '1');    // never accept session ID from URL
    ini_set('session.cookie_path', '/');

    // Only mark Secure when serving over HTTPS in production
    if (defined('APP_ENV') && APP_ENV === 'production') {
        ini_set('session.cookie_secure', '1');
    }

    // Session lifetime: 2 hours
    ini_set('session.gc_maxlifetime', '7200');
    ini_set('session.cookie_lifetime', '7200');

    // Stronger session ID (48 chars, 6 bits per char)
    ini_set('session.sid_length', '48');
    ini_set('session.sid_bits_per_character', '6');
}

/**
 * Prevent session fixation by regenerating the ID on first visit.
 * Call AFTER session_start() on every request.
 *
 * @return void
 */
function prevent_session_fixation(): void {
    if (!isset($_SESSION['_initiated'])) {
        session_regenerate_id(true);
        $_SESSION['_initiated'] = true;
        $_SESSION['_created']   = time();
        $_SESSION['_ip']        = $_SERVER['REMOTE_ADDR']     ?? '';
        $_SESSION['_ua']        = $_SERVER['HTTP_USER_AGENT'] ?? '';
    }
}

/**
 * Validate session integrity on every request.
 * - Rotates the session ID every 30 minutes (limits theft window).
 * - Destroys the session when the User-Agent changes (likely hijack).
 * - IP changes are recorded but tolerated (mobile networks rotate IPs).
 *
 * Call AFTER session_start() and AFTER prevent_session_fixation().
 *
 * @return void
 */
function validate_session_integrity(): void {
    if (!isset($_SESSION['_initiated'])) {
        return; // first visit — handled by prevent_session_fixation()
    }

    // Note: $ip_changed is observed but not enforced (NAT / mobile reality)
    $ip_changed = isset($_SESSION['_ip'])
        && $_SESSION['_ip'] !== ($_SERVER['REMOTE_ADDR'] ?? '');
    $ua_changed = isset($_SESSION['_ua'])
        && $_SESSION['_ua'] !== ($_SERVER['HTTP_USER_AGENT'] ?? '');

    // Periodic ID rotation: every 30 minutes
    $age = time() - ($_SESSION['_created'] ?? time());
    if ($age > 1800) {
        session_regenerate_id(true);
        $_SESSION['_created'] = time();
    }

    // UA change is treated as session theft — destroy and exit
    if ($ua_changed) {
        session_unset();
        session_destroy();
        http_response_code(403);
        exit('Session invalide. Veuillez recharger la page.');
    }
}

/**
 * Abort the request with an HTTP error code and a clean error page.
 * Used for 400, 403, 404, 429, 500.
 *
 * @param int    $code    HTTP status code.
 * @param string $message Optional override for the user-facing message.
 * @return never
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
    $msg = $message !== '' ? $message : ($defaults[$code] ?? 'Erreur.');

    echo '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8">';
    echo '<title>' . $code . '</title>';
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
