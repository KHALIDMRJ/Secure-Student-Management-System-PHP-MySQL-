<?php
/**
 * Shared closing partial: closes layout shell, mounts toast container,
 * loads Bootstrap JS bundle and the app's own script.
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/helpers.php';

// CSP nonce produced by send_security_headers() in header.php.
$cspNonce = $_SERVER['CSP_NONCE'] ?? '';
?>
            </main>
        </div>
    </div>

    <div class="toast-container" id="toastContainer" aria-live="polite" aria-atomic="true"></div>

    <script nonce="<?= e($cspNonce) ?>" src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script nonce="<?= e($cspNonce) ?>" src="/SecureStudentMS/public/js/app.js"></script></body>
</html>
