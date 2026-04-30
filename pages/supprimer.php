<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../includes/helpers.php';

$pageTitle = 'Supprimer un étudiant';
$pdo       = getPDO();

// Resolve the student ID from POST (preferred) or GET
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($id === false || $id === null) {
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
}
if ($id === false || $id === null) {
    redirect('?page=index');
}

// Load the target student so we can show details (and bail if it does not exist)
try {
    $stmt = $pdo->prepare('SELECT id, nom, prenom, email, filieres
                           FROM etudiants
                           WHERE id = :id');
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $etudiant = $stmt->fetch();
} catch (PDOException $e) {
    error_log('SELECT etudiant for delete : ' . $e->getMessage());
    http_response_code(500);
    exit('Erreur interne du serveur.');
}

if (!$etudiant) {
    redirect('?page=index');
}

// On POST: verify CSRF then delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_token_verify()) {
        http_response_code(403);
        exit('Jeton CSRF invalide.');
    }

    try {
        $stmt = $pdo->prepare('DELETE FROM etudiants WHERE id = :id');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        csrf_token_renew();
        redirect('?page=index&supprimer=ok');
    } catch (PDOException $e) {
        error_log('DELETE etudiant : ' . $e->getMessage());
        http_response_code(500);
        exit('Erreur interne du serveur.');
    }
}

require __DIR__ . '/../includes/header.php';
?>
        <div class="page-header">
            <h1>Confirmer la suppression</h1>
        </div>

        <div class="confirmation-box">
            <p>Vous êtes sur le point de supprimer l'étudiant suivant :</p>
            <dl>
                <dt>Nom</dt>     <dd><?= e($etudiant['nom']) ?> <?= e($etudiant['prenom']) ?></dd>
                <dt>Email</dt>   <dd><?= e($etudiant['email']) ?></dd>
                <dt>Filière</dt> <dd><?= e($etudiant['filieres']) ?></dd>
            </dl>
            <form method="post" action="?page=supprimer&id=<?= e((string)$etudiant['id']) ?>">
                <?= csrf_token_field() ?>
                <input type="hidden" name="id" value="<?= e((string)$etudiant['id']) ?>">
                <div style="display:flex;gap:0.75rem;margin-top:1.5rem">
                    <button type="submit" class="btn btn-danger">Confirmer la suppression</button>
                    <a href="?page=index" class="btn btn-secondary">Annuler</a>
                </div>
            </form>
        </div>
<?php
require __DIR__ . '/../includes/footer.php';
