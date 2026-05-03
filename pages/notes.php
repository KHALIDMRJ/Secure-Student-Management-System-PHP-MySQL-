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

$pageTitle  = 'Notes — étudiants';
$breadcrumb = 'Notes';
$pdo        = getPDO();

// ── 1. Inputs ───────────────────────────────────────────────────────────────
$search  = trim((string)($_GET['search']  ?? ''));
$filiere = trim((string)($_GET['filiere'] ?? ''));
$sort    = (string)($_GET['sort']  ?? 'nom');
$order   = (string)($_GET['order'] ?? 'asc');
$p       = max(1, (int)($_GET['p'] ?? 1));

// Whitelist sort + order to safely interpolate.
$allowedSorts  = ['nom', 'prenom', 'email', 'filieres', 'nb_total', 'nb_notes', 'nb_valides', 'nb_echoues'];
$allowedOrders = ['asc', 'desc'];
if (!in_array($sort,  $allowedSorts,  true)) $sort  = 'nom';
if (!in_array($order, $allowedOrders, true)) $order = 'asc';

const PER_PAGE = 10;

// ── 2. WHERE clause ────────────────────────────────────────────────────────
$where  = ['e.is_active = 1'];
$params = [];

if ($search !== '') {
    $where[]           = '(e.nom LIKE :search OR e.prenom LIKE :search OR e.email LIKE :search)';
    $params[':search'] = '%' . $search . '%';
}
if ($filiere !== '') {
    $where[]            = 'e.filieres = :filiere';
    $params[':filiere'] = $filiere;
}
$whereSQL = 'WHERE ' . implode(' AND ', $where);

// ── 3. Count ───────────────────────────────────────────────────────────────
try {
    $cnt = $pdo->prepare("SELECT COUNT(*) FROM etudiants e $whereSQL");
    foreach ($params as $k => $v) $cnt->bindValue($k, $v, PDO::PARAM_STR);
    $cnt->execute();
    $totalRows = (int)$cnt->fetchColumn();
} catch (PDOException $e) {
    error_log('notes COUNT: ' . $e->getMessage());
    abort(500);
}

$pagination = paginate($totalRows, $p, PER_PAGE);

// ── 4. Page query ──────────────────────────────────────────────────────────
try {
    $sql = "SELECT
                e.id, e.nom, e.prenom, e.email, e.filieres,
                COUNT(i.id)                          AS nb_total,
                SUM(i.note IS NOT NULL)              AS nb_notes,
                SUM(i.statut = 'valide')             AS nb_valides,
                SUM(i.statut = 'echoue')             AS nb_echoues
            FROM etudiants e
            LEFT JOIN inscriptions i ON i.etudiant_id = e.id
            $whereSQL
            GROUP BY e.id
            ORDER BY $sort $order
            LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) $stmt->bindValue($k, $v, PDO::PARAM_STR);
    $stmt->bindValue(':limit',  $pagination['perPage'], PDO::PARAM_INT);
    $stmt->bindValue(':offset', $pagination['offset'],  PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('notes SELECT: ' . $e->getMessage());
    abort(500);
}

// Filière dropdown source
try {
    $filieresList = $pdo->query(
        "SELECT DISTINCT filieres FROM etudiants WHERE is_active = 1 ORDER BY filieres"
    )->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $filieresList = [];
}

/**
 * Toggle ASC↔DESC for the active column.
 */
function next_order(string $col, string $currentSort, string $currentOrder): string {
    return ($col === $currentSort && $currentOrder === 'asc') ? 'desc' : 'asc';
}

require __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <div>
        <h2>Notes des étudiants</h2>
        <div class="subtitle">
            Sélectionnez un étudiant pour saisir ou ajuster ses notes.
        </div>
    </div>
</div>

<!-- Filter bar -->
<div class="card mb-3 filter-bar">
    <div class="card-body py-3">
        <form method="get" action="" autocomplete="off">
            <input type="hidden" name="page" value="notes">

            <div class="row g-2 align-items-end">
                <div class="col-12 col-md-7">
                    <label for="search" class="form-label small fw-semibold mb-1">Rechercher</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" id="search" name="search" class="form-control"
                               placeholder="Nom, prénom ou email…"
                               value="<?= e($search) ?>">
                        <?php if ($search !== ''): ?>
                            <a href="<?= e(build_url(['search'=>'','p'=>1])) ?>"
                               class="btn btn-outline-secondary" title="Effacer">
                                <i class="bi bi-x-lg"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-8 col-md-3">
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
                <div class="col-4 col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-search me-1"></i>Filtrer
                    </button>
                </div>
            </div>
            <input type="hidden" name="sort"  value="<?= e($sort) ?>">
            <input type="hidden" name="order" value="<?= e($order) ?>">
        </form>
    </div>
</div>

