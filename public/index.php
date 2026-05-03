<?php
/**
 * Front controller: maps ?page=... to the matching file under /pages.
 * Page files are responsible for their own session_start() call and HTML output.
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

const ALLOWED_PAGES = [
    // Admin scope
    'index', 'ajouter', 'modifier', 'supprimer', 'dashboard',
    'login', 'logout',
    // Admin module CRUD
    'modules', 'modules_ajouter', 'modules_modifier', 'modules_supprimer',
    // Admin grading interface
    'notes', 'notes_etudiant',
    // Student scope (nested under pages/etudiant/)
    'etudiant/login', 'etudiant/logout', 'etudiant/dashboard',
    'etudiant/profil', 'etudiant/modules',
];

// Resolve requested route; fall back to 'index' on anything unknown
$requested = $_GET['page'] ?? 'index';
$page = in_array($requested, ALLOWED_PAGES, true) ? $requested : 'index';

require_once PAGES_PATH . '/' . $page . '.php';
