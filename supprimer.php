<?php
// cette page doit :
// Supprimer l'étudiant à partir de son ID
// Rediriger l'utilisateur vers la page index.php

session_start();
require_once 'connexion.php';
$pdo = connexion();


if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$erreurs = [];
$etudiant = null;

$id = filter_input(
    $_SERVER['REQUEST_METHOD'] === 'POST' ? INPUT_POST : INPUT_GET,
    'id',
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if ($id === false || $id === null) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $erreurs[] = "Jeton CSRF invalide.";
    }

    if (empty($erreurs)) {
        try {
            $stmt = $pdo->prepare("DELETE FROM etudiants WHERE id = :id");
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

            header('Location: index.php?supprimer=ok');
            exit;
        } catch (PDOException $e) {
            error_log("Erreur DELETE etudiant : " . $e->getMessage());
            $erreurs[] = "Une erreur est survenue lors de la suppression.";
        }
    }
}

try {
    $stmt = $pdo->prepare("SELECT id, nom, prenom, email, filieres
                            FROM etudiants
                            WHERE id = :id");
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $etudiant = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$etudiant) {
        header('Location: index.php');
        exit;
    }
} catch (PDOException $e) {
    error_log("Erreur SELECT etudiant : " . $e->getMessage());
    $erreurs[] = "Une erreur est survenue lors de la récupération des données.";
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
    <title>Supprimer un étudiant</title>
</head>
<body>
    <h1>Supprimer un étudiant</h1>

    <?php if (!empty($erreurs)): ?>
        <div style="color:red;">
            <ul>
                <?php foreach ($erreurs as $err): ?>
                    <li><?= e($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($etudiant): ?>
        <p>Êtes-vous sûr de vouloir supprimer l'étudiant suivant ?</p>
        <ul>
            <li><strong>ID :</strong> <?= e((string)$etudiant['id']) ?></li>
            <li><strong>Nom :</strong> <?= e($etudiant['nom']) ?></li>
            <li><strong>Prénom :</strong> <?= e($etudiant['prenom']) ?></li>
            <li><strong>Email :</strong> <?= e($etudiant['email']) ?></li>
            <li><strong>Filière :</strong> <?= e($etudiant['filieres']) ?></li>
        </ul>

        <form action="supprimer.php" method="post">
            <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="id" value="<?= e((string)$etudiant['id']) ?>">
            <input type="submit" value="Confirmer la suppression">
            <a href="index.php">Annuler</a>
        </form>
    <?php endif; ?>
</body>
</html>
