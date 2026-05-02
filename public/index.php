<?php
/**
 * Front controller: maps ?page=... to the matching file under /pages.
 * Page files are responsible for their own session_start() call and HTML output.
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

const ALLOWED_PAGES = ['index', 'ajouter', 'modifier', 'supprimer', 'dashboard'];

// Resolve requested route; fall back to 'index' on anything unknown
$requested = $_GET['page'] ?? 'index';
$page = in_array($requested, ALLOWED_PAGES, true) ? $requested : 'index';

require_once PAGES_PATH . '/' . $page . '.php';
