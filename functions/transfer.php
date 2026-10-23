<?php
/**
 * Transfer functions — Create, load, validate, stream, delete
 */

// ── Dateinamen-Sanitisierung ──────────────────────────────────────────────────

function transfer_sanitize_filename(string $name): string
{
    // Pfad-Komponenten entfernen
    $name = basename($name);

    // Unicode-Normalisierung auf NFC
    if (function_exists('normalizer_normalize')) {
        $normalized = normalizer_normalize($name, Normalizer::FORM_C);
        if ($normalized !== false) {
            $name = $normalized;
        }
    }

    // Steuerzeichen und problematische Zeichen entfernen
    $name = preg_replace('/[\x00-\x1f\x7f]/', '', $name);
    $name = preg_replace('/[\/\\\\:*?"<>|]/', '_', $name);

    // Führende/nachfolgende Punkte und Leerzeichen entfernen
    $name = trim($name, '. ');

    // Fallback bei leerem Namen
    if ($name === '') {
        $name = 'file_' . time();
    }

    // Maximallänge 255 Zeichen
    if (mb_strlen($name) > 255) {
        $ext  = pathinfo($name, PATHINFO_EXTENSION);
        $base = mb_substr(pathinfo($name, PATHINFO_FILENAME), 0, 240);
        $name = $base . ($ext !== '' ? '.' . $ext : '');
    }

    return $name;
}

// ── Sicherheitsprüfungen ──────────────────────────────────────────────────────

function transfer_check_extension_blacklist(string $name): bool
{
    $blacklist = [
        'php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'php8', 'phpx', 'phps', 'phar',
        'asp', 'aspx', 'jsp', 'cfm', 'cgi', 'pl', 'py', 'rb', 'lua',
        'sh', 'bash', 'zsh', 'ksh', 'fish', 'ps1', 'psm1',
        'exe', 'bat', 'cmd', 'com', 'scr', 'msi', 'dll', 'vbs', 'vbe', 'js',
        'htaccess', 'htpasswd',
    ];
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    return in_array($ext, $blacklist, true);
}

function transfer_check_mimetype(string $tmp_path): bool
{
    $whitelist = [
        // Dokumente
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/rtf',
        // Archive
        'application/zip',
        'application/x-zip-compressed',
        'application/x-zip',
        'application/x-rar-compressed',
        'application/x-7z-compressed',
        'application/gzip',
        'application/x-tar',
        // Bilder
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/tiff',
        // Text
        'text/plain',
        'text/csv',
        // Video
        'video/mp4',
        'video/quicktime',
        // Audio
        'audio/mpeg',
        'audio/mp4',
        'audio/wav',
        // Generic binary (für sonstige bekannte Formate)
        'application/octet-stream',
    ];

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($tmp_path);

    return in_array($mime, $whitelist, true);
}

// ── Transfer erstellen ────────────────────────────────────────────────────────

