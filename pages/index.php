<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/rate_limiter.php';

configure_session();
session_start();
prevent_session_fixation();
validate_session_integrity();

$pageTitle  = 'Liste des étudiants';
$breadcrumb = null;
$pdo        = getPDO();

// ── 1. Input sanitisation ───────────────────────────────────────────────────
$search  = trim((string)($_GET['search']  ?? ''));
$filiere = trim((string)($_GET['filiere'] ?? ''));
$sort    = (string)($_GET['sort']  ?? 'nom');
$order   = (string)($_GET['order'] ?? 'asc');
$p       = max(1, (int)($_GET['p'] ?? 1));

// Whitelist sort column and order — required to safely interpolate them
// into the ORDER BY clause below.
$allowedSorts  = ['nom', 'prenom', 'email', 'filieres', 'created_at'];
$allowedOrders = ['asc', 'desc'];
if (!in_array($sort,  $allowedSorts,  true)) $sort  = 'nom';
if (!in_array($order, $allowedOrders, true)) $order = 'asc';

const PER_PAGE = 10;

// ── 2. Build WHERE clause (parameterised) ───────────────────────────────────
$where  = [];
$params = [];

if ($search !== '') {
    $where[]           = '(nom LIKE :search
                          OR prenom LIKE :search
                          OR email LIKE :search
                          OR filieres LIKE :search)';
    $params[':search'] = '%' . $search . '%';
}

if ($filiere !== '') {
    $where[]            = 'filieres = :filiere';
    $params[':filiere'] = $filiere;
}

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// ── 3. Count total matching rows ────────────────────────────────────────────
try {
    $countSQL  = "SELECT COUNT(*) FROM etudiants $whereSQL";
    $countStmt = $pdo->prepare($countSQL);
    foreach ($params as $k => $v) {
        $countStmt->bindValue($k, $v, PDO::PARAM_STR);
    }
    $countStmt->execute();
    $totalRows = (int)$countStmt->fetchColumn();
} catch (PDOException $e) {
    error_log('COUNT etudiants : ' . $e->getMessage());
    abort(500);
}

// ── 4. Paginate ─────────────────────────────────────────────────────────────
$pagination = paginate($totalRows, $p, PER_PAGE);

// ── 5. Fetch the requested page of results ──────────────────────────────────
// $sort and $order are whitelisted above — safe to interpolate.
try {
    $sql = "SELECT id, nom, prenom, email, filieres, created_at
            FROM etudiants
            $whereSQL
            ORDER BY $sort $order
            LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v, PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit',  $pagination['perPage'], PDO::PARAM_INT);
    $stmt->bindValue(':offset', $pagination['offset'],  PDO::PARAM_INT);
    $stmt->execute();
    $etudiants = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('SELECT etudiants : ' . $e->getMessage());
    abort(500);
}

// ── 6. Distinct filières for the filter dropdown ───────────────────────────
try {
    $filieresList = $pdo->query(
        "SELECT DISTINCT filieres FROM etudiants ORDER BY filieres"
    )->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $filieresList = [];
}

// ── 7. Helper: toggle ASC↔DESC for the active column, ASC for inactive ─────
function next_order(string $col, string $currentSort, string $currentOrder): string {
    return ($col === $currentSort && $currentOrder === 'asc') ? 'desc' : 'asc';
}

