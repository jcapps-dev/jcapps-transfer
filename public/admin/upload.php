<?php
/**
 * Neuer Transfer — Datei hochladen und Download-Link generieren
 */

umask(0077);
ini_set('display_errors', 0);

require_once dirname(dirname(__DIR__)) . '/functions/bootstrap.php';

auth_session_start();
auth_check();

$settings    = settings_load();
$error       = '';
$result      = null; // Nach erfolgreichem Upload: ['token' => ..., 'url' => ...]
$max_mb      = (int)($config['max_filesize_mb'] ?? 200);
$max_files   = (int)($config['max_files_per_upload'] ?? 10);
$lifetime    = (int)($config['transfer_lifetime_days'] ?? 14);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    // Dateien aus $_FILES normalisieren
    $raw = $_FILES['files'] ?? null;
    if (!$raw || empty($raw['name'][0])) {
        $error = 'Bitte mindestens eine Datei auswählen.';
    } else {
        // Normalize: immer als Array
        $file_count = is_array($raw['name']) ? count($raw['name']) : 1;

        if ($file_count > $max_files) {
            $error = "Maximal {$max_files} Dateien pro Transfer erlaubt.";
        } else {
            $files      = [];
            $total_size = 0;

            for ($i = 0; $i < $file_count; $i++) {
                $name     = is_array($raw['name'])     ? $raw['name'][$i]     : $raw['name'];
                $tmp      = is_array($raw['tmp_name']) ? $raw['tmp_name'][$i] : $raw['tmp_name'];
                $size     = is_array($raw['size'])     ? $raw['size'][$i]     : $raw['size'];
                $err_code = is_array($raw['error'])    ? $raw['error'][$i]    : $raw['error'];

                if ($err_code === UPLOAD_ERR_NO_FILE) continue;

                if ($err_code !== UPLOAD_ERR_OK) {
                    $error = "Upload-Fehler bei Datei " . ($i + 1) . " (Code: {$err_code}).";
                    break;
                }

                $total_size += $size;
                $files[] = ['name' => $name, 'tmp_path' => $tmp, 'size' => $size];
            }

            if (!$error && empty($files)) {
                $error = 'Keine gültige Datei hochgeladen.';
            }

            if (!$error && $total_size > $max_mb * 1048576) {
                $error = "Gesamtgröße überschreitet {$max_mb} MB.";
            }

            if (!$error) {
                $password      = $_POST['password'] ?? '';
                $max_downloads = $_POST['max_downloads'] !== '' ? (int)$_POST['max_downloads'] : null;
                $custom_days   = isset($_POST['lifetime_days']) && (int)$_POST['lifetime_days'] > 0
                    ? min((int)$_POST['lifetime_days'], 365)
                    : $lifetime;

                if ($max_downloads !== null && $max_downloads < 1) {
                    $error = 'Maximale Downloads muss mindestens 1 sein.';
                }
            }

            if (!$error) {
                $res = transfer_create(
                    $files,
                    $password !== '' ? $password : null,
                    $max_downloads ?? null,
                    $custom_days
                );

                if (isset($res['error'])) {
                    $error = $res['error'];
                } else {
                    log_event('transfer_created', [
                        'files'    => count($files),
                        'size'     => $total_size,
                        'has_pw'   => $password !== '',
                        'max_dl'   => $max_downloads,
                        'days'     => $custom_days,
                    ]);
                    $result = [
                        'token'    => $res['token'],
                        'url'      => transfer_download_url($res['token']),
                        'password' => $password,
                    ];
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Neuer Transfer – <?= htmlspecialchars($settings['site_name'], ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="../assets/style.css">
    <meta name="max-files" content="<?= (int)$max_files ?>">
    <meta name="max-mb" content="<?= (int)$max_mb ?>">
</head>
<body>
<div class="app-layout">

    <!-- Sidebar -->
    <aside class="app-sidebar">
        <div class="sidebar-header">
            <a class="sidebar-logo" href="dashboard.php">
                <span class="logo-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg></span>
                <span class="logo-text"><?= htmlspecialchars($settings['site_name'], ENT_QUOTES, 'UTF-8') ?></span>
            </a>
        </div>
        <nav class="sidebar-nav">
            <a class="sidebar-link" href="dashboard.php">
                <span class="link-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg></span>
                <span class="link-text">Übersicht</span>
            </a>
            <a class="sidebar-link active" href="upload.php">
                <span class="link-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 16 12 12 8 16"/><line x1="12" y1="12" x2="12" y2="21"/><path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"/></svg></span>
                <span class="link-text">Neuer Transfer</span>
            </a>
        </nav>
        <div class="sidebar-footer">
            <a class="sidebar-link" href="logout.php" title="Ausloggen">
                <span class="link-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg></span>
                <span class="link-text">Ausloggen</span>
            </a>
            <a class="sidebar-link" href="settings.php">
                <span class="link-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg></span>
                <span class="link-text">Einstellungen</span>
            </a>
        </div>
    </aside>

    <!-- Content -->
    <div class="app-content">
        <header class="app-toolbar">
            <div class="toolbar-left">
                <div class="breadcrumbs">
                    <a href="dashboard.php" style="color:var(--color-gray-600);text-decoration:none;">Transfers</a>
                    <span class="breadcrumb-sep">›</span>
                    <span class="breadcrumb-item">Neuer Transfer</span>
                </div>
            </div>
            <div class="toolbar-right">
                <a href="dashboard.php" class="toolbar-btn">← Zurück</a>
            </div>
        </header>

        <main class="app-main">
            <div class="container">

                <?php if ($result): ?>
                <!-- Erfolg: Link anzeigen -->
                <div class="msg msg-success">Transfer erfolgreich erstellt.</div>

                <div class="card">
                    <div class="card-header">
                        <h2 style="margin:0;"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -3px;"><polyline points="20 6 9 17 4 12"/></svg> Download-Link</h2>
                    </div>

                    <div class="link-box">
                        <div class="link-box-label">Download-URL (per E-Mail verschicken)</div>
                        <div class="link-box-url" id="dl-url"><?= htmlspecialchars($result['url'], ENT_QUOTES, 'UTF-8') ?></div>
                        <button id="copy-url-btn" class="btn btn-sm"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -2px;"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg> Link kopieren</button>
                    </div>

                    <?php if ($result['password'] !== ''): ?>
                    <div class="link-box" style="background: color-mix(in srgb, var(--color-pastel-yellow) 10%, white); border-color: var(--color-pastel-yellow);">
                        <div class="link-box-label"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -2px;"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg> Passwort (einmalig angezeigt — jetzt notieren!)</div>
                        <div class="link-box-url" style="font-family:'JetBrains Mono',monospace; letter-spacing:0.1em;">
                            <?= htmlspecialchars($result['password'], ENT_QUOTES, 'UTF-8') ?>
                        </div>
                        <button id="copy-pw-btn" class="btn btn-sm btn-warning"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -2px;"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg> Passwort kopieren</button>
                    </div>
                    <?php endif; ?>

                    <div style="margin-top: var(--spacing-md); display:flex; gap: var(--spacing-sm);">
                        <a href="upload.php" class="btn btn-ghost"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -2px;"><polyline points="16 16 12 12 8 16"/><line x1="12" y1="12" x2="12" y2="21"/><path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"/></svg> Weiteren Transfer erstellen</a>
                        <a href="dashboard.php" class="btn btn-ghost">← Zur Übersicht</a>
                    </div>
                </div>

                <script type="application/json" id="upload-result">
                <?= json_encode(['password' => $result['password'] ?? null], JSON_HEX_TAG | JSON_HEX_AMP) ?>
                </script>

                <?php else: ?>
                <!-- Upload-Formular -->
                <?php if ($error): ?>
                <div class="msg msg-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header">
                        <h2 style="margin:0;">Neuer Transfer</h2>
                    </div>

                    <form method="POST" action="" enctype="multipart/form-data" id="upload-form">
                        <?= csrf_field() ?>

                        <!-- Datei-Upload -->
                        <div class="form-group">
                            <label>Dateien (max. <?= $max_files ?> Dateien, gesamt max. <?= $max_mb ?> MB)</label>
                            <div class="upload-area" id="upload-area">
                                <svg class="upload-icon" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                                <div style="font-weight:600; color:var(--color-navy);">Dateien hierher ziehen oder klicken</div>
                                <div class="upload-hint">PDF, Word, Excel, ZIP, Bilder, …</div>
                                <input type="file" id="file-input" name="files[]" multiple
                                       accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip,.rar,.7z,.gz,.tar,.jpg,.jpeg,.png,.gif,.webp,.tiff,.txt,.csv,.rtf,.mp4,.mov,.mp3,.wav">
                            </div>
                            <div class="file-list" id="file-list"></div>
                        </div>

                        <!-- Passwort -->
                        <div class="form-group">
                            <label>Passwort <span style="font-weight:400; color:var(--color-gray-500);">(optional)</span></label>
                            <div class="input-group">
                                <input type="text" name="password" id="pw-field" class="form-control"
                                       placeholder="Leer lassen = kein Passwort"
                                       autocomplete="new-password">
                                <button type="button" id="generate-pw-btn" class="btn btn-ghost" title="Zufälliges Passwort generieren"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -2px;"><polyline points="16 3 21 3 21 8"/><line x1="4" y1="20" x2="21" y2="3"/><polyline points="21 16 21 21 16 21"/><line x1="15" y1="15" x2="21" y2="21"/></svg> Generieren</button>
                            </div>
                            <div class="form-hint">Passwort wird dem Empfänger separat mitgeteilt.</div>
                        </div>

                        <!-- Max. Downloads -->
                        <div class="form-group">
                            <label>Maximale Downloads <span style="font-weight:400; color:var(--color-gray-500);">(optional)</span></label>
                            <input type="number" name="max_downloads" class="form-control"
                                   placeholder="Leer lassen = unbegrenzt" min="1" max="9999"
                                   style="max-width: 200px;">
                            <div class="form-hint">Erlaubt z.B. nur 1 Download → Link wird danach automatisch gesperrt.</div>
                        </div>

                        <!-- Ablaufzeit -->
                        <div class="form-group">
                            <label>Ablaufzeit in Tagen</label>
                            <input type="number" name="lifetime_days" class="form-control"
                                   value="<?= (int)$lifetime ?>" min="1" max="365"
                                   style="max-width: 120px;">
                        </div>

                        <button type="submit" class="btn" id="submit-btn"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -2px;"><polyline points="16 16 12 12 8 16"/><line x1="12" y1="12" x2="12" y2="21"/><path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"/></svg> Transfer erstellen</button>
                    </form>
                </div>
                <?php endif; ?>

            </div>
        </main>

        <footer class="app-statusbar">
            <div class="statusbar-left">
                <span>Neuer Transfer</span>
            </div>
            <div class="statusbar-right">
                <span id="upload-size"></span>
            </div>
        </footer>
    </div>
</div>

<script src="../assets/admin-upload.js"></script>

</body>
</html>
