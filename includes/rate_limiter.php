<?php
declare(strict_types=1);

define('RATE_LIMIT_DIR', sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'studentms_rl');

/**
 * Initialize rate limit storage directory.
 */
function rate_limit_init(): void {
    if (!is_dir(RATE_LIMIT_DIR)) {
        mkdir(RATE_LIMIT_DIR, 0700, true);
    }
}

/**
 * Check if current IP exceeded the rate limit for a given action.
 */
function rate_limit_exceeded(string $action, int $max = 10, int $window = 60): bool {
    rate_limit_init();

    $ip   = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $key  = preg_replace('/[^a-zA-Z0-9_]/', '_', $action . '_' . $ip);
    $file = RATE_LIMIT_DIR . DIRECTORY_SEPARATOR . $key . '.json';
    $now  = time();
    $data = ['attempts' => [], 'blocked_until' => 0];

    if (file_exists($file)) {
        $raw     = file_get_contents($file);
        $decoded = json_decode($raw ?: '', true);
        if (is_array($decoded)) {
            $data = $decoded;
        }
    }

    // Still blocked?
    if ($data['blocked_until'] > $now) {
        return true;
    }

    // Remove expired attempts
    $data['attempts'] = array_values(array_filter(
        $data['attempts'],
        fn(int $t) => ($now - $t) < $window
    ));

    // Limit reached → block for 5 minutes
    if (count($data['attempts']) >= $max) {
        $data['blocked_until'] = $now + 300;
        file_put_contents($file, json_encode($data), LOCK_EX);
        return true;
    }

    // Record attempt
    $data['attempts'][] = $now;
    file_put_contents($file, json_encode($data), LOCK_EX);
    return false;
}

/**
 * Reset rate limit for an IP + action after a successful operation.
 */
function rate_limit_reset(string $action): void {
    rate_limit_init();

    $ip   = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $key  = preg_replace('/[^a-zA-Z0-9_]/', '_', $action . '_' . $ip);
    $file = RATE_LIMIT_DIR . DIRECTORY_SEPARATOR . $key . '.json';

    if (file_exists($file)) {
        unlink($file);
    }
}