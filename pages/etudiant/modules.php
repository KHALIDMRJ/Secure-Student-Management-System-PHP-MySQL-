<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/rate_limiter.php';
require_once __DIR__ . '/../../includes/etudiant_auth.php';

configure_session();
session_start();
prevent_session_fixation();
validate_session_integrity();
require_etudiant_auth();

$pageTitle = 'Mes modules';
$pdo       = getPDO();
$me        = current_etudiant();
$myId      = $me['id'];
$myFiliere = $me['filiere'];

$flash = null;

// =============================================================================
// Enrolment action (POST). The student can enrol themselves in a module
// from their own filière. Filtered by $myId + $myFiliere — no parameter
// from the client decides which student is being enrolled.
// =============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_token_verify()) {
        abort(403, 'Jeton CSRF invalide.');
    }
    if (rate_limit_exceeded('etudiant_enrol', 30, 60)) {
        abort(429, 'Trop de demandes. Patientez une minute.');
    }

    $moduleId = filter_input(INPUT_POST, 'module_id', FILTER_VALIDATE_INT,
                             ['options' => ['min_range' => 1]]);

    if ($moduleId !== null && $moduleId !== false) {
        try {
            // Confirm the module exists AND belongs to the student's filière.
            $stmt = $pdo->prepare(
                'SELECT id FROM modules WHERE id = :id AND filiere = :f LIMIT 1'
            );
            $stmt->bindValue(':id', $moduleId,  PDO::PARAM_INT);
            $stmt->bindValue(':f',  $myFiliere, PDO::PARAM_STR);
            $stmt->execute();
            if ($stmt->fetchColumn()) {
                // INSERT IGNORE — second click is a no-op thanks to the
                // (etudiant_id, module_id) unique key.
                $ins = $pdo->prepare(
                    'INSERT IGNORE INTO inscriptions (etudiant_id, module_id, statut)
                     VALUES (:e, :m, "inscrit")'
                );
                $ins->bindValue(':e', $myId,     PDO::PARAM_INT);
                $ins->bindValue(':m', $moduleId, PDO::PARAM_INT);
                $ins->execute();
                $flash = $ins->rowCount() > 0
                    ? 'Inscription enregistrée.'
                    : 'Vous êtes déjà inscrit à ce module.';
            } else {
                $flash = 'Module indisponible pour votre filière.';
            }
            csrf_token_renew();
        } catch (PDOException $e) {
            error_log('etudiant enrol: ' . $e->getMessage());
            abort(500);
        }
    }
}

// =============================================================================
// Load every module of the student's filière + their enrolment status (LEFT JOIN).
// Group server-side by semester so the view just iterates.
// =============================================================================
$bySemester = [1 => [], 2 => []];
$totalModules   = 0;
$totalInscrits  = 0;
$totalValides   = 0;

try {
    $stmt = $pdo->prepare(
        "SELECT
            m.id, m.code, m.nom, m.description, m.credits, m.semestre,
            i.statut, i.note, i.inscribed_at
         FROM modules m
         LEFT JOIN inscriptions i
             ON i.module_id = m.id AND i.etudiant_id = :id
         WHERE m.filiere = :f
         ORDER BY m.semestre, m.code"
    );
    $stmt->bindValue(':id', $myId,     PDO::PARAM_INT);
    $stmt->bindValue(':f',  $myFiliere, PDO::PARAM_STR);
    $stmt->execute();
    foreach ($stmt as $row) {
        $sem = (int)$row['semestre'];
        if (!isset($bySemester[$sem])) {
            $bySemester[$sem] = [];
        }
        $bySemester[$sem][] = $row;
        $totalModules++;
        if ($row['statut'] !== null) {
            $totalInscrits++;
            if ($row['statut'] === 'valide') {
                $totalValides++;
            }
        }
    }
} catch (PDOException $e) {
    error_log('etudiant modules: ' . $e->getMessage());
    abort(500);
}

$progressPct = $totalModules > 0
    ? (int)round(($totalValides / $totalModules) * 100)
    : 0;

require __DIR__ . '/../../includes/etudiant_header.php';
?>

<section class="etudiant-greeting">
    <h2>Mes modules</h2>
    <p>
        Filière <strong><?= e($myFiliere) ?></strong> —
        <?= e((string)$totalInscrits) ?> inscription(s) sur
        <?= e((string)$totalModules) ?> module(s).
    </p>
</section>

<?php if ($flash !== null): ?>
    <div class="alert alert-info">
        <i class="bi bi-info-circle-fill me-2"></i><?= e($flash) ?>
    </div>
<?php endif; ?>

<!-- Overall progress bar -->
<div class="card mb-4">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="text-muted small">Progression</span>
            <strong><?= e((string)$progressPct) ?>%</strong>
        </div>
        <div class="progress progress-teal">
            <div class="progress-bar" role="progressbar"
                 style="width: <?= e((string)$progressPct) ?>%"
                 aria-valuenow="<?= e((string)$progressPct) ?>"
                 aria-valuemin="0" aria-valuemax="100"></div>
        </div>
        <div class="text-muted small mt-2">
            <?= e((string)$totalValides) ?> module(s) validé(s) sur
            <?= e((string)$totalModules) ?>.
        </div>
    </div>
</div>

<?php foreach ($bySemester as $semNum => $list): ?>
    <?php if (empty($list)) continue; ?>
    <div class="semestre-header">
        <i class="bi bi-calendar3"></i>
        <h3>Semestre <?= e((string)$semNum) ?></h3>
        <span class="text-muted small ms-auto"><?= count($list) ?> module(s)</span>
    </div>

    <div class="row g-3 mb-4">
        <?php foreach ($list as $m): ?>
            <div class="col-12 col-md-6 col-xl-4">
                <div class="module-card h-100">
                    <div class="module-card-header">
                        <code class="module-code"><?= e((string)$m['code']) ?></code>
                        <span class="credits"><?= e((string)$m['credits']) ?> ECTS</span>
                    </div>
                    <h5 class="module-title"><?= e((string)$m['nom']) ?></h5>
                    <?php if (!empty($m['description'])): ?>
                        <p class="module-desc"><?= e((string)$m['description']) ?></p>
                    <?php endif; ?>

                    <div class="module-card-footer">
                        <?php if ($m['statut'] !== null): ?>
                            <span class="badge-statut badge-statut--<?= e((string)$m['statut']) ?>">
                                <?= e(ucfirst((string)$m['statut'])) ?>
                            </span>
                            <?php if ($m['note'] !== null): ?>
                                <span class="module-note">
                                    <strong><?= e(number_format((float)$m['note'], 2)) ?></strong> / 20
                                </span>
                            <?php endif; ?>
                        <?php else: ?>
                            <form method="post" action="?page=etudiant/modules" class="d-inline">
                                <?= csrf_token_field() ?>
                                <input type="hidden" name="module_id" value="<?= e((string)$m['id']) ?>">
                                <button type="submit" class="btn btn-sm btn-teal">
                                    <i class="bi bi-plus-lg me-1"></i>S'inscrire
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endforeach; ?>

<?php if ($totalModules === 0): ?>
    <div class="empty-state">
        <div class="empty-icon"><i class="bi bi-collection"></i></div>
        <h5>Aucun module</h5>
        <p>Aucun module n'est encore défini pour la filière <strong><?= e($myFiliere) ?></strong>.</p>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../../includes/etudiant_footer.php'; ?>
