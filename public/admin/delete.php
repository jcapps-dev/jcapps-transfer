<?php
/**
 * Transfer löschen — Metadaten und Dateien werden dauerhaft entfernt (POST only)
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
    flash_set('error', 'Invalid token.');
    header('Location: ' . APP_URL . '/admin/dashboard.php');
    exit;
}

if (!transfer_load($token)) {
    flash_set('error', 'Transfer not found.');
    header('Location: ' . APP_URL . '/admin/dashboard.php');
    exit;
}

if (transfer_delete($token)) {
    log_event('transfer_deleted', ['pfx' => substr($token, 0, 8)]);
    flash_set('success', 'Transfer and all files have been deleted.');
} else {
    flash_set('error', 'Error deleting transfer.');
}

header('Location: ' . APP_URL . '/admin/dashboard.php');
exit;
