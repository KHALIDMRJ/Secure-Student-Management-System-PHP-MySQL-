<?php
/**
 * Shared closing partial: closes layout shell, mounts toast container,
 * loads Bootstrap JS bundle and the app's own script.
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/helpers.php';

// CSP nonce — populated by send_security_headers() when the security layer
// is active. Falls back to '' so the nonce attribute is harmless when CSP
// isn't being enforced (browsers ignore an empty nonce).
$cspNonce = $_SERVER['CSP_NONCE'] ?? '';
?>
            </main>
        </div>
    </div>

    <div class="toast-container" id="toastContainer" aria-live="polite" aria-atomic="true"></div>

    <!-- Chart.js loaded BEFORE bootstrap so it's globally available when app.js runs -->
    <script nonce="<?= e($cspNonce) ?>"
            src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/SecureStudentMS/public/js/app.js"></script></body>
</html>
