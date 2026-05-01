<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

/**
 * Generates a CSRF token, stores it in $_SESSION and returns it.
 *
 * @return string The current CSRF token (hex string).
 */
function csrf_token_generate(): string {
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verifies the POSTed CSRF token against the one stored in session.
 *
 * @return bool True if tokens match (timing-safe), false otherwise.
 */
function csrf_token_verify(): bool {
    $posted = $_POST['csrf_token'] ?? '';
    $stored = $_SESSION['csrf_token'] ?? '';
    if (!is_string($posted) || $posted === '' || !is_string($stored) || $stored === '') {
        return false;
    }
    return hash_equals($stored, $posted);
}

/**
 * Returns ready-to-echo HTML for a hidden CSRF input field.
 *
 * @return string HTML <input> element.
 */
function csrf_token_field(): string {
    $token = csrf_token_generate();
    return '<input type="hidden" name="csrf_token" value="' . e($token) . '">';
}

/**
 * Regenerates the CSRF token. Call after every successful write operation.
 *
 * @return void
 */
function csrf_token_renew(): void {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * HTML-escape helper (UTF-8, double-encoded quotes).
 *
 * @param string|null $v Raw value to escape.
 * @return string Escaped HTML-safe value.
 */
function e(?string $v): string {
    return htmlspecialchars($v ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Sends a Location header and terminates the script.
 *
 * @param string $url Target URL (relative or absolute).
 * @return never
 */
function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

/**
 * Returns 'active' when the given route matches the current ?page query, '' otherwise.
 * Defaults to 'index' when no ?page is present.
 *
 * @param string $page Page identifier to test (e.g. 'index', 'ajouter').
 * @return string Either 'active' or ''.
 */
function active_page(string $page): string {
    $current = $_GET['page'] ?? 'index';
    return ($current === $page) ? 'active' : '';
}

/**
 * Validates a student data array (typically $_POST).
 * Returns trimmed clean values plus any user-facing errors.
 *
 * @param array $data Raw input array.
 * @return array{errors: string[], clean: array{nom: string, prenom: string, email: string, filieres: string}}
 */
function validate_student(array $data): array {
    $errors = [];

    // Trim and coerce to strings
    $clean = [
        'nom'      => trim((string)($data['nom']      ?? '')),
        'prenom'   => trim((string)($data['prenom']   ?? '')),
        'email'    => trim((string)($data['email']    ?? '')),
        'filieres' => trim((string)($data['filieres'] ?? '')),
    ];

    // Nom
    if ($clean['nom'] === '' || mb_strlen($clean['nom']) > 100) {
        $errors[] = "Nom invalide (1 à 100 caractères).";
    } elseif (!preg_match("/^[\p{L}\s'-]+$/u", $clean['nom'])) {
        $errors[] = "Le nom contient des caractères non autorisés.";
    }

    // Prénom
    if ($clean['prenom'] === '' || mb_strlen($clean['prenom']) > 100) {
        $errors[] = "Prénom invalide (1 à 100 caractères).";
    } elseif (!preg_match("/^[\p{L}\s'-]+$/u", $clean['prenom'])) {
        $errors[] = "Le prénom contient des caractères non autorisés.";
    }

    // Email
    if ($clean['email'] === '' || mb_strlen($clean['email']) > 150
        || !filter_var($clean['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Adresse email invalide.";
    }

    // Filière
    if ($clean['filieres'] === '' || mb_strlen($clean['filieres']) > 100) {
        $errors[] = "Filière invalide (1 à 100 caractères).";
    }

    return ['errors' => $errors, 'clean' => $clean];
}

/**
 * Returns true if the given email already exists in the etudiants table.
 * On edit, pass the current student ID via $excludeId so it isn't matched.
 *
 * @param PDO      $pdo       PDO instance.
 * @param string   $email     Email to check.
 * @param int|null $excludeId Optional ID to exclude from the lookup.
 * @return bool
 */
function is_email_taken(PDO $pdo, string $email, ?int $excludeId = null): bool {
    try {
        if ($excludeId !== null) {
            $stmt = $pdo->prepare('SELECT 1 FROM etudiants WHERE email = :email AND id <> :id LIMIT 1');
            $stmt->bindValue(':email', $email,      PDO::PARAM_STR);
            $stmt->bindValue(':id',    $excludeId,  PDO::PARAM_INT);
        } else {
            $stmt = $pdo->prepare('SELECT 1 FROM etudiants WHERE email = :email LIMIT 1');
            $stmt->bindValue(':email', $email, PDO::PARAM_STR);
        }
        $stmt->execute();
        return (bool)$stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log('is_email_taken : ' . $e->getMessage());
        // Fail-closed: surface a duplicate-error rather than silently allow.
        return true;
    }
}

/**
 * Build a URL preserving current query params, overriding specified ones.
 * Used to build sort/page/filter links without losing other params.
 *
 * @param array $overrides Key-value pairs to override in current query.
 * @return string          Full URL string safe for use in href.
 */
function build_url(array $overrides = []): string {
    $params = array_merge($_GET, $overrides);

    // The router page name (?page=...) is always preserved separately so it
    // can never be polluted by sort/filter overrides.
    $routePage = $params['page'] ?? 'index';
    unset($params['page']);

    // Strip empty / null values so the URL stays tidy.
    $params = array_filter($params, static fn($v): bool => $v !== '' && $v !== null);

    $query = http_build_query($params);
    return '?page=' . urlencode((string)$routePage) . ($query ? '&' . $query : '');
}

/**
 * Render a Bootstrap-Icon sort indicator for a sortable table header.
 *
 * @param string $column       Column name this header sorts by.
 * @param string $currentSort  Currently active sort column.
 * @param string $currentOrder Currently active sort order ('asc'|'desc').
 * @return string              HTML <i> element.
 */
function sort_icon(string $column, string $currentSort, string $currentOrder): string {
    if ($currentSort !== $column) {
        return '<i class="bi bi-arrow-down-up sort-icon sort-icon--inactive"></i>';
    }
    $icon = $currentOrder === 'asc' ? 'bi-sort-up' : 'bi-sort-down';
    return '<i class="bi ' . $icon . ' sort-icon sort-icon--active"></i>';
}

/**
 * Build a pagination descriptor for a result set.
 *
 * Smart page list: always show first, last, and current ±2; gaps are
 * collapsed to a single -1 sentinel which the view renders as an ellipsis.
 *
 * @param int $totalRows   Total matching rows.
 * @param int $currentPage Requested page number (1-based; clamped here).
 * @param int $perPage     Rows per page.
 * @return array{
 *     totalPages: int,
 *     currentPage: int,
 *     perPage: int,
 *     offset: int,
 *     totalRows: int,
 *     hasPrev: bool,
 *     hasNext: bool,
 *     pages: int[]
 * }
 */
function paginate(int $totalRows, int $currentPage, int $perPage = 10): array {
    $totalPages  = max(1, (int)ceil($totalRows / max(1, $perPage)));
    $currentPage = max(1, min($currentPage, $totalPages));
    $offset      = ($currentPage - 1) * $perPage;

    $pages = [];
    for ($i = 1; $i <= $totalPages; $i++) {
        if ($i === 1 || $i === $totalPages
            || ($i >= $currentPage - 2 && $i <= $currentPage + 2)) {
            $pages[] = $i;
        } elseif (end($pages) !== -1) {
            $pages[] = -1; // ellipsis sentinel
        }
    }

    return [
        'totalPages'  => $totalPages,
        'currentPage' => $currentPage,
        'perPage'     => $perPage,
        'offset'      => $offset,
        'totalRows'   => $totalRows,
        'hasPrev'     => $currentPage > 1,
        'hasNext'     => $currentPage < $totalPages,
        'pages'       => $pages,
    ];
}


/**
 * Render an invisible honeypot field to trap bots.
 */
function honeypot_field(): string {
    return '<div class="d-none" aria-hidden="true">' .
           '<label for="' . HONEYPOT_FIELD . '">Ne pas remplir</label>' .
           '<input type="text" id="' . HONEYPOT_FIELD . '" ' .
           'name="' . HONEYPOT_FIELD . '" ' .
           'tabindex="-1" autocomplete="off">' .
           '</div>';
}

/**
 * Returns true if the honeypot field was filled (bot detected).
 */
function honeypot_triggered(): bool {
    return !empty($_POST[HONEYPOT_FIELD] ?? '');
}