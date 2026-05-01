<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/rate_limiter.php';

// Session must be configured BEFORE session_start().
configure_session();
session_start();
prevent_session_fixation();
validate_session_integrity();

$pageTitle  = 'Modifier un étudiant';
$breadcrumb = 'Modifier';
$pdo        = getPDO();

// Resolve the student ID from POST (preferred) or GET
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($id === false || $id === null) {
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
}
if ($id === false || $id === null) {
    redirect('?page=index');
}

// Load the student record
try {
    $stmt = $pdo->prepare('SELECT id, nom, prenom, email, filieres
                           FROM etudiants
                           WHERE id = :id');
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $etudiant = $stmt->fetch();
} catch (PDOException $e) {
    error_log('SELECT etudiant by id : ' . $e->getMessage());
    abort(500);
}

if (!$etudiant) {
    redirect('?page=index');
}

// Initial form state = current DB values
$errors = [];
$old = [
    'nom'      => (string)$etudiant['nom'],
    'prenom'   => (string)$etudiant['prenom'],
    'email'    => (string)$etudiant['email'],
    'filieres' => (string)$etudiant['filieres'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Rate limiting
    if (rate_limit_exceeded('edit_student', RATE_LIMIT_EDIT, RATE_LIMIT_WINDOW)) {
        abort(429, 'Trop de tentatives. Veuillez patienter 5 minutes.');
    }

    // 2. Honeypot — silent fake success
    if (honeypot_triggered()) {
        error_log('Honeypot triggered on modifier.php from ' . ($_SERVER['REMOTE_ADDR'] ?? ''));
        redirect('?page=index&modifier=ok');
    }

    // 3. CSRF
    if (!csrf_token_verify()) {
        abort(403, 'Jeton CSRF invalide.');
    }

    // 4. Validate posted data
    $result = validate_student($_POST);
    $errors = $result['errors'];
    $old    = $result['clean'];

    // 5. Duplicate email check (excluding current student)
    if (empty($errors) && is_email_taken($pdo, $old['email'], $id)) {
        $errors[] = "Cette adresse email est déjà utilisée par un autre étudiant.";
    }

    // 6. Apply update
    if (empty($errors)) {
        try {
            $sql = 'UPDATE etudiants
                    SET nom = :nom, prenom = :prenom, email = :email, filieres = :filieres
                    WHERE id = :id';
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':nom',      $old['nom'],      PDO::PARAM_STR);
            $stmt->bindValue(':prenom',   $old['prenom'],   PDO::PARAM_STR);
            $stmt->bindValue(':email',    $old['email'],    PDO::PARAM_STR);
            $stmt->bindValue(':filieres', $old['filieres'], PDO::PARAM_STR);
            $stmt->bindValue(':id',       $id,              PDO::PARAM_INT);
            $stmt->execute();

            csrf_token_renew();
            rate_limit_reset('edit_student');
            redirect('?page=index&modifier=ok');
        } catch (PDOException $e) {
            error_log('UPDATE etudiant : ' . $e->getMessage());
            http_response_code(500);
            $errors[] = "Une erreur est survenue lors de la mise à jour.";
        }
    }
}

require __DIR__ . '/../includes/header.php';
?>
                <div class="page-header">
                    <div>
                        <h2>Modifier un étudiant</h2>
                        <div class="subtitle">Identifiant&nbsp;: #<?= e((string)$id) ?></div>
                    </div>
                </div>

                <div class="card form-card">
                    <div class="card-header">
                        <h5><i class="bi bi-pencil-square me-2 text-primary"></i>Informations de l'étudiant</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger" role="alert">
                                <strong><i class="bi bi-exclamation-triangle-fill me-2"></i>Veuillez corriger les erreurs suivantes :</strong>
                                <ul>
                                    <?php foreach ($errors as $err): ?>
                                        <li><?= e($err) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <form method="post" action="?page=modifier&id=<?= e((string)$id) ?>" autocomplete="off" novalidate>
                            <?= csrf_token_field() ?>
                            <?= honeypot_field() ?>
                            <input type="hidden" name="id" value="<?= e((string)$id) ?>">

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="nom" class="form-label">Nom</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-person"></i></span>
                                        <input type="text" id="nom" name="nom" class="form-control"
                                               maxlength="100" required value="<?= e($old['nom']) ?>">
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label for="prenom" class="form-label">Prénom</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-person"></i></span>
                                        <input type="text" id="prenom" name="prenom" class="form-control"
                                               maxlength="100" required value="<?= e($old['prenom']) ?>">
                                    </div>
                                </div>

                                <div class="col-12">
                                    <label for="email" class="form-label">Email</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                        <input type="email" id="email" name="email" class="form-control"
                                               maxlength="150" required value="<?= e($old['email']) ?>">
                                    </div>
                                </div>

                                <div class="col-12">
                                    <label for="filieres" class="form-label">Filière</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-mortarboard"></i></span>
                                        <input type="text" id="filieres" name="filieres" class="form-control"
                                               maxlength="100" required value="<?= e($old['filieres']) ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save"></i>
                                    <span>Enregistrer les modifications</span>
                                </button>
                                <a href="?page=index" class="btn btn-outline-secondary">
                                    <i class="bi bi-x-lg"></i>
                                    <span>Annuler</span>
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
<?php
require __DIR__ . '/../includes/footer.php';
