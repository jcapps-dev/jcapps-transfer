<?php
/**
 * jcapps-transfer — Installer
 *
 * Diese Datei lädt das aktuelle Release von GitHub herunter
 * und startet anschließend den Setup-Assistenten.
 *
 * Nutzung:
 *   1. Diese Datei in das Webverzeichnis hochladen
 *   2. Im Browser aufrufen: https://deine-domain.de/install.php
 */

// Schon installiert?
if (is_file(__DIR__ . '/config.php') || is_file(__DIR__ . '/public/admin/index.php')) {
    header('Location: public/admin/dashboard.php');
    exit;
}

define('GITHUB_REPO', 'jcapps-dev/jcapps-transfer');
define('GITHUB_API',  'https://api.github.com/repos/' . GITHUB_REPO . '/releases/latest');

$error   = '';
$release = null;

// Requirements
$reqs = [
    'PHP 8.0+'    => version_compare(PHP_VERSION, '8.0.0', '>='),
    'ZipArchive'  => class_exists('ZipArchive'),
    'Download'    => function_exists('curl_init') || (bool) ini_get('allow_url_fopen'),
    'Schreibrecht' => is_writable(__DIR__),
];
$all_ok = !in_array(false, $reqs, true);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $all_ok) {
    $release = _fetch_latest_release();
    if (!$release) {
        $error = 'GitHub konnte nicht erreicht werden. Bitte Internetverbindung prüfen und erneut versuchen.';
    } else {
        $zip_url = $release['zipball_url'];
        $tmp     = sys_get_temp_dir() . '/jcapps-transfer-' . time() . '.zip';

        if (!_download($zip_url, $tmp)) {
            $error = 'Download fehlgeschlagen. Bitte erneut versuchen.';
        } else {
            if (!_extract($tmp, __DIR__)) {
                $error = 'Entpacken fehlgeschlagen. Bitte Schreibrechte im Verzeichnis prüfen.';
            } else {
                @unlink($tmp);
                // install.php nach dem Redirect löschen
                header('Location: setup.php');
                ob_start();
                register_shutdown_function(function () {
                    @unlink(__FILE__);
                });
                exit;
            }
            @unlink($tmp);
        }
    }
}

function _fetch_latest_release(): ?array {
    $opts = ['http' => ['header' => "User-Agent: jcapps-transfer-installer\r\n", 'timeout' => 10]];
    $json = @file_get_contents(GITHUB_API, false, stream_context_create($opts));
    if (!$json) return null;
    $data = json_decode($json, true);
    return is_array($data) ? $data : null;
}

function _download(string $url, string $dest): bool {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        $fp = fopen($dest, 'wb');
        curl_setopt_array($ch, [
            CURLOPT_FILE           => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT      => 'jcapps-transfer-installer',
            CURLOPT_TIMEOUT        => 60,
        ]);
        $ok  = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        fclose($fp);
        return $ok && !$err;
    }
    $data = @file_get_contents($url);
    if ($data === false) return false;
    return file_put_contents($dest, $data) !== false;
}

function _extract(string $zip_path, string $dest): bool {
    $zip = new ZipArchive();
    if ($zip->open($zip_path) !== true) return false;

    // Alles in temporäres Verzeichnis entpacken
    $tmp_dir = sys_get_temp_dir() . '/jcapps_install_' . time();
    @mkdir($tmp_dir, 0755, true);

    if (!$zip->extractTo($tmp_dir)) {
        $zip->close();
        _rmdir_recursive($tmp_dir);
        return false;
    }
    $zip->close();

    // GitHub-Archive haben ein Top-Level-Verzeichnis — eine Ebene überspringen
    $items = array_values(array_diff(scandir($tmp_dir), ['.', '..']));
    $source = (count($items) === 1 && is_dir($tmp_dir . '/' . $items[0]))
        ? $tmp_dir . '/' . $items[0]
        : $tmp_dir;

    // Dateien ans Ziel kopieren (install.php nicht überschreiben)
    _copy_dir($source, rtrim($dest, '/'), ['install.php']);
    _rmdir_recursive($tmp_dir);

    return true;
}

