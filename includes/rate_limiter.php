<?php
/**
 * File-based rate limiter — no Redis, no DB. Works on XAMPP.
 *
 * Storage: one JSON file per (action, IP) tuple in sys_get_temp_dir().
 * Window:  rolling N seconds; overflow blocks the IP for 5 minutes.
 */
declare(strict_types=1);

// Storage directory for rate-limit state files
define('RATE_LIMIT_DIR', sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'studentms_rl');

/**
 * Ensure the rate-limit storage directory exists. Idempotent.
 *
 * @return void
 */
function rate_limit_init(): void {
    if (!is_dir(RATE_LIMIT_DIR)) {
        @mkdir(RATE_LIMIT_DIR, 0700, true);
    }
}

/**
 * Check whether the current IP has exceeded the rate limit for $action.
 * Records the current attempt as a side effect when not yet over the limit.
 *
 * @param string $action Unique action key (e.g. 'add_student').
 * @param int    $max    Maximum attempts allowed inside the window.
 * @param int    $window Time window in seconds.
 * @return bool          True when the limit is exceeded (caller should abort).
 */
function rate_limit_exceeded(string $action, int $max = 10, int $window = 60): bool {
    rate_limit_init();

    $ip   = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $key  = preg_replace('/[^a-zA-Z0-9_]/', '_', $action . '_' . $ip);
    $file = RATE_LIMIT_DIR . DIRECTORY_SEPARATOR . $key . '.json';
    $now  = time();
    $data = ['attempts' => [], 'blocked_until' => 0];

    // Load existing state
    if (file_exists($file)) {
        $raw = @file_get_contents($file);
        if ($raw !== false) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $data = $decoded + $data; // preserve defaults for missing keys
            }
        }
    }

    // Currently in penalty box?
    if (($data['blocked_until'] ?? 0) > $now) {
        return true;
    }

    // Drop attempts that fell out of the rolling window
    $attempts = is_array($data['attempts'] ?? null) ? $data['attempts'] : [];
    $attempts = array_values(array_filter(
        $attempts,
        static fn($t): bool => is_int($t) && ($now - $t) < $window
    ));
    $data['attempts'] = $attempts;

    // Over the limit — block for 5 minutes and persist
    if (count($attempts) >= $max) {
        $data['blocked_until'] = $now + 300;
        @file_put_contents($file, json_encode($data), LOCK_EX);
        return true;
    }

    // Record this attempt
    $data['attempts'][] = $now;
    @file_put_contents($file, json_encode($data), LOCK_EX);
    return false;
}

/**
 * Reset the counter for an (IP, action) pair. Call after a successful
 * operation so a legitimate user is not penalised by stale attempts.
 *
 * @param string $action Action key passed to rate_limit_exceeded().
 * @return void
 */
function rate_limit_reset(string $action): void {
    rate_limit_init();

    $ip   = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $key  = preg_replace('/[^a-zA-Z0-9_]/', '_', $action . '_' . $ip);
    $file = RATE_LIMIT_DIR . DIRECTORY_SEPARATOR . $key . '.json';

    if (file_exists($file)) {
        @unlink($file);
    }
}
