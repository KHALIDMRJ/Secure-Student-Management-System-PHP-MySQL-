<?php
// cette page doit :
// Supprimer l'étudiant à partir de son ID
// Rediriger l'utilisateur vers la page index.php

session_start();
require_once 'connexion.php';
$pdo = connexion();

// Génération d'un jeton CSRF : c'est une mesure de sécurité pour prévenir les attaques CSRF c'est a dire que
// chaque page protegée doit avoir un jeton CSRF et chaque formulaire doit avoir un jeton CSRF  
// le jeton CSRF est un code généré aléatoirement qui est stocké dans la session et envoyé avec le formulaire
// si le jeton CSRF n'est pas le même que celui de la session, alors la requête est rejetée
// et l'utilisateur est redirigé vers la page index.php
// le jeton CSRF est généré à l'aide de la fonction bin2hex(random_bytes(32));
// la fonction hash_equals() est utilisée pour comparer les deux jetons CSRF de manière sécurisée
// cette fonction est plus sécurisée que la simple comparaison avec ==  car elle est insensible aux attaques par temporisation (timing attacks)
// et elle est recommandée pour comparer les jetons CSRF  pour éviter les attaques par temporisation comme 
// l'attaque par temporisation (timing attack) est une attaque qui consiste à mesurer le temps d'exécution d'une requête pour déterminer si le jeton CSRF est correct
// cette attaque est possible car la fonction == compare les jetons CSRF de manière séquentielle
// et la fonction hash_equals() compare les jetons CSRF de manière aléatoire
// il est important de régénérer le jeton CSRF après chaque opération sensible comme la suppression ou la mise à jour
// une autre mesure de sécurité est de régénérer le jeton CSRF après chaque opération sensible comme la suppression ou la mise à jour 

// la fonction random_bytes() génère des octets aléatoires cryptographiquement sécurisés
// la fonction bin2hex() convertit les octets aléatoires en une chaîne hexadécimale
// la fonction hash_equals() compare les deux chaînes hexadécimales de manière sécurisée
// la fonction htmlspecialchars() convertit les caractères spéciaux en entités HTML
// la fonction filter_var() filtre une variable avec un filtre ou un ensemble de filtres
// la fonction bindValue() lie une valeur à un paramètre de requête préparée
// la fonction execute() exécute une requête préparée
// la fonction fetch() récupère la ligne suivante d'un jeu de résultats
// la fonction fetchAll() récupère toutes les lignes d'un jeu de résultats

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$erreurs = [];
$etudiant = null;

// ID en GET (page de confirmation) ou POST (suppression effective)
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

// Suppression effective (POST uniquement)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $erreurs[] = "Jeton CSRF invalide.";
    }

    if (empty($erreurs)) {
        try {
            $stmt = $pdo->prepare("DELETE FROM etudiants WHERE id = :id");
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            // Régénération du jeton après une opération sensible
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

            header('Location: index.php?supprimer=ok');
            exit;
        } catch (PDOException $e) {
            error_log("Erreur DELETE etudiant : " . $e->getMessage());
            $erreurs[] = "Une erreur est survenue lors de la suppression.";
        }
    }
}

// Récupération de l'étudiant pour la page de confirmation
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