function _copy_dir(string $src, string $dest, array $skip = []): void {
    foreach (scandir($src) as $item) {
        if ($item === '.' || $item === '..') continue;
        if (in_array($item, $skip, true)) continue;
        $s = $src  . '/' . $item;
        $d = $dest . '/' . $item;
        if (is_dir($s)) {
            @mkdir($d, 0755, true);
            _copy_dir($s, $d);
        } else {
            @copy($s, $d);
        }
    }
}

function _rmdir_recursive(string $dir): void {
    if (!is_dir($dir)) return;
    foreach (scandir($dir) as $f) {
        if ($f === '.' || $f === '..') continue;
        $p = $dir . '/' . $f;
        is_dir($p) ? _rmdir_recursive($p) : @unlink($p);
    }
    @rmdir($dir);
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>jcapps-transfer installieren</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f3f4f6; margin: 0; padding: 2rem 1rem; color: #111827; }
        .wrap { max-width: 520px; margin: 0 auto; }
        .logo { display: flex; align-items: center; gap: 10px; margin-bottom: 2rem; }
        .logo svg { color: #1e3a5f; }
        .logo span { font-size: 1.25rem; font-weight: 700; color: #1e3a5f; }
        .card { background: #fff; border-radius: 8px; box-shadow: 0 1px 4px rgba(0,0,0,.08); padding: 2rem; }
        h1 { font-size: 1.25rem; margin: 0 0 .5rem; }
        .sub { color: #6b7280; font-size: .9rem; margin: 0 0 1.5rem; }
        .req-list { list-style: none; padding: 0; margin: 0 0 1.5rem; display: flex; flex-direction: column; gap: .5rem; }
        .req-list li { display: flex; align-items: center; gap: .5rem; font-size: .9rem; }
        .ok  { color: #16a34a; }
        .fail{ color: #dc2626; }
        .btn { display: inline-flex; align-items: center; gap: 6px; background: #1e3a5f; color: #fff; border: none; border-radius: 6px; padding: .6rem 1.25rem; font-size: .9rem; font-weight: 600; cursor: pointer; text-decoration: none; }
        .btn:disabled { opacity: .5; cursor: not-allowed; }
        .error { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; border-radius: 6px; padding: .75rem 1rem; margin-bottom: 1rem; font-size: .875rem; }
        .version { font-size: .8rem; color: #9ca3af; margin-top: 1.5rem; text-align: center; }
        .version a { color: inherit; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="logo">
        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
        <span>jcapps-transfer</span>
    </div>

    <div class="card">
        <h1>Installation</h1>
        <p class="sub">Die aktuelle Version wird von GitHub heruntergeladen und entpackt. Danach startet der Setup-Assistent.</p>

        <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <ul class="req-list">
        <?php foreach ($reqs as $label => $ok): ?>
            <li>
                <?php if ($ok): ?>
                <svg class="ok" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                <?php else: ?>
                <svg class="fail" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                <?php endif; ?>
                <span><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span>
                <?php if (!$ok): ?><span class="fail" style="font-size:.8rem;">— nicht erfüllt</span><?php endif; ?>
            </li>
        <?php endforeach; ?>
        </ul>

        <?php if ($all_ok): ?>
        <form method="POST">
            <button type="submit" class="btn">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="8 17 12 21 16 17"/><line x1="12" y1="12" x2="12" y2="21"/><path d="M20.88 18.09A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.36"/></svg>
                Jetzt installieren
            </button>
        </form>
        <?php else: ?>
        <p style="color:#dc2626;font-size:.875rem;">Bitte die fehlenden Voraussetzungen erfüllen und die Seite neu laden.</p>
        <?php endif; ?>
    </div>

    <p class="version"><a href="https://jcapps.dev" target="_blank" rel="noopener">jcapps.dev</a></p>
</div>
</body>
</html>
