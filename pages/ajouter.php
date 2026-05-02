<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_auth();

$pageTitle  = 'Ajouter un étudiant';
$breadcrumb = 'Ajouter';
$pdo        = getPDO();
$errors     = [];
$old        = ['nom' => '', 'prenom' => '', 'email' => '', 'filieres' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check
    if (!csrf_token_verify()) {
        http_response_code(403);
        exit('Jeton CSRF invalide.');
    }

    // Validate inputs
    $result = validate_student($_POST);
    $errors = $result['errors'];
    $old    = $result['clean'];

    // Duplicate email check
    if (empty($errors) && is_email_taken($pdo, $old['email'])) {
        $errors[] = "Cette adresse email est déjà utilisée.";
    }

    // Persist if everything is clean
    if (empty($errors)) {
        try {
            $sql  = 'INSERT INTO etudiants (nom, prenom, email, filieres)
                     VALUES (:nom, :prenom, :email, :filieres)';
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':nom',      $old['nom'],      PDO::PARAM_STR);
            $stmt->bindValue(':prenom',   $old['prenom'],   PDO::PARAM_STR);
            $stmt->bindValue(':email',    $old['email'],    PDO::PARAM_STR);
            $stmt->bindValue(':filieres', $old['filieres'], PDO::PARAM_STR);
            $stmt->execute();

            csrf_token_renew();
            redirect('?page=index&ajout=ok');
        } catch (PDOException $e) {
            error_log('INSERT etudiant : ' . $e->getMessage());
            http_response_code(500);
            $errors[] = "Une erreur est survenue lors de l'ajout.";
        }
    }
}

require __DIR__ . '/../includes/header.php';
?>
                <div class="page-header">
                    <div>
                        <h2>Ajouter un étudiant</h2>
                        <div class="subtitle">Renseignez les informations du nouvel étudiant.</div>
                    </div>
                </div>

                <div class="card form-card">
                    <div class="card-header">
                        <h5><i class="bi bi-person-vcard me-2 text-primary"></i>Informations de l'étudiant</h5>
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

                        <form method="post" action="?page=ajouter" autocomplete="off" novalidate>
                            <?= csrf_token_field() ?>

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
                                    <div class="form-text">L'adresse email doit être unique.</div>
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
                                    <i class="bi bi-check2"></i>
                                    <span>Ajouter l'étudiant</span>
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
