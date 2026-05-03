<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/rate_limiter.php';
require_once __DIR__ . '/../../includes/etudiant_auth.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    configure_session();
    session_start();
}

// Already logged in? Skip to dashboard.
if (is_etudiant_authenticated()) {
    redirect('?page=etudiant/dashboard');
}

$pageTitle   = 'Connexion étudiant';
$error       = null;
$rateLimited = false;
$oldEmail    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Rate limit BEFORE anything else — 5 attempts/min/IP, 5-min lockout.
    if (rate_limit_exceeded('etudiant_login', 5, 60)) {
        $rateLimited = true;
        $error       = 'Trop de tentatives. Patientez 5 minutes.';
    }
    // 2. CSRF
    elseif (!csrf_token_verify()) {
        $error = 'Jeton CSRF invalide.';
    }
    // 3. Process form
    else {
        $email    = trim((string)($_POST['email']    ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $oldEmail = $email;

        // Generic validation — same error message in every failure mode.
        if ($email === '' || mb_strlen($email) > 150
            || !filter_var($email, FILTER_VALIDATE_EMAIL)
            || $password === ''
        ) {
            $error = 'Identifiants incorrects.';
        } elseif (login_etudiant($email, $password)) {
            rate_limit_reset('etudiant_login');
            redirect('?page=etudiant/dashboard');
        } else {
            error_log(sprintf(
                'Failed etudiant login [%s]: email=%s',
                $_SERVER['REMOTE_ADDR'] ?? '?',
                mb_substr($email, 0, 150)
            ));
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
<body class="login-body login-body--teal">
    <main class="login-shell">
        <div class="login-card">
            <div class="login-logo login-logo--teal">
                <i class="bi bi-mortarboard-fill"></i>
            </div>
            <h1 class="login-title">Espace étudiant</h1>
            <p class="login-subtitle">Connectez-vous avec votre adresse email</p>

            <?php if ($error !== null): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i><?= e($error) ?>
                </div>
            <?php endif; ?>

            <form method="post" action="?page=etudiant/login" autocomplete="on" novalidate>
                <?= csrf_token_field() ?>

                <div class="mb-3">
                    <label for="email" class="form-label">Adresse email</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                        <input type="email"
                               id="email"
                               name="email"
                               class="form-control"
                               maxlength="150"
                               required
                               autocomplete="email"
                               autofocus
                               value="<?= e($oldEmail) ?>"
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
                        class="btn btn-teal w-100 login-submit"
                        <?= $rateLimited ? 'disabled' : '' ?>>
                    <i class="bi bi-box-arrow-in-right me-1"></i>Se connecter
                </button>
            </form>

            <div class="login-footer-text">
                Espace administrateur :
                <a href="?page=login" class="login-other-link">se connecter en admin</a>
            </div>
        </div>
    </main>
</body>
</html>
