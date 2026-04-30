<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../includes/helpers.php';

$pageTitle = 'Liste des étudiants';
$pdo       = getPDO();
$etudiants = [];
$dbError   = null;

// Fetch students; degrade gracefully on DB error
try {
    $stmt = $pdo->query("SELECT id, nom, prenom, email, filieres, created_at
                         FROM etudiants
                         ORDER BY nom, prenom");
    $etudiants = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('SELECT etudiants : ' . $e->getMessage());
    http_response_code(500);
    $dbError = "Une erreur est survenue lors du chargement des étudiants.";
}

require __DIR__ . '/../includes/header.php';
?>
        <div class="page-header">
            <h1>Liste des étudiants</h1>
            <a href="?page=ajouter" class="btn btn-primary">+ Ajouter un étudiant</a>
        </div>

        <?php // Flash messages from PRG redirects ?>
        <?php if (isset($_GET['ajout']) && $_GET['ajout'] === 'ok'): ?>
            <div class="flash-success">Étudiant ajouté avec succès.</div>
        <?php endif; ?>
        <?php if (isset($_GET['modifier']) && $_GET['modifier'] === 'ok'): ?>
            <div class="flash-success">Étudiant modifié avec succès.</div>
        <?php endif; ?>
        <?php if (isset($_GET['supprimer']) && $_GET['supprimer'] === 'ok'): ?>
            <div class="flash-success">Étudiant supprimé avec succès.</div>
        <?php endif; ?>

        <?php if ($dbError !== null): ?>
            <div class="flash-error"><?= e($dbError) ?></div>
        <?php elseif (!empty($etudiants)): ?>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Nom</th>
                        <th>Prénom</th>
                        <th>Email</th>
                        <th>Filière</th>
                        <th>Ajouté le</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($etudiants as $i => $etudiant): ?>
                        <tr>
                            <td><?= e((string)($i + 1)) ?></td>
                            <td><?= e($etudiant['nom']) ?></td>
                            <td><?= e($etudiant['prenom']) ?></td>
                            <td><?= e($etudiant['email']) ?></td>
                            <td><?= e($etudiant['filieres']) ?></td>
                            <td><?= e(date('d/m/Y', strtotime((string)$etudiant['created_at']))) ?></td>
                            <td>
                                <a href="?page=modifier&id=<?= e((string)$etudiant['id']) ?>" class="btn btn-primary">Modifier</a>
                                <a href="?page=supprimer&id=<?= e((string)$etudiant['id']) ?>" class="btn btn-danger">Supprimer</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="empty-state">Aucun étudiant enregistré pour le moment.</p>
        <?php endif; ?>
<?php
require __DIR__ . '/../includes/footer.php';
