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

$pageTitle = 'Mon profil';
$pdo       = getPDO();
$me        = current_etudiant();
$myId      = $me['id'];

$pwErrors  = [];
$pwSuccess = false;

// =============================================================================
// Password change handler — runs first so the freshly-loaded profile shows
// any post-update state.
// =============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_token_verify()) {
        abort(403, 'Jeton CSRF invalide.');
    }

    $current = (string)($_POST['current_password'] ?? '');
    $next    = (string)($_POST['new_password']     ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');

    // Lookup current hash.
    try {
        $stmt = $pdo->prepare('SELECT password_hash FROM etudiants WHERE id = :id LIMIT 1');
        $stmt->bindValue(':id', $myId, PDO::PARAM_INT);
        $stmt->execute();
        $hash = (string)$stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log('profil password lookup: ' . $e->getMessage());
        abort(500);
    }

    if (!password_verify($current, $hash)) {
        $pwErrors[] = 'Mot de passe actuel incorrect.';
    }
    if (mb_strlen($next) < 8) {
        $pwErrors[] = 'Le nouveau mot de passe doit faire au moins 8 caractères.';
    }
    if ($next !== $confirm) {
        $pwErrors[] = 'Les deux nouveaux mots de passe ne correspondent pas.';
    }
    if ($next === $current && $next !== '') {
        $pwErrors[] = 'Le nouveau mot de passe doit être différent de l\'ancien.';
    }

    if (empty($pwErrors)) {
        try {
            $newHash = password_hash($next, PASSWORD_DEFAULT);
            $up = $pdo->prepare('UPDATE etudiants SET password_hash = :h WHERE id = :id');
            $up->bindValue(':h',  $newHash, PDO::PARAM_STR);
            $up->bindValue(':id', $myId,    PDO::PARAM_INT);
            $up->execute();
            csrf_token_renew();
            $pwSuccess = true;
        } catch (PDOException $e) {
            error_log('profil password update: ' . $e->getMessage());
            abort(500);
        }
    }
}

// =============================================================================
// Load full profile (always — POST handlers above don't return until done).
// =============================================================================
$profile  = null;
$inscrits = 0;
$valides  = 0;
$moyenne  = null;

try {
    $stmt = $pdo->prepare(
        'SELECT id, nom, prenom, email, filieres, created_at, last_login
         FROM etudiants WHERE id = :id LIMIT 1'
    );
    $stmt->bindValue(':id', $myId, PDO::PARAM_INT);
    $stmt->execute();
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('profil load: ' . $e->getMessage());
    abort(500);
}

if (!$profile) {
    abort(404, 'Profil introuvable.');
}

try {
    $stmt = $pdo->prepare(
        "SELECT
            COUNT(*)                AS total,
            SUM(statut = 'valide')  AS valides,
            AVG(note)               AS moyenne
         FROM inscriptions WHERE etudiant_id = :id"
    );
    $stmt->bindValue(':id', $myId, PDO::PARAM_INT);
    $stmt->execute();
    $s = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($s) {
        $inscrits = (int)($s['total']   ?? 0);
        $valides  = (int)($s['valides'] ?? 0);
        $moyenne  = $s['moyenne'] !== null ? (float)$s['moyenne'] : null;
    }
} catch (PDOException $e) {
    error_log('profil stats: ' . $e->getMessage());
}

$initials = mb_strtoupper(
    mb_substr((string)$profile['nom'],    0, 1)
    . mb_substr((string)$profile['prenom'], 0, 1)
);

require __DIR__ . '/../../includes/etudiant_header.php';
?>

<section class="etudiant-greeting">
    <h2>Mon profil</h2>
    <p>Vos informations personnelles et la sécurité de votre compte.</p>
</section>

<div class="row g-3">
    <!-- Profile card -->
    <div class="col-12 col-lg-5">
        <div class="card profile-card h-100">
            <div class="card-body text-center">
                <div class="profile-avatar"><?= e($initials) ?></div>
                <h3 class="profile-name">
                    <?= e($profile['nom']) ?> <?= e($profile['prenom']) ?>
                </h3>
                <div class="profile-email"><?= e($profile['email']) ?></div>
                <div class="profile-filiere">
                    <i class="bi bi-mortarboard-fill me-1"></i><?= e($profile['filieres']) ?>
                </div>
                <hr>
                <dl class="profile-meta">
                    <div>
                        <dt>Inscrit le</dt>
                        <dd><?= e(date('d/m/Y', strtotime((string)$profile['created_at']))) ?></dd>
                    </div>
                    <?php if (!empty($profile['last_login'])): ?>
                        <div>
                            <dt>Dernière connexion</dt>
                            <dd><?= e(date('d/m/Y à H:i', strtotime((string)$profile['last_login']))) ?></dd>
                        </div>
                    <?php endif; ?>
                </dl>
                <hr>
                <div class="profile-stats">
                    <div>
                        <div class="profile-stat-value"><?= e((string)$inscrits) ?></div>
                        <div class="profile-stat-label">Inscrits</div>
                    </div>
                    <div>
                        <div class="profile-stat-value"><?= e((string)$valides) ?></div>
                        <div class="profile-stat-label">Validés</div>
                    </div>
                    <div>
                        <div class="profile-stat-value">
                            <?= $moyenne !== null ? e(number_format($moyenne, 2)) : '—' ?>
                        </div>
                        <div class="profile-stat-label">Moyenne</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Password card -->
    <div class="col-12 col-lg-7">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-shield-lock-fill me-2 text-teal"></i>Changer mon mot de passe
                </h5>
            </div>
            <div class="card-body">
                <?php if ($pwSuccess): ?>
                    <div class="alert alert-success" role="alert">
                        <i class="bi bi-check-circle-fill me-2"></i>
                        Mot de passe mis à jour avec succès.
                    </div>
                <?php elseif (!empty($pwErrors)): ?>
                    <div class="alert alert-danger" role="alert">
                        <strong><i class="bi bi-exclamation-triangle-fill me-2"></i>
                            Veuillez corriger les erreurs suivantes :
                        </strong>
                        <ul class="mb-0 mt-2">
                            <?php foreach ($pwErrors as $err): ?>
                                <li><?= e($err) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="post" action="?page=etudiant/profil" autocomplete="off">
                    <?= csrf_token_field() ?>

                    <div class="mb-3">
                        <label for="current_password" class="form-label">Mot de passe actuel</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-key"></i></span>
                            <input type="password"
                                   id="current_password"
                                   name="current_password"
                                   class="form-control"
                                   required
                                   autocomplete="current-password">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="new_password" class="form-label">Nouveau mot de passe</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-shield-lock"></i></span>
                            <input type="password"
                                   id="new_password"
                                   name="new_password"
                                   class="form-control"
                                   minlength="8"
                                   required
                                   autocomplete="new-password">
                        </div>
                        <div class="form-text">Au moins 8 caractères.</div>
                    </div>

                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirmer le nouveau mot de passe</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-shield-check"></i></span>
                            <input type="password"
                                   id="confirm_password"
                                   name="confirm_password"
                                   class="form-control"
                                   minlength="8"
                                   required
                                   autocomplete="new-password">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-teal">
                        <i class="bi bi-save me-1"></i>Enregistrer
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../../includes/etudiant_footer.php'; ?>