function transfer_create(array $files, ?string $password, ?int $max_downloads, int $lifetime_days): array
{
    // 256-Bit-Token
    $token = bin2hex(random_bytes(32));

    // Verzeichnisse anlegen
    $transfer_dir = TRANSFER_BASE . '/' . $token;
    $files_dir    = $transfer_dir . '/files';

    if (!mkdir($transfer_dir, 0700, true)) {
        return ['error' => 'Transfer directory could not be created.'];
    }
    if (!mkdir($files_dir, 0700)) {
        rmdir($transfer_dir);
        return ['error' => 'File directory could not be created.'];
    }

    $stored_files = [];

    foreach ($files as $file) {
        $original_name = transfer_sanitize_filename($file['name']);

        // Extension-Blacklist (nach Normalisierung prüfen)
        if (transfer_check_extension_blacklist($original_name)) {
            transfer_delete_dir($transfer_dir);
            return ['error' => 'File type not allowed: ' . htmlspecialchars($original_name, ENT_QUOTES, 'UTF-8')];
        }

        // MIME type whitelist
        if (!transfer_check_mimetype($file['tmp_path'])) {
            transfer_delete_dir($transfer_dir);
            return ['error' => 'MIME type not allowed: ' . htmlspecialchars($original_name, ENT_QUOTES, 'UTF-8')];
        }

        // Zufälligen Speichernamen vergeben (kein Rückschluss auf Original)
        $stored_name = bin2hex(random_bytes(16)) . '.dat';
        $dest        = $files_dir . '/' . $stored_name;

        if (!move_uploaded_file($file['tmp_path'], $dest)) {
            transfer_delete_dir($transfer_dir);
            return ['error' => 'File could not be saved: ' . htmlspecialchars($original_name, ENT_QUOTES, 'UTF-8')];
        }
        chmod($dest, 0600);

        $finfo        = new finfo(FILEINFO_MIME_TYPE);
        $stored_files[] = [
            'original_name' => $original_name,
            'stored_name'   => $stored_name,
            'size'          => filesize($dest),
            'mimetype'      => $finfo->file($dest),
        ];
    }

    // Metadaten schreiben
    $now     = new DateTimeImmutable('now', new DateTimeZone('Europe/Berlin'));
    $expires = $now->modify('+' . $lifetime_days . ' days');

    $meta = [
        'token'          => $token,
        'files'          => $stored_files,
        'password_hash'  => ($password !== null && $password !== '')
            ? password_hash($password, PASSWORD_BCRYPT, ['cost' => 12])
            : null,
        'max_downloads'  => $max_downloads,
        'download_count' => 0,
        'created_at'     => $now->format('c'),
        'expires_at'     => $expires->format('c'),
        'revoked'        => false,
    ];

    $meta_path = $transfer_dir . '/meta.json';
    if (file_put_contents($meta_path, json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX) === false) {
        transfer_delete_dir($transfer_dir);
        return ['error' => 'Metadata could not be saved.'];
    }
    chmod($meta_path, 0600);

    return ['token' => $token, 'meta' => $meta];
}

// ── Create transfer from assembled paths ─────────────────────────────────────

function transfer_create_from_paths(array $files, ?string $password, ?int $max_downloads, int $lifetime_days): array
{
    $token      = bin2hex(random_bytes(32));
    $short_code = transfer_generate_short_code();

    $transfer_dir = TRANSFER_BASE . '/' . $token;
    $files_dir    = $transfer_dir . '/files';

    if (!mkdir($transfer_dir, 0700, true)) {
        return ['error' => 'Transfer directory could not be created.'];
    }
    if (!mkdir($files_dir, 0700)) {
        rmdir($transfer_dir);
        return ['error' => 'File directory could not be created.'];
    }

    $stored_files = [];

    foreach ($files as $file) {
        $original_name = transfer_sanitize_filename($file['name']);

        if (transfer_check_extension_blacklist($original_name)) {
            transfer_delete_dir($transfer_dir);
            return ['error' => 'File type not allowed: ' . htmlspecialchars($original_name, ENT_QUOTES, 'UTF-8')];
        }

        if (!transfer_check_mimetype($file['path'])) {
            transfer_delete_dir($transfer_dir);
            return ['error' => 'MIME type not allowed: ' . htmlspecialchars($original_name, ENT_QUOTES, 'UTF-8')];
        }

        $stored_name = bin2hex(random_bytes(16)) . '.dat';
        $dest        = $files_dir . '/' . $stored_name;

        if (!rename($file['path'], $dest)) {
            transfer_delete_dir($transfer_dir);
            return ['error' => 'File could not be saved: ' . htmlspecialchars($original_name, ENT_QUOTES, 'UTF-8')];
        }
        chmod($dest, 0600);

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $stored_files[] = [
            'original_name' => $original_name,
            'stored_name'   => $stored_name,
            'size'          => filesize($dest),
            'mimetype'      => $finfo->file($dest),
        ];
    }

    $now     = new DateTimeImmutable('now', new DateTimeZone('Europe/Berlin'));
    $expires = $now->modify('+' . $lifetime_days . ' days');

    $meta = [
        'token'          => $token,
        'short_code'     => $short_code,
        'files'          => $stored_files,
        'password_hash'  => ($password !== null && $password !== '')
            ? password_hash($password, PASSWORD_BCRYPT, ['cost' => 12])
            : null,
        'max_downloads'  => $max_downloads,
        'download_count' => 0,
        'created_at'     => $now->format('c'),
        'expires_at'     => $expires->format('c'),
        'revoked'        => false,
    ];

    $meta_path = $transfer_dir . '/meta.json';
    if (file_put_contents($meta_path, json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX) === false) {
        transfer_delete_dir($transfer_dir);
        return ['error' => 'Metadata could not be saved.'];
    }
    chmod($meta_path, 0600);

    return ['token' => $token, 'short_code' => $short_code, 'meta' => $meta];
}

