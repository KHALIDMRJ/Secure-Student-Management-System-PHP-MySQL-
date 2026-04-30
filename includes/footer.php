<?php
/**
 * Shared closing partial: closes <main>, prints footer, links app.js, closes body/html.
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/helpers.php';
?>
    </main>
    <footer>
        &copy; 2025 <?= e(APP_NAME) ?> &mdash; Khalid MORJANE
    </footer>
    <script src="<?= e(BASE_URL) ?>js/app.js"></script>
</body>
</html>
