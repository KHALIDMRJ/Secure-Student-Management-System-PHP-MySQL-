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

$pageTitle  = 'Modifier un module';
$breadcrumb = 'Modifier un module';
$pdo        = getPDO();

/**
 * Validate the posted edit payload. Identical rules to the add form.
 * Defined locally so the two pages don't share a function with subtly
 * different validation rules; trivially refactorable later.
 *
 * @param array $data
 * @return array{errors: string[], clean: array{code:string, nom:string, description:string, credits:int, semestre:int, filiere:string}}
 */
function validate_module_edit(array $data): array {
    $errors = [];

    $clean = [
        'code'        => strtoupper(trim((string)($data['code']        ?? ''))),
        'nom'         => trim((string)($data['nom']         ?? '')),
        'description' => trim((string)($data['description'] ?? '')),
        'credits'     => (int)($data['credits']  ?? 0),
        'semestre'    => (int)($data['semestre'] ?? 0),
        'filiere'     => trim((string)($data['filiere']     ?? '')),
    ];

    if ($clean['code'] === '' || mb_strlen($clean['code']) > 20) {
        $errors[] = 'Code invalide (1 à 20 caractères).';
    } elseif (!preg_match('/^[A-Z0-9\-]+$/', $clean['code'])) {
        $errors[] = 'Le code ne peut contenir que des lettres, chiffres et tirets.';
    }
    if (mb_strlen($clean['nom']) < 3 || mb_strlen($clean['nom']) > 150) {
        $errors[] = 'Nom invalide (3 à 150 caractères).';
    }
    if (mb_strlen($clean['description']) > 1000) {
        $errors[] = 'Description trop longue (max 1000 caractères).';
    }
    if ($clean['credits'] < 1 || $clean['credits'] > 10) {
        $errors[] = 'Crédits invalides (entre 1 et 10).';
    }
    if ($clean['semestre'] < 1 || $clean['semestre'] > 6) {
        $errors[] = 'Semestre invalide (entre 1 et 6).';
    }
    if ($clean['filiere'] === '' || mb_strlen($clean['filiere']) > 100) {
        $errors[] = 'Filière invalide (1 à 100 caractères).';
    }

    return ['errors' => $errors, 'clean' => $clean];
}

/**
 * True if a different module already uses this code.
 */
function is_module_code_taken_other(PDO $pdo, string $code, int $excludeId): bool {
    try {
        $stmt = $pdo->prepare('SELECT 1 FROM modules WHERE code = :c AND id <> :id LIMIT 1');
        $stmt->bindValue(':c',  $code,      PDO::PARAM_STR);
        $stmt->bindValue(':id', $excludeId, PDO::PARAM_INT);
        $stmt->execute();
        return (bool)$stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log('is_module_code_taken_other: ' . $e->getMessage());
        return true;
    }
}

// ── Resolve and load the module ─────────────────────────────────────────────
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($id === false || $id === null) {
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
}
if ($id === false || $id === null) {
    redirect('?page=modules');
}

try {
    $stmt = $pdo->prepare(
        'SELECT id, code, nom, description, credits, semestre, filiere
         FROM modules WHERE id = :id LIMIT 1'
    );
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $module = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('modules_modifier load: ' . $e->getMessage());
    abort(500);
}
if (!$module) {
    redirect('?page=modules');
}

// Initial form state = current DB values.
$errors = [];
$old = [
    'code'        => (string)$module['code'],
    'nom'         => (string)$module['nom'],
    'description' => (string)($module['description'] ?? ''),
    'credits'     => (int)$module['credits'],
    'semestre'    => (int)$module['semestre'],
    'filiere'     => (string)$module['filiere'],
];
$originalFiliere = $old['filiere'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (rate_limit_exceeded('edit_module', 10, 60)) {
        abort(429, 'Trop de tentatives. Veuillez patienter 5 minutes.');
    }
    if (honeypot_triggered()) {
        error_log('Honeypot on modules_modifier from ' . ($_SERVER['REMOTE_ADDR'] ?? '?'));
        redirect('?page=modules&modifier=ok');
    }
    if (!csrf_token_verify()) {
        abort(403, 'Jeton CSRF invalide.');
    }

    $r      = validate_module_edit($_POST);
    $errors = $r['errors'];
    $old    = $r['clean'];

    if (empty($errors) && is_module_code_taken_other($pdo, $old['code'], $id)) {
        $errors[] = 'Ce code est déjà utilisé par un autre module.';
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // If filière changed, sync inscriptions:
            //   1. Drop students no longer in the new filière
            //   2. Auto-enrol active students of the new filière
            if ($old['filiere'] !== $originalFiliere) {
                $del = $pdo->prepare(
                    'DELETE FROM inscriptions
                     WHERE module_id = :mid
                       AND etudiant_id NOT IN (
                           SELECT id FROM etudiants WHERE filieres = :fil
                       )'
                );
                $del->bindValue(':mid', $id,             PDO::PARAM_INT);
                $del->bindValue(':fil', $old['filiere'], PDO::PARAM_STR);
                $del->execute();

                $ins = $pdo->prepare(
                    'INSERT IGNORE INTO inscriptions (etudiant_id, module_id)
                     SELECT e.id, :mid
                     FROM etudiants e
                     WHERE e.filieres = :fil AND e.is_active = 1'
                );
                $ins->bindValue(':mid', $id,             PDO::PARAM_INT);
                $ins->bindValue(':fil', $old['filiere'], PDO::PARAM_STR);
                $ins->execute();
            }

            $up = $pdo->prepare(
                'UPDATE modules
                    SET code = :code, nom = :nom, description = :description,
                        credits = :credits, semestre = :semestre, filiere = :filiere
                  WHERE id = :id'
            );
            $up->bindValue(':code',        $old['code'],        PDO::PARAM_STR);
            $up->bindValue(':nom',         $old['nom'],         PDO::PARAM_STR);
            $up->bindValue(':description', $old['description'], PDO::PARAM_STR);
            $up->bindValue(':credits',     $old['credits'],     PDO::PARAM_INT);
            $up->bindValue(':semestre',    $old['semestre'],    PDO::PARAM_INT);
            $up->bindValue(':filiere',     $old['filiere'],     PDO::PARAM_STR);
            $up->bindValue(':id',          $id,                 PDO::PARAM_INT);
            $up->execute();

            $pdo->commit();
            csrf_token_renew();
            rate_limit_reset('edit_module');
            redirect('?page=modules&modifier=ok');
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('UPDATE module: ' . $e->getMessage());
            http_response_code(500);
            $errors[] = 'Une erreur est survenue lors de la mise à jour.';
        }
    }
}

