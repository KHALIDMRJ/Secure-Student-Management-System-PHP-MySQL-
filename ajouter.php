<?php
// cette page doit contenir un formulaire avec les champs suivants :
// nom
// prenom
// email
// filieres
// Après validation du formulaire, les données doivent etre insérées dans la table etudiants.
// l'ajout d'etudiant doit etre sécurisé contre l'injection sql

session_start();
require_once 'connexion.php';
$pdo = connexion();

// Génération d'un jeton CSRF (anti-CSRF)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$erreurs = [];

// Valeurs à réafficher en cas d'erreur
$old = ['nom' => '', 'prenom' => '', 'email' => '', 'filieres' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérification du jeton CSRF
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $erreurs[] = "Jeton CSRF invalide.";
    }

    $nom      = trim($_POST['nom']      ?? '');
    $prenom   = trim($_POST['prenom']   ?? '');
    $email    = trim($_POST['email']    ?? '');
    $filieres = trim($_POST['filieres'] ?? '');

    $old = compact('nom', 'prenom', 'email', 'filieres');

    // Validation côté serveur
    if ($nom === '' || mb_strlen($nom) > 100) {
        $erreurs[] = "Nom invalide (1 à 100 caractères).";
    } elseif (!preg_match("/^[\p{L}\s'-]+$/u", $nom)) {
        $erreurs[] = "Le nom contient des caractères non autorisés.";
    }

    if ($prenom === '' || mb_strlen($prenom) > 100) {
        $erreurs[] = "Prénom invalide (1 à 100 caractères).";
    } elseif (!preg_match("/^[\p{L}\s'-]+$/u", $prenom)) {
        $erreurs[] = "Le prénom contient des caractères non autorisés.";
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) {
        $erreurs[] = "Adresse email invalide.";
    }

    if ($filieres === '' || mb_strlen($filieres) > 100) {
        $erreurs[] = "Filière invalide (1 à 100 caractères).";
    }

    // Insertion sécurisée par requête préparée (anti SQL injection)
    if (empty($erreurs)) {
        try {
            $sql = "INSERT INTO etudiants (nom, prenom, email, filieres)
                    VALUES (:nom, :prenom, :email, :filieres)";
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':nom',      $nom,      PDO::PARAM_STR);
            $stmt->bindValue(':prenom',   $prenom,   PDO::PARAM_STR);
            $stmt->bindValue(':email',    $email,    PDO::PARAM_STR);
            $stmt->bindValue(':filieres', $filieres, PDO::PARAM_STR);
            $stmt->execute();

            // Régénération du jeton après une opération sensible
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

            // Redirection PRG (Post/Redirect/Get) pour éviter la double soumission
            header('Location: index.php?ajout=ok');
            exit;
        } catch (PDOException $e) {
            error_log("Erreur INSERT etudiant : " . $e->getMessage());
            $erreurs[] = "Une erreur est survenue lors de l'ajout.";
        }
    }
}

// Helper d'échappement HTML (anti-XSS)
function e(?string $value): string {
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter un étudiant</title>
</head>
<body>
    <h1>Ajouter un étudiant</h1>

    <?php if (!empty($erreurs)): ?>
        <div style="color:red;">
            <ul>
                <?php foreach ($erreurs as $err): ?>
                    <li><?= e($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form action="ajouter.php" method="post" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">

        <label for="nom">Nom :</label>
        <input type="text" id="nom" name="nom" maxlength="100" required
               value="<?= e($old['nom']) ?>"><br><br>

        <label for="prenom">Prénom :</label>
        <input type="text" id="prenom" name="prenom" maxlength="100" required
               value="<?= e($old['prenom']) ?>"><br><br>

        <label for="email">Email :</label>
        <input type="email" id="email" name="email" maxlength="150" required
               value="<?= e($old['email']) ?>"><br><br>

        <label for="filieres">Filière :</label>
        <input type="text" id="filieres" name="filieres" maxlength="100" required
               value="<?= e($old['filieres']) ?>"><br><br>

        <input type="submit" value="Ajouter">
        <a href="index.php">Retour</a>
    </form>
</body>
</html>
