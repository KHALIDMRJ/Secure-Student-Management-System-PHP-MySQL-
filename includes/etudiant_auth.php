<?php
/**
 * Student authentication module — separate session keys from admin auth so
 * the two roles can NEVER bleed into each other. A logged-in student has
 * `etudiant_id` set; a logged-in admin has `admin_id` set; gates check the
 * specific key for their role.
 *
 * Session keys after a successful login:
 *   etudiant_id                int   — primary key from the etudiants table
 *   etudiant_email             string
 *   etudiant_full_name         string  — "Nom Prénom"
 *   etudiant_filiere           string
 *   etudiant_auth_verified_at  int    — last DB re-verification timestamp
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/helpers.php';

/** Re-verify the student row in DB at most this often (seconds). */
const ETUDIANT_AUTH_REVERIFY_TTL = 300;

/**
 * True if the current session has an authenticated student.
 *
 * @return bool
 */
function is_etudiant_authenticated(): bool {
    return isset($_SESSION['etudiant_id']);
}

/**
 * Return the current student's profile or null if not authenticated.
 *
 * @return array{id:int, email:string, full_name:string, filiere:string}|null
 */
function current_etudiant(): ?array {
    if (!is_etudiant_authenticated()) {
        return null;
    }
    return [
        'id'        => (int)$_SESSION['etudiant_id'],
        'email'     => (string)($_SESSION['etudiant_email']     ?? ''),
        'full_name' => (string)($_SESSION['etudiant_full_name'] ?? ''),
        'filiere'   => (string)($_SESSION['etudiant_filiere']   ?? ''),
    ];
}

/**
 * Page-level gate for student-only pages. Redirects unauthenticated requests
 * to the student login. Re-verifies the row in DB once per
 * ETUDIANT_AUTH_REVERIFY_TTL — also enforces the is_active flag, so a student
 * deactivated by an admin loses access within the TTL window.
 *
 * Must be called AFTER session_start().
 *
 * @return void
 */
function require_etudiant_auth(): void {
    if (!is_etudiant_authenticated()) {
        redirect('?page=etudiant/login');
    }

    $verifiedAt = (int)($_SESSION['etudiant_auth_verified_at'] ?? 0);
    if (time() - $verifiedAt < ETUDIANT_AUTH_REVERIFY_TTL) {
        return; // session cache still fresh
    }

    try {
        $pdo  = getPDO();
        $stmt = $pdo->prepare(
            'SELECT is_active FROM etudiants WHERE id = :id LIMIT 1'
        );
        $stmt->bindValue(':id', (int)$_SESSION['etudiant_id'], PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row || (int)$row['is_active'] !== 1) {
            // Student deleted or deactivated — terminate session.
            logout_etudiant();
        }
        $_SESSION['etudiant_auth_verified_at'] = time();
    } catch (PDOException $e) {
        // Don't lock out on transient DB errors; log and continue.
        error_log('require_etudiant_auth: ' . $e->getMessage());
    }
}

/**
 * Validate a student's credentials and start an authenticated session.
 *
 * Always runs password_verify() — even when the email isn't found or
 * the account is inactive — so timing can't be used to enumerate users.
 *
 * @param string $email    Email from the login form.
 * @param string $password Plain password from the login form.
 * @return bool True on successful authentication.
 */
function login_etudiant(string $email, string $password): bool {
    // Constant-time decoy hash for timing safety.
    $decoy = '$2y$10$BqTkS6oqv1F7ZxLQp5x9wOk6sV8mYtWjN3hRzU2bC7dE9fGhI4jK6';

    try {
        $pdo  = getPDO();
        $stmt = $pdo->prepare(
            'SELECT id, email, nom, prenom, filieres, password_hash, is_active
             FROM etudiants
             WHERE email = :e
             LIMIT 1'
        );
        $stmt->bindValue(':e', $email, PDO::PARAM_STR);
        $stmt->execute();
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('login_etudiant lookup: ' . $e->getMessage());
        return false;
    }

    // Conditions where we MUST fail but still spend bcrypt time:
    //   - user not found
    //   - user has no password set
    //   - user is deactivated
    if (!$student
        || empty($student['password_hash'])
        || (int)$student['is_active'] !== 1
    ) {
        password_verify($password, $decoy);
        return false;
    }

    if (!password_verify($password, (string)$student['password_hash'])) {
        return false;
    }

    // Opportunistic rehash if PHP's default cost moved up since signup.
    if (password_needs_rehash((string)$student['password_hash'], PASSWORD_DEFAULT)) {
        try {
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            $up = $pdo->prepare('UPDATE etudiants SET password_hash = :h WHERE id = :id');
            $up->bindValue(':h',  $newHash,             PDO::PARAM_STR);
            $up->bindValue(':id', (int)$student['id'],  PDO::PARAM_INT);
            $up->execute();
        } catch (PDOException $e) {
            error_log('login_etudiant rehash: ' . $e->getMessage());
        }
    }

    // Session-fixation defence — fresh ID for the authenticated session.
    session_regenerate_id(true);

    $_SESSION['etudiant_id']                = (int)$student['id'];
    $_SESSION['etudiant_email']             = (string)$student['email'];
    $_SESSION['etudiant_full_name']         = trim(
        ((string)$student['nom']) . ' ' . ((string)$student['prenom'])
    );
    $_SESSION['etudiant_filiere']           = (string)$student['filieres'];
    $_SESSION['etudiant_auth_verified_at']  = time();

    try {
        $up = $pdo->prepare('UPDATE etudiants SET last_login = NOW() WHERE id = :id');
        $up->bindValue(':id', (int)$student['id'], PDO::PARAM_INT);
        $up->execute();
    } catch (PDOException $e) {
        error_log('login_etudiant last_login: ' . $e->getMessage());
    }

    if (function_exists('csrf_token_renew')) {
        csrf_token_renew();
    }

    error_log(sprintf(
        'Etudiant login success [%s]: id=%d email=%s',
        $_SERVER['REMOTE_ADDR'] ?? '?',
        (int)$student['id'],
        $student['email']
    ));

    return true;
}

/**
 * Tear down the student session and redirect to the student login.
 *
 * @return never
 */
function logout_etudiant(): void {
    $_SESSION = [];

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
    redirect('?page=etudiant/login');
}
