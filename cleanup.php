#!/usr/bin/env php
<?php
/**
 * Cleanup-Script — nur über CLI ausführbar!
 * Löscht abgelaufene Transfers und rotiert Rate-Limit-Dateien.
 *
 * Cronjob-Eintrag (04:00 Uhr täglich):
 *   0 4 * * * php /home/www/public_html/filetransfer/cleanup.php >> /home/www/transfers/logs/cleanup.log 2>&1
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die("Dieses Script ist nur über die Kommandozeile ausführbar.\n");
}

umask(0077);
ini_set('display_errors', 1);
date_default_timezone_set('Europe/Berlin');

// Config laden
$_config_paths = [
    '/home/www/filetransfer_config.php',
    __DIR__ . '/config.php',
];

$config = null;
foreach ($_config_paths as $_p) {
    if (is_file($_p)) {
        $config = require $_p;
        break;
    }
}

if (!is_array($config)) {
    die("FEHLER: config.php nicht gefunden.\n");
}

define('TRANSFER_BASE',  rtrim($config['transfer_base_path'], '/'));
define('LOGS_PATH',      TRANSFER_BASE . '/logs');
define('RATELIMIT_PATH', LOGS_PATH . '/ratelimit');
define('APP_URL',        rtrim($config['app_url'], '/'));

require_once __DIR__ . '/functions/ratelimit.php';

$now     = new DateTimeImmutable('now', new DateTimeZone('Europe/Berlin'));
$deleted = 0;
$kept    = 0;
$errors  = 0;

echo "[" . $now->format('Y-m-d H:i:s') . "] Cleanup startet...\n";

// ── Abgelaufene Transfers bereinigen ──────────────────────────────────────────
if (!is_dir(TRANSFER_BASE)) {
    echo "WARNUNG: TRANSFER_BASE existiert nicht: " . TRANSFER_BASE . "\n";
} else {
    $dirs = glob(TRANSFER_BASE . '/[a-f0-9]*', GLOB_ONLYDIR) ?: [];

    foreach ($dirs as $dir) {
        $token = basename($dir);
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) continue;

        $meta_path = $dir . '/meta.json';
        if (!is_file($meta_path)) {
            // Verwaistes Verzeichnis ohne Meta → löschen
            if (delete_dir_recursive($dir)) {
                echo "  ✓ Verwaistes Verzeichnis gelöscht: {$token}\n";
                $deleted++;
            } else {
                echo "  ✗ Fehler beim Löschen: {$token}\n";
                $errors++;
            }
            continue;
        }

        $json = file_get_contents($meta_path);
        $meta = $json ? json_decode($json, true) : null;

        if (!is_array($meta)) {
            echo "  ✗ Ungültige meta.json: {$token}\n";
            $errors++;
            continue;
        }

        $expires = new DateTimeImmutable($meta['expires_at']);
        // 1 Tag Gnadenfrist nach Ablauf
        $delete_after = $expires->modify('+1 day');

        if ($now > $delete_after) {
            if (delete_dir_recursive($dir)) {
                echo "  ✓ Abgelaufener Transfer gelöscht: " . substr($token, 0, 8) . "... (abgelaufen: " . $expires->format('Y-m-d') . ")\n";
                $deleted++;
            } else {
                echo "  ✗ Fehler beim Löschen: " . substr($token, 0, 8) . "...\n";
                $errors++;
            }
        } else {
            $kept++;
        }
    }
}

// ── Rate-Limit-Dateien aufräumen ──────────────────────────────────────────────
ratelimit_cleanup();
echo "  ✓ Rate-Limit-Dateien bereinigt\n";

// ── Log-Rotation ──────────────────────────────────────────────────────────────
$log_file = LOGS_PATH . '/app.log';
if (file_exists($log_file) && filesize($log_file) > 1572864) {
    $rotated = $log_file . '.' . date('Ymd-His');
    rename($log_file, $rotated);
    echo "  ✓ app.log rotiert → " . basename($rotated) . "\n";

    // Alte rotierte Logs entfernen (max. 5)
    $logs = glob($log_file . '.*') ?: [];
    rsort($logs);
    foreach (array_slice($logs, 5) as $old) {
        @unlink($old);
    }
}

echo "[" . date('Y-m-d H:i:s') . "] Fertig. Gelöscht: {$deleted}, Behalten: {$kept}, Fehler: {$errors}\n";

// ─────────────────────────────────────────────────────────────────────────────

function delete_dir_recursive(string $dir): bool
{
    if (!is_dir($dir)) return false;
    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $path = $dir . '/' . $item;
        if (is_dir($path)) {
            delete_dir_recursive($path);
        } else {
            unlink($path);
        }
    }
    return rmdir($dir);
}
