<?php
// cette page doit :
// Récupérer l'étudiant à modifier à partir de son ID qui est envoyé par la page ajouter.php
// Afficher les informations dans un formulaire pré-rempli
// Enregister les modifications dans la base de données

session_start();
require_once 'connexion.php';
$pdo = connexion();

// Génération d'un jeton CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$erreurs = [];
$etudiant = null;

// Récupération sécurisée de l'ID
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1]
]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérification du jeton CSRF
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $erreurs[] = "Jeton CSRF invalide.";
    }

    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1]
    ]);
    if ($id === false || $id === null) {
        $erreurs[] = "Identifiant invalide.";
    }

    $nom      = trim($_POST['nom']      ?? '');
    $prenom   = trim($_POST['prenom']   ?? '');
    $email    = trim($_POST['email']    ?? '');
    $filieres = trim($_POST['filieres'] ?? '');

    // Validation des champs
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

    // Mise à jour si aucune erreur (requête préparée -> anti SQL injection)
    if (empty($erreurs)) {
        try {
            $sql = "UPDATE etudiants
                    SET nom = :nom, prenom = :prenom, email = :email, filieres = :filieres
                    WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':nom',      $nom,      PDO::PARAM_STR);
            $stmt->bindValue(':prenom',   $prenom,   PDO::PARAM_STR);
            $stmt->bindValue(':email',    $email,    PDO::PARAM_STR);
            $stmt->bindValue(':filieres', $filieres, PDO::PARAM_STR);
            $stmt->bindValue(':id',       $id,       PDO::PARAM_INT);
            $stmt->execute();

            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

            header('Location: index.php?modifier=ok');
            exit;
        } catch (PDOException $e) {
            error_log("Erreur UPDATE etudiant : " . $e->getMessage());
            $erreurs[] = "Une erreur est survenue lors de la mise à jour.";
        }
    }
}

// Récupération de l'étudiant pour pré-remplir le formulaire
if ($id !== false && $id !== null) {
    try {
        $stmt = $pdo->prepare("SELECT id, nom, prenom, email, filieres FROM etudiants WHERE id = :id");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $etudiant = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$etudiant) {
            $erreurs[] = "Aucun étudiant trouvé pour cet identifiant.";
        }
    } catch (PDOException $e) {
        error_log("Erreur SELECT etudiant : " . $e->getMessage());
        $erreurs[] = "Une erreur est survenue lors de la récupération des données.";
    }
} else {
    $erreurs[] = "Identifiant manquant ou invalide.";
}

// Helper d'échappement HTML
function e(?string $value): string {
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier un étudiant</title>
</head>
<body>
    <h1>Modifier un étudiant</h1>

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
        <form action="modifier.php?id=<?= e((string)$etudiant['id']) ?>" method="post" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="id" value="<?= e((string)$etudiant['id']) ?>">

            <label for="nom">Nom :</label>
            <input type="text" id="nom" name="nom" maxlength="100" required
                   value="<?= e($_POST['nom'] ?? $etudiant['nom']) ?>"><br><br>

            <label for="prenom">Prénom :</label>
            <input type="text" id="prenom" name="prenom" maxlength="100" required
                   value="<?= e($_POST['prenom'] ?? $etudiant['prenom']) ?>"><br><br>

            <label for="email">Email :</label>
            <input type="email" id="email" name="email" maxlength="150" required
                   value="<?= e($_POST['email'] ?? $etudiant['email']) ?>"><br><br>

            <label for="filieres">Filière :</label>
            <input type="text" id="filieres" name="filieres" maxlength="100" required
                   value="<?= e($_POST['filieres'] ?? $etudiant['filieres']) ?>"><br><br>

            <input type="submit" value="Enregistrer les modifications">
            <a href="index.php">Annuler</a>
        </form>
    <?php endif; ?>
</body>
</html>
