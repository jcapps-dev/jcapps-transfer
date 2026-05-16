<?php
umask(0077);
ini_set('display_errors', 0);

require_once dirname(dirname(__DIR__)) . '/functions/bootstrap.php';

auth_session_start();
auth_check();

$name = basename($_GET['file'] ?? '');

if ($name === '' || $name === '.' || $name === '..') {
    http_response_code(400);
    exit('Invalid request.');
}

if (!is_dir(STORAGE_DIR)) {
    http_response_code(404);
    exit('File not found.');
}

$real_base = realpath(STORAGE_DIR);
$candidate = STORAGE_DIR . '/' . $name;

if (!is_file($candidate)) {
    http_response_code(404);
    exit('File not found.');
}

$real_file = realpath($candidate);
if (!$real_base || !$real_file || !str_starts_with($real_file, $real_base . '/')) {
    http_response_code(403);
    exit('Access denied.');
}

$finfo    = new finfo(FILEINFO_MIME_TYPE);
$mime     = $finfo->file($real_file) ?: 'application/octet-stream';
$filesize = filesize($real_file);

header('Content-Type: ' . $mime);
header('Content-Length: ' . $filesize);
header('Content-Disposition: attachment; filename="' . str_replace('"', '', $name) . '"');
header('Cache-Control: private, no-cache');
header('X-Content-Type-Options: nosniff');

readfile($real_file);
