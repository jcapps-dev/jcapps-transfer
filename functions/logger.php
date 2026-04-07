<?php
/**
 * DSGVO-konformes Logging — IP-Adressen werden als SHA256-Hash gespeichert
 */

function log_get_ip(): string
{
    // Kein Vertrauen in X-Forwarded-For ohne Proxy-Whitelist
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

function log_ip_hash(string $ip): string
{
    global $config;
    $salt = $config['rate_limit_salt'] ?? '';
    return hash('sha256', $ip . $salt);
}

function log_event(string $event, array $context = []): void
{
    if (!is_dir(LOGS_PATH)) {
        @mkdir(LOGS_PATH, 0700, true);
    }

    $log_file = LOGS_PATH . '/app.log';

    // Rotieren wenn nötig (> 10.000 Zeilen ≈ ~1.5 MB)
    if (file_exists($log_file) && filesize($log_file) > 1572864) {
        log_rotate($log_file);
    }

    $ip_hash = log_ip_hash(log_get_ip());
    $entry   = json_encode([
        'ts'    => date('c'),
        'ip'    => $ip_hash,
        'event' => $event,
        'ctx'   => $context,
    ], JSON_UNESCAPED_UNICODE) . "\n";

    file_put_contents($log_file, $entry, FILE_APPEND | LOCK_EX);
}

function log_rotate(string $log_file): void
{
    $rotated = $log_file . '.' . date('Ymd-His');
    rename($log_file, $rotated);

    // Nur die letzten 5 rotierten Logs behalten
    $logs = glob($log_file . '.*') ?: [];
    rsort($logs);
    foreach (array_slice($logs, 5) as $old) {
        @unlink($old);
    }
}
