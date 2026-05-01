<?php
/**
 * Front controller: maps ?page=... to the matching file under /pages.
 * Bootstraps the security layer (headers, session config, rate limiter)
 * before delegating to the page. Page files are responsible for their
 * own session_start() call and HTML output.
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/rate_limiter.php';

// Harden session ini settings BEFORE any page calls session_start().
configure_session();

const ALLOWED_PAGES = ['index', 'ajouter', 'modifier', 'supprimer'];

// Resolve requested route; fall back to 'index' on anything unknown
$requested = $_GET['page'] ?? 'index';
$page = in_array($requested, ALLOWED_PAGES, true) ? $requested : 'index';

require_once PAGES_PATH . '/' . $page . '.php';
