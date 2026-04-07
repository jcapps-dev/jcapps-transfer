<?php
/**
 * jcapps-transfer — Setup-Assistent
 * Wird nach install.php automatisch aufgerufen.
 * Löscht sich nach erfolgreicher Einrichtung selbst.
 */

// Schon eingerichtet?
$_config = dirname(__DIR__) . '/config.php';
if (is_file($_config)) {
    header('Location: admin/dashboard.php');
    exit;
}

$errors  = [];
$success = false;

// Standardwerte
$doc_root     = rtrim($_SERVER['DOCUMENT_ROOT'] ?? __DIR__, '/');
$default_path = dirname($doc_root) . '/transfers';
$default_url  = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
              . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password   = $_POST['password']   ?? '';
    $password2  = $_POST['password2']  ?? '';
    $base_path  = rtrim(trim($_POST['base_path']  ?? ''), '/');
    $app_url    = rtrim(trim($_POST['app_url']    ?? ''), '/');
    $site_name  = trim($_POST['site_name'] ?? 'Filetransfer');

    if (strlen($password) < 8) {
        $errors[] = 'Das Passwort muss mindestens 8 Zeichen lang sein.';
    }
    if ($password !== $password2) {
        $errors[] = 'Die Passwörter stimmen nicht überein.';
    }
    if (!$base_path) {
        $errors[] = 'Bitte einen Pfad für die Uploads angeben.';
    }
    if (!$app_url) {
        $errors[] = 'Bitte die URL der App angeben.';
    }

    if (!$errors) {
        // Verzeichnisse anlegen
        $dirs = [$base_path, $base_path . '/logs', $base_path . '/logs/ratelimit'];
        foreach ($dirs as $dir) {
            if (!is_dir($dir) && !@mkdir($dir, 0700, true)) {
                $errors[] = 'Verzeichnis konnte nicht angelegt werden: ' . htmlspecialchars($dir, ENT_QUOTES, 'UTF-8');
                break;
            }
        }
    }

    if (!$errors) {
        // Config schreiben
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $salt = bin2hex(random_bytes(24));
        $session_name = 'JCAPPS_FT_' . strtoupper(substr(md5($app_url), 0, 6));

        $config_content = <<<PHP
        <?php
        /**
         * jcapps-transfer Konfiguration
         * Generiert von setup.php am <?= date('Y-m-d H:i') ?>

         *
         * ACHTUNG: Diese Datei enthält sensible Daten.
         * Nicht in Git committen — ist bereits in .gitignore.
         */
        return [
            'transfer_base_path'     => '$base_path',
            'admin_password_hash'    => '$hash',
            'max_filesize_mb'        => 1024,
            'max_files_per_upload'   => 10,
            'transfer_lifetime_days' => 14,
            'rate_limit_salt'        => '$salt',
            'app_url'                => '$app_url',
            'session_name'           => '$session_name',
        ];
        PHP;

        // Einrückung entfernen (Heredoc-Whitespace)
        $config_content = preg_replace('/^        /m', '', $config_content);
        $config_content = "<?php\n" . ltrim(preg_replace('/^<\?php\n/', '', $config_content));

        // Initiale Settings-Datei anlegen
        $settings = [
            'site_name'    => $site_name,
            'company_name' => '',
            'footer_text'  => '',
            'impressum_url'=> '',
            'has_logo'     => false,
            'logo_mime'    => null,
            'updated'      => date('c'),
        ];
        @file_put_contents($base_path . '/settings.json', json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        @chmod($base_path . '/settings.json', 0600);

        if (file_put_contents($_config, $config_content) === false) {
            $errors[] = 'Konfigurationsdatei konnte nicht geschrieben werden. Bitte Schreibrechte prüfen.';
        } else {
            @chmod($_config, 0600);
            $success = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>jcapps-transfer einrichten</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f3f4f6; margin: 0; padding: 2rem 1rem; color: #111827; }
        .wrap { max-width: 540px; margin: 0 auto; }
        .logo { display: flex; align-items: center; gap: 10px; margin-bottom: 2rem; }
        .logo svg { color: #1e3a5f; }
        .logo span { font-size: 1.25rem; font-weight: 700; color: #1e3a5f; }
        .card { background: #fff; border-radius: 8px; box-shadow: 0 1px 4px rgba(0,0,0,.08); padding: 2rem; }
        h1 { font-size: 1.25rem; margin: 0 0 .25rem; }
        .sub { color: #6b7280; font-size: .875rem; margin: 0 0 1.75rem; }
        .form-group { margin-bottom: 1.25rem; }
        label { display: block; font-size: .875rem; font-weight: 600; margin-bottom: .375rem; }
        input[type=text], input[type=url], input[type=password] {
            width: 100%; border: 1px solid #d1d5db; border-radius: 6px;
            padding: .5rem .75rem; font-size: .9rem; color: #111827;
        }
        input:focus { outline: none; border-color: #1e3a5f; box-shadow: 0 0 0 2px rgba(30,58,95,.15); }
        .hint { font-size: .8rem; color: #6b7280; margin-top: .375rem; }
        .btn { display: inline-flex; align-items: center; gap: 6px; background: #1e3a5f; color: #fff; border: none; border-radius: 6px; padding: .65rem 1.25rem; font-size: .9rem; font-weight: 600; cursor: pointer; }
        .error-box { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; border-radius: 6px; padding: .75rem 1rem; margin-bottom: 1.25rem; font-size: .875rem; }
        .error-box ul { margin: .25rem 0 0; padding-left: 1.25rem; }
        .success-box { background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; border-radius: 6px; padding: 1.25rem; text-align: center; }
        .success-box h2 { margin: 0 0 .5rem; font-size: 1.1rem; }
        .success-box p { margin: 0 0 1rem; font-size: .9rem; }
        .version { font-size: .8rem; color: #9ca3af; margin-top: 1.5rem; text-align: center; }
        .version a { color: inherit; }
        hr { border: none; border-top: 1px solid #e5e7eb; margin: 1.5rem 0; }
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
            <h2>Einrichtung abgeschlossen!</h2>
            <p>jcapps-transfer ist bereit. Du kannst dich jetzt einloggen.</p>
            <a href="admin/index.php" class="btn">Zum Admin-Login</a>
        </div>
        <?php else: ?>

        <h1>Einrichtung</h1>
        <p class="sub">Gib dein Admin-Passwort und den Speicherort für Uploads an — fertig.</p>

        <?php if ($errors): ?>
        <div class="error-box">
            <strong>Bitte korrigieren:</strong>
            <ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></li><?php endforeach; ?></ul>
        </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="site_name">Name der App</label>
                <input type="text" id="site_name" name="site_name" maxlength="60"
                       value="<?= htmlspecialchars($_POST['site_name'] ?? 'Filetransfer', ENT_QUOTES, 'UTF-8') ?>">
                <div class="hint">Wird in der Sidebar angezeigt.</div>
            </div>

            <hr>

            <div class="form-group">
                <label for="password">Admin-Passwort</label>
                <input type="password" id="password" name="password" autocomplete="new-password">
                <div class="hint">Mindestens 8 Zeichen. Wird verschlüsselt gespeichert.</div>
            </div>
            <div class="form-group">
                <label for="password2">Passwort wiederholen</label>
                <input type="password" id="password2" name="password2" autocomplete="new-password">
            </div>

            <hr>

            <div class="form-group">
                <label for="app_url">URL der App</label>
                <input type="url" id="app_url" name="app_url" maxlength="500"
                       value="<?= htmlspecialchars($_POST['app_url'] ?? $default_url, ENT_QUOTES, 'UTF-8') ?>">
                <div class="hint">Öffentliche Adresse deiner Installation, z.B. <code>https://transfer.meinefirma.de</code></div>
            </div>

            <div class="form-group">
                <label for="base_path">Upload-Verzeichnis (absoluter Pfad)</label>
                <input type="text" id="base_path" name="base_path" maxlength="500"
                       value="<?= htmlspecialchars($_POST['base_path'] ?? $default_path, ENT_QUOTES, 'UTF-8') ?>">
                <div class="hint">Wo Dateien gespeichert werden. Idealerweise <strong>außerhalb</strong> des öffentlichen Webverzeichnisses. Das Verzeichnis wird automatisch angelegt.</div>
            </div>

            <button type="submit" class="btn">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                Einrichtung abschließen
            </button>
        </form>

        <?php endif; ?>
    </div>

    <p class="version"><a href="https://jcapps.dev" target="_blank" rel="noopener">jcapps.dev</a></p>
</div>

<?php if ($success): ?>
<script>
    // setup.php nach Weiterleitung löschen (serverseitig via AJAX)
    setTimeout(() => fetch('setup.php?_cleanup=1').catch(()=>{}), 2000);
</script>
<?php endif; ?>
<?php
// Selbst löschen nach erfolgreicher Einrichtung
if ($success && ($_GET['_cleanup'] ?? '') === '1') {
    @unlink(__FILE__);
    exit;
}
?>
</body>
</html>
