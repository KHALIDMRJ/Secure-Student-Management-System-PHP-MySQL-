<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/rate_limiter.php';

configure_session();
session_start();
prevent_session_fixation();
validate_session_integrity();

$pageTitle  = 'SQL Console';
$breadcrumb = 'SQL';
$pdo        = getPDO();

/**
 * Detect the high-level type of a SQL statement by inspecting its first
 * meaningful word (after stripping leading comments and whitespace).
 *
 * Recognised: SELECT / INSERT / UPDATE / DELETE / DROP / ALTER / CREATE /
 * TRUNCATE / SHOW / DESCRIBE / EXPLAIN. Anything else is bucketed as OTHER.
 *
 * @param string $sql Raw SQL string.
 * @return string Uppercase type label.
 */
function detect_query_type(string $sql): string {
    // Strip leading line comments, block comments and whitespace.
    $stripped = preg_replace('#^(\s*(/\*.*?\*/|--[^\n]*\n?))*#s', '', $sql) ?? $sql;

    // First whitespace/punct-bounded token, uppercased.
    $first = strtoupper((string)strtok(ltrim($stripped), " \t\n\r;("));

    // DESC is the legacy alias of DESCRIBE.
    if ($first === 'DESC') {
        $first = 'DESCRIBE';
    }

    $known = [
        'SELECT', 'INSERT', 'UPDATE', 'DELETE', 'DROP', 'ALTER',
        'CREATE', 'TRUNCATE', 'SHOW', 'DESCRIBE', 'EXPLAIN',
    ];
    return in_array($first, $known, true) ? $first : 'OTHER';
}

/**
 * Map a query type to its visual badge metadata.
 *
 * @param string $type One of detect_query_type()'s return values.
 * @return array{class: string, danger: bool, group: string}
 *   - class:   modifier suffix used by .badge-query-type--{class}
 *   - danger:  true for DELETE/DROP/TRUNCATE → triggers pulse animation
 *   - group:   read | write | ddl | other (drives result panel layout)
 */
function query_type_meta(string $type): array {
    static $map = [
        'SELECT'   => ['class' => 'select',   'danger' => false, 'group' => 'read'],
        'SHOW'     => ['class' => 'select',   'danger' => false, 'group' => 'read'],
        'DESCRIBE' => ['class' => 'select',   'danger' => false, 'group' => 'read'],
        'EXPLAIN'  => ['class' => 'select',   'danger' => false, 'group' => 'read'],
        'INSERT'   => ['class' => 'insert',   'danger' => false, 'group' => 'write'],
        'CREATE'   => ['class' => 'create',   'danger' => false, 'group' => 'ddl'],
        'UPDATE'   => ['class' => 'update',   'danger' => false, 'group' => 'write'],
        'ALTER'    => ['class' => 'alter',    'danger' => false, 'group' => 'ddl'],
        'DELETE'   => ['class' => 'delete',   'danger' => true,  'group' => 'write'],
        'DROP'     => ['class' => 'drop',     'danger' => true,  'group' => 'ddl'],
        'TRUNCATE' => ['class' => 'truncate', 'danger' => true,  'group' => 'ddl'],
        'OTHER'    => ['class' => 'other',    'danger' => false, 'group' => 'other'],
    ];
    return $map[$type] ?? $map['OTHER'];
}

/**
 * Append a successful query to the per-session history (deduped, max 10).
 *
 * @param string $sql The query just executed.
 * @return void
 */
function sql_history_push(string $sql): void {
    if (!isset($_SESSION['sql_history']) || !is_array($_SESSION['sql_history'])) {
        $_SESSION['sql_history'] = [];
    }
    // Dedupe + move-to-front.
    $hist = array_values(array_filter(
        $_SESSION['sql_history'],
        static fn($q) => is_string($q) && $q !== $sql
    ));
    array_unshift($hist, $sql);
    $_SESSION['sql_history'] = array_slice($hist, 0, 10);
}