require __DIR__ . '/../includes/header.php';
?>
<!-- Flash messages -->
<?php foreach ([
    'ajout'     => 'Étudiant ajouté avec succès.',
    'modifier'  => 'Étudiant modifié avec succès.',
    'supprimer' => 'Étudiant supprimé avec succès.',
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
        <h2>Étudiants</h2>
        <div class="subtitle">
            <?php if ($totalRows === 0): ?>
                Aucun résultat
            <?php elseif ($totalRows === 1): ?>
                1 étudiant trouvé
            <?php else: ?>
                <?= $totalRows ?> étudiants — <?= $pagination['offset'] + 1 ?>–<?= min($pagination['offset'] + PER_PAGE, $totalRows) ?>
            <?php endif; ?>
        </div>
    </div>
    <a href="?page=ajouter" class="btn btn-primary">
        <i class="bi bi-person-plus-fill me-1"></i>Ajouter un étudiant
    </a>
</div>

<!-- Search + Filter bar -->
<div class="card mb-3 filter-bar">
    <div class="card-body py-3">
        <form method="get" action="" id="filterForm" autocomplete="off">
            <input type="hidden" name="page" value="index">

            <div class="row g-2 align-items-end">
                <!-- Search input -->
                <div class="col-12 col-md-5">
                    <label for="search" class="form-label small fw-semibold mb-1">
                        Rechercher
                    </label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="bi bi-search"></i>
                        </span>
                        <input type="text"
                               id="search"
                               name="search"
                               class="form-control"
                               placeholder="Nom, prénom, email, filière…"
                               value="<?= e($search) ?>"
                               autocomplete="off">
                        <?php if ($search !== ''): ?>
                            <a href="<?= e(build_url(['search' => '', 'p' => 1])) ?>"
                               class="btn btn-outline-secondary"
                               title="Effacer la recherche">
                                <i class="bi bi-x-lg"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Filière filter -->
                <div class="col-12 col-md-3">
                    <label for="filiere" class="form-label small fw-semibold mb-1">
                        Filière
                    </label>
                    <select id="filiere" name="filiere" class="form-select"
                            onchange="document.getElementById('filterForm').submit()">
                        <option value="">Toutes les filières</option>
                        <?php foreach ($filieresList as $f): ?>
                            <option value="<?= e($f) ?>"
                                <?= ($filiere === $f ? 'selected' : '') ?>>
                                <?= e($f) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Per-page selector (UI only for now — PER_PAGE is fixed at 10) -->
                <div class="col-6 col-md-2">
                    <label for="perpage" class="form-label small fw-semibold mb-1">
                        Par page
                    </label>
                    <select id="perpage" name="perpage" class="form-select"
                            onchange="document.getElementById('filterForm').submit()">
                        <?php foreach ([5, 10, 25, 50] as $n): ?>
                            <option value="<?= $n ?>"
                                <?= (PER_PAGE === $n ? 'selected' : '') ?>>
                                <?= $n ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Submit -->
                <div class="col-6 col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-search me-1"></i>Rechercher
                    </button>
                </div>
            </div>

            <!-- Preserve sort state across filter submissions -->
            <input type="hidden" name="sort"  value="<?= e($sort) ?>">
            <input type="hidden" name="order" value="<?= e($order) ?>">
        </form>
    </div>
</div>

<!-- Student table card -->
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0" id="studentTable">
            <thead>
                <tr>
                    <th style="width:50px">#</th>
                    <th>
                        <a href="<?= e(build_url(['sort' => 'nom', 'order' => next_order('nom', $sort, $order), 'p' => 1])) ?>"
                           class="sort-link <?= $sort === 'nom' ? 'sort-link--active' : '' ?>">
                            Étudiant
                            <?= sort_icon('nom', $sort, $order) ?>
                        </a>
                    </th>
                    <th>
                        <a href="<?= e(build_url(['sort' => 'email', 'order' => next_order('email', $sort, $order), 'p' => 1])) ?>"
                           class="sort-link <?= $sort === 'email' ? 'sort-link--active' : '' ?>">
                            Email
                            <?= sort_icon('email', $sort, $order) ?>
                        </a>
                    </th>
                    <th>
                        <a href="<?= e(build_url(['sort' => 'filieres', 'order' => next_order('filieres', $sort, $order), 'p' => 1])) ?>"
                           class="sort-link <?= $sort === 'filieres' ? 'sort-link--active' : '' ?>">
                            Filière
                            <?= sort_icon('filieres', $sort, $order) ?>
                        </a>
                    </th>
                    <th>
                        <a href="<?= e(build_url(['sort' => 'created_at', 'order' => next_order('created_at', $sort, $order), 'p' => 1])) ?>"
                           class="sort-link <?= $sort === 'created_at' ? 'sort-link--active' : '' ?>">
                            Ajouté le
                            <?= sort_icon('created_at', $sort, $order) ?>
                        </a>
                    </th>
                    <th style="width:100px">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($etudiants)): ?>
                    <tr>
                        <td colspan="6">
                            <div class="empty-state">
                                <div class="empty-icon"><i class="bi bi-search"></i></div>
                                <h5>Aucun résultat</h5>
                                <p>
                                    <?php if ($search !== '' || $filiere !== ''): ?>
                                        Aucun étudiant ne correspond à votre recherche.
                                        <a href="?page=index">Réinitialiser les filtres</a>
                                    <?php else: ?>
                                        Aucun étudiant enregistré.
                                    <?php endif; ?>
                                </p>
                                <?php if ($search === '' && $filiere === ''): ?>
                                    <a href="?page=ajouter" class="btn btn-primary btn-sm mt-2">
                                        <i class="bi bi-plus me-1"></i>Ajouter le premier étudiant
                                    </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php
                    $rowNum = $pagination['offset'] + 1;
                    foreach ($etudiants as $row):
                        $initials = mb_strtoupper(
                            mb_substr((string)$row['nom'],    0, 1) .
                            mb_substr((string)$row['prenom'], 0, 1)
                        );
                    ?>
                    <tr>
                        <td class="text-muted"><?= $rowNum++ ?></td>
                        <td>
                            <span class="student-avatar"><?= e($initials) ?></span>
                            <strong><?= e($row['nom']) ?></strong>
                            <?= e($row['prenom']) ?>
                        </td>
                        <td class="text-muted"><?= e($row['email']) ?></td>
                        <td>
                            <a href="<?= e(build_url(['filiere' => $row['filieres'], 'p' => 1, 'search' => ''])) ?>"
                               class="badge-filiere text-decoration-none">
                                <?= e($row['filieres']) ?>
                            </a>
                        </td>
                        <td class="text-muted" style="font-size:.82rem">
                            <?= e(date('d/m/Y', strtotime((string)$row['created_at']))) ?>
                        </td>
                        <td>
                            <a href="?page=modifier&id=<?= e((string)$row['id']) ?>"
                               class="btn btn-sm btn-outline-primary btn-icon me-1"
                               title="Modifier <?= e($row['nom']) ?>">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <a href="?page=supprimer&id=<?= e((string)$row['id']) ?>"
                               class="btn btn-sm btn-outline-danger btn-icon"
                               title="Supprimer <?= e($row['nom']) ?>">
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
            Page <?= $pagination['currentPage'] ?> sur <?= $pagination['totalPages'] ?>
        </div>
        <nav aria-label="Pagination">
            <ul class="pagination pagination-sm mb-0">
                <!-- Previous -->
                <li class="page-item <?= !$pagination['hasPrev'] ? 'disabled' : '' ?>">
                    <a class="page-link"
                       href="<?= e(build_url(['p' => $pagination['currentPage'] - 1])) ?>"
                       aria-label="Page précédente">
                        <i class="bi bi-chevron-left"></i>
                    </a>
                </li>

                <!-- Page numbers -->
                <?php foreach ($pagination['pages'] as $pageNum): ?>
                    <?php if ($pageNum === -1): ?>
                        <li class="page-item disabled">
                            <span class="page-link">…</span>
                        </li>
                    <?php else: ?>
                        <li class="page-item <?= $pageNum === $pagination['currentPage'] ? 'active' : '' ?>">
                            <a class="page-link"
                               href="<?= e(build_url(['p' => $pageNum])) ?>">
                                <?= $pageNum ?>
                            </a>
                        </li>
                    <?php endif; ?>
                <?php endforeach; ?>

                <!-- Next -->
                <li class="page-item <?= !$pagination['hasNext'] ? 'disabled' : '' ?>">
                    <a class="page-link"
                       href="<?= e(build_url(['p' => $pagination['currentPage'] + 1])) ?>"
                       aria-label="Page suivante">
                        <i class="bi bi-chevron-right"></i>
                    </a>
                </li>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
