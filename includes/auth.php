<?php
/**
 * Authentication module — admin login, logout, and per-page gate.
 *
 * Session keys set after a successful login:
 *   admin_id                int   — primary key from the admins table
 *   admin_username          string
 *   admin_full_name         string
 *   admin_last_login        ?string — DATETIME of the previous login
 *   admin_auth_verified_at  int    — unix ts of the last DB re-verification
 *
 * The auth gate (require_auth) re-verifies against the database every
 * AUTH_REVERIFY_TTL seconds so that revoked accounts stop working within
 * that window without paying a DB hit on every request.
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/helpers.php';

/** Re-verify the admin row in DB at most this often (seconds). */
const AUTH_REVERIFY_TTL = 300;

/**
 * True if the current session has an authenticated admin.
 *
 * @return bool
 */
function is_authenticated(): bool {
    return isset($_SESSION['admin_id']);
}

/**
 * Return the current admin profile or null if not authenticated.
 *
 * @return array{id:int, username:string, full_name:string, last_login:?string}|null
 */
function current_admin(): ?array {
    if (!is_authenticated()) {
        return null;
    }
    return [
        'id'         => (int)$_SESSION['admin_id'],
        'username'   => (string)($_SESSION['admin_username']  ?? ''),
        'full_name'  => (string)($_SESSION['admin_full_name'] ?? ''),
        'last_login' => $_SESSION['admin_last_login'] ?? null,
    ];
}

/**
 * Page-level auth gate. Redirects unauthenticated requests to ?page=login.
 * Re-verifies the admin row in the database once per AUTH_REVERIFY_TTL,
 * so deleted/disabled accounts are kicked out within the TTL window.
 *
 * Must be called AFTER session_start().
 *
 * @return void
 */
function require_auth(): void {
    if (!is_authenticated()) {
        redirect('?page=login');
    }

    $verifiedAt = (int)($_SESSION['admin_auth_verified_at'] ?? 0);
    if (time() - $verifiedAt < AUTH_REVERIFY_TTL) {
        return; // session cache still fresh
    }

    try {
        $pdo  = getPDO();
        $stmt = $pdo->prepare('SELECT id FROM admins WHERE id = :id LIMIT 1');
        $stmt->bindValue(':id', (int)$_SESSION['admin_id'], PDO::PARAM_INT);
        $stmt->execute();
        if (!$stmt->fetchColumn()) {
            // Admin no longer exists — terminate session.
            logout_admin();
        }
        $_SESSION['admin_auth_verified_at'] = time();
    } catch (PDOException $e) {
        // Don't lock the user out on a transient DB error; log and continue.
        error_log('require_auth verify: ' . $e->getMessage());
    }
}

/**
 * Validate credentials and start an authenticated session on success.
 *
 * On success: rotates the session ID (fixation defence), populates
 * the admin_* session keys, refreshes the CSRF token, and stamps
 * last_login = NOW() in the admins table.
 *
 * On failure: returns false. A constant-time fake hash check is
 * performed even for unknown usernames so timing cannot reveal
 * whether a username exists.
 *
 * @param string $username Plain username from the form.
 * @param string $password Plain password from the form.
 * @return bool True on successful authentication.
 */
function login_admin(string $username, string $password): bool {
    // Pre-computed valid bcrypt hash used as a timing decoy.
    // (Hash of an unguessable random string — never matches anything real.)
    $decoy = '$2y$10$BqTkS6oqv1F7ZxLQp5x9wOk6sV8mYtWjN3hRzU2bC7dE9fGhI4jK6';

    try {
        $pdo  = getPDO();
        $stmt = $pdo->prepare(
            'SELECT id, username, password_hash, full_name, last_login
             FROM admins
             WHERE username = :u
             LIMIT 1'
        );
        $stmt->bindValue(':u', $username, PDO::PARAM_STR);
        $stmt->execute();
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('login_admin lookup: ' . $e->getMessage());
        return false;
    }

    if (!$admin) {
        // Burn comparable time so user-enumeration via timing is infeasible.
        password_verify($password, $decoy);
        return false;
    }

    if (!password_verify($password, (string)$admin['password_hash'])) {
        return false;
    }

    // Opportunistic rehash if PHP's default cost has changed.
    if (password_needs_rehash((string)$admin['password_hash'], PASSWORD_DEFAULT)) {
        try {
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            $up = $pdo->prepare('UPDATE admins SET password_hash = :h WHERE id = :id');
            $up->bindValue(':h',  $newHash,           PDO::PARAM_STR);
            $up->bindValue(':id', (int)$admin['id'],  PDO::PARAM_INT);
            $up->execute();
        } catch (PDOException $e) {
            error_log('login_admin rehash: ' . $e->getMessage());
        }
    }

    // Session-fixation defence — the authenticated session gets a fresh ID.
    session_regenerate_id(true);

    $_SESSION['admin_id']               = (int)$admin['id'];
    $_SESSION['admin_username']         = (string)$admin['username'];
    $_SESSION['admin_full_name']        = (string)$admin['full_name'];
    $_SESSION['admin_last_login']       = $admin['last_login'] ?? null;
    $_SESSION['admin_auth_verified_at'] = time();

    // Stamp last_login = NOW() so the next session knows when this one began.
    try {
        $up = $pdo->prepare('UPDATE admins SET last_login = NOW() WHERE id = :id');
        $up->bindValue(':id', (int)$admin['id'], PDO::PARAM_INT);
        $up->execute();
    } catch (PDOException $e) {
        error_log('login_admin last_login: ' . $e->getMessage());
    }

    // Rotate CSRF token for the freshly authenticated session.
    if (function_exists('csrf_token_renew')) {
        csrf_token_renew();
    }

    error_log(sprintf(
        'Login success [%s]: user=%s id=%d',
        $_SERVER['REMOTE_ADDR'] ?? '?',
        $admin['username'],
        (int)$admin['id']
    ));

    return true;
}

/**
 * Tear down the authenticated session and redirect to ?page=login.
 *
 * Clears $_SESSION, expires the session cookie, destroys the session,
 * then redirects. Never returns.
 *
 * @return never
 */
function logout_admin(): void {
    $_SESSION = [];

    // Expire the session cookie on the client.
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $params['path']     ?? '/',
            'domain'   => $params['domain']   ?? '',
            'secure'   => $params['secure']   ?? false,
            'httponly' => $params['httponly'] ?? true,
            'samesite' => $params['samesite'] ?? 'Lax',
        ]);
    }

    session_destroy();
    redirect('?page=login');
}
