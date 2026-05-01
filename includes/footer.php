<?php
/**
 * Shared closing partial: closes layout shell, mounts toast container,
 * loads Bootstrap JS bundle and the app's own script.
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/helpers.php';
?>
            </main>
        </div>
    </div>

    <div class="toast-container" id="toastContainer" aria-live="polite" aria-atomic="true"></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/SecureStudentMS/public/js/app.js"></script></body>
</html>
