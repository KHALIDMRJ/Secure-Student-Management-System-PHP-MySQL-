<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/rate_limiter.php';

configure_session();
session_start();
prevent_session_fixation();
validate_session_integrity();

$pageTitle  = 'Tableau de bord';
$breadcrumb = 'Dashboard';
$pdo        = getPDO();

// =============================================================================
// STATS — each query degrades to a sane fallback so one failed stat never
// takes the whole dashboard down. All errors logged, never exposed.
// =============================================================================

// ── STAT 1: Total students ───────────────────────────────────────────────────
try {
    $totalStudents = (int)$pdo->query(
        "SELECT COUNT(*) FROM etudiants"
    )->fetchColumn();
} catch (PDOException $e) {
    error_log('DASH total: ' . $e->getMessage());
    $totalStudents = 0;
}

// ── STAT 2: Total distinct filières ──────────────────────────────────────────
try {
    $totalFilieres = (int)$pdo->query(
        "SELECT COUNT(DISTINCT filieres) FROM etudiants"
    )->fetchColumn();
} catch (PDOException $e) {
    error_log('DASH filieres: ' . $e->getMessage());
    $totalFilieres = 0;
}

// ── STAT 3: Newest student ───────────────────────────────────────────────────
try {
    $newest = $pdo->query(
        "SELECT nom, prenom, filieres, created_at
         FROM etudiants
         ORDER BY created_at DESC, id DESC
         LIMIT 1"
    )->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('DASH newest: ' . $e->getMessage());
    $newest = null;
}

// ── STAT 4: Most popular filière ─────────────────────────────────────────────
try {
    $topFiliere = $pdo->query(
        "SELECT filieres, COUNT(*) AS total
         FROM etudiants
         GROUP BY filieres
         ORDER BY total DESC
         LIMIT 1"
    )->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('DASH top filiere: ' . $e->getMessage());
    $topFiliere = null;
}

