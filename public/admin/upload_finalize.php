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

$session_id    = $_POST['session_id']    ?? '';
$password      = $_POST['password']      ?? '';
$max_downloads_raw = $_POST['max_downloads'] ?? '';
$max_downloads = ($max_downloads_raw !== '') ? (int)$max_downloads_raw : null;
$lifetime      = (int)($config['transfer_lifetime_days'] ?? 14);
$custom_days   = isset($_POST['lifetime_days']) && (int)$_POST['lifetime_days'] > 0
    ? min((int)$_POST['lifetime_days'], 365)
    : $lifetime;

if (!preg_match('/^[a-f0-9]{32}$/', $session_id)) {
    http_response_code(400);
    die(json_encode(['error' => 'Invalid session_id']));
}

if ($max_downloads !== null && $max_downloads < 1) {
    http_response_code(400);
    die(json_encode(['error' => 'Maximum downloads must be at least 1']));
}

$raw_files = $_POST['files'] ?? [];
if (!is_array($raw_files) || empty($raw_files)) {
    http_response_code(400);
    die(json_encode(['error' => 'No files specified']));
}

$max_mb    = (int)($config['max_filesize_mb'] ?? 200);
$max_files = (int)($config['max_files_per_upload'] ?? 10);

if (count($raw_files) > $max_files) {
    http_response_code(400);
    die(json_encode(['error' => "Maximum {$max_files} files allowed"]));
}

$chunk_dir = TRANSFER_BASE . '/chunks/' . $session_id;

if (!is_dir($chunk_dir)) {
    http_response_code(400);
    die(json_encode(['error' => 'Upload session not found']));
}

$assembled  = [];
$total_size = 0;

foreach ($raw_files as $rf) {
    $file_idx     = (int)($rf['file_index']   ?? -1);
    $total_chunks = (int)($rf['total_chunks'] ?? 0);
    $filename     = (string)($rf['name']       ?? '');

    if ($file_idx < 0 || $file_idx >= $max_files || $total_chunks < 1) {
        transfer_delete_dir($chunk_dir);
        http_response_code(400);
        die(json_encode(['error' => 'Invalid file metadata']));
    }

    $assembled_path = $chunk_dir . '/assembled_' . $file_idx . '.dat';
    $fp = fopen($assembled_path, 'wb');
    if (!$fp) {
        transfer_delete_dir($chunk_dir);
        http_response_code(500);
        die(json_encode(['error' => 'Assembly failed']));
    }

    for ($c = 0; $c < $total_chunks; $c++) {
        $part = $chunk_dir . '/' . $file_idx . '_' . sprintf('%05d', $c) . '.part';
        if (!is_file($part)) {
            fclose($fp);
            transfer_delete_dir($chunk_dir);
            http_response_code(400);
            die(json_encode(['error' => 'Chunk missing (' . $file_idx . '_' . $c . ')']));
        }
        $data = file_get_contents($part);
        if ($data === false) {
            fclose($fp);
            transfer_delete_dir($chunk_dir);
            http_response_code(500);
            die(json_encode(['error' => 'Chunk could not be read']));
        }
        fwrite($fp, $data);
    }
    fclose($fp);
    chmod($assembled_path, 0600);

    $size        = filesize($assembled_path);
    $total_size += $size;
    $assembled[] = ['name' => $filename, 'path' => $assembled_path, 'size' => $size];
}

if ($total_size > $max_mb * 1048576) {
    transfer_delete_dir($chunk_dir);
    http_response_code(400);
    die(json_encode(['error' => "Total size exceeds {$max_mb} MB"]));
}

$res = transfer_create_from_paths(
    $assembled,
    $password !== '' ? $password : null,
    $max_downloads,
    $custom_days
);

transfer_delete_dir($chunk_dir);

if (isset($res['error'])) {
    http_response_code(500);
    die(json_encode(['error' => $res['error']]));
}

log_event('transfer_created', [
    'files'   => count($assembled),
    'size'    => $total_size,
    'has_pw'  => $password !== '',
    'max_dl'  => $max_downloads,
    'days'    => $custom_days,
    'chunked' => true,
]);

echo json_encode([
    'ok'       => true,
    'token'    => $res['token'],
    'url'      => isset($res['short_code']) ? APP_URL . '/dl/' . $res['short_code'] : transfer_download_url($res['token']),
    'password' => $password,
]);
