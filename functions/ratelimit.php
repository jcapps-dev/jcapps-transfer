<?php
/**
 * Filesystem-basiertes Rate-Limiting (DSGVO-konform: IP als SHA256-Hash)
 */

function ratelimit_get_window_key(int $window_seconds): string
{
    return (string)floor(time() / $window_seconds);
}

function ratelimit_get_filepath(string $endpoint, string $ip_hash, int $window_seconds): string
{
    $window = ratelimit_get_window_key($window_seconds);
    // Endpoint-Name auf sichere Zeichen beschränken
    $safe_endpoint = preg_replace('/[^a-zA-Z0-9_]/', '_', $endpoint);
    return RATELIMIT_PATH . '/' . $safe_endpoint . '_' . $ip_hash . '_' . $window . '.count';
}

function ratelimit_increment(string $endpoint, string $ip_hash, int $window_seconds = 60): int
{
    if (!is_dir(RATELIMIT_PATH)) {
        mkdir(RATELIMIT_PATH, 0700, true);
    }

    $file = ratelimit_get_filepath($endpoint, $ip_hash, $window_seconds);

    $fp = fopen($file, 'c+');
    if (!$fp) return 0;

    flock($fp, LOCK_EX);
    $count = (int)(fread($fp, 32) ?: '0');
    $count++;
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, (string)$count);
    flock($fp, LOCK_UN);
    fclose($fp);

    return $count;
}

function ratelimit_get_count(string $endpoint, string $ip_hash, int $window_seconds = 60): int
{
    $file = ratelimit_get_filepath($endpoint, $ip_hash, $window_seconds);
    if (!file_exists($file)) return 0;
    $val = file_get_contents($file);
    return (int)($val ?: '0');
}

function ratelimit_is_blocked(string $endpoint, string $ip_hash, int $max_requests, int $window_seconds = 60): bool
{
    return ratelimit_get_count($endpoint, $ip_hash, $window_seconds) >= $max_requests;
}

/**
 * Alte Rate-Limit-Dateien aufräumen (älter als 2 Stunden).
 * Wird von cleanup.php aufgerufen.
 */
function ratelimit_cleanup(): void
{
    if (!is_dir(RATELIMIT_PATH)) return;

    $files  = glob(RATELIMIT_PATH . '/*.count') ?: [];
    $cutoff = time() - 7200; // 2 Stunden

    foreach ($files as $file) {
        if (filemtime($file) < $cutoff) {
            @unlink($file);
        }
    }
}