// ── Pre-defined example queries ──────────────────────────────────────────────
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
        'label' => 'Tables de la base',
        'sql'   => 'SHOW TABLES',
    ],
    [
        'label' => 'Structure de etudiants',
        'sql'   => 'DESCRIBE etudiants',
    ],
    [
        'label' => 'Statistiques',
        'sql'   => "SELECT\n    COUNT(*) AS total,\n    COUNT(DISTINCT filieres) AS filieres\nFROM etudiants",
    ],
];

// ── Default state ────────────────────────────────────────────────────────────
$submittedSql = '';
$type         = '';
$rows         = null;        // null = no query ran yet, [] = ran but empty
$columns      = [];
$affected     = null;
$lastInsertId = null;
$execTime     = 0.0;
$dbError      = null;
$truncated    = false;

// ── POST handler ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Rate limit — 60 queries / minute / IP.
    if (function_exists('rate_limit_exceeded')
        && rate_limit_exceeded('sql_runner', 60, 60)) {
        abort(429, 'Trop de requêtes. Veuillez patienter.');
    }

    // 2. CSRF.
    if (!csrf_token_verify()) {
        abort(403, 'Jeton CSRF invalide.');
    }

    // 3. Trim + reject empty.
    $submittedSql = trim((string)($_POST['sql'] ?? ''));

    if ($submittedSql === '') {
        $dbError = 'Veuillez saisir une requête SQL.';
    } else {
        $type = detect_query_type($submittedSql);

        // 4. Audit log — every executed query is logged for traceability.
        //    Format: "SQL Runner [{IP}]: {first 200 chars}"
        $ip = $_SERVER['REMOTE_ADDR'] ?? '?';
        error_log(sprintf(
            'SQL Runner [%s]: %s',
            $ip,
            mb_substr($submittedSql, 0, 200)
        ));

        // 5. Execute. PDO::query handles every single-statement type;
        //    we branch on whether to fetch rows or just report rowCount.
        try {
            $start = microtime(true);
            $stmt  = $pdo->query($submittedSql);
            if ($stmt === false) {
                throw new PDOException('La requête n\'a pas pu être exécutée.');
            }

            if (in_array($type, ['SELECT', 'SHOW', 'DESCRIBE', 'EXPLAIN'], true)) {
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                if (count($rows) > 1000) {
                    $rows      = array_slice($rows, 0, 1000);
                    $truncated = true;
                }
                $columns = $rows ? array_keys($rows[0]) : [];
            } else {
                $affected = $stmt->rowCount();
                if ($type === 'INSERT') {
                    $lid = $pdo->lastInsertId();
                    if ($lid !== '' && $lid !== '0') {
                        $lastInsertId = $lid;
                    }
                }
            }
            $execTime = round((microtime(true) - $start) * 1000, 2);

            sql_history_push($submittedSql);
            csrf_token_renew();
        } catch (PDOException $e) {
            error_log('SQL Runner error: ' . $e->getMessage());
            // Show full PDO message in dev, generic in production.
            $dbError = (defined('APP_ENV') && APP_ENV === 'development')
                ? 'Erreur SQL : ' . $e->getMessage()
                : 'La requête a échoué. Vérifiez la syntaxe.';
        }
    }
}

$history  = $_SESSION['sql_history'] ?? [];
$typeMeta = $type !== '' ? query_type_meta($type) : null;

require __DIR__ . '/../includes/header.php';
?>
<!-- ──────────────────────────  PAGE HEADER  ────────────────────────── -->
<div class="page-header">
    <div>
        <h2>SQL Console</h2>
        <div class="subtitle">
            Console SQL — toutes les requêtes sont exécutées et journalisées.
        </div>
    </div>
</div>

