<?php
/**
 * jcapps-transfer — Updater
 *
 * Lädt das aktuelle Release von GitHub herunter und aktualisiert alle App-Dateien.
 * Konfiguration, Uploads und Logs bleiben unangetastet.
 *
 * Nutzung:
 *   1. Diese Datei herunterladen und in das Webverzeichnis hochladen
 *   2. Im Browser aufrufen und Admin-Passwort eingeben
 */

define('GITHUB_REPO', 'jcapps-dev/jcapps-transfer');
define('GITHUB_API',  'https://api.github.com/repos/' . GITHUB_REPO . '/releases/latest');

// App-Root ermitteln (eine Ebene über public/)
$app_root   = dirname(__DIR__);
$config_file = $app_root . '/config.php';

// Nicht installiert?
if (!is_file($config_file)) {
    header('Location: setup.php');
    exit;
}

// Bootstrap für Auth
require_once $app_root . '/functions/bootstrap.php';

$error   = '';
$success = false;
$latest  = null;

// Aktuelle Version
if (!defined('APP_VERSION')) {
    @include $app_root . '/public/admin/version.php';
}
$current_version = defined('APP_VERSION') ? APP_VERSION : '?';

// Aktuelles Release von GitHub abrufen
$ctx     = stream_context_create(['http' => ['header' => "User-Agent: jcapps-transfer-updater\r\n", 'timeout' => 8]]);
$release_json = @file_get_contents(GITHUB_API, false, $ctx);
if ($release_json) {
    $latest = json_decode($release_json, true);
}
$latest_version = ltrim($latest['tag_name'] ?? '', 'v');
$up_to_date     = $latest_version && version_compare($latest_version, $current_version, '<=');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Auth: Admin-Passwort prüfen
    $password = $_POST['password'] ?? '';
    $config   = require $config_file;

    if (!password_verify($password, $config['admin_password_hash'])) {
        $error = 'Falsches Passwort.';
    } elseif (!$latest) {
        $error = 'GitHub konnte nicht erreicht werden.';
    } else {
        $zip_url = $latest['zipball_url'];
        $tmp     = sys_get_temp_dir() . '/jcapps-transfer-update-' . time() . '.zip';

        if (!_update_download($zip_url, $tmp)) {
            $error = 'Download fehlgeschlagen.';
        } else {
            $result = _update_extract($tmp, $app_root);
            @unlink($tmp);

            if (!$result) {
                $error = 'Update fehlgeschlagen. Bitte Schreibrechte prüfen.';
            } else {
                // Update-Check-Cache leeren damit neue Version sofort erkannt wird
                @unlink(TRANSFER_BASE . '/update_check.json');
                $success = true;
            }
        }
    }
}

function _update_download(string $url, string $dest): bool {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        $fp = fopen($dest, 'wb');
        curl_setopt_array($ch, [
            CURLOPT_FILE => $fp, CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => 'jcapps-transfer-updater', CURLOPT_TIMEOUT => 60,
        ]);
        $ok = curl_exec($ch); $err = curl_error($ch);
        curl_close($ch); fclose($fp);
        return $ok && !$err;
    }
    $data = @file_get_contents($url);
    return $data !== false && file_put_contents($dest, $data) !== false;
}

