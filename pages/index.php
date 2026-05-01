<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../includes/helpers.php';

$pageTitle  = 'Liste des étudiants';
$breadcrumb = 'Étudiants';
$pdo        = getPDO();
$etudiants  = [];
$dbError    = null;

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

/**
 * Build the avatar initials for a student (e.g. "El Amrani" + "Yassine" -> "EY").
 */
$initials = static function (string $nom, string $prenom): string {
    $first = mb_substr($nom, 0, 1);
    $last  = mb_substr($prenom, 0, 1);
    return mb_strtoupper($first . $last);
};

require __DIR__ . '/../includes/header.php';
?>
                <div class="page-header">
                    <div>
                        <h2>Liste des étudiants</h2>
                        <div class="subtitle">
                            <?= e((string)count($etudiants)) ?>
                            étudiant<?= count($etudiants) > 1 ? 's' : '' ?> enregistré<?= count($etudiants) > 1 ? 's' : '' ?>
                        </div>
                    </div>
                    <a href="?page=ajouter" class="btn btn-primary">
                        <i class="bi bi-person-plus"></i>
                        <span>Ajouter un étudiant</span>
                    </a>
                </div>

                <?php if ($dbError !== null): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i><?= e($dbError) ?>
                    </div>
                <?php elseif (!empty($etudiants)): ?>
                    <div class="card mb-3">
                        <div class="card-body py-3">
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-search"></i></span>
                                <input type="text"
                                       id="studentFilter"
                                       class="form-control"
                                       placeholder="Filtrer par nom, prénom, email ou filière…"
                                       autocomplete="off">
                            </div>
                        </div>
                    </div>

                    <div class="card table-card">
                        <table class="students-table">
                            <thead>
                                <tr>
                                    <th class="row-index">#</th>
                                    <th>Étudiant</th>
                                    <th>Email</th>
                                    <th>Filière</th>
                                    <th>Ajouté le</th>
                                    <th class="actions-cell">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($etudiants as $i => $etudiant): ?>
                                    <tr>
                                        <td class="row-index" data-label="#"><?= e((string)($i + 1)) ?></td>
                                        <td data-label="Étudiant">
                                            <div class="student-name-cell">
                                                <span class="student-avatar"><?= e($initials((string)$etudiant['nom'], (string)$etudiant['prenom'])) ?></span>
                                                <span><?= e($etudiant['nom']) ?> <?= e($etudiant['prenom']) ?></span>
                                            </div>
                                        </td>
                                        <td data-label="Email">
                                            <a href="mailto:<?= e($etudiant['email']) ?>" class="text-decoration-none">
                                                <?= e($etudiant['email']) ?>
                                            </a>
                                        </td>
                                        <td data-label="Filière">
                                            <span class="filiere-pill"><?= e($etudiant['filieres']) ?></span>
                                        </td>
                                        <td data-label="Ajouté le">
                                            <?= e(date('d/m/Y', strtotime((string)$etudiant['created_at']))) ?>
                                        </td>
                                        <td class="actions-cell" data-label="Actions">
                                            <a href="?page=modifier&id=<?= e((string)$etudiant['id']) ?>"
                                               class="btn btn-sm btn-outline-primary btn-icon"
                                               title="Modifier"
                                               aria-label="Modifier l'étudiant <?= e($etudiant['nom']) ?>">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <a href="?page=supprimer&id=<?= e((string)$etudiant['id']) ?>"
                                               class="btn btn-sm btn-outline-danger btn-icon"
                                               title="Supprimer"
                                               aria-label="Supprimer l'étudiant <?= e($etudiant['nom']) ?>">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <div id="filterEmpty" class="empty-state" hidden>
                            <div class="empty-icon"><i class="bi bi-search"></i></div>
                            <h3>Aucun résultat</h3>
                            <p>Aucun étudiant ne correspond à votre recherche.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card">
                        <div class="empty-state">
                            <div class="empty-icon"><i class="bi bi-people"></i></div>
                            <h3>Aucun étudiant enregistré</h3>
                            <p>Commencez par ajouter le premier étudiant à votre base.</p>
                            <a href="?page=ajouter" class="btn btn-primary">
                                <i class="bi bi-person-plus"></i>
                                <span>Ajouter un étudiant</span>
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
<?php
require __DIR__ . '/../includes/footer.php';