<!-- Students list -->
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th style="width:50px">#</th>
                    <th>
                        <a href="<?= e(build_url(['sort'=>'nom','order'=>next_order('nom',$sort,$order),'p'=>1])) ?>"
                           class="sort-link <?= $sort==='nom' ? 'sort-link--active' : '' ?>">
                            Étudiant <?= sort_icon('nom',$sort,$order) ?>
                        </a>
                    </th>
                    <th>
                        <a href="<?= e(build_url(['sort'=>'filieres','order'=>next_order('filieres',$sort,$order),'p'=>1])) ?>"
                           class="sort-link <?= $sort==='filieres' ? 'sort-link--active' : '' ?>">
                            Filière <?= sort_icon('filieres',$sort,$order) ?>
                        </a>
                    </th>
                    <th class="text-center">
                        <a href="<?= e(build_url(['sort'=>'nb_total','order'=>next_order('nb_total',$sort,$order),'p'=>1])) ?>"
                           class="sort-link <?= $sort==='nb_total' ? 'sort-link--active' : '' ?>">
                            Inscrits <?= sort_icon('nb_total',$sort,$order) ?>
                        </a>
                    </th>
                    <th class="text-center">
                        <a href="<?= e(build_url(['sort'=>'nb_notes','order'=>next_order('nb_notes',$sort,$order),'p'=>1])) ?>"
                           class="sort-link <?= $sort==='nb_notes' ? 'sort-link--active' : '' ?>">
                            Notés <?= sort_icon('nb_notes',$sort,$order) ?>
                        </a>
                    </th>
                    <th class="text-center">
                        <a href="<?= e(build_url(['sort'=>'nb_valides','order'=>next_order('nb_valides',$sort,$order),'p'=>1])) ?>"
                           class="sort-link <?= $sort==='nb_valides' ? 'sort-link--active' : '' ?>">
                            Validés <?= sort_icon('nb_valides',$sort,$order) ?>
                        </a>
                    </th>
                    <th class="text-center">
                        <a href="<?= e(build_url(['sort'=>'nb_echoues','order'=>next_order('nb_echoues',$sort,$order),'p'=>1])) ?>"
                           class="sort-link <?= $sort==='nb_echoues' ? 'sort-link--active' : '' ?>">
                            Échoués <?= sort_icon('nb_echoues',$sort,$order) ?>
                        </a>
                    </th>
                    <th style="width:140px" class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr>
                        <td colspan="8">
                            <div class="empty-state">
                                <div class="empty-icon"><i class="bi bi-clipboard"></i></div>
                                <h5>Aucun étudiant</h5>
                                <p>
                                    <?php if ($search !== '' || $filiere !== ''): ?>
                                        Aucun étudiant ne correspond à vos filtres.
                                        <a href="?page=notes">Réinitialiser</a>
                                    <?php else: ?>
                                        Aucun étudiant actif.
                                    <?php endif; ?>
                                </p>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php $rowNum = $pagination['offset'] + 1; ?>
                    <?php foreach ($rows as $r):
                        $initials = mb_strtoupper(
                            mb_substr((string)$r['nom'],    0, 1) .
                            mb_substr((string)$r['prenom'], 0, 1)
                        );
                        $nbTotal   = (int)$r['nb_total'];
                        $nbNotes   = (int)$r['nb_notes'];
                        $progress  = $nbTotal > 0 ? (int)round(($nbNotes / $nbTotal) * 100) : 0;
                    ?>
                        <tr>
                            <td class="text-muted"><?= e((string)$rowNum++) ?></td>
                            <td>
                                <span class="student-avatar"><?= e($initials) ?></span>
                                <strong><?= e((string)$r['nom']) ?></strong>
                                <?= e((string)$r['prenom']) ?>
                                <div class="text-muted small mt-1"><?= e((string)$r['email']) ?></div>
                            </td>
                            <td><span class="badge-filiere"><?= e((string)$r['filieres']) ?></span></td>
                            <td class="text-center">
                                <span class="notes-count notes-count--inscrits"><?= e((string)$nbTotal) ?></span>
                            </td>
                            <td class="text-center">
                                <span class="notes-count notes-count--notes">
                                    <?= e((string)$nbNotes) ?>
                                    <?php if ($nbTotal > 0): ?>
                                        <small class="text-muted">/<?= e((string)$nbTotal) ?></small>
                                    <?php endif; ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <span class="notes-count notes-count--valides"><?= e((string)$r['nb_valides']) ?></span>
                            </td>
                            <td class="text-center">
                                <span class="notes-count notes-count--echoues"><?= e((string)$r['nb_echoues']) ?></span>
                            </td>
                            <td class="text-end">
                                <a href="?page=notes_etudiant&id=<?= e((string)$r['id']) ?>"
                                   class="btn btn-sm btn-primary">
                                    <i class="bi bi-pencil-square me-1"></i>Saisir
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($pagination['totalPages'] > 1): ?>
        <div class="card-footer d-flex align-items-center justify-content-between">
            <div class="pagination-info text-muted small">
                Page <?= e((string)$pagination['currentPage']) ?> sur <?= e((string)$pagination['totalPages']) ?>
            </div>
            <nav aria-label="Pagination">
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item <?= !$pagination['hasPrev'] ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= e(build_url(['p' => $pagination['currentPage'] - 1])) ?>">
                            <i class="bi bi-chevron-left"></i>
                        </a>
                    </li>
                    <?php foreach ($pagination['pages'] as $pn): ?>
                        <?php if ($pn === -1): ?>
                            <li class="page-item disabled"><span class="page-link">…</span></li>
                        <?php else: ?>
                            <li class="page-item <?= $pn === $pagination['currentPage'] ? 'active' : '' ?>">
                                <a class="page-link" href="<?= e(build_url(['p' => $pn])) ?>"><?= e((string)$pn) ?></a>
                            </li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <li class="page-item <?= !$pagination['hasNext'] ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= e(build_url(['p' => $pagination['currentPage'] + 1])) ?>">
                            <i class="bi bi-chevron-right"></i>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
