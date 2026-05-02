<?php
/**
 * Shared HTML head + sidebar/topbar shell.
 * The including page MUST set $pageTitle (string) before the include.
 * Optionally, it may set $breadcrumb (string|null) for the topbar trail.
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/helpers.php';

$pageTitle  = $pageTitle  ?? '';
$breadcrumb = $breadcrumb ?? null;

// Collect flash messages from PRG redirects so the JS can render them as toasts.
$flashes = [];
if (isset($_GET['ajout'])     && $_GET['ajout']     === 'ok') $flashes[] = ['type' => 'success', 'msg' => 'Étudiant ajouté avec succès.'];
if (isset($_GET['modifier'])  && $_GET['modifier']  === 'ok') $flashes[] = ['type' => 'success', 'msg' => 'Étudiant modifié avec succès.'];
if (isset($_GET['supprimer']) && $_GET['supprimer'] === 'ok') $flashes[] = ['type' => 'success', 'msg' => 'Étudiant supprimé avec succès.'];
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
<body data-flashes='<?= e(json_encode($flashes, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>'>

    <div class="app-wrapper">
        <aside class="sidebar" id="sidebar" aria-label="Navigation principale">
            <div class="sidebar-brand">
                <span class="brand-icon"><i class="bi bi-mortarboard-fill"></i></span>
                <span class="brand-name"><?= e(APP_NAME) ?></span>
            </div>
            <ul class="sidebar-menu">
                <li>
                    <a class="<?= e(active_page('dashboard')) ?>" href="?page=dashboard">
                        <i class="bi bi-speedometer2"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li>
                    <a class="<?= e(active_page('index')) ?>" href="?page=index">
                        <i class="bi bi-people-fill"></i>
                        <span>Étudiants</span>
                    </a>
                </li>
                <li>
                    <a class="<?= e(active_page('ajouter')) ?>" href="?page=ajouter">
                        <i class="bi bi-person-plus-fill"></i>
                        <span>Ajouter</span>
                    </a>
                </li>
                <li>
                    <a class="<?= e(active_page('sql')) ?>" href="?page=sql">
                        <i class="bi bi-terminal-fill"></i>
                        <span>SQL Runner</span>
                    </a>
                </li>
            </ul>
            <div class="sidebar-footer">
                &copy; 2025 Khalid MORJANE
            </div>
        </aside>

        <div class="sidebar-backdrop" id="sidebarBackdrop" aria-hidden="true"></div>

        <div class="main-content">
            <header class="top-bar">
    <div class="top-bar-left">
        <button type="button"
                class="btn btn-outline-secondary btn-icon d-lg-none me-2"
                id="sidebarToggle"
                aria-label="Ouvrir le menu"
                aria-controls="sidebar"
                aria-expanded="false">
            <i class="bi bi-list"></i>
        </button>
        <div>
            <h1><?= e($pageTitle) ?></h1>
            <?php if ($breadcrumb !== null): ?>
                <nav aria-label="Fil d'Ariane">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item">
                            <a href="?page=index">Accueil</a>
                        </li>
                        <li class="breadcrumb-item active" aria-current="page">
                            <?= e($breadcrumb) ?>
                        </li>
                    </ol>
                </nav>
            <?php endif; ?>
        </div>
    </div>
    <div class="top-bar-right">
        <button id="darkModeToggle"
                class="btn btn-sm btn-outline-secondary"
                title="Changer le thème"
                aria-label="Changer le thème">
            <i class="bi bi-moon-stars-fill" id="darkModeIcon"></i>
        </button>
    </div>
</header>

            <main class="content-area">
