<?php
umask(0077);
ini_set('display_errors', 0);

require_once dirname(dirname(__DIR__)) . '/functions/bootstrap.php';

auth_session_start();
auth_check();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

csrf_verify();

$name = basename($_POST['file'] ?? '');

if ($name !== '' && $name !== '.' && $name !== '..' && is_dir(STORAGE_DIR)) {
    $real_base = realpath(STORAGE_DIR);
    $candidate = STORAGE_DIR . '/' . $name;

    if (is_file($candidate)) {
        $real_file = realpath($candidate);
        if ($real_base && $real_file && str_starts_with($real_file, $real_base . '/')) {
            unlink($real_file);
            log_event('storage_delete', ['file' => $name]);
        }
    }
}

header('Location: storage.php');
exit;
