<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../includes/helpers.php';

$pageTitle = 'Modifier un étudiant';
$pdo       = getPDO();

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
    http_response_code(500);
    exit('Erreur interne du serveur.');
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
    // CSRF check
    if (!csrf_token_verify()) {
        http_response_code(403);
        exit('Jeton CSRF invalide.');
    }

    // Validate posted data
    $result = validate_student($_POST);
    $errors = $result['errors'];
    $old    = $result['clean'];

    // Duplicate email check (excluding current student)
    if (empty($errors) && is_email_taken($pdo, $old['email'], $id)) {
        $errors[] = "Cette adresse email est déjà utilisée par un autre étudiant.";
    }

    // Apply update
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
            <h1>Modifier un étudiant <small style="color:#6b7280;font-weight:400">(ID #<?= e((string)$id) ?>)</small></h1>
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

            <form method="post" action="?page=modifier&id=<?= e((string)$id) ?>" autocomplete="off">
                <?= csrf_token_field() ?>
                <input type="hidden" name="id" value="<?= e((string)$id) ?>">

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
                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                    <a href="?page=index" class="btn btn-secondary">Annuler</a>
                </div>
            </form>
        </div>
<?php
require __DIR__ . '/../includes/footer.php';
