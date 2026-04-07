<?php
/**
 * Admin-Einstellungen — Firmenname, Footer-Text, Logo
 */

require_once __DIR__ . '/version.php';

umask(0077);
ini_set('display_errors', 0);

require_once dirname(dirname(__DIR__)) . '/functions/bootstrap.php';

auth_session_start();
auth_check();

$settings = settings_load();
$error    = '';
$success  = '';

// ── POST-Handler ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $action = $_POST['action'] ?? 'save';

    if ($action === 'delete_logo') {
        $logo_path = TRANSFER_BASE . '/logo.dat';
        if (is_file($logo_path)) {
            unlink($logo_path);
        }
        $settings['has_logo']  = false;
        $settings['logo_mime'] = null;
        if (settings_save($settings)) {
            log_event('settings_logo_deleted');
            $success = 'Logo gelöscht.';
        } else {
            $error = 'Fehler beim Speichern der Einstellungen.';
        }

    } else {
        // Textfelder übernehmen
        $settings['site_name']     = $_POST['site_name']     ?? '';
        $settings['company_name']  = $_POST['company_name']  ?? '';
        $settings['footer_text']   = $_POST['footer_text']   ?? '';
        $settings['impressum_url'] = $_POST['impressum_url'] ?? '';

        // Logo-Upload (optional)
        $upload = $_FILES['logo'] ?? null;
        if ($upload && $upload['error'] === UPLOAD_ERR_OK) {

            $allowed_mimes = [
                'image/png', 'image/jpeg', 'image/gif',
                'image/webp',
            ];
            $max_bytes = 2 * 1048576; // 2 MB

            if ($upload['size'] > $max_bytes) {
                $error = 'Logo zu groß (max. 2 MB).';
            } else {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime  = $finfo->file($upload['tmp_name']);

                if (!in_array($mime, $allowed_mimes, true)) {
                    $error = 'Dateityp nicht erlaubt. Erlaubt: PNG, JPG, GIF, WebP.';
                } else {
                    $logo_path = TRANSFER_BASE . '/logo.dat';
                    if (!move_uploaded_file($upload['tmp_name'], $logo_path)) {
                        $error = 'Logo konnte nicht gespeichert werden.';
                    } else {
                        chmod($logo_path, 0600);
                        $settings['has_logo']  = true;
                        $settings['logo_mime'] = $mime;
                    }
                }
            }

        } elseif ($upload && $upload['error'] !== UPLOAD_ERR_NO_FILE) {
            $error = 'Upload-Fehler (Code: ' . $upload['error'] . ').';
        }

        if (!$error) {
            if (settings_save($settings)) {
                log_event('settings_saved');
                $success  = 'Einstellungen gespeichert.';
                $settings = settings_load();
            } else {
                $error = 'Fehler beim Speichern.';
            }
        }
    }
}

