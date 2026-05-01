<?php
umask(0077);
ini_set('display_errors', 0);

require_once dirname(dirname(__DIR__)) . '/functions/bootstrap.php';

auth_session_start();

header('Content-Type: application/json');

if (empty($_SESSION['admin_logged_in'])) {
    http_response_code(401);
    die(json_encode(['error' => 'Not logged in – please reload the page and log in again.']));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['error' => 'Method Not Allowed']));
}

$posted_token = $_POST['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $posted_token)) {
    http_response_code(403);
    die(json_encode(['error' => 'Invalid request (CSRF)']));
}

$session_id  = $_POST['session_id']   ?? '';
$file_idx    = (int)($_POST['file_index']   ?? -1);
$chunk_idx   = (int)($_POST['chunk_index']  ?? -1);
$total       = (int)($_POST['total_chunks'] ?? 0);
$max_files   = (int)($config['max_files_per_upload'] ?? 10);

if (!preg_match('/^[a-f0-9]{32}$/', $session_id)) {
    http_response_code(400);
    die(json_encode(['error' => 'Invalid session_id']));
}
if ($file_idx < 0 || $file_idx >= $max_files) {
    http_response_code(400);
    die(json_encode(['error' => 'Invalid file_index']));
}
if ($chunk_idx < 0 || $chunk_idx > 9999) {
    http_response_code(400);
    die(json_encode(['error' => 'Invalid chunk_index']));
}
if ($total < 1 || $total > 10000) {
    http_response_code(400);
    die(json_encode(['error' => 'Invalid total_chunks']));
}

$chunk_file = $_FILES['chunk'] ?? null;
if (!$chunk_file || $chunk_file['error'] !== UPLOAD_ERR_OK) {
    $err = $chunk_file['error'] ?? -1;
    http_response_code(400);
    die(json_encode(['error' => 'Chunk upload failed (Code: ' . $err . ')']));
}

if ($chunk_file['size'] > 4 * 1024 * 1024 + 8192) {
    http_response_code(400);
    die(json_encode(['error' => 'Chunk too large']));
}

$chunks_base = TRANSFER_BASE . '/chunks';
$chunk_dir   = $chunks_base . '/' . $session_id;

if (!is_dir($chunk_dir) && !mkdir($chunk_dir, 0700, true)) {
    http_response_code(500);
    die(json_encode(['error' => 'Chunk directory could not be created']));
}

$real_base = realpath($chunks_base);
$real_dir  = realpath($chunk_dir);
if (!$real_base || !$real_dir || !str_starts_with($real_dir, $real_base . '/')) {
    http_response_code(400);
    die(json_encode(['error' => 'Invalid path']));
}

$chunk_path = $chunk_dir . '/' . $file_idx . '_' . sprintf('%05d', $chunk_idx) . '.part';
if (!move_uploaded_file($chunk_file['tmp_name'], $chunk_path)) {
    http_response_code(500);
    die(json_encode(['error' => 'Chunk could not be saved']));
}
chmod($chunk_path, 0600);

// Cumulative size check (running total in _size.dat, flock-protected)
$max_bytes = (int)($config['max_filesize_mb'] ?? 200) * 1048576;
$size_file = $chunk_dir . '/_size.dat';
$lock_fp   = fopen($size_file . '.lock', 'c');
if ($lock_fp === false) {
    echo json_encode(['ok' => true]); // finalize will re-check total size
    exit;
}
flock($lock_fp, LOCK_EX);
$prev_total = is_file($size_file) ? (int)file_get_contents($size_file) : 0;
$new_total  = $prev_total + $chunk_file['size'];
if ($new_total > $max_bytes) {
    unlink($chunk_path);
    flock($lock_fp, LOCK_UN);
    fclose($lock_fp);
    http_response_code(400);
    die(json_encode(['error' => 'Total size exceeds ' . ($config['max_filesize_mb'] ?? 200) . ' MB.']));
}
file_put_contents($size_file, (string)$new_total);
flock($lock_fp, LOCK_UN);
fclose($lock_fp);

echo json_encode(['ok' => true]);