// ── CHART 1: Students per filière ────────────────────────────────────────────
try {
    $filiereStats = $pdo->query(
        "SELECT filieres, COUNT(*) AS total
         FROM etudiants
         GROUP BY filieres
         ORDER BY total DESC"
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('DASH filiere chart: ' . $e->getMessage());
    $filiereStats = [];
}

// ── CHART 2: Monthly registrations (last 12 months) ──────────────────────────
try {
    $monthlyStats = $pdo->query(
        "SELECT
            DATE_FORMAT(created_at, '%Y-%m') AS month,
            DATE_FORMAT(created_at, '%b %Y') AS label,
            COUNT(*) AS total
         FROM etudiants
         WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
         GROUP BY DATE_FORMAT(created_at, '%Y-%m'), DATE_FORMAT(created_at, '%b %Y')
         ORDER BY month ASC"
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('DASH monthly chart: ' . $e->getMessage());
    $monthlyStats = [];
}

// ── Recent 5 students ────────────────────────────────────────────────────────
try {
    $recentStudents = $pdo->query(
        "SELECT id, nom, prenom, email, filieres, created_at
         FROM etudiants
         ORDER BY created_at DESC, id DESC
         LIMIT 5"
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('DASH recent: ' . $e->getMessage());
    $recentStudents = [];
}

// =============================================================================
// Encode chart data for safe pickup via data-* attributes (no inline scripts).
// JSON_HEX_APOS | JSON_HEX_QUOT escapes both quote styles for HTML attributes.
// =============================================================================
$jsonOpts = JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT;

$filiereLabelsJson = json_encode(array_column($filiereStats, 'filieres'), $jsonOpts);
$filiereDataJson   = json_encode(array_map('intval', array_column($filiereStats, 'total')), $jsonOpts);
$monthlyLabelsJson = json_encode(array_column($monthlyStats, 'label'),    $jsonOpts);
$monthlyDataJson   = json_encode(array_map('intval', array_column($monthlyStats, 'total')), $jsonOpts);

require __DIR__ . '/../includes/header.php';
?>

<!-- Hidden carrier — JS reads chart data from data-* attributes, no inline JS. -->
<div id="chartData"
     class="d-none"
     data-filiere-labels='<?= e($filiereLabelsJson) ?>'
     data-filiere-data='<?= e($filiereDataJson) ?>'
     data-monthly-labels='<?= e($monthlyLabelsJson) ?>'
     data-monthly-data='<?= e($monthlyDataJson) ?>'></div>

<!-- ──────────────────────────  STAT CARDS  ────────────────────────── -->
<div class="row g-3 mb-4">

    <!-- Total étudiants -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="d-flex align-items-start justify-content-between">
                <div>
                    <div class="stat-label">Total étudiants</div>
                    <div class="stat-value"><?= e((string)$totalStudents) ?></div>
                    <div class="stat-sublabel">inscrits dans le système</div>
                </div>
                <div class="stat-icon stat-icon--primary">
                    <i class="bi bi-people-fill"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Filières -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="d-flex align-items-start justify-content-between">
                <div>
                    <div class="stat-label">Filières</div>
                    <div class="stat-value"><?= e((string)$totalFilieres) ?></div>
                    <div class="stat-sublabel">spécialités distinctes</div>
                </div>
                <div class="stat-icon stat-icon--success">
                    <i class="bi bi-mortarboard-fill"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Filière populaire -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="d-flex align-items-start justify-content-between">
                <div>
                    <div class="stat-label">Filière populaire</div>
                    <div class="stat-value stat-value--sm">
                        <?= $topFiliere ? e($topFiliere['filieres']) : '—' ?>
                    </div>
                    <div class="stat-sublabel">
                        <?= $topFiliere ? e((string)$topFiliere['total']) . ' étudiant(s)' : 'Aucune donnée' ?>
                    </div>
                </div>
                <div class="stat-icon stat-icon--warning">
                    <i class="bi bi-trophy-fill"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Dernier inscrit -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="d-flex align-items-start justify-content-between">
                <div>
                    <div class="stat-label">Dernier inscrit</div>
                    <div class="stat-value stat-value--sm">
                        <?php if ($newest): ?>
                            <?= e($newest['nom']) ?> <?= e($newest['prenom']) ?>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </div>
                    <div class="stat-sublabel">
                        <?= $newest ? e(date('d/m/Y', strtotime((string)$newest['created_at']))) : 'Aucune donnée' ?>
                    </div>
                </div>
                <div class="stat-icon stat-icon--danger">
                    <i class="bi bi-person-check-fill"></i>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- ──────────────────────────  CHARTS  ────────────────────────── -->
<div class="row g-3 mb-4">

    <!-- Bar chart: students per filière -->
    <div class="col-12 col-lg-7">
        <div class="card h-100">
            <div class="card-header">
                <h5><i class="bi bi-bar-chart-fill me-2 text-primary"></i>Répartition par filière</h5>
            </div>
            <div class="card-body">
                <?php if (empty($filiereStats)): ?>
                    <div class="empty-state">
                        <div class="empty-icon"><i class="bi bi-bar-chart"></i></div>
                        <h5>Pas encore de données</h5>
                        <p>Ajoutez des étudiants pour voir la répartition.</p>
                    </div>
                <?php else: ?>
                    <div class="chart-wrap">
                        <canvas id="filiereChart"></canvas>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Line chart: monthly trend -->
    <div class="col-12 col-lg-5">
        <div class="card h-100">
            <div class="card-header">
                <h5><i class="bi bi-graph-up-arrow me-2 text-primary"></i>Tendance mensuelle</h5>
            </div>
            <div class="card-body">
                <?php if (empty($monthlyStats)): ?>
                    <div class="empty-state">
                        <div class="empty-icon"><i class="bi bi-graph-up"></i></div>
                        <h5>Pas encore de données</h5>
                        <p>Aucune inscription sur les 12 derniers mois.</p>
                    </div>
                <?php else: ?>
                    <div class="chart-wrap">
                        <canvas id="monthlyChart"></canvas>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div>

<!-- ─────────────────────  RECENT STUDENTS TABLE  ───────────────────── -->
<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <h5 class="mb-0">
            <i class="bi bi-clock-history me-2 text-primary"></i>Inscriptions récentes
        </h5>
        <a href="?page=index" class="btn btn-sm btn-outline-primary">
            Voir tout <i class="bi bi-arrow-right ms-1"></i>
        </a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Étudiant</th>
                    <th>Email</th>
                    <th>Filière</th>
                    <th>Inscrit le</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recentStudents)): ?>
                    <tr>
                        <td colspan="5">
                            <div class="empty-state">
                                <div class="empty-icon"><i class="bi bi-people"></i></div>
                                <h5>Aucun étudiant</h5>
                                <p>Commencez par <a href="?page=ajouter">ajouter</a> un étudiant.</p>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($recentStudents as $row):
                        $initials = mb_strtoupper(
                            mb_substr((string)$row['nom'],    0, 1) .
                            mb_substr((string)$row['prenom'], 0, 1)
                        );
                    ?>
                        <tr>
                            <td>
                                <span class="student-avatar"><?= e($initials) ?></span>
                                <strong><?= e($row['nom']) ?></strong>
                                <?= e($row['prenom']) ?>
                            </td>
                            <td class="text-muted"><?= e($row['email']) ?></td>
                            <td>
                                <span class="badge-filiere">
                                    <?= e($row['filieres']) ?>
                                </span>
                            </td>
                            <td class="text-muted" style="font-size:.82rem">
                                <?= e(date('d/m/Y', strtotime((string)$row['created_at']))) ?>
                            </td>
                            <td class="text-end">
                                <a href="?page=modifier&id=<?= e((string)$row['id']) ?>"
                                   class="btn btn-sm btn-outline-primary btn-icon me-1"
                                   title="Modifier">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <a href="?page=supprimer&id=<?= e((string)$row['id']) ?>"
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
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
