<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../includes/helpers.php';

$pageTitle  = 'Supprimer un étudiant';
$breadcrumb = 'Supprimer';
$pdo        = getPDO();

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
                    <div>
                        <h2>Confirmer la suppression</h2>
                        <div class="subtitle">Cette action est définitive.</div>
                    </div>
                </div>

                <div class="card confirmation-card">
                    <div class="card-header">
                        <h5><i class="bi bi-exclamation-triangle-fill me-2 text-danger"></i>Suppression d'un étudiant</h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-warning" role="alert">
                            Vous êtes sur le point de supprimer définitivement l'étudiant suivant.
                            Cette opération ne peut pas être annulée.
                        </div>

                        <ul class="detail-list">
                            <li>
                                <span class="label">Nom complet</span>
                                <span class="value"><?= e($etudiant['nom']) ?> <?= e($etudiant['prenom']) ?></span>
                            </li>
                            <li>
                                <span class="label">Email</span>
                                <span class="value"><?= e($etudiant['email']) ?></span>
                            </li>
                            <li>
                                <span class="label">Filière</span>
                                <span class="value"><?= e($etudiant['filieres']) ?></span>
                            </li>
                        </ul>

                        <form method="post"
                              action="?page=supprimer&id=<?= e((string)$etudiant['id']) ?>"
                              data-confirm="Êtes-vous certain de vouloir supprimer cet étudiant ?">
                            <?= csrf_token_field() ?>
                            <input type="hidden" name="id" value="<?= e((string)$etudiant['id']) ?>">
                            <div class="form-actions">
                                <button type="submit" class="btn btn-danger">
                                    <i class="bi bi-trash"></i>
                                    <span>Confirmer la suppression</span>
                                </button>
                                <a href="?page=index" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-left"></i>
                                    <span>Annuler</span>
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
<?php
require __DIR__ . '/../includes/footer.php';