// Inscription count (for the right-side info card).
$inscriptionCount = 0;
try {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM inscriptions WHERE module_id = :id');
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $inscriptionCount = (int)$stmt->fetchColumn();
} catch (PDOException $e) {
    error_log('inscription count: ' . $e->getMessage());
}

require __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <div>
        <h2>Modifier un module</h2>
        <div class="subtitle">Identifiant&nbsp;: #<?= e((string)$id) ?></div>
    </div>
</div>

<div class="row g-3">
    <div class="col-12 col-lg-8">
        <div class="card form-card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h5 class="mb-0"><i class="bi bi-pencil-square me-2 text-primary"></i>Informations du module</h5>
                <span class="badge-filiere">#<?= e((string)$id) ?></span>
            </div>
            <div class="card-body">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger" role="alert">
                        <strong><i class="bi bi-exclamation-triangle-fill me-2"></i>
                            Veuillez corriger les erreurs suivantes :
                        </strong>
                        <ul class="mb-0 mt-2">
                            <?php foreach ($errors as $err): ?>
                                <li><?= e($err) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="post" action="?page=modules_modifier&id=<?= e((string)$id) ?>"
                      autocomplete="off" novalidate>
                    <?= csrf_token_field() ?>
                    <?= honeypot_field() ?>
                    <input type="hidden" name="id" value="<?= e((string)$id) ?>">

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="code" class="form-label">Code</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-hash"></i></span>
                                <input type="text" id="code" name="code"
                                       class="form-control text-uppercase"
                                       maxlength="20" required
                                       value="<?= e((string)$old['code']) ?>">
                            </div>
                        </div>

                        <div class="col-md-8">
                            <label for="nom" class="form-label">Nom</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-book"></i></span>
                                <input type="text" id="nom" name="nom" class="form-control"
                                       maxlength="150" required
                                       value="<?= e((string)$old['nom']) ?>">
                            </div>
                        </div>

                        <div class="col-12">
                            <label for="description" class="form-label">Description</label>
                            <div class="input-group">
                                <span class="input-group-text align-items-start pt-2">
                                    <i class="bi bi-text-paragraph"></i>
                                </span>
                                <textarea id="description" name="description" class="form-control"
                                          rows="4" maxlength="1000"><?= e((string)$old['description']) ?></textarea>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <label for="credits" class="form-label">Crédits</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-award"></i></span>
                                <input type="number" id="credits" name="credits" class="form-control"
                                       min="1" max="10" required
                                       value="<?= e((string)$old['credits']) ?>">
                            </div>
                        </div>

                        <div class="col-md-4">
                            <label for="semestre" class="form-label">Semestre</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-calendar3"></i></span>
                                <input type="number" id="semestre" name="semestre" class="form-control"
                                       min="1" max="6" required
                                       value="<?= e((string)$old['semestre']) ?>">
                            </div>
                        </div>

                        <div class="col-md-4">
                            <label for="filiere" class="form-label">Filière</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-mortarboard"></i></span>
                                <input type="text" id="filiere" name="filiere" class="form-control"
                                       maxlength="100" required
                                       value="<?= e((string)$old['filiere']) ?>">
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-1"></i>Enregistrer
                        </button>
                        <a href="?page=modules" class="btn btn-outline-secondary">
                            <i class="bi bi-x-lg me-1"></i>Annuler
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-4">
        <div class="card h-100">
            <div class="card-header">
                <h5><i class="bi bi-people-fill me-2 text-primary"></i>Inscriptions</h5>
            </div>
            <div class="card-body">
                <p class="mb-3">
                    Ce module compte
                    <strong class="text-primary"><?= e((string)$inscriptionCount) ?></strong>
                    étudiant<?= $inscriptionCount > 1 ? 's' : '' ?> inscrit<?= $inscriptionCount > 1 ? 's' : '' ?>.
                </p>
                <div class="alert alert-warning small mb-0" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i>
                    Changer la filière supprimera les inscriptions des étudiants
                    qui n'en font plus partie et inscrira automatiquement les
                    étudiants actifs de la nouvelle filière.
                </div>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
