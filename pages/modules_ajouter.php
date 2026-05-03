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

$pageTitle  = 'Ajouter un module';
$breadcrumb = 'Ajouter un module';
$pdo        = getPDO();

/**
 * Validate a posted module payload. Returns trimmed clean values plus
 * any user-facing errors. Caller still handles the UNIQUE-code check
 * because it requires a DB call.
 *
 * @param array $data Raw input ($_POST).
 * @return array{errors: string[], clean: array{code:string, nom:string, description:string, credits:int, semestre:int, filiere:string}}
 */
function validate_module(array $data): array {
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
 * True if the given module code already exists.
 *
 * @param PDO    $pdo
 * @param string $code
 * @return bool
 */
function is_module_code_taken(PDO $pdo, string $code): bool {
    try {
        $stmt = $pdo->prepare('SELECT 1 FROM modules WHERE code = :c LIMIT 1');
        $stmt->bindValue(':c', $code, PDO::PARAM_STR);
        $stmt->execute();
        return (bool)$stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log('is_module_code_taken: ' . $e->getMessage());
        return true; // fail-closed
    }
}

$errors = [];
$old    = ['code' => '', 'nom' => '', 'description' => '', 'credits' => 4, 'semestre' => 1, 'filiere' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Rate limit
    if (rate_limit_exceeded('add_module', 10, 60)) {
        abort(429, 'Trop de tentatives. Veuillez patienter 5 minutes.');
    }

    // 2. Honeypot — silent fake-success so bots don't learn they were caught.
    if (honeypot_triggered()) {
        error_log('Honeypot on modules_ajouter from ' . ($_SERVER['REMOTE_ADDR'] ?? '?'));
        redirect('?page=modules&ajout=ok');
    }

    // 3. CSRF
    if (!csrf_token_verify()) {
        abort(403, 'Jeton CSRF invalide.');
    }

    // 4. Validate
    $r       = validate_module($_POST);
    $errors  = $r['errors'];
    $old     = $r['clean'];

    // 5. Uniqueness
    if (empty($errors) && is_module_code_taken($pdo, $old['code'])) {
        $errors[] = 'Ce code est déjà utilisé par un autre module.';
    }

    // 6. Persist + auto-enrol students of that filière
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $ins = $pdo->prepare(
                'INSERT INTO modules (code, nom, description, credits, semestre, filiere)
                 VALUES (:code, :nom, :description, :credits, :semestre, :filiere)'
            );
            $ins->bindValue(':code',        $old['code'],        PDO::PARAM_STR);
            $ins->bindValue(':nom',         $old['nom'],         PDO::PARAM_STR);
            $ins->bindValue(':description', $old['description'], PDO::PARAM_STR);
            $ins->bindValue(':credits',     $old['credits'],     PDO::PARAM_INT);
            $ins->bindValue(':semestre',    $old['semestre'],    PDO::PARAM_INT);
            $ins->bindValue(':filiere',     $old['filiere'],     PDO::PARAM_STR);
            $ins->execute();

            $moduleId = (int)$pdo->lastInsertId();

            // Auto-enrol every active student of that filière. INSERT IGNORE
            // skips any rare race where the same admin double-submits.
            $enrol = $pdo->prepare(
                'INSERT IGNORE INTO inscriptions (etudiant_id, module_id)
                 SELECT e.id, :mid
                 FROM etudiants e
                 WHERE e.filieres = :fil AND e.is_active = 1'
            );
            $enrol->bindValue(':mid', $moduleId,      PDO::PARAM_INT);
            $enrol->bindValue(':fil', $old['filiere'], PDO::PARAM_STR);
            $enrol->execute();

            $pdo->commit();
            csrf_token_renew();
            rate_limit_reset('add_module');
            redirect('?page=modules&ajout=ok');
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('INSERT module: ' . $e->getMessage());
            http_response_code(500);
            $errors[] = "Une erreur est survenue lors de l'ajout.";
        }
    }
}

// Reference list shown in the right-side info card.
$filiereCounts = [];
try {
    $filiereCounts = $pdo->query(
        'SELECT filieres, COUNT(*) AS nb
         FROM etudiants WHERE is_active = 1
         GROUP BY filieres ORDER BY filieres'
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('filiere counts: ' . $e->getMessage());
}

require __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <div>
        <h2>Ajouter un module</h2>
        <div class="subtitle">Créez un nouveau module et inscrivez automatiquement la promo concernée.</div>
    </div>
</div>

<div class="row g-3">
    <!-- Form -->
    <div class="col-12 col-lg-8">
        <div class="card form-card">
            <div class="card-header">
                <h5><i class="bi bi-book me-2 text-primary"></i>Informations du module</h5>
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

                <form method="post" action="?page=modules_ajouter" autocomplete="off" novalidate>
                    <?= csrf_token_field() ?>
                    <?= honeypot_field() ?>

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="code" class="form-label">Code</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-hash"></i></span>
                                <input type="text" id="code" name="code"
                                       class="form-control text-uppercase"
                                       maxlength="20" required
                                       placeholder="Ex: INF101"
                                       value="<?= e((string)$old['code']) ?>">
                            </div>
                        </div>

                        <div class="col-md-8">
                            <label for="nom" class="form-label">Nom</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-book"></i></span>
                                <input type="text" id="nom" name="nom" class="form-control"
                                       maxlength="150" required
                                       placeholder="Ex: Algorithmique"
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
                                          rows="4" maxlength="1000"
                                          placeholder="Sujets abordés, prérequis, etc."><?= e((string)$old['description']) ?></textarea>
                            </div>
                            <div class="form-text">Optionnel — 1000 caractères max.</div>
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
                                       placeholder="Ex: SIIA"
                                       value="<?= e((string)$old['filiere']) ?>">
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check2 me-1"></i>Créer le module
                        </button>
                        <a href="?page=modules" class="btn btn-outline-secondary">
                            <i class="bi bi-x-lg me-1"></i>Annuler
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Info / reference -->
    <div class="col-12 col-lg-4">
        <div class="card h-100">
            <div class="card-header">
                <h5><i class="bi bi-info-circle-fill me-2 text-primary"></i>Inscription automatique</h5>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3">
                    Après l'ajout, <strong>tous les étudiants actifs</strong> de la
                    filière indiquée seront automatiquement inscrits à ce module.
                </p>
                <h6 class="small fw-semibold text-uppercase text-muted mb-2">Étudiants par filière</h6>
                <?php if (empty($filiereCounts)): ?>
                    <p class="text-muted small">Aucun étudiant actif.</p>
                <?php else: ?>
                    <ul class="list-unstyled mb-0">
                        <?php foreach ($filiereCounts as $fc): ?>
                            <li class="d-flex justify-content-between align-items-center py-1 border-bottom">
                                <span class="badge-filiere"><?= e((string)$fc['filieres']) ?></span>
                                <span class="text-muted small">
                                    <strong><?= e((string)$fc['nb']) ?></strong>
                                    étudiant<?= (int)$fc['nb'] > 1 ? 's' : '' ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