<!-- ──────────────────────────  EXAMPLES  ────────────────────────── -->
<div class="sql-examples mb-2">
    <span class="sql-examples-label">Exemples</span>
    <?php foreach ($examples as $ex): ?>
        <button type="button"
                class="sql-example-chip"
                data-sql="<?= e($ex['sql']) ?>">
            <i class="bi bi-lightning-charge-fill me-1"></i><?= e($ex['label']) ?>
        </button>
    <?php endforeach; ?>
</div>

<!-- ──────────────────────────  HISTORY  ────────────────────────── -->
<?php if (!empty($history)): ?>
    <div class="sql-examples sql-history mb-3">
        <span class="sql-examples-label">Historique</span>
        <?php foreach ($history as $h):
            $hType    = detect_query_type($h);
            $hMeta    = query_type_meta($hType);
            $hPreview = mb_substr($h, 0, 50);
            $hClipped = mb_strlen($h) > 50 ? '…' : '';
        ?>
            <button type="button"
                    class="sql-example-chip sql-history-chip"
                    data-sql="<?= e($h) ?>"
                    title="<?= e($h) ?>">
                <span class="badge-query-type-mini badge-query-type--<?= e($hMeta['class']) ?>">
                    <?= e($hType) ?>
                </span>
                <span class="sql-history-text"><?= e($hPreview) ?><?= $hClipped ?></span>
            </button>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- ──────────────────────────  EDITOR  ────────────────────────── -->
<form method="post" id="sqlForm" class="mb-3" autocomplete="off">
    <?= csrf_token_field() ?>
    <div class="card sql-editor-card">

        <!-- Tab-style header -->
        <div class="sql-editor-header">
            <span class="sql-editor-title">
                <i class="bi bi-terminal-fill me-2"></i>
                <span>console.sql</span>
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
                      placeholder="-- Toutes les requêtes SQL sont autorisées
SELECT * FROM etudiants;"><?= e($submittedSql) ?></textarea>
        </div>

        <!-- Footer hint + execute -->
        <div class="sql-editor-footer">
            <span class="sql-editor-hint">
                <i class="bi bi-shield-check me-1"></i>
                <strong>Toutes</strong> les requêtes sont journalisées
                <span class="sql-editor-shortcut ms-2">
                    <kbd>Ctrl</kbd>+<kbd>Enter</kbd>
                </span>
            </span>
            <button type="submit" class="btn btn-primary btn-sm">
                <i class="bi bi-play-fill me-1"></i>Exécuter
            </button>
        </div>

    </div>
</form>

<!-- ──────────────────────────  ERROR  ────────────────────────── -->
<?php if ($dbError !== null): ?>
    <div class="alert alert-danger mb-3" role="alert">
        <strong>
            <i class="bi bi-exclamation-triangle-fill me-2"></i>Échec
        </strong>
        <div class="mt-2"><?= e($dbError) ?></div>
    </div>
<?php endif; ?>

