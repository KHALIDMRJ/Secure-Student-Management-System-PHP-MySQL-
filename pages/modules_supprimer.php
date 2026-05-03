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

$pageTitle  = 'Supprimer un module';
$breadcrumb = 'Supprimer un module';
$pdo        = getPDO();

// Resolve ID from POST first (preferred), then GET.
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($id === false || $id === null) {
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
}
if ($id === false || $id === null) {
    redirect('?page=modules');
}

// Load the target module + count of dependent inscriptions.
try {
    $stmt = $pdo->prepare(
        'SELECT id, code, nom, filiere, semestre, credits, created_at
         FROM modules WHERE id = :id LIMIT 1'
    );
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $module = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('modules_supprimer load: ' . $e->getMessage());
    abort(500);
}
if (!$module) {
    redirect('?page=modules');
}

try {
    $cnt = $pdo->prepare('SELECT COUNT(*) FROM inscriptions WHERE module_id = :id');
    $cnt->bindValue(':id', $id, PDO::PARAM_INT);
    $cnt->execute();
    $inscriptionCount = (int)$cnt->fetchColumn();
} catch (PDOException $e) {
    error_log('modules_supprimer count: ' . $e->getMessage());
    $inscriptionCount = 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (rate_limit_exceeded('delete_module', 5, 60)) {
        abort(429, 'Trop de tentatives. Veuillez patienter 5 minutes.');
    }
    if (!csrf_token_verify()) {
        abort(403, 'Jeton CSRF invalide.');
    }

    try {
        // FK CASCADE on inscriptions(module_id) drops dependent rows automatically.
        $del = $pdo->prepare('DELETE FROM modules WHERE id = :id');
        $del->bindValue(':id', $id, PDO::PARAM_INT);
        $del->execute();

        csrf_token_renew();
        rate_limit_reset('delete_module');
        redirect('?page=modules&supprimer=ok');
    } catch (PDOException $e) {
        error_log('DELETE module: ' . $e->getMessage());
        abort(500);
    }
}

require __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <div>
        <h2>Confirmer la suppression</h2>
        <div class="subtitle">Cette action est définitive.</div>
    </div>
</div>

<div class="card confirmation-card">
    <div class="card-header">
        <h5><i class="bi bi-exclamation-triangle-fill me-2 text-danger"></i>Suppression d'un module</h5>
    </div>
    <div class="card-body">
        <?php if ($inscriptionCount > 0): ?>
            <div class="alert alert-warning" role="alert">
                <strong><i class="bi bi-exclamation-triangle-fill me-1"></i>
                    Ce module a <?= e((string)$inscriptionCount) ?> étudiant<?= $inscriptionCount > 1 ? 's' : '' ?> inscrit<?= $inscriptionCount > 1 ? 's' : '' ?>.
                </strong><br>
                La suppression effacera également toutes leurs inscriptions, notes
                et statuts. Cette opération ne peut pas être annulée.
            </div>
        <?php else: ?>
            <div class="alert alert-info" role="alert">
                <i class="bi bi-info-circle-fill me-1"></i>
                Aucun étudiant n'est inscrit à ce module — la suppression est sans
                impact pour les étudiants.
            </div>
        <?php endif; ?>

        <ul class="detail-list">
            <li>
                <span class="label">Code</span>
                <span class="value"><code class="module-code"><?= e((string)$module['code']) ?></code></span>
            </li>
            <li>
                <span class="label">Nom</span>
                <span class="value"><?= e((string)$module['nom']) ?></span>
            </li>
            <li>
                <span class="label">Filière</span>
                <span class="value"><span class="badge-filiere"><?= e((string)$module['filiere']) ?></span></span>
            </li>
            <li>
                <span class="label">Semestre</span>
                <span class="value">S<?= e((string)$module['semestre']) ?></span>
            </li>
            <li>
                <span class="label">Crédits</span>
                <span class="value"><span class="credits-badge"><?= e((string)$module['credits']) ?> ECTS</span></span>
            </li>
            <li>
                <span class="label">Inscrit le</span>
                <span class="value"><?= e(date('d/m/Y', strtotime((string)$module['created_at']))) ?></span>
            </li>
        </ul>

        <form method="post"
              action="?page=modules_supprimer&id=<?= e((string)$module['id']) ?>"
              data-confirm="Êtes-vous certain de vouloir supprimer ce module ?">
            <?= csrf_token_field() ?>
            <input type="hidden" name="id" value="<?= e((string)$module['id']) ?>">
            <div class="form-actions">
                <button type="submit" class="btn btn-danger">
                    <i class="bi bi-trash me-1"></i>Confirmer la suppression
                </button>
                <a href="?page=modules" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i>Annuler
                </a>
            </div>
        </form>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
