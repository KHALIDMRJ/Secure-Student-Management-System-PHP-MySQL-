<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/rate_limiter.php';
require_once __DIR__ . '/../includes/auth.php';

configure_session();
session_start();
prevent_session_fixation();
validate_session_integrity();
require_auth();

$pageTitle  = 'Modules';
$breadcrumb = 'Modules';
$pdo        = getPDO();

// ── 1. Inputs ───────────────────────────────────────────────────────────────
$search   = trim((string)($_GET['search']   ?? ''));
$filiere  = trim((string)($_GET['filiere']  ?? ''));
$semestre = trim((string)($_GET['semestre'] ?? ''));
$sort     = (string)($_GET['sort']  ?? 'code');
$order    = (string)($_GET['order'] ?? 'asc');
$p        = max(1, (int)($_GET['p'] ?? 1));

// Whitelist sort + order to safely interpolate into ORDER BY.
$allowedSorts  = ['code', 'nom', 'filiere', 'semestre', 'credits', 'nb_etudiants', 'created_at'];
$allowedOrders = ['asc', 'desc'];
if (!in_array($sort,  $allowedSorts,  true)) $sort  = 'code';
if (!in_array($order, $allowedOrders, true)) $order = 'asc';

// Whitelist semestre filter (1-6 hardcoded).
$allowedSemestres = ['1', '2', '3', '4', '5', '6'];
if ($semestre !== '' && !in_array($semestre, $allowedSemestres, true)) {
    $semestre = '';
}

const PER_PAGE = 10;

// ── 2. Build WHERE clause (parameterised) ───────────────────────────────────
$where  = [];
$params = [];