<!-- ──────────────────────────  RESULT  ────────────────────────── -->
<?php if ($dbError === null && $type !== '' && $typeMeta !== null): ?>
    <div class="card sql-results">

        <!-- Header: query-type badge + execution time + (export when SELECT-like) -->
        <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center">
                <span class="badge-query-type badge-query-type--<?= e($typeMeta['class']) ?> <?= $typeMeta['danger'] ? 'danger-pulse' : '' ?> me-2">
                    <?= e($type) ?>
                </span>
                <span class="text-muted small">
                    Exécutée en <strong><?= e((string)$execTime) ?> ms</strong>
                </span>
            </div>
            <?php if ($rows !== null && !empty($rows)): ?>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="exportCsv">
                    <i class="bi bi-download me-1"></i>Exporter CSV
                </button>
            <?php endif; ?>
        </div>

        <?php if ($rows !== null): /* SELECT / SHOW / DESCRIBE / EXPLAIN */ ?>
            <?php if (empty($rows)): ?>
                <div class="empty-state">
                    <div class="empty-icon"><i class="bi bi-database"></i></div>
                    <h5>Aucune ligne</h5>
                    <p>La requête a réussi mais n'a retourné aucun résultat.</p>
                </div>
            <?php else: ?>
                <div class="results-wrapper">
                    <table class="table table-hover mb-0 sql-result-table" id="sqlResultTable">
                        <thead>
                            <tr>
                                <th class="sql-row-num">#</th>
                                <?php foreach ($columns as $col): ?>
                                    <th><?= e((string)$col) ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $rowNum = 1; foreach ($rows as $row): ?>
                                <tr>
                                    <td class="sql-row-num"><?= e((string)$rowNum++) ?></td>
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
                <div class="card-footer d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <span class="text-muted small">
                        <strong><?= e((string)count($rows)) ?></strong>
                        ligne<?= count($rows) > 1 ? 's' : '' ?>
                        retournée<?= count($rows) > 1 ? 's' : '' ?>
                        <?php if ($truncated): ?>
                            <span class="text-warning ms-2">
                                <i class="bi bi-exclamation-triangle-fill me-1"></i>
                                limité aux 1000 premières
                            </span>
                        <?php endif; ?>
                    </span>
                </div>
            <?php endif; ?>

        <?php else: /* INSERT / UPDATE / DELETE / DDL / OTHER */ ?>
            <div class="sql-result-summary">
                <div class="sql-result-icon sql-result-icon--<?= e($typeMeta['class']) ?>">
                    <i class="bi bi-check-circle-fill"></i>
                </div>
                <div>
                    <div class="sql-result-title">
                        <?php
                        $titles = [
                            'INSERT'   => 'Insertion réussie',
                            'UPDATE'   => 'Mise à jour réussie',
                            'DELETE'   => 'Suppression réussie',
                            'CREATE'   => 'Création réussie',
                            'DROP'     => 'Suppression de l\'objet réussie',
                            'ALTER'    => 'Modification réussie',
                            'TRUNCATE' => 'Table vidée',
                            'OTHER'    => 'Requête exécutée',
                        ];
                        echo e($titles[$type] ?? 'Requête exécutée');
                        ?>
                    </div>
                    <div class="sql-result-meta">
                        <?php if (in_array($type, ['INSERT', 'UPDATE', 'DELETE'], true)
                                  && $affected !== null): ?>
                            <strong><?= e((string)$affected) ?></strong>
                            ligne<?= $affected !== 1 ? 's' : '' ?>
                            affectée<?= $affected !== 1 ? 's' : '' ?>
                        <?php endif; ?>
                        <?php if ($lastInsertId !== null): ?>
                            <span class="ms-1">
                                · ID auto-généré : <code><?= e((string)$lastInsertId) ?></code>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

    </div>
<?php endif; ?>

<!-- ──────────────────────  CONFIRMATION MODAL  ────────────────────── -->
<!-- Bootstrap modal — populated and shown by app.js when the user submits a -->
<!-- DELETE / DROP / TRUNCATE / ALTER query. CSP-safe: no inline handlers.   -->
<div class="modal fade" id="sqlConfirmModal" tabindex="-1"
     aria-labelledby="sqlConfirmLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content sql-confirm-modal">
            <div class="modal-header">
                <h5 class="modal-title" id="sqlConfirmLabel">
                    <i class="bi bi-exclamation-octagon-fill text-danger me-2"></i>
                    Confirmation requise
                </h5>
                <button type="button" class="btn-close"
                        data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2">
                    Vous êtes sur le point d'exécuter une requête de type
                    <span class="badge-query-type danger-pulse" id="sqlConfirmBadge">DESTRUCTIVE</span>.
                </p>
                <p class="text-muted small mb-3">
                    Cette opération peut être <strong>irréversible</strong>.
                    Vérifiez la requête ci-dessous avant de continuer.
                </p>
                <pre class="sql-confirm-preview" id="sqlConfirmPreview"></pre>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary"
                        data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-danger" id="sqlConfirmExecuteBtn">
                    <i class="bi bi-play-fill me-1"></i>Exécuter quand même
                </button>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