// ── Transfer laden ────────────────────────────────────────────────────────────

function transfer_load(string $token): ?array
{
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        return null;
    }

    $meta_path = TRANSFER_BASE . '/' . $token . '/meta.json';

    // Path-Traversal-Schutz via realpath()
    $real_meta = realpath($meta_path);
    $real_base = realpath(TRANSFER_BASE);
    if (!$real_meta || !$real_base || !str_starts_with($real_meta, $real_base . '/')) {
        return null;
    }

    if (!is_file($meta_path)) {
        return null;
    }

    $json = file_get_contents($meta_path);
    if ($json === false) return null;

    $meta = json_decode($json, true);
    if (!is_array($meta)) return null;

    return $meta;
}

// ── Transfer validieren ───────────────────────────────────────────────────────

function transfer_is_valid(array $meta): bool
{
    if ($meta['revoked'] ?? false) return false;

    $expires = new DateTimeImmutable($meta['expires_at']);
    if (new DateTimeImmutable() > $expires) return false;

    if (isset($meta['max_downloads']) && $meta['max_downloads'] !== null) {
        if (($meta['download_count'] ?? 0) >= $meta['max_downloads']) return false;
    }

    return true;
}

function transfer_get_status(array $meta): string
{
    if ($meta['revoked'] ?? false) return 'revoked';
    $expires = new DateTimeImmutable($meta['expires_at']);
    if (new DateTimeImmutable() > $expires) return 'expired';
    if (isset($meta['max_downloads']) && $meta['max_downloads'] !== null) {
        if (($meta['download_count'] ?? 0) >= $meta['max_downloads']) return 'maxdownloads';
    }
    return 'active';
}

// ── Lock and atomically write metadata ───────────────────────────────────────

/**
 * Acquire an exclusive lock on a transfer's metadata.
 * The lock file is kept — deleting it would let waiting processes
 * acquire a lock on an orphaned file.
 *
 * @return resource|false
 */
function transfer_lock(string $token)
{
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) return false;
    $dir = TRANSFER_BASE . '/' . $token;
    if (!is_dir($dir)) return false;
    $lock = fopen($dir . '/meta.lock', 'c');
    if (!$lock) return false;
    flock($lock, LOCK_EX);
    return $lock;
}

function transfer_unlock($lock): void
{
    flock($lock, LOCK_UN);
    fclose($lock);
}

/**
 * Write meta.json atomically (temp file + rename) so concurrent
 * readers never see an empty or half-written file.
 */
function transfer_write_meta(string $token, array $meta): bool
{
    $meta_path = TRANSFER_BASE . '/' . $token . '/meta.json';
    $tmp_path  = $meta_path . '.' . bin2hex(random_bytes(4)) . '.tmp';
    if (file_put_contents($tmp_path, json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) === false) {
        @unlink($tmp_path);
        return false;
    }
    chmod($tmp_path, 0600);
    if (!rename($tmp_path, $meta_path)) {
        @unlink($tmp_path);
        return false;
    }
    return true;
}

// ── Claim download ────────────────────────────────────────────────────────────

/**
 * Check validity and increment the download counter in one step (under lock),
 * so parallel requests cannot exceed the download limit.
 * Returns the updated metadata, or null if the transfer is not (or no longer) valid.
 */
