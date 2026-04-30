<?php
/**
 * Shared HTML head + navbar partial.
 * The including page MUST set $pageTitle (string) before the include.
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/helpers.php';

$pageTitle = $pageTitle ?? '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(APP_NAME) ?> — <?= e($pageTitle) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap">
    <link rel="stylesheet" href="<?= e(BASE_URL) ?>css/style.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', sans-serif;
            background: #f5f7fa;
            color: #1a1a2e;
        }
        nav {
            background: #ffffff;
            border-bottom: 1px solid #e5e7eb;
            padding: 0.75rem 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        nav .brand {
            font-weight: 600;
            font-size: 1rem;
            color: #1a1a2e;
            text-decoration: none;
        }
        nav .links { display: flex; gap: 0.25rem; }
        nav a {
            text-decoration: none;
            color: #1a1a2e;
            font-weight: 500;
            padding: 0.5rem 1rem;
            border-bottom: 2px solid transparent;
        }
        nav a.active {
            color: #4f46e5;
            border-bottom: 2px solid #4f46e5;
        }
        main {
            max-width: 1100px;
            margin: 2rem auto;
            padding: 0 1.5rem;
        }
        .flash-success,
        .flash-error {
            padding: 0.75rem 1rem;
            margin-bottom: 1rem;
            border-radius: 4px;
        }
        .flash-success {
            background: #ecfdf5;
            color: #065f46;
            border-left: 4px solid #10b981;
        }
        .flash-error {
            background: #fef2f2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background: #ffffff;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.08);
        }
        th {
            background: #4f46e5;
            color: #ffffff;
            padding: 0.75rem 1rem;
            text-align: left;
            font-weight: 600;
            font-size: 0.9rem;
        }
        td {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #e5e7eb;
            font-size: 0.92rem;
        }
        tbody tr:last-child td { border-bottom: none; }
        tbody tr:hover { background: #f9fafb; }
        .btn {
            display: inline-block;
            padding: 0.4rem 0.9rem;
            border-radius: 6px;
            font-size: 0.875rem;
            font-weight: 500;
            text-decoration: none;
            cursor: pointer;
            border: none;
            font-family: inherit;
        }
        .btn-primary   { background: #4f46e5; color: #ffffff; }
        .btn-danger    { background: #ef4444; color: #ffffff; }
        .btn-secondary { background: #6b7280; color: #ffffff; }
        .btn:hover { opacity: 0.88; }
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        h1 { font-size: 1.5rem; font-weight: 600; }
        form .form-group { margin-bottom: 1.25rem; }
        form label {
            display: block;
            font-weight: 500;
            margin-bottom: 0.35rem;
            font-size: 0.9rem;
        }
        form input[type=text],
        form input[type=email] {
            width: 100%;
            padding: 0.6rem 0.85rem;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 0.95rem;
            font-family: inherit;
        }
        form input:focus {
            outline: none;
            border-color: #4f46e5;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.15);
        }
        .form-card {
            background: #ffffff;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.08);
            max-width: 560px;
        }
        .error-list {
            color: #dc2626;
            background: #fef2f2;
            border-left: 4px solid #ef4444;
            padding: 0.75rem 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
        }
        .error-list ul { padding-left: 1.25rem; }
        .confirmation-box {
            background: #ffffff;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.08);
            max-width: 480px;
        }
        .confirmation-box dl {
            display: grid;
            grid-template-columns: 140px 1fr;
            gap: 0.5rem 1rem;
            margin: 1rem 0;
        }
        .confirmation-box dt { font-weight: 600; color: #6b7280; }
        .confirmation-box dd { color: #1a1a2e; }
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: #6b7280;
        }
        footer {
            margin-top: 3rem;
            padding: 1.5rem;
            text-align: center;
            color: #9ca3af;
            font-size: 0.85rem;
            border-top: 1px solid #e5e7eb;
        }
    </style>
</head>
<body>
    <nav>
        <a class="brand" href="<?= e(BASE_URL) ?>"><?= e(APP_NAME) ?></a>
        <div class="links">
            <a class="<?= e(active_page('index')) ?>"   href="?page=index">Étudiants</a>
            <a class="<?= e(active_page('ajouter')) ?>" href="?page=ajouter">Ajouter</a>
        </div>
    </nav>
    <main>
