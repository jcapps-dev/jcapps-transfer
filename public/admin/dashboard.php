<?php
/**
 * Admin-Dashboard — Übersicht aller Transfers
 */

require_once __DIR__ . '/version.php';

umask(0077);
ini_set('display_errors', 0);

require_once dirname(dirname(__DIR__)) . '/functions/bootstrap.php';

auth_session_start();
auth_check();

$settings  = settings_load();

// Alle Transfers laden
$transfers = transfer_get_all();

// Statistiken
$total    = count($transfers);
$active   = 0;
$revoked  = 0;
$expired  = 0;

foreach ($transfers as $t) {
    $s = transfer_get_status($t);
    if ($s === 'active') $active++;
    elseif ($s === 'revoked') $revoked++;
    else $expired++;
}

// Flash-Nachrichten
$flash = flash_get();

// Update-Check (einmal täglich, gecacht)
$update_available = null;
$update_version   = null;
$_cache_file = TRANSFER_BASE . '/update_check.json';
$_cache_ttl  = 86400; // 24h
$_cache_valid = is_file($_cache_file) && (time() - filemtime($_cache_file) < $_cache_ttl);
if (!$_cache_valid) {
    $_ctx = stream_context_create(['http' => [
        'header'  => "User-Agent: jcapps-transfer/" . APP_VERSION . "\r\n",
        'timeout' => 3,
    ]]);
    $_json = @file_get_contents('https://api.github.com/repos/jcapps-dev/jcapps-transfer/releases/latest', false, $_ctx);
    if ($_json) {
        @file_put_contents($_cache_file, $_json, LOCK_EX);
        @chmod($_cache_file, 0600);
    }
}
if (is_file($_cache_file)) {
    $_data = json_decode(@file_get_contents($_cache_file), true);
    $_latest = ltrim($_data['tag_name'] ?? '', 'v');
    if ($_latest && version_compare($_latest, APP_VERSION, '>')) {
        $update_available = true;
        $update_version   = $_latest;
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard – <?= htmlspecialchars($settings['site_name'], ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="../assets/style.css">
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
            <a class="sidebar-link active" href="dashboard.php">
                <span class="link-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg></span>
                <span class="link-text">Übersicht</span>
            </a>
            <a class="sidebar-link" href="upload.php">
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
                    <span class="breadcrumb-item">Transfers</span>
                    <span class="breadcrumb-sep">›</span>
                    <span>Übersicht</span>
                </div>
            </div>
            <div class="toolbar-right">
                <a href="upload.php" class="toolbar-btn"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -2px;"><polyline points="16 16 12 12 8 16"/><line x1="12" y1="12" x2="12" y2="21"/><path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"/></svg> Neuer Transfer</a>
            </div>
        </header>

        <main class="app-main">
            <div class="container">

                <?php if ($update_available): ?>
                <div class="msg msg-info">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;margin-right:6px;"><polyline points="16 16 12 12 8 16"/><line x1="12" y1="12" x2="12" y2="21"/><path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"/></svg>
                    Version <strong><?= htmlspecialchars($update_version, ENT_QUOTES, 'UTF-8') ?></strong> verfügbar —
                    <a href="https://github.com/jcapps-dev/jcapps-transfer/releases/latest" target="_blank" rel="noopener" style="color:inherit;font-weight:600;">update.php herunterladen</a> und im App-Verzeichnis hochladen.
                </div>
                <?php endif; ?>
                <?php foreach ($flash as $m): ?>
                <div class="msg msg-<?= htmlspecialchars($m['type'], ENT_QUOTES, 'UTF-8') ?>">
                    <?= htmlspecialchars($m['text'], ENT_QUOTES, 'UTF-8') ?>
                </div>
                <?php endforeach; ?>

                <!-- Statistiken -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-value"><?= $total ?></div>
                        <div class="stat-label">Transfers gesamt</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?= $active ?></div>
                        <div class="stat-label">Aktiv</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?= $revoked ?></div>
                        <div class="stat-label">Widerrufen</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?= $expired ?></div>
                        <div class="stat-label">Abgelaufen / Limit</div>
                    </div>
                </div>

                <!-- Tabelle -->
                <?php if (empty($transfers)): ?>
                <div class="card" style="text-align:center; padding: 2rem; color: var(--color-gray-500);">
                    Noch keine Transfers. <a href="upload.php" style="color:var(--color-navy);font-weight:600;">Ersten Transfer erstellen →</a>
                </div>
                <?php else: ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Datei(en)</th>
                                <th>Größe</th>
                                <th>Erstellt</th>
                                <th>Läuft ab</th>
                                <th>Downloads</th>
                                <th>Status</th>
                                <th>Aktionen</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($transfers as $t): ?>
                            <?php
                            $status    = transfer_get_status($t);
                            $files     = $t['files'];
                            $filename  = count($files) === 1
                                ? $files[0]['original_name']
                                : count($files) . ' Dateien';
                            $size      = transfer_format_size(transfer_total_size($t));
                            $created   = date('d.m.Y H:i', strtotime($t['created_at']));
                            $expires   = date('d.m.Y', strtotime($t['expires_at']));
                            $dl_count  = (int)($t['download_count'] ?? 0);
                            $dl_max    = $t['max_downloads'] ?? null;
                            $dl_text   = $dl_max !== null ? $dl_count . ' / ' . $dl_max : $dl_count . ' / ∞';
                            $dl_url    = transfer_download_url($t['token']);
                            $has_pw    = $t['password_hash'] !== null;

                            $badge_class = match($status) {
                                'active'       => 'badge-active',
                                'revoked'      => 'badge-revoked',
                                'expired'      => 'badge-expired',
                                'maxdownloads' => 'badge-maxdownloads',
                                default        => 'badge-expired',
                            };
                            $badge_text = match($status) {
                                'active'       => 'Aktiv',
                                'revoked'      => 'Widerrufen',
                                'expired'      => 'Abgelaufen',
                                'maxdownloads' => 'Limit erreicht',
                                default        => 'Unbekannt',
                            };
                            ?>
                            <tr>
                                <td>
                                    <?= htmlspecialchars($filename, ENT_QUOTES, 'UTF-8') ?>
                                    <?php if ($has_pw): ?><span title="Passwortgeschützt" style="margin-left:4px;"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -1px;"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></span><?php endif; ?>
                                    <?php if (count($files) > 1): ?><span style="font-size:0.75rem;color:var(--color-gray-400);margin-left:4px;">(ZIP)</span><?php endif; ?>
                                </td>
                                <td style="font-family:'JetBrains Mono',monospace; font-size:0.8125rem;"><?= htmlspecialchars($size, ENT_QUOTES, 'UTF-8') ?></td>
                                <td style="font-size:0.8125rem;"><?= htmlspecialchars($created, ENT_QUOTES, 'UTF-8') ?></td>
                                <td style="font-size:0.8125rem;"><?= htmlspecialchars($expires, ENT_QUOTES, 'UTF-8') ?></td>
                                <td style="font-family:'JetBrains Mono',monospace; font-size:0.8125rem;"><?= htmlspecialchars($dl_text, ENT_QUOTES, 'UTF-8') ?></td>
                                <td><span class="badge <?= $badge_class ?>"><?= $badge_text ?></span></td>
                                <td>
                                    <div class="td-actions">
                                        <?php if ($status === 'active'): ?>
                                        <button class="btn btn-sm btn-ghost copy-btn"
                                                title="Link kopieren"
                                                data-url="<?= htmlspecialchars($dl_url, ENT_QUOTES, 'UTF-8') ?>"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -2px;"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg> Link</button>
                                        <form method="POST" action="revoke.php" style="display:inline;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="token" value="<?= htmlspecialchars($t['token'], ENT_QUOTES, 'UTF-8') ?>">
                                            <button type="submit" class="btn btn-sm btn-warning"
                                                    data-confirm="Transfer sperren?"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -2px;"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg> Sperren</button>
                                        </form>
                                        <?php endif; ?>
                                        <form method="POST" action="delete.php" style="display:inline;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="token" value="<?= htmlspecialchars($t['token'], ENT_QUOTES, 'UTF-8') ?>">
                                            <button type="submit" class="btn btn-sm btn-danger"
                                                    data-confirm="Unwiderruflich löschen?"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -2px;"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg> Löschen</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>

            </div>
        </main>

        <footer class="app-statusbar">
            <div class="statusbar-left">
                <span><?= htmlspecialchars($settings['site_name'], ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <div class="statusbar-right">
                <span>v<?= APP_VERSION ?></span>
            </div>
        </footer>
    </div>
</div>

<script src="../assets/admin-dashboard.js"></script>
</body>
</html>