function transfer_claim_download(string $token): ?array
{
    $lock = transfer_lock($token);
    if (!$lock) return null;
    try {
        $meta = transfer_load($token);
        if (!$meta || !transfer_is_valid($meta)) return null;
        $meta['download_count'] = ($meta['download_count'] ?? 0) + 1;
        return transfer_write_meta($token, $meta) ? $meta : null;
    } finally {
        transfer_unlock($lock);
    }
}

// ── Stream file ────────────────────────────────────────────────────────────

function transfer_stream_file(array $meta): void
{
    $token = $meta['token'];
    $files = $meta['files'];

    set_time_limit(0); // before ZIP creation, not after

    if (count($files) === 1) {
        // Stream a single file directly
        $f    = $files[0];
        $path = TRANSFER_BASE . '/' . $token . '/files/' . $f['stored_name'];

        // Path traversal protection
        $real = realpath($path);
        $base = realpath(TRANSFER_BASE . '/' . $token . '/files');
        if (!$real || !$base || !str_starts_with($real, $base . '/')) {
            http_response_code(500);
            die('Invalid file path.');
        }

        $disposition = 'attachment; filename="' . addslashes($f['original_name']) . '"; filename*=UTF-8\'\'' . rawurlencode($f['original_name']);
        transfer_send_range($real, 'application/octet-stream', $disposition);
        return;
    }

    // Multiple files → create ZIP (cached in the transfer directory)
    $zip_path = TRANSFER_BASE . '/' . $token . '/_download.zip';

    $zip_lock = fopen($zip_path . '.lock', 'c');
    flock($zip_lock, LOCK_EX);
    if (!file_exists($zip_path)) {
        if (!class_exists('ZipArchive')) {
            flock($zip_lock, LOCK_UN);
            fclose($zip_lock);
            http_response_code(500);
            die('ZIP creation not possible (ZipArchive not available).');
        }
        $zip = new ZipArchive();
        if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            flock($zip_lock, LOCK_UN);
            fclose($zip_lock);
            http_response_code(500);
            die('ZIP could not be created.');
        }
        foreach ($files as $f) {
            $src = TRANSFER_BASE . '/' . $token . '/files/' . $f['stored_name'];
            if (is_file($src)) {
                $zip->addFile($src, basename($f['original_name']));
            }
        }
        $zip->close();
        chmod($zip_path, 0600);
    }
    flock($zip_lock, LOCK_UN);
    fclose($zip_lock);

    $site_name = preg_replace('/[^\w\-]/u', '_', settings_load()['site_name'] ?? 'Transfer');
    $zip_name  = $site_name . '_' . date('Y-m-d', filemtime($zip_path)) . '.zip';
    transfer_send_range($zip_path, 'application/zip', 'attachment; filename="' . $zip_name . '"');
}

/**
 * Send a file with support for a single byte range (HTTP Range) so that
 * interrupted downloads can be resumed. Browsers need ETag and
 * Last-Modified to request a resume.
 */
