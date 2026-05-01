<?php
<<<<<<< HEAD

require_once 'connexion.php';
$pdo = connexion();

try {
    $stmt = $pdo->query("SELECT id, nom, prenom, email, filieres
                         FROM etudiants
                         ORDER BY nom, prenom");
    $etudiants = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erreur SELECT etudiants : " . $e->getMessage());
    die("Erreur de récupération des étudiants.");
}

function e(?string $value): string {
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Liste des étudiants</title>
</head>
<body>
    <h1>Liste des étudiants</h1>

    <?php if (isset($_GET['ajout']) && $_GET['ajout'] === 'ok'): ?>
        <div style="color:green;">Étudiant ajouté avec succès.</div>
    <?php endif; ?>

    <?php if (isset($_GET['modifier']) && $_GET['modifier'] === 'ok'): ?>
        <div style="color:green;">Étudiant modifié avec succès.</div>
    <?php endif; ?>

    <?php if (isset($_GET['supprimer']) && $_GET['supprimer'] === 'ok'): ?>
        <div style="color:green;">Étudiant supprimé avec succès.</div>
    <?php endif; ?>

    <?php if (!empty($etudiants)): ?>
        <table border="1" cellpadding="5" cellspacing="0">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nom</th>
                    <th>Prénom</th>
                    <th>Email</th>
                    <th>Filière</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($etudiants as $etudiant): ?>
                <tr>
                    <td><?= e((string)$etudiant['id']) ?></td>
                    <td><?= e($etudiant['nom']) ?></td>
                    <td><?= e($etudiant['prenom']) ?></td>
                    <td><?= e($etudiant['email']) ?></td>
                    <td><?= e($etudiant['filieres']) ?></td>
                    <td>
                        <a href="modifier.php?id=<?= e((string)$etudiant['id']) ?>">Modifier</a> |
                        <a href="supprimer.php?id=<?= e((string)$etudiant['id']) ?>" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet étudiant ?');">Supprimer</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>Aucun étudiant enregistré.</p>
    <?php endif; ?>

    <br>
    <a href="ajouter.php">Ajouter un nouvel étudiant</a>

</body>
</html>
=======
header('Location: /SecureStudentMS/public/');
exit;
>>>>>>> 7a57ca4 (Phase 3: add Bootstrap UI with sidebar, dark mode, search and toasts)
