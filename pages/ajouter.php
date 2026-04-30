<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../includes/helpers.php';

$pageTitle = 'Ajouter un étudiant';
$pdo       = getPDO();
$errors    = [];
$old       = ['nom' => '', 'prenom' => '', 'email' => '', 'filieres' => ''];

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
            <h1>Ajouter un étudiant</h1>
        </div>

        <div class="form-card">
            <?php if (!empty($errors)): ?>
                <div class="error-list">
                    <ul>
                        <?php foreach ($errors as $err): ?>
                            <li><?= e($err) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="post" action="?page=ajouter" autocomplete="off">
                <?= csrf_token_field() ?>

                <div class="form-group">
                    <label for="nom">Nom</label>
                    <input type="text" id="nom" name="nom" maxlength="100" required
                           value="<?= e($old['nom']) ?>">
                </div>

                <div class="form-group">
                    <label for="prenom">Prénom</label>
                    <input type="text" id="prenom" name="prenom" maxlength="100" required
                           value="<?= e($old['prenom']) ?>">
                </div>

                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" maxlength="150" required
                           value="<?= e($old['email']) ?>">
                </div>

                <div class="form-group">
                    <label for="filieres">Filière</label>
                    <input type="text" id="filieres" name="filieres" maxlength="100" required
                           value="<?= e($old['filieres']) ?>">
                </div>

                <div style="display:flex;gap:0.75rem;margin-top:1.5rem">
                    <button type="submit" class="btn btn-primary">Ajouter</button>
                    <a href="?page=index" class="btn btn-secondary">Annuler</a>
                </div>
            </form>
        </div>
<?php
require __DIR__ . '/../includes/footer.php';