function transfer_send_range(string $path, string $content_type, string $disposition): void
{
    $size  = filesize($path);
    $mtime = filemtime($path);
    $etag  = '"' . md5($path . '|' . $size . '|' . $mtime) . '"';
    $start = 0;
    $end   = $size - 1;
    $partial = false;

    // If-Range: only send a partial range if the file has not changed
    $if_range = $_SERVER['HTTP_IF_RANGE'] ?? '';
    $range_ok = $if_range === '' || $if_range === $etag || strtotime($if_range) === $mtime;

    if ($range_ok && preg_match('/^bytes=(\d*)-(\d*)$/', trim($_SERVER['HTTP_RANGE'] ?? ''), $m) && ($m[1] !== '' || $m[2] !== '')) {
        if ($m[1] === '') {
            $start = max(0, $size - (int)$m[2]); // bytes=-500 → the last 500 bytes
        } else {
            $start = (int)$m[1];
            if ($m[2] !== '') $end = min((int)$m[2], $size - 1);
        }
        if ($start > $end || $start >= $size) {
            http_response_code(416);
            header('Content-Range: bytes */' . $size);
            exit;
        }
        $partial = true;
    }

    $fp = fopen($path, 'rb');
    if (!$fp) { http_response_code(500); die('File could not be opened.'); }

    header('Content-Type: ' . $content_type);
    header('Content-Disposition: ' . $disposition);
    header('Accept-Ranges: bytes');
    header('ETag: ' . $etag);
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
    header('Cache-Control: private, no-cache');
    header('X-Content-Type-Options: nosniff');
    if ($partial) {
        http_response_code(206);
        header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
    }
    header('Content-Length: ' . ($end - $start + 1));

    if ($_SERVER['REQUEST_METHOD'] === 'HEAD') {
        fclose($fp);
        return;
    }

    fseek($fp, $start);
    $remaining = $end - $start + 1;
    while ($remaining > 0 && !feof($fp) && !connection_aborted()) {
        $chunk = fread($fp, min(65536, $remaining));
        if ($chunk === false || $chunk === '') break;
        echo $chunk;
        $remaining -= strlen($chunk);
        if (ob_get_level() > 0) ob_flush();
        flush();
    }
    fclose($fp);
}

// ── Signed download links (resumable downloads) ───────────────────────────────
// After the password check and counting, the recipient is redirected to a
// time-limited, HMAC-signed GET link. Only this link can be resumed (browsers
// cannot resume POST downloads) and it does not count again.

const TRANSFER_TICKET_TTL = 21600; // 6 hours

function transfer_ticket_secret(): string
{
    global $config;
    $salt = (string)($config['rate_limit_salt'] ?? '');
    return $salt !== '' ? $salt . '|dl-ticket' : '';
}

function transfer_ticket_url(string $token): ?string
{
    $secret = transfer_ticket_secret();
    if ($secret === '') return null;
    $expires = time() + TRANSFER_TICKET_TTL;
    $sig     = hash_hmac('sha256', $token . '|' . $expires, $secret);
    return APP_URL . '/dl.php?d=' . $token . '&e=' . $expires . '&s=' . $sig;
}

/** Returns 'ok', 'expired' or 'invalid'. */
function transfer_ticket_check(string $token, string $expires, string $sig): string
{
    $secret = transfer_ticket_secret();
    if ($secret === '' || !preg_match('/^[a-f0-9]{64}$/', $token) || !ctype_digit($expires)) return 'invalid';
    if (!hash_equals(hash_hmac('sha256', $token . '|' . $expires, $secret), $sig)) return 'invalid';
    return (int)$expires >= time() ? 'ok' : 'expired';
}

// ── Alle Transfers laden ──────────────────────────────────────────────────────

function transfer_get_all(): array
{
    if (!is_dir(TRANSFER_BASE)) return [];

    $transfers = [];
    $dirs = glob(TRANSFER_BASE . '/[a-f0-9]*', GLOB_ONLYDIR);
    if (!$dirs) return [];

    foreach ($dirs as $dir) {
        $token = basename($dir);
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) continue;

        $meta = transfer_load($token);
        if ($meta) {
            $transfers[] = $meta;
        }
    }

    // Neueste zuerst
    usort($transfers, fn($a, $b) => strcmp($b['created_at'], $a['created_at']));

    return $transfers;
}

// ── Transfer widerrufen ───────────────────────────────────────────────────────

function transfer_revoke(string $token): bool
{
    $lock = transfer_lock($token);
    if (!$lock) return false;
    try {
        $meta = transfer_load($token);
        if (!$meta) return false;

        $meta['revoked']    = true;
        $meta['revoked_at'] = (new DateTimeImmutable())->format('c');

        return transfer_write_meta($token, $meta);
    } finally {
        transfer_unlock($lock);
    }
}

// ── Transfer löschen ─────────────────────────────────────────────────────────

function transfer_delete(string $token): bool
{
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) return false;

    $transfer_dir = TRANSFER_BASE . '/' . $token;

    // Path-Traversal-Schutz
    $real = realpath($transfer_dir);
    $base = realpath(TRANSFER_BASE);
    if (!$real || !$base || !str_starts_with($real, $base . '/')) return false;

    return transfer_delete_dir($real);
}

