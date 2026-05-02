<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/rate_limiter.php';

configure_session();
session_start();
prevent_session_fixation();
validate_session_integrity();

$pageTitle  = 'SQL Runner';
$breadcrumb = 'SQL';
$pdo        = getPDO();

/**
 * Validate that a user-submitted SQL string is safe to execute against the
 * etudiants database. Allows ONE single SELECT statement; rejects everything
 * that could mutate state, leak files, or execute multiple statements.
 *
 * The validator is deliberately conservative — false positives are preferred
 * over false negatives. It is NOT a SQL parser; it is a keyword filter.
 *
 * @param string $sql Raw SQL from the textarea.
 * @return string[]   Human-readable error messages (empty array = safe).
 */
function sql_runner_validate(string $sql): array {
    $errors = [];
    $sql    = trim($sql);

    if ($sql === '') {
        return ['Veuillez saisir une requête SQL.'];
    }

    if (mb_strlen($sql) > 5000) {
        $errors[] = 'Requête trop longue (max 5000 caractères).';
    }

    // Must start with SELECT after stripping leading comments/whitespace.
    $stripped = preg_replace('#^(\s*(/\*.*?\*/|--[^\n]*\n?))*#s', '', $sql) ?? $sql;
    if (!preg_match('/^\s*select\b/i', $stripped)) {
        $errors[] = 'Seules les requêtes SELECT sont autorisées.';
    }

    // Block stacked statements — allow only an optional trailing semicolon.
    $coreSql = rtrim($sql, " \t\r\n;");
    if (str_contains($coreSql, ';')) {
        $errors[] = 'Plusieurs instructions (;) ne sont pas autorisées.';
    }

    // Forbidden top-level keywords. \b ensures "insert" doesn't match
    // "Insertion" or similar non-keyword substrings.
    $forbidden = [
        'insert', 'update', 'delete', 'drop', 'truncate', 'alter', 'create',
        'replace', 'grant', 'revoke', 'rename', 'lock', 'unlock', 'load',
        'handler', 'call', 'set', 'into outfile', 'into dumpfile',
    ];
    foreach ($forbidden as $kw) {
        if (preg_match('/\b' . preg_quote($kw, '/') . '\b/i', $sql)) {
            $errors[] = 'Mot-clé interdit : « ' . $kw . ' »';
        }
    }

    return $errors;
}

// Pre-defined example queries — rendered as quick-fill chips above the editor.
// JS reads each chip's data-sql attribute and pastes it into the textarea.
$examples = [
    [
        'label' => 'Tous les étudiants',
        'sql'   => 'SELECT * FROM etudiants ORDER BY nom, prenom',
    ],
    [
        'label' => 'Étudiants par filière',
        'sql'   => "SELECT filieres, COUNT(*) AS total\nFROM etudiants\nGROUP BY filieres\nORDER BY total DESC",
    ],
    [
        'label' => 'Derniers inscrits',
        'sql'   => "SELECT id, nom, prenom, email, filieres, created_at\nFROM etudiants\nORDER BY created_at DESC\nLIMIT 5",
    ],
    [
        'label' => 'Filière SIIA',
        'sql'   => "SELECT * FROM etudiants\nWHERE filieres = 'SIIA'\nORDER BY nom",
    ],
    [
        'label' => 'Statistiques générales',
        'sql'   => "SELECT\n    COUNT(*) AS total_etudiants,\n    COUNT(DISTINCT filieres) AS total_filieres,\n    MIN(created_at) AS premier_inscrit,\n    MAX(created_at) AS dernier_inscrit\nFROM etudiants",
    ],
];

// Default state — populated by POST handler below.
$submittedSql = '';
$errors       = [];
$rows         = null;   // null = no query run yet, [] = ran but empty result
$columns      = [];
$rowCount     = 0;
$execTime     = 0.0;
$dbError      = null;
$truncated    = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Rate limit — defends against runaway click-and-execute loops.
    if (function_exists('rate_limit_exceeded')
        && rate_limit_exceeded('sql_runner', 20, 60)) {
        abort(429, 'Trop de requêtes. Veuillez patienter.');
    }

    // 2. CSRF
    if (!csrf_token_verify()) {
        abort(403, 'Jeton CSRF invalide.');
    }

    $submittedSql = (string)($_POST['sql'] ?? '');
    $errors       = sql_runner_validate($submittedSql);

    // 3. Execute only when validation passes
    if (empty($errors)) {
        try {
            $start    = microtime(true);
            $stmt     = $pdo->query($submittedSql);
            $rows     = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
            $execTime = round((microtime(true) - $start) * 1000, 2);
            $rowCount = count($rows);

            // Cap displayed rows so a runaway SELECT doesn't blow the DOM.
            // Total count is reported separately so the user knows.
            if ($rowCount > 500) {
                $rows      = array_slice($rows, 0, 500);
                $truncated = true;
            }
            $columns = $rows ? array_keys($rows[0]) : [];

            csrf_token_renew();
        } catch (PDOException $e) {
            error_log('SQL Runner: ' . $e->getMessage());
            // Surface the SQL message (column-not-found etc.) but no stack trace.
            $dbError = 'Requête invalide : ' . $e->getMessage();
            $rows    = null;
        }
    }
}

