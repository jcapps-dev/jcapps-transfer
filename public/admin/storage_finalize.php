<?php
umask(0077);
ini_set('display_errors', 0);

require_once dirname(dirname(__DIR__)) . '/functions/bootstrap.php';

auth_session_start();

header('Content-Type: application/json');

if (empty($_SESSION['admin_logged_in'])) {
    http_response_code(401);
    die(json_encode(['error' => 'Not logged in — please reload and log in again.']));
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

$session_id = $_POST['session_id'] ?? '';
if (!preg_match('/^[a-f0-9]{32}$/', $session_id)) {
    http_response_code(400);
    die(json_encode(['error' => 'Invalid session_id']));
}

$raw_files = $_POST['files'] ?? [];
if (!is_array($raw_files) || empty($raw_files)) {
    http_response_code(400);
    die(json_encode(['error' => 'No files provided']));
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

function storage_sanitize_filename(string $raw): string {
    $name = basename($raw);
    $name = preg_replace('/[^a-zA-Z0-9 _\-.()\[\]]/', '_', $name);
    $name = trim($name, '. ');
    if ($name === '') $name = 'file';
    return mb_substr($name, 0, 200);
}

// Sanitize filenames and check for duplicates upfront
$files_to_save = [];
foreach ($raw_files as $rf) {
    $file_idx     = (int)($rf['file_index']   ?? -1);
    $total_chunks = (int)($rf['total_chunks'] ?? 0);
    $raw_name     = (string)($rf['name']       ?? '');

    if ($file_idx < 0 || $file_idx >= $max_files || $total_chunks < 1) {
        transfer_delete_dir($chunk_dir);
        http_response_code(400);
        die(json_encode(['error' => 'Invalid file metadata']));
    }

    $safe_name = storage_sanitize_filename($raw_name);
    $dest_path = STORAGE_DIR . '/' . $safe_name;

    if (is_dir(STORAGE_DIR) && is_file($dest_path)) {
        transfer_delete_dir($chunk_dir);
        http_response_code(409);
        die(json_encode(['error' => "\"$safe_name\" already exists in storage."]));
    }

    $files_to_save[] = [
        'file_index'   => $file_idx,
        'total_chunks' => $total_chunks,
        'safe_name'    => $safe_name,
        'dest_path'    => $dest_path,
    ];
}

// Create storage directory if needed
if (!is_dir(STORAGE_DIR) && !mkdir(STORAGE_DIR, 0700, true)) {
    transfer_delete_dir($chunk_dir);
    http_response_code(500);
    die(json_encode(['error' => 'Could not create storage directory']));
}

// Assemble chunks
$assembled  = [];
$total_size = 0;

foreach ($files_to_save as $f) {
    $assembled_path = $chunk_dir . '/assembled_' . $f['file_index'] . '.dat';
    $fp = fopen($assembled_path, 'wb');
    if (!$fp) {
        transfer_delete_dir($chunk_dir);
        http_response_code(500);
        die(json_encode(['error' => 'Assembly failed']));
    }

    for ($c = 0; $c < $f['total_chunks']; $c++) {
        $part = $chunk_dir . '/' . $f['file_index'] . '_' . sprintf('%05d', $c) . '.part';
        if (!is_file($part)) {
            fclose($fp);
            transfer_delete_dir($chunk_dir);
            http_response_code(400);
            die(json_encode(['error' => 'Chunk missing (' . $f['file_index'] . '_' . $c . ')']));
        }
        $data = file_get_contents($part);
        if ($data === false) {
            fclose($fp);
            transfer_delete_dir($chunk_dir);
            http_response_code(500);
            die(json_encode(['error' => 'Could not read chunk']));
        }
        fwrite($fp, $data);
    }
    fclose($fp);

    $size        = filesize($assembled_path);
    $total_size += $size;
    $assembled[] = array_merge($f, ['assembled_path' => $assembled_path, 'size' => $size]);
}

if ($total_size > $max_mb * 1048576) {
    transfer_delete_dir($chunk_dir);
    http_response_code(400);
    die(json_encode(['error' => "Total size exceeds {$max_mb} MB"]));
}

// Move files to storage
foreach ($assembled as $f) {
    if (!rename($f['assembled_path'], $f['dest_path'])) {
        if (!copy($f['assembled_path'], $f['dest_path'])) {
            transfer_delete_dir($chunk_dir);
            http_response_code(500);
            die(json_encode(['error' => 'Could not save file to storage']));
        }
        unlink($f['assembled_path']);
    }
    chmod($f['dest_path'], 0600);
}

transfer_delete_dir($chunk_dir);

log_event('storage_upload', ['files' => count($assembled), 'size' => $total_size]);

echo json_encode(['ok' => true, 'redirect' => 'storage.php?ok=1']);