function transfer_delete_dir(string $dir): bool
{
    if (!is_dir($dir)) return false;

    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $path = $dir . '/' . $item;
        if (is_dir($path)) {
            transfer_delete_dir($path);
        } else {
            unlink($path);
        }
    }
    return rmdir($dir);
}

// ── Hilfsfunktionen ───────────────────────────────────────────────────────────

function transfer_format_size(int $bytes): string
{
    if ($bytes < 1024)       return $bytes . ' B';
    if ($bytes < 1048576)    return round($bytes / 1024, 1) . ' KB';
    if ($bytes < 1073741824) return round($bytes / 1048576, 1) . ' MB';
    return round($bytes / 1073741824, 2) . ' GB';
}

function transfer_total_size(array $meta): int
{
    $total = 0;
    foreach ($meta['files'] as $f) {
        $total += (int)($f['size'] ?? 0);
    }
    return $total;
}

function transfer_download_url(string $token): string
{
    return APP_URL . '/dl.php?token=' . urlencode($token);
}

function transfer_generate_short_code(): string
{
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $code  = '';
    for ($i = 0; $i < 8; $i++) {
        $code .= $chars[random_int(0, 61)];
    }
    return $code;
}

function transfer_load_by_short_code(string $short_code): ?array
{
    if (!preg_match('/^[a-zA-Z0-9]{8}$/', $short_code)) return null;
    $base = TRANSFER_BASE;
    if (!is_dir($base)) return null;
    foreach (scandir($base) as $dir) {
        if (!preg_match('/^[a-f0-9]{64}$/', $dir)) continue;
        $meta_path = $base . '/' . $dir . '/meta.json';
        if (!is_file($meta_path)) continue;
        $json = @file_get_contents($meta_path);
        if (!$json) continue;
        $meta = json_decode($json, true);
        if (is_array($meta) && ($meta['short_code'] ?? '') === $short_code) {
            return $meta;
        }
    }
    return null;
}

// ── Settings ──────────────────────────────────────────────────────────────────

function settings_defaults(): array
{
    return [
        'site_name'     => 'Filetransfer',
        'company_name'  => 'Secure File Transfer',
        'footer_text'   => '',
        'impressum_url' => '',
        'datenschutz_url' => '',
        'has_logo'      => false,
        'logo_mime'     => null,
    ];
}

function settings_load(): array
{
    $path = TRANSFER_BASE . '/settings.json';
    if (!is_file($path)) return settings_defaults();
    $json = file_get_contents($path);
    if ($json === false) return settings_defaults();
    $data = json_decode($json, true);
    if (!is_array($data)) return settings_defaults();
    return array_merge(settings_defaults(), $data);
}

function settings_save(array $data): bool
{
    $path  = TRANSFER_BASE . '/settings.json';

    // Only allow http(s) links (no javascript:, data: etc.)
    $clean_url = function (string $key) use ($data): string {
        $url = mb_substr(trim((string)($data[$key] ?? '')), 0, 500);
        return preg_match('#^https?://#i', $url) ? $url : '';
    };

    $clean = [
        'site_name'     => mb_substr(trim((string)($data['site_name']     ?? '')), 0, 60) ?: 'Filetransfer',
        'company_name'  => mb_substr(trim((string)($data['company_name']  ?? '')), 0, 100),
        'footer_text'   => mb_substr(trim((string)($data['footer_text']   ?? '')), 0, 300),
        'impressum_url' => $clean_url('impressum_url'),
        'datenschutz_url' => $clean_url('datenschutz_url'),
        'has_logo'      => (bool)($data['has_logo']  ?? false),
        'logo_mime'     => isset($data['logo_mime']) ? (string)$data['logo_mime'] : null,
    ];
    $written = file_put_contents($path, json_encode($clean, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
    if ($written !== false) {
        chmod($path, 0600);
        return true;
    }
    return false;
}
