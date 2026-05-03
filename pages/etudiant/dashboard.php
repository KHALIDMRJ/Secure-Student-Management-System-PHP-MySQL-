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

$pageTitle = 'Mon tableau';
$pdo       = getPDO();
$me        = current_etudiant();   // never null after require_etudiant_auth
$myId      = $me['id'];

// =============================================================================
// Stats — every query is filtered by $myId. Students see their data only.
// =============================================================================
$inscritsCount = 0;
$valideCount   = 0;
$echoueCount   = 0;
$moyenne       = null;
$prenom        = '';
$recent        = [];

try {
    // Pull the student's prénom for the greeting (cheap, single field).
    $stmt = $pdo->prepare('SELECT prenom FROM etudiants WHERE id = :id LIMIT 1');
    $stmt->bindValue(':id', $myId, PDO::PARAM_INT);
    $stmt->execute();
    $prenom = (string)$stmt->fetchColumn();
} catch (PDOException $e) {
    error_log('etudiant dashboard prenom: ' . $e->getMessage());
}

try {
    $stmt = $pdo->prepare(
        "SELECT
            SUM(statut = 'inscrit') AS inscrits,
            SUM(statut = 'valide')  AS valides,
            SUM(statut = 'echoue')  AS echoues,
            AVG(note)               AS moyenne
         FROM inscriptions
         WHERE etudiant_id = :id"
    );
    $stmt->bindValue(':id', $myId, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $inscritsCount = (int)($row['inscrits'] ?? 0);
        $valideCount   = (int)($row['valides']  ?? 0);
        $echoueCount   = (int)($row['echoues']  ?? 0);
        $moyenne       = $row['moyenne'] !== null ? (float)$row['moyenne'] : null;
    }
} catch (PDOException $e) {
    error_log('etudiant dashboard stats: ' . $e->getMessage());
}

try {
    // Three most-recent enrolments with their module info.
    $stmt = $pdo->prepare(
        "SELECT m.code, m.nom, m.credits, i.statut, i.note, i.inscribed_at
         FROM inscriptions i
         JOIN modules m ON m.id = i.module_id
         WHERE i.etudiant_id = :id
         ORDER BY i.inscribed_at DESC, i.id DESC
         LIMIT 3"
    );
    $stmt->bindValue(':id', $myId, PDO::PARAM_INT);
    $stmt->execute();
    $recent = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('etudiant dashboard recent: ' . $e->getMessage());
}

require __DIR__ . '/../../includes/etudiant_header.php';
?>

<!-- Greeting -->
<section class="etudiant-greeting">
    <h2>Bonjour, <span class="name"><?= e($prenom !== '' ? $prenom : $me['email']) ?></span></h2>
    <p>Voici un aperçu de votre parcours.</p>
</section>

<!-- Stat cards (teal palette) -->
<div class="row g-3 mb-4">
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="d-flex align-items-start justify-content-between">
                <div>
                    <div class="stat-label">Modules inscrits</div>
                    <div class="stat-value"><?= e((string)$inscritsCount) ?></div>
                    <div class="stat-sublabel">en cours</div>
                </div>
                <div class="stat-icon stat-icon--teal">
                    <i class="bi bi-bookmark-fill"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="d-flex align-items-start justify-content-between">
                <div>
                    <div class="stat-label">Modules validés</div>
                    <div class="stat-value"><?= e((string)$valideCount) ?></div>
                    <div class="stat-sublabel">crédits acquis</div>
                </div>
                <div class="stat-icon stat-icon--success">
                    <i class="bi bi-check-circle-fill"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="d-flex align-items-start justify-content-between">
                <div>
                    <div class="stat-label">Modules échoués</div>
                    <div class="stat-value"><?= e((string)$echoueCount) ?></div>
                    <div class="stat-sublabel">à rattraper</div>
                </div>
                <div class="stat-icon stat-icon--danger">
                    <i class="bi bi-x-circle-fill"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="d-flex align-items-start justify-content-between">
                <div>
                    <div class="stat-label">Moyenne générale</div>
                    <div class="stat-value">
                        <?= $moyenne !== null ? e(number_format($moyenne, 2)) : '—' ?>
                    </div>
                    <div class="stat-sublabel">
                        <?= $moyenne !== null ? '/ 20' : 'pas encore de notes' ?>
                    </div>
                </div>
                <div class="stat-icon stat-icon--purple">
                    <i class="bi bi-graph-up-arrow"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent enrolments -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="bi bi-clock-history me-2 text-teal"></i>Activité récente
        </h5>
    </div>
    <?php if (empty($recent)): ?>
        <div class="empty-state">
            <div class="empty-icon"><i class="bi bi-collection"></i></div>
            <h5>Pas encore d'inscription</h5>
            <p>Inscrivez-vous à un module depuis la page « Mes modules ».</p>
            <a href="?page=etudiant/modules" class="btn btn-teal btn-sm">
                <i class="bi bi-collection-fill me-1"></i>Voir les modules
            </a>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Module</th>
                        <th>Crédits</th>
                        <th>Statut</th>
                        <th>Note</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent as $r): ?>
                        <tr>
                            <td><code class="text-muted"><?= e((string)$r['code']) ?></code></td>
                            <td><strong><?= e((string)$r['nom']) ?></strong></td>
                            <td><span class="credits"><?= e((string)$r['credits']) ?> ECTS</span></td>
                            <td>
                                <span class="badge-statut badge-statut--<?= e((string)$r['statut']) ?>">
                                    <?= e(ucfirst((string)$r['statut'])) ?>
                                </span>
                            </td>
                            <td class="text-muted">
                                <?= $r['note'] !== null ? e(number_format((float)$r['note'], 2)) : '—' ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Quick actions -->
<div class="d-flex flex-wrap gap-2">
    <a href="?page=etudiant/modules" class="btn btn-teal">
        <i class="bi bi-collection-fill me-1"></i>Voir mes modules
    </a>
    <a href="?page=etudiant/profil" class="btn btn-outline-teal">
        <i class="bi bi-person-circle me-1"></i>Mon profil
    </a>
</div>

<?php require __DIR__ . '/../../includes/etudiant_footer.php'; ?>