if ($search !== '') {
    $where[]           = '(m.nom LIKE :search OR m.code LIKE :search)';
    $params[':search'] = '%' . $search . '%';
}
if ($filiere !== '') {
    $where[]            = 'm.filiere = :filiere';
    $params[':filiere'] = $filiere;
}
if ($semestre !== '') {
    $where[]             = 'm.semestre = :semestre';
    $params[':semestre'] = (int)$semestre;
}
$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// ── 3. Count matching rows ─────────────────────────────────────────────────
try {
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM modules m $whereSQL");
    foreach ($params as $k => $v) {
        $countStmt->bindValue($k, $v, $k === ':semestre' ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $countStmt->execute();
    $totalRows = (int)$countStmt->fetchColumn();
} catch (PDOException $e) {
    error_log('modules COUNT: ' . $e->getMessage());
    abort(500);
}

// ── 4. Paginate ─────────────────────────────────────────────────────────────
$pagination = paginate($totalRows, $p, PER_PAGE);

// ── 5. Fetch the page (sort + order whitelisted above) ─────────────────────
try {
    $sql = "SELECT
                m.id, m.code, m.nom, m.filiere, m.semestre, m.credits, m.created_at,
                (SELECT COUNT(*) FROM inscriptions WHERE module_id = m.id) AS nb_etudiants
            FROM modules m
            $whereSQL
            ORDER BY $sort $order
            LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v, $k === ':semestre' ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit',  $pagination['perPage'], PDO::PARAM_INT);
    $stmt->bindValue(':offset', $pagination['offset'],  PDO::PARAM_INT);
    $stmt->execute();
    $modules = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('modules SELECT: ' . $e->getMessage());
    abort(500);
}

// ── 6. Distinct filières for the filter dropdown ───────────────────────────
try {
    $filieresList = $pdo->query(
        "SELECT DISTINCT filiere FROM modules ORDER BY filiere"
    )->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    error_log('modules filieres list: ' . $e->getMessage());
    $filieresList = [];
}

/**
 * Toggle ASC↔DESC for the active column; ASC for inactive columns.
 *
 * @param string $col          Column being clicked.
 * @param string $currentSort  Currently active sort column.
 * @param string $currentOrder Currently active sort order.
 * @return string 'asc' or 'desc'.
 */
function next_order(string $col, string $currentSort, string $currentOrder): string {
    return ($col === $currentSort && $currentOrder === 'asc') ? 'desc' : 'asc';
}

require __DIR__ . '/../includes/header.php';
?>

<!-- Flash messages from the modules CRUD redirects -->
<?php foreach ([
    'ajout'     => 'Module ajouté avec succès.',
    'modifier'  => 'Module modifié avec succès.',
    'supprimer' => 'Module supprimé avec succès.',
] as $key => $msg): ?>
    <?php if (isset($_GET[$key]) && $_GET[$key] === 'ok'): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i><?= e($msg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
<?php endforeach; ?>

<!-- Page header -->
<div class="page-header">
    <div>
        <h2>Modules</h2>
        <div class="subtitle">
            <?php if ($totalRows === 0): ?>
                Aucun module trouvé
            <?php elseif ($totalRows === 1): ?>
                1 module
            <?php else: ?>
                <?= e((string)$totalRows) ?> modules — page <?= e((string)$pagination['currentPage']) ?>/<?= e((string)$pagination['totalPages']) ?>
            <?php endif; ?>
        </div>
    </div>
    <a href="?page=modules_ajouter" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i>Ajouter un module
    </a>
</div>

<!-- Filter bar -->
<div class="card mb-3 filter-bar">
    <div class="card-body py-3">
        <form method="get" action="" id="modulesFilterForm" autocomplete="off">
            <input type="hidden" name="page" value="modules">

            <div class="row g-2 align-items-end">
                <!-- Search -->
                <div class="col-12 col-md-5">
                    <label for="search" class="form-label small fw-semibold mb-1">Rechercher</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text"
                               id="search"
                               name="search"
                               class="form-control"
                               placeholder="Nom ou code du module…"
                               value="<?= e($search) ?>">
                        <?php if ($search !== ''): ?>
                            <a href="<?= e(build_url(['search' => '', 'p' => 1])) ?>"
                               class="btn btn-outline-secondary"
                               title="Effacer">
                                <i class="bi bi-x-lg"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Filière -->
                <div class="col-6 col-md-3">
                    <label for="filiere" class="form-label small fw-semibold mb-1">Filière</label>
                    <select id="filiere" name="filiere" class="form-select">
                        <option value="">Toutes les filières</option>
                        <?php foreach ($filieresList as $f): ?>
                            <option value="<?= e((string)$f) ?>" <?= $filiere === $f ? 'selected' : '' ?>>
                                <?= e((string)$f) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Semestre -->
                <div class="col-6 col-md-2">
                    <label for="semestre" class="form-label small fw-semibold mb-1">Semestre</label>
                    <select id="semestre" name="semestre" class="form-select">
                        <option value="">Tous</option>
                        <?php foreach ($allowedSemestres as $s): ?>
                            <option value="<?= e($s) ?>" <?= $semestre === $s ? 'selected' : '' ?>>S<?= e($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-12 col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-search me-1"></i>Filtrer
                    </button>
                </div>
            </div>

            <!-- Preserve sort state across filter submissions -->
            <input type="hidden" name="sort"  value="<?= e($sort) ?>">
            <input type="hidden" name="order" value="<?= e($order) ?>">
        </form>
    </div>
</div>

<!-- Results table -->
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th style="width:50px">#</th>
                    <th>
                        <a href="<?= e(build_url(['sort'=>'code','order'=>next_order('code',$sort,$order),'p'=>1])) ?>"
                           class="sort-link <?= $sort==='code' ? 'sort-link--active' : '' ?>">
                            Code <?= sort_icon('code', $sort, $order) ?>
                        </a>
                    </th>
                    <th>
                        <a href="<?= e(build_url(['sort'=>'nom','order'=>next_order('nom',$sort,$order),'p'=>1])) ?>"
                           class="sort-link <?= $sort==='nom' ? 'sort-link--active' : '' ?>">
                            Nom <?= sort_icon('nom', $sort, $order) ?>
                        </a>
                    </th>
                    <th>
                        <a href="<?= e(build_url(['sort'=>'filiere','order'=>next_order('filiere',$sort,$order),'p'=>1])) ?>"
                           class="sort-link <?= $sort==='filiere' ? 'sort-link--active' : '' ?>">
                            Filière <?= sort_icon('filiere', $sort, $order) ?>
                        </a>
                    </th>
                    <th class="text-center">
                        <a href="<?= e(build_url(['sort'=>'semestre','order'=>next_order('semestre',$sort,$order),'p'=>1])) ?>"
                           class="sort-link <?= $sort==='semestre' ? 'sort-link--active' : '' ?>">
                            Semestre <?= sort_icon('semestre', $sort, $order) ?>
                        </a>
                    </th>
                    <th class="text-center">
                        <a href="<?= e(build_url(['sort'=>'credits','order'=>next_order('credits',$sort,$order),'p'=>1])) ?>"
                           class="sort-link <?= $sort==='credits' ? 'sort-link--active' : '' ?>">
                            Crédits <?= sort_icon('credits', $sort, $order) ?>
                        </a>
                    </th>
                    <th class="text-center">
                        <a href="<?= e(build_url(['sort'=>'nb_etudiants','order'=>next_order('nb_etudiants',$sort,$order),'p'=>1])) ?>"
                           class="sort-link <?= $sort==='nb_etudiants' ? 'sort-link--active' : '' ?>">
                            Étudiants <?= sort_icon('nb_etudiants', $sort, $order) ?>
                        </a>
                    </th>
                    <th>
                        <a href="<?= e(build_url(['sort'=>'created_at','order'=>next_order('created_at',$sort,$order),'p'=>1])) ?>"
                           class="sort-link <?= $sort==='created_at' ? 'sort-link--active' : '' ?>">
                            Ajouté le <?= sort_icon('created_at', $sort, $order) ?>
                        </a>
                    </th>
                    <th style="width:120px" class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($modules)): ?>
                    <tr>
                        <td colspan="9">
                            <div class="empty-state">
                                <div class="empty-icon"><i class="bi bi-book"></i></div>
                                <h5>Aucun module</h5>
                                <p>
                                    <?php if ($search !== '' || $filiere !== '' || $semestre !== ''): ?>
                                        Aucun module ne correspond à vos filtres.
                                        <a href="?page=modules">Réinitialiser</a>
                                    <?php else: ?>
                                        Aucun module n'a encore été créé.
                                    <?php endif; ?>
                                </p>
                                <?php if ($search === '' && $filiere === '' && $semestre === ''): ?>
                                    <a href="?page=modules_ajouter" class="btn btn-primary btn-sm mt-2">
                                        <i class="bi bi-plus-lg me-1"></i>Créer le premier module
                                    </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php $rowNum = $pagination['offset'] + 1; ?>
                    <?php foreach ($modules as $m): ?>
                        <tr>
                            <td class="text-muted"><?= e((string)$rowNum++) ?></td>
                            <td><code class="module-code"><?= e((string)$m['code']) ?></code></td>
                            <td><strong><?= e((string)$m['nom']) ?></strong></td>
                            <td><span class="badge-filiere"><?= e((string)$m['filiere']) ?></span></td>
                            <td class="text-center">S<?= e((string)$m['semestre']) ?></td>
                            <td class="text-center">
                                <span class="credits-badge"><?= e((string)$m['credits']) ?> ECTS</span>
                            </td>
                            <td class="text-center">
                                <span class="inscriptions-count">
                                    <i class="bi bi-people"></i><?= e((string)$m['nb_etudiants']) ?>
                                </span>
                            </td>
                            <td class="text-muted" style="font-size:.82rem">
                                <?= e(date('d/m/Y', strtotime((string)$m['created_at']))) ?>
                            </td>
                            <td class="text-end">
                                <a href="?page=modules_modifier&id=<?= e((string)$m['id']) ?>"
                                   class="btn btn-sm btn-outline-primary btn-icon me-1"
                                   title="Modifier">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <a href="?page=modules_supprimer&id=<?= e((string)$m['id']) ?>"
                                   class="btn btn-sm btn-outline-danger btn-icon"
                                   title="Supprimer">
                                    <i class="bi bi-trash"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination footer -->
    <?php if ($pagination['totalPages'] > 1): ?>
    <div class="card-footer d-flex align-items-center justify-content-between">
        <div class="pagination-info text-muted small">
            Page <?= e((string)$pagination['currentPage']) ?> sur <?= e((string)$pagination['totalPages']) ?>
        </div>
        <nav aria-label="Pagination">
            <ul class="pagination pagination-sm mb-0">
                <li class="page-item <?= !$pagination['hasPrev'] ? 'disabled' : '' ?>">
                    <a class="page-link"
                       href="<?= e(build_url(['p' => $pagination['currentPage'] - 1])) ?>"
                       aria-label="Précédent">
                        <i class="bi bi-chevron-left"></i>
                    </a>
                </li>
                <?php foreach ($pagination['pages'] as $pn): ?>
                    <?php if ($pn === -1): ?>
                        <li class="page-item disabled">
                            <span class="page-link">…</span>
                        </li>
                    <?php else: ?>
                        <li class="page-item <?= $pn === $pagination['currentPage'] ? 'active' : '' ?>">
                            <a class="page-link" href="<?= e(build_url(['p' => $pn])) ?>">
                                <?= e((string)$pn) ?>
                            </a>
                        </li>
                    <?php endif; ?>
                <?php endforeach; ?>
                <li class="page-item <?= !$pagination['hasNext'] ? 'disabled' : '' ?>">
                    <a class="page-link"
                       href="<?= e(build_url(['p' => $pagination['currentPage'] + 1])) ?>"
                       aria-label="Suivant">
                        <i class="bi bi-chevron-right"></i>
                    </a>
                </li>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