require __DIR__ . '/../includes/header.php';
?>
<!-- ──────────────────────────  PAGE HEADER  ────────────────────────── -->
<div class="page-header">
    <div>
        <h2>SQL Runner</h2>
        <div class="subtitle">
            Exécutez des requêtes <code>SELECT</code> en lecture seule sur la table
            <code>etudiants</code>.
        </div>
    </div>
</div>

<!-- ──────────────────────────  EXAMPLE CHIPS  ────────────────────────── -->
<div class="sql-examples mb-3">
    <span class="sql-examples-label">Exemples</span>
    <?php foreach ($examples as $ex): ?>
        <button type="button"
                class="sql-example-chip"
                data-sql="<?= e($ex['sql']) ?>">
            <i class="bi bi-lightning-charge-fill me-1"></i><?= e($ex['label']) ?>
        </button>
    <?php endforeach; ?>
</div>

<!-- ──────────────────────────  EDITOR  ────────────────────────── -->
<form method="post" id="sqlForm" class="mb-3" autocomplete="off">
    <?= csrf_token_field() ?>
    <div class="card sql-editor-card">

        <!-- File-tab style header -->
        <div class="sql-editor-header">
            <span class="sql-editor-title">
                <i class="bi bi-terminal-fill me-2"></i>
                <span>query.sql</span>
            </span>
            <div class="sql-editor-actions">
                <button type="button" id="copyBtn" class="btn-editor"
                        title="Copier la requête" aria-label="Copier la requête">
                    <i class="bi bi-clipboard"></i>
                </button>
                <button type="button" id="clearBtn" class="btn-editor"
                        title="Effacer l'éditeur" aria-label="Effacer l'éditeur">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
        </div>

        <!-- Gutter + textarea -->
        <div class="sql-editor-body">
            <div class="sql-editor-gutter" id="lineNumbers" aria-hidden="true">1</div>
            <textarea id="sqlEditor"
                      name="sql"
                      class="sql-editor"
                      spellcheck="false"
                      autocomplete="off"
                      autocorrect="off"
                      autocapitalize="off"
                      placeholder="SELECT * FROM etudiants ..."><?= e($submittedSql) ?></textarea>
        </div>

        <!-- Footer hint + execute button -->
        <div class="sql-editor-footer">
            <span class="sql-editor-hint">
                <i class="bi bi-shield-check me-1"></i>
                Lecture seule — uniquement <strong>SELECT</strong>
                <span class="sql-editor-shortcut ms-2">
                    <kbd>Ctrl</kbd>+<kbd>Enter</kbd> pour exécuter
                </span>
            </span>
            <button type="submit" class="btn btn-primary btn-sm">
                <i class="bi bi-play-fill me-1"></i>Exécuter
            </button>
        </div>

    </div>
</form>

<!-- ──────────────────────────  ERROR ALERT  ────────────────────────── -->
<?php if (!empty($errors) || $dbError !== null): ?>
    <div class="alert alert-danger mb-3" role="alert">
        <strong>
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            Impossible d'exécuter la requête
        </strong>
        <ul class="mb-0 mt-2">
            <?php foreach ($errors as $err): ?>
                <li><?= e($err) ?></li>
            <?php endforeach; ?>
            <?php if ($dbError !== null): ?>
                <li><?= e($dbError) ?></li>
            <?php endif; ?>
        </ul>
    </div>
<?php endif; ?>

<!-- ──────────────────────────  RESULTS  ────────────────────────── -->
<?php if ($rows !== null && empty($errors) && $dbError === null): ?>
    <div class="card sql-results">
        <div class="card-header d-flex align-items-center">
            <h5 class="mb-0">
                <i class="bi bi-table me-2 text-primary"></i>Résultat
            </h5>
        </div>

        <?php if (empty($rows)): ?>
            <div class="empty-state">
                <div class="empty-icon"><i class="bi bi-database"></i></div>
                <h5>Aucun résultat</h5>
                <p>La requête s'est exécutée avec succès mais n'a retourné aucune ligne.</p>
            </div>
        <?php else: ?>
            <div class="sql-result-scroll">
                <table class="table table-hover mb-0 sql-result-table" id="sqlResultTable">
                    <thead>
                        <tr>
                            <?php foreach ($columns as $col): ?>
                                <th><?= e((string)$col) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <?php foreach ($columns as $col): ?>
                                    <td>
                                        <?php
                                        $val = $row[$col] ?? null;
                                        if ($val === null) {
                                            echo '<span class="sql-null">NULL</span>';
                                        } else {
                                            echo e((string)$val);
                                        }
                                        ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <div class="card-footer d-flex align-items-center justify-content-between flex-wrap gap-2">
            <span class="text-muted small">
                <strong><?= e((string)$rowCount) ?></strong>
                ligne<?= $rowCount > 1 ? 's' : '' ?>
                retournée<?= $rowCount > 1 ? 's' : '' ?>
                en <strong><?= e((string)$execTime) ?> ms</strong>
                <?php if ($truncated): ?>
                    <span class="text-warning ms-2">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i>
                        affichage limité aux 500 premières
                    </span>
                <?php endif; ?>
            </span>
            <?php if (!empty($rows)): ?>
                <button type="button"
                        class="btn btn-sm btn-outline-secondary"
                        id="exportCsv">
                    <i class="bi bi-download me-1"></i>Exporter CSV
                </button>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
