<?php
/**
 * Transfer widerrufen (POST only, kein HTML-Output)
 */

umask(0077);
ini_set('display_errors', 0);

require_once dirname(dirname(__DIR__)) . '/functions/bootstrap.php';

auth_session_start();
auth_check();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit;
}

csrf_verify();

$token = $_POST['token'] ?? '';

if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
    flash_set('error', 'Ungültiger Token.');
    header('Location: ' . APP_URL . '/admin/dashboard.php');
    exit;
}

$meta = transfer_load($token);

if (!$meta) {
    flash_set('error', 'Transfer nicht gefunden.');
    header('Location: ' . APP_URL . '/admin/dashboard.php');
    exit;
}

if ($meta['revoked'] ?? false) {
    flash_set('warning', 'Transfer war bereits widerrufen.');
    header('Location: ' . APP_URL . '/admin/dashboard.php');
    exit;
}

if (transfer_revoke($token)) {
    log_event('transfer_revoked', ['pfx' => substr($token, 0, 8)]);
    flash_set('success', 'Transfer wurde gesperrt.');
} else {
    flash_set('error', 'Fehler beim Widerrufen des Transfers.');
}

header('Location: ' . APP_URL . '/admin/dashboard.php');
exit;