$logo_version = is_file(TRANSFER_BASE . '/logo.dat') ? filemtime(TRANSFER_BASE . '/logo.dat') : 0;
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Einstellungen – Filetransfer Admin</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<div class="app-layout">

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
            <a class="sidebar-link" href="upload.php">
                <span class="link-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 16 12 12 8 16"/><line x1="12" y1="12" x2="12" y2="21"/><path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"/></svg></span>
                <span class="link-text">Neuer Transfer</span>
            </a>
        </nav>
        <div class="sidebar-footer">
            <a class="sidebar-link" href="logout.php">
                <span class="link-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg></span>
                <span class="link-text">Ausloggen</span>
            </a>
            <a class="sidebar-link active" href="settings.php">
                <span class="link-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg></span>
                <span class="link-text">Einstellungen</span>
            </a>
        </div>
    </aside>

    <div class="app-content">
        <header class="app-toolbar">
            <div class="toolbar-left">
                <div class="breadcrumbs">
                    <span>Admin</span>
                    <span class="breadcrumb-sep">›</span>
                    <span class="breadcrumb-item">Einstellungen</span>
                </div>
            </div>
        </header>

        <main class="app-main">
            <div class="container">

                <?php if ($success): ?>
                <div class="msg msg-success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                <div class="msg msg-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header">
                        <h2 style="margin:0;">Branding &amp; Download-Seite</h2>
                    </div>

                    <form method="POST" action="" enctype="multipart/form-data">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="save">

                        <div class="form-group">
                            <label for="site_name">Seitenname (Sidebar)</label>
                            <input type="text" id="site_name" name="site_name"
                                   class="form-control" maxlength="60"
                                   value="<?= htmlspecialchars($settings['site_name'], ENT_QUOTES, 'UTF-8') ?>">
                            <div class="form-hint">Wird in der Sidebar oben links angezeigt.</div>
                        </div>

                        <div class="form-group">
                            <label for="company_name">Firmenname</label>
                            <input type="text" id="company_name" name="company_name"
                                   class="form-control" maxlength="100"
                                   value="<?= htmlspecialchars($settings['company_name'], ENT_QUOTES, 'UTF-8') ?>">
                            <div class="form-hint">Wird auf der Download-Seite als Überschrift angezeigt.</div>
                        </div>

                        <div class="form-group">
                            <label for="footer_text">Footer-Text</label>
                            <input type="text" id="footer_text" name="footer_text"
                                   class="form-control" maxlength="300"
                                   placeholder="z.B. Muster GmbH · Vertraulich"
                                   value="<?= htmlspecialchars($settings['footer_text'], ENT_QUOTES, 'UTF-8') ?>">
                            <div class="form-hint">Erscheint in der Fußzeile der Download-Seite. Leer lassen = kein Footer.</div>
                        </div>

                        <div class="form-group">
                            <label for="impressum_url">Impressum-URL</label>
                            <input type="url" class="form-control" id="impressum_url" name="impressum_url"
                                   maxlength="500" placeholder="https://example.com/impressum"
                                   value="<?= htmlspecialchars($settings['impressum_url'], ENT_QUOTES, 'UTF-8') ?>">
                            <div class="form-hint">Wird als kleiner Link im Footer der Login- und Download-Seite angezeigt. Leer lassen = kein Link.</div>
                        </div>

                        <div class="form-group">
                            <label>Firmenlogo</label>
                            <?php if ($settings['has_logo']): ?>
                            <div style="display:inline-block; margin-bottom:14px; line-height:0;">
                                <img src="../logo.php?v=<?= (int)$logo_version ?>"
                                     alt="Aktuelles Logo"
                                     width="240"
                                     style="height:auto; border:1px solid #E5E7EB; border-radius:4px; display:block;">
                            </div>
                            <div class="form-hint" style="margin-bottom: var(--spacing-sm);">Neue Datei ersetzt das aktuelle Logo.</div>
                            <?php endif; ?>
                            <input type="file" name="logo" class="form-control"
                                   accept="image/png,image/jpeg,image/gif,image/webp"
                                   style="padding: 0.375rem;">
                            <div class="form-hint">PNG, JPG, GIF oder WebP — max. 2 MB. Empfohlen: transparenter Hintergrund (PNG), min. 200 px Breite.</div>
                        </div>

                        <div style="display:flex; gap:var(--spacing-sm); align-items:center;">
                            <button type="submit" class="btn"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -2px;"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg> Speichern</button>
                            <?php if ($settings['has_logo']): ?>
                            <button type="button" class="btn btn-danger"
                                    data-open-modal="logo-delete-modal"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -2px;"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg> Logo löschen</button>
                            <?php endif; ?>
                        </div>
                    </form>

                    <form id="logo-delete-form" method="POST" action="" style="display:none;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete_logo">
                    </form>

                </div>

                <div class="card">
                    <div class="card-header">
                        <h3><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -2px;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg> System-Informationen</h3>
                    </div>

                    <?php
                    $all_transfers  = transfer_get_all();
                    $aktiv_count    = count(array_filter($all_transfers, fn($t) => transfer_get_status($t) === 'active'));
                    $inaktiv_count  = count($all_transfers) - $aktiv_count;
                    $upload_bytes   = array_sum(array_map('transfer_total_size', $all_transfers));
                    $upload_mb      = round($upload_bytes / 1024 / 1024, 1);
                    ?>

                    <div class="table-container">
                        <table>
                            <tr>
                                <td><strong>Version</strong></td>
                                <td><?= APP_VERSION ?></td>
                            </tr>
                            <tr>
                                <td><strong>PHP Version</strong></td>
                                <td><?= phpversion() ?></td>
                            </tr>
                            <tr>
                                <td><strong>Einstellungen zuletzt gespeichert</strong></td>
                                <td><?= isset($settings['updated']) ? date('d.m.Y H:i', strtotime($settings['updated'])) : '-' ?></td>
                            </tr>
                            <tr>
                                <td><strong>Transfers gesamt</strong></td>
                                <td><?= count($all_transfers) ?></td>
                            </tr>
                            <tr>
                                <td><strong>Aktive Transfers</strong></td>
                                <td><?= $aktiv_count ?></td>
                            </tr>
                            <tr>
                                <td><strong>Inaktiv / abgelaufen</strong></td>
                                <td><?= $inaktiv_count ?></td>
                            </tr>
                            <tr>
                                <td><strong>Speicherplatz (Uploads)</strong></td>
                                <td><?= $upload_mb ?> MB</td>
                            </tr>
                        </table>
                    </div>
                </div>

            </div>
            <div style="margin-top: var(--spacing-lg); text-align: center; color: var(--color-gray-400); font-size: 0.813rem;">
                <a href="https://jcapps.dev" target="_blank" rel="noopener" style="color:inherit;">jcapps-transfer</a> &middot; Open Source &middot; MIT
            </div>
        </main>

        <footer class="app-statusbar">
            <div class="statusbar-left"><span>Einstellungen</span></div>
            <div class="statusbar-right"><span>v<?= APP_VERSION ?></span></div>
        </footer>
    </div>
</div>

<div id="logo-delete-modal" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-header"><h3>Logo löschen?</h3></div>
        <div class="modal-body">Das aktuelle Logo wird unwiderruflich gelöscht.</div>
        <div class="modal-footer">
            <button type="button" class="btn btn-ghost btn-sm"
                    data-close-modal="logo-delete-modal">Abbrechen</button>
            <button type="button" class="btn btn-danger"
                    data-submit-form="logo-delete-form">Ja, löschen</button>
        </div>
    </div>
</div>
<script src="../assets/admin-settings.js"></script>
</body>
</html>
