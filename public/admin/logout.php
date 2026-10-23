<?php
/**
 * Logout — POST with CSRF token only (prevents logout CSRF via foreign links)
 */

umask(0077);
ini_set('display_errors', 0);

require_once dirname(dirname(__DIR__)) . '/functions/bootstrap.php';

auth_session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_SESSION['admin_logged_in'])) {
    header('Location: ' . APP_URL . '/admin/index.php');
    exit;
}

csrf_verify();
log_event('admin_logout');
auth_logout();