function _update_extract(string $zip_path, string $app_root): bool {
    // Dateien die beim Update NICHT überschrieben werden
    $protected = ['config.php', 'install.php'];

    $zip = new ZipArchive();
    if ($zip->open($zip_path) !== true) return false;

    $top    = $zip->getNameIndex(0);
    $prefix = strpos($top, '/') !== false ? substr($top, 0, strpos($top, '/') + 1) : '';

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name     = $zip->getNameIndex($i);
        $relative = $prefix ? substr($name, strlen($prefix)) : $name;
        if (!$relative || str_ends_with($name, '/')) continue;
        if (in_array($relative, $protected, true)) continue;

        $target = rtrim($app_root, '/') . '/' . $relative;
        @mkdir(dirname($target), 0755, true);

        // Temporär extrahieren dann verschieben
        $tmp_dir = sys_get_temp_dir() . '/jcapps_update_' . getmypid();
        @mkdir($tmp_dir, 0755, true);
        $zip->extractTo($tmp_dir, $name);
        $extracted = $tmp_dir . '/' . $name;
        if (is_file($extracted)) {
            rename($extracted, $target);
        }
    }
    $zip->close();
    return true;
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>jcapps-transfer aktualisieren</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f3f4f6; margin: 0; padding: 2rem 1rem; color: #111827; }
        .wrap { max-width: 500px; margin: 0 auto; }
        .logo { display: flex; align-items: center; gap: 10px; margin-bottom: 2rem; }
        .logo svg { color: #1e3a5f; }
        .logo span { font-size: 1.25rem; font-weight: 700; color: #1e3a5f; }
        .card { background: #fff; border-radius: 8px; box-shadow: 0 1px 4px rgba(0,0,0,.08); padding: 2rem; }
        h1 { font-size: 1.25rem; margin: 0 0 .25rem; }
        .sub { color: #6b7280; font-size: .875rem; margin: 0 0 1.5rem; }
        .versions { display: flex; gap: 1.5rem; margin-bottom: 1.5rem; }
        .ver-box { flex: 1; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; padding: .75rem 1rem; }
        .ver-label { font-size: .75rem; color: #6b7280; margin-bottom: .25rem; }
        .ver-number { font-size: 1.1rem; font-weight: 700; color: #111827; font-family: monospace; }
        .ver-number.new { color: #16a34a; }
        .form-group { margin-bottom: 1.25rem; }
        label { display: block; font-size: .875rem; font-weight: 600; margin-bottom: .375rem; }
        input[type=password] { width: 100%; border: 1px solid #d1d5db; border-radius: 6px; padding: .5rem .75rem; font-size: .9rem; }
        input:focus { outline: none; border-color: #1e3a5f; box-shadow: 0 0 0 2px rgba(30,58,95,.15); }
        .btn { display: inline-flex; align-items: center; gap: 6px; background: #1e3a5f; color: #fff; border: none; border-radius: 6px; padding: .65rem 1.25rem; font-size: .9rem; font-weight: 600; cursor: pointer; }
        .btn-ghost { background: none; color: #374151; border: 1px solid #d1d5db; }
        .error-box { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; border-radius: 6px; padding: .75rem 1rem; margin-bottom: 1rem; font-size: .875rem; }
        .info-box  { background: #eff6ff; border: 1px solid #bfdbfe; color: #1e40af; border-radius: 6px; padding: .75rem 1rem; margin-bottom: 1rem; font-size: .875rem; }
        .success-box { background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; border-radius: 6px; padding: 1.25rem; text-align: center; }
        .success-box h2 { margin: 0 0 .5rem; }
        .success-box p { margin: 0 0 1rem; font-size: .9rem; }
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

        <?php if ($success): ?>
        <div class="success-box">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom:.75rem;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            <h2>Update erfolgreich!</h2>
            <p>jcapps-transfer wurde auf Version <strong><?= htmlspecialchars($latest_version, ENT_QUOTES, 'UTF-8') ?></strong> aktualisiert.<br>Deine Konfiguration und alle Uploads sind unverändert.</p>
            <a href="admin/dashboard.php" class="btn">Zum Dashboard</a>
        </div>
        <?php else: ?>

        <h1>Update</h1>
        <p class="sub">Alle App-Dateien werden aktualisiert. Deine Konfiguration, Uploads und Logs bleiben erhalten.</p>

        <div class="versions">
            <div class="ver-box">
                <div class="ver-label">Installiert</div>
                <div class="ver-number"><?= htmlspecialchars($current_version, ENT_QUOTES, 'UTF-8') ?></div>
            </div>
            <div class="ver-box">
                <div class="ver-label">Verfügbar</div>
                <div class="ver-number new"><?= $latest_version ? htmlspecialchars($latest_version, ENT_QUOTES, 'UTF-8') : '–' ?></div>
            </div>
        </div>

        <?php if ($error): ?>
        <div class="error-box"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <?php if ($up_to_date): ?>
        <div class="info-box">Du verwendest bereits die aktuelle Version. Ein Update ist nicht nötig.</div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="password">Admin-Passwort zur Bestätigung</label>
                <input type="password" id="password" name="password" autocomplete="current-password">
            </div>
            <div style="display:flex;gap:.75rem;">
                <button type="submit" class="btn">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-3.5"/></svg>
                    Update installieren
                </button>
                <a href="admin/dashboard.php" class="btn btn-ghost">Abbrechen</a>
            </div>
        </form>

        <?php endif; ?>
    </div>

    <p class="version"><a href="https://jcapps.dev" target="_blank" rel="noopener">jcapps.dev</a></p>
</div>

<?php if ($success): ?>
<script>
    // update.php nach Erfolg löschen
    setTimeout(() => fetch('update.php?_cleanup=1').catch(()=>{}), 3000);
</script>
<?php endif; ?>
<?php
if ($success && ($_GET['_cleanup'] ?? '') === '1') {
    @unlink(__FILE__);
    exit;
}
?>
</body>
</html>
