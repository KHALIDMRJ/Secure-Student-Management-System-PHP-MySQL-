<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/rate_limiter.php';
require_once __DIR__ . '/../includes/auth.php';

// Session bootstrap — defensive, in case the front controller didn't.
if (session_status() !== PHP_SESSION_ACTIVE) {
    configure_session();
    session_start();
}

// Already logged in? Skip straight to the dashboard.
if (is_authenticated()) {
    redirect('?page=dashboard');
}

$pageTitle    = 'Connexion';
$error        = null;
$rateLimited  = false;
$oldUsername  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Rate limit BEFORE anything else — 5 attempts/min/IP, 5-min block.
    if (rate_limit_exceeded('login', 5, 60)) {
        $rateLimited = true;
        $error       = 'Trop de tentatives. Patientez 5 minutes.';
    }

    // 2. CSRF
    elseif (!csrf_token_verify()) {
        $error = 'Jeton CSRF invalide.';
    }

    // 3. Process form
    else {
        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $oldUsername = $username;

        // Field validation — generic error message either way.
        if ($username === '' || mb_strlen($username) > 60 || $password === '') {
            $error = 'Identifiants incorrects.';
        } elseif (login_admin($username, $password)) {
            // Reset rate-limit counter so a returning user isn't penalised.
            rate_limit_reset('login');
            redirect('?page=dashboard');
        } else {
            error_log(sprintf(
                'Failed login [%s]: username=%s',
                $_SERVER['REMOTE_ADDR'] ?? '?',
                mb_substr($username, 0, 60)
            ));
            // Generic — never reveal whether the username exists.
            $error = 'Identifiants incorrects.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> — <?= e(APP_NAME) ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/SecureStudentMS/public/css/style.css">
</head>
<body class="login-body">
    <main class="login-shell">
        <div class="login-card">

            <!-- Brand mark — anchors the page; subtle scale on load -->
            <div class="login-logo">
                <i class="bi bi-mortarboard-fill"></i>
            </div>
            <h1 class="login-title"><?= e(APP_NAME) ?></h1>
            <p class="login-subtitle">Connectez-vous pour accéder à l'administration</p>

            <?php if ($error !== null): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i><?= e($error) ?>
                </div>
            <?php endif; ?>

            <form method="post" action="?page=login" autocomplete="on" novalidate>
                <?= csrf_token_field() ?>

                <div class="mb-3">
                    <label for="username" class="form-label">Nom d'utilisateur</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-person"></i></span>
                        <input type="text"
                               id="username"
                               name="username"
                               class="form-control"
                               maxlength="60"
                               required
                               autocomplete="username"
                               autofocus
                               value="<?= e($oldUsername) ?>"
                               <?= $rateLimited ? 'disabled' : '' ?>>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">Mot de passe</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-key"></i></span>
                        <input type="password"
                               id="password"
                               name="password"
                               class="form-control"
                               required
                               autocomplete="current-password"
                               <?= $rateLimited ? 'disabled' : '' ?>>
                    </div>
                </div>

                <button type="submit"
                        class="btn btn-primary w-100 login-submit"
                        <?= $rateLimited ? 'disabled' : '' ?>>
                    <i class="bi bi-box-arrow-in-right me-1"></i>Se connecter
                </button>
            </form>

            <div class="login-footer-text">
                &copy; 2025 <?= e(APP_NAME) ?>
            </div>
        </div>
    </main>
</body>
</html>
