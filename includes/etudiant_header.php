<?php
/**
 * Student layout shell — horizontal top-nav (no sidebar).
 * Different visual identity from the admin shell: teal accent, single
 * brand bar, three nav items. Page sets $pageTitle before include.
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/etudiant_auth.php';

$pageTitle = $pageTitle ?? '';
$__et      = current_etudiant();
$__display = $__et !== null
    ? ($__et['full_name'] !== '' ? $__et['full_name'] : $__et['email'])
    : '';
$__initial = $__display !== '' ? mb_strtoupper(mb_substr($__display, 0, 1)) : '?';

/**
 * Tiny helper: return 'active' when the current ?page matches one of
 * the supplied routes (etudiant nav has nested routes like 'etudiant/...').
 *
 * @param string ...$routes Routes to test.
 * @return string 'active' or ''.
 */
function et_nav_active(string ...$routes): string {
    $current = $_GET['page'] ?? '';
    return in_array($current, $routes, true) ? 'active' : '';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(APP_NAME) ?> — <?= e($pageTitle) ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/SecureStudentMS/public/css/style.css">
</head>
<body class="etudiant-body">

    <header class="etudiant-topbar">
        <div class="etudiant-brand">
            <span class="etudiant-brand-icon"><i class="bi bi-mortarboard-fill"></i></span>
            <span class="etudiant-brand-name"><?= e(APP_NAME) ?></span>
        </div>

        <nav class="etudiant-nav" aria-label="Navigation étudiant">
            <a href="?page=etudiant/dashboard"
               class="<?= e(et_nav_active('etudiant/dashboard')) ?>">
                <i class="bi bi-speedometer2"></i>
                <span>Tableau</span>
            </a>
            <a href="?page=etudiant/modules"
               class="<?= e(et_nav_active('etudiant/modules')) ?>">
                <i class="bi bi-collection-fill"></i>
                <span>Mes modules</span>
            </a>
            <a href="?page=etudiant/profil"
               class="<?= e(et_nav_active('etudiant/profil')) ?>">
                <i class="bi bi-person-circle"></i>
                <span>Profil</span>
            </a>
        </nav>

        <div class="etudiant-actions">
            <?php if ($__et !== null): ?>
                <div class="user-chip user-chip--teal" title="<?= e($__display) ?>">
                    <span class="user-avatar user-avatar--teal"><?= e($__initial) ?></span>
                    <span class="user-name d-none d-md-inline"><?= e($__display) ?></span>
                </div>
                <form method="post" action="?page=etudiant/logout" class="d-inline-block">
                    <?= csrf_token_field() ?>
                    <button type="submit"
                            class="btn btn-sm btn-outline-secondary"
                            title="Déconnexion"
                            aria-label="Déconnexion">
                        <i class="bi bi-box-arrow-right"></i>
                    </button>
                </form>
            <?php endif; ?>
            <button id="darkModeToggle"
                    class="btn btn-sm btn-outline-secondary"
                    title="Changer le thème"
                    aria-label="Changer le thème">
                <i class="bi bi-moon-stars-fill" id="darkModeIcon"></i>
            </button>
        </div>
    </header>

    <main class="etudiant-content">
