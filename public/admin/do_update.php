<?php
/**
 * In-App Update-Endpoint — wird per AJAX vom Dashboard aufgerufen.
 */

require_once __DIR__ . '/version.php';

umask(0077);
ini_set('display_errors', 0);

require_once dirname(dirname(__DIR__)) . '/functions/bootstrap.php';

header('Content-Type: application/json');

// Nur POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Auth + CSRF
auth_session_start();
if (empty($_SESSION['admin_logged_in'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Nicht eingeloggt']);
    exit;
}
csrf_verify();

// Aktuelles Release von GitHub holen
$ctx  = stream_context_create(['http' => [
    'header'  => "User-Agent: jcapps-transfer/" . APP_VERSION . "\r\n",
    'timeout' => 10,
]]);
$json = @file_get_contents('https://api.github.com/repos/jcapps-dev/jcapps-transfer/releases/latest', false, $ctx);
if (!$json) {
    echo json_encode(['success' => false, 'error' => 'GitHub konnte nicht erreicht werden.']);
    exit;
}

$release = json_decode($json, true);
$zip_url = $release['zipball_url'] ?? null;
$latest  = ltrim($release['tag_name'] ?? '', 'v');

if (!$zip_url) {
    echo json_encode(['success' => false, 'error' => 'Kein Release gefunden.']);
    exit;
}

if (!version_compare($latest, APP_VERSION, '>')) {
    echo json_encode(['success' => false, 'error' => 'Bereits aktuell (' . APP_VERSION . ').']);
    exit;
}

// ZIP herunterladen
$tmp = sys_get_temp_dir() . '/jcapps-transfer-update-' . time() . '.zip';
if (!_do_download($zip_url, $tmp)) {
    echo json_encode(['success' => false, 'error' => 'Download fehlgeschlagen.']);
    exit;
}

// Entpacken
$app_root  = dirname(dirname(__DIR__));
$protected = ['config.php'];

if (!_do_extract($tmp, $app_root, $protected)) {
    @unlink($tmp);
    echo json_encode(['success' => false, 'error' => 'Entpacken fehlgeschlagen. Schreibrechte prüfen.']);
    exit;
}

@unlink($tmp);

// Update-Cache leeren
@unlink(TRANSFER_BASE . '/update_check.json');

echo json_encode(['success' => true, 'version' => $latest]);

// ── Hilfsfunktionen ────────────────────────────────────────────────────────

function _do_download(string $url, string $dest): bool {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        $fp = fopen($dest, 'wb');
        curl_setopt_array($ch, [
            CURLOPT_FILE           => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT      => 'jcapps-transfer-updater',
            CURLOPT_TIMEOUT        => 60,
        ]);
        $ok  = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        fclose($fp);
        return $ok && !$err;
    }
    $data = @file_get_contents($url);
    return $data !== false && file_put_contents($dest, $data) !== false;
}

function _do_extract(string $zip_path, string $app_root, array $protected): bool {
    if (!class_exists('ZipArchive')) return false;

    $zip = new ZipArchive();
    if ($zip->open($zip_path) !== true) return false;

    // Temp-Verzeichnis im App-Root (dort hat PHP garantiert Schreibrechte)
    $tmp_dir = rtrim($app_root, '/') . '/_update_tmp_' . time();
    @mkdir($tmp_dir, 0755, true);

    if (!$zip->extractTo($tmp_dir)) {
        $zip->close();
        _do_rmdir($tmp_dir);
        return false;
    }
    $zip->close();

    // GitHub-Archive haben ein Top-Level-Verzeichnis — eine Ebene überspringen
    $items = array_values(array_diff(scandir($tmp_dir), ['.', '..']));
    $source = (count($items) === 1 && is_dir($tmp_dir . '/' . $items[0]))
        ? $tmp_dir . '/' . $items[0]
        : $tmp_dir;

    _do_copy($source, rtrim($app_root, '/'), $protected);
    _do_rmdir($tmp_dir);

    return true;
}

function _do_copy(string $src, string $dest, array $skip = []): void {
    foreach (scandir($src) as $item) {
        if ($item === '.' || $item === '..') continue;
        if (in_array($item, $skip, true)) continue;
        // Eigenes Temp-Verzeichnis nicht kopieren
        if (str_starts_with($item, '_update_tmp_')) continue;
        $s = $src  . '/' . $item;
        $d = $dest . '/' . $item;
        if (is_dir($s)) {
            @mkdir($d, 0755, true);
            _do_copy($s, $d);
        } else {
            @copy($s, $d);
        }
    }
}

function _do_rmdir(string $dir): void {
    if (!is_dir($dir)) return;
    foreach (scandir($dir) as $f) {
        if ($f === '.' || $f === '..') continue;
        $p = $dir . '/' . $f;
        is_dir($p) ? _do_rmdir($p) : @unlink($p);
    }
    @rmdir($dir);
}
