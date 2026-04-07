<?php
/**
 * Logo-Endpunkt — serviert das Firmenlogo aus TRANSFER_BASE (außerhalb Webroot)
 * Nur für eingeloggte Admins — öffentliche Seiten nutzen Base64-Data-URI
 */

umask(0077);
ini_set('display_errors', 0);

require_once dirname(__DIR__) . '/functions/bootstrap.php';

auth_session_start();
if (empty($_SESSION['admin_logged_in'])) {
    http_response_code(403);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    exit;
}

$settings  = settings_load();
$logo_path = TRANSFER_BASE . '/logo.dat';

if (!$settings['has_logo'] || !is_file($logo_path)) {
    http_response_code(404);
    exit;
}

$mime_whitelist = [
    'image/png', 'image/jpeg', 'image/gif', 'image/webp', 'image/svg+xml',
];

$mime = $settings['logo_mime'];
if (!in_array($mime, $mime_whitelist, true)) {
    http_response_code(403);
    exit;
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$real_mime = $finfo->file($logo_path);
if (!in_array($real_mime, $mime_whitelist, true)) {
    http_response_code(403);
    exit;
}
$mime = $real_mime;

header('Content-Type: ' . $mime);
header('Cache-Control: public, max-age=86400');
header('X-Content-Type-Options: nosniff');
header('Content-Length: ' . filesize($logo_path));
readfile($logo_path);
