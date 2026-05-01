<?php
/**
 * Bootstrap — wird von allen öffentlichen PHP-Seiten als erstes inkludiert.
 * Lädt Config, setzt Konstanten, inkludiert alle Function-Dateien.
 */

umask(0077);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
date_default_timezone_set('Europe/Berlin');

// Config laden: außerhalb Webroot (empfohlen) oder im App-Root
$_doc_root = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');
$_config_paths = [
    dirname($_doc_root) . '/jcapps_transfer_config.php',  // außerhalb Webroot (empfohlen)
    dirname(__DIR__) . '/config.php',                      // App-Root (setup.php schreibt hier)
];

$config = null;
foreach ($_config_paths as $_p) {
    if (is_file($_p)) {
        $config = require $_p;
        break;
    }
}

if (!is_array($config)) {
    http_response_code(503);
    die('Configuration error: config.php not found. Please copy config.example.php and adjust.');
}

// Fehler-Log in Transfer-Verzeichnis (außerhalb Webroot)
$_log_dir = rtrim($config['transfer_base_path'], '/') . '/logs';
if (!is_dir($_log_dir)) {
    @mkdir($_log_dir, 0700, true);
}
ini_set('error_log', $_log_dir . '/php_errors.log');

// Konstanten
define('TRANSFER_BASE',  rtrim($config['transfer_base_path'], '/'));
define('LOGS_PATH',      TRANSFER_BASE . '/logs');
define('RATELIMIT_PATH', LOGS_PATH . '/ratelimit');
define('APP_URL',        rtrim($config['app_url'], '/'));
if (!defined('APP_VERSION')) define('APP_VERSION', '1.0.6');

// Security-Header
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; frame-ancestors 'none';");

// Functions laden
require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/ratelimit.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/transfer.php';
