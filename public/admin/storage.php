<?php
umask(0077);
ini_set('display_errors', 0);

require_once dirname(dirname(__DIR__)) . '/functions/bootstrap.php';

auth_session_start();
auth_check();

$settings  = settings_load();
$max_mb    = (int)($config['max_filesize_mb'] ?? 200);
$max_files = (int)($config['max_files_per_upload'] ?? 10);

// Load files from storage directory
$files = [];
if (is_dir(STORAGE_DIR)) {
    foreach (glob(STORAGE_DIR . '/*') as $path) {
        if (!is_file($path)) continue;
        $files[] = [
            'name'  => basename($path),
            'size'  => filesize($path),
            'mtime' => filemtime($path),
        ];
    }
    usort($files, fn($a, $b) => $b['mtime'] - $a['mtime']);
}

function storage_format_size(int $bytes): string {
    if ($bytes < 1024)       return $bytes . ' B';
    if ($bytes < 1048576)    return round($bytes / 1024, 1) . ' KB';
    if ($bytes < 1073741824) return round($bytes / 1048576, 1) . ' MB';
    return round($bytes / 1073741824, 2) . ' GB';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Storage – <?= htmlspecialchars($settings['site_name'], ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="../assets/style.css">
    <meta name="csrf-token" content="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
    <meta name="max-files" content="<?= $max_files ?>">
    <meta name="max-mb" content="<?= $max_mb ?>">
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
                <span class="link-text">Overview</span>
            </a>
            <a class="sidebar-link" href="upload.php">
                <span class="link-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 16 12 12 8 16"/><line x1="12" y1="12" x2="12" y2="21"/><path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"/></svg></span>
                <span class="link-text">New Transfer</span>
            </a>
            <a class="sidebar-link active" href="storage.php">
                <span class="link-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg></span>
                <span class="link-text">Storage</span>
            </a>
        </nav>
        <div class="sidebar-footer">
            <a class="sidebar-link" href="logout.php" title="Log out">
                <span class="link-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg></span>
                <span class="link-text">Log out</span>
            </a>
            <a class="sidebar-link" href="settings.php">
                <span class="link-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg></span>
                <span class="link-text">Settings</span>
            </a>
        </div>
    </aside>

    <div class="app-content">
        <header class="app-toolbar">
            <div class="toolbar-left">
                <span class="breadcrumb-item">Storage</span>
            </div>
            <div class="toolbar-right">
                <span id="upload-size"></span>
            </div>
        </header>

        <main class="app-main">
            <div class="container">

                <?php if (isset($_GET['ok'])): ?>
                <div class="msg msg-success" style="margin-bottom: var(--spacing-lg);">File(s) successfully saved to storage.</div>
                <?php endif; ?>

                <div id="error-msg" class="msg msg-error" style="display:none; margin-bottom: var(--spacing-lg);"></div>

                <!-- Upload -->
                <div class="card" style="margin-bottom: var(--spacing-xl);">
                    <div class="card-header">
                        <h2 style="margin:0;"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-3px"><polyline points="16 16 12 12 8 16"/><line x1="12" y1="12" x2="12" y2="21"/><path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"/></svg> Upload File</h2>
                    </div>

                    <form id="upload-form" enctype="multipart/form-data" data-finalize-url="storage_finalize.php">
                        <div class="form-group">
                            <label>Files (max. <?= $max_files ?> files, <?= $max_mb ?> MB total)</label>
                            <div class="upload-area" id="upload-area">
                                <svg class="upload-icon" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                                <div style="font-weight:600; color:var(--color-navy);">Drop files here or click to select</div>
                                <div class="upload-hint">Files are saved directly to your private storage.</div>
                                <input type="file" id="file-input" name="files[]" multiple
                                       accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip,.rar,.7z,.gz,.tar,.jpg,.jpeg,.png,.gif,.webp,.tiff,.txt,.csv,.rtf,.mp4,.mov,.mp3,.wav">
                            </div>
                            <div class="file-list" id="file-list"></div>
                        </div>

                        <button type="submit" class="btn" id="submit-btn"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px"><polyline points="16 16 12 12 8 16"/><line x1="12" y1="12" x2="12" y2="21"/><path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"/></svg> Save to Storage</button>
                    </form>
                </div>

                <!-- File list -->
                <div class="card">
                    <div class="card-header">
                        <h2 style="margin:0;"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-3px"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg> Stored Files</h2>
                    </div>

                    <?php if (empty($files)): ?>
                    <div style="padding: var(--spacing-xl); text-align:center; color: var(--color-gray-500);">
                        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="display:block; margin: 0 auto var(--spacing-md);"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>
                        No files in storage yet.
                    </div>
                    <?php else: ?>
                    <table style="width:100%; border-collapse:collapse;">
                        <thead>
                            <tr style="border-bottom: 1px solid var(--color-gray-200);">
                                <th style="text-align:left; padding: var(--spacing-sm) var(--spacing-md); font-size:.8125rem; color:var(--color-gray-500); font-weight:600;">Filename</th>
                                <th style="text-align:right; padding: var(--spacing-sm) var(--spacing-md); font-size:.8125rem; color:var(--color-gray-500); font-weight:600; width:90px;">Size</th>
                                <th style="text-align:left; padding: var(--spacing-sm) var(--spacing-md); font-size:.8125rem; color:var(--color-gray-500); font-weight:600; width:160px;">Uploaded</th>
                                <th style="width:220px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($files as $f): ?>
                            <tr style="border-bottom: 1px solid var(--color-gray-100);">
                                <td style="padding: var(--spacing-sm) var(--spacing-md);">
                                    <span style="display:inline-flex; align-items:center; gap:6px;">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0; color:var(--color-gray-400);"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                        <?= htmlspecialchars($f['name'], ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </td>
                                <td style="padding: var(--spacing-sm) var(--spacing-md); text-align:right; font-size:.875rem; color:var(--color-gray-500);">
                                    <?= storage_format_size($f['size']) ?>
                                </td>
                                <td style="padding: var(--spacing-sm) var(--spacing-md); font-size:.875rem; color:var(--color-gray-500);">
                                    <?= date('Y-m-d H:i', $f['mtime']) ?>
                                </td>
                                <td style="padding: var(--spacing-sm) var(--spacing-md); width:220px;">
                                    <div class="td-actions" style="display:flex; gap:6px;">
                                        <a href="storage_download.php?file=<?= urlencode($f['name']) ?>"
                                           class="btn btn-sm btn-ghost" style="flex:1; justify-content:center;">
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-1px; flex-shrink:0;"><polyline points="8 17 12 21 16 17"/><line x1="12" y1="12" x2="12" y2="21"/><path d="M20.88 18.09A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.29"/></svg>
                                            Download
                                        </a>
                                        <form method="post" action="storage_delete.php" style="flex:1; display:flex;">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                                            <input type="hidden" name="file" value="<?= htmlspecialchars($f['name'], ENT_QUOTES, 'UTF-8') ?>">
                                            <button type="submit" class="btn btn-sm btn-danger" style="flex:1; justify-content:center;"
                                                    data-confirm="Permanently delete this file?">
                                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-1px; flex-shrink:0;"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
                                                Delete
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>

            </div>
        </main>

        <footer class="app-statusbar">
            <div class="statusbar-left">
                <span>Storage<?= count($files) > 0 ? ' — ' . count($files) . ' file(s)' : '' ?></span>
            </div>
            <div class="statusbar-right">
                <span id="upload-size"></span>
            </div>
        </footer>
    </div>
</div>

<script src="../assets/admin-upload.js"></script>
<script src="../assets/admin-dashboard.js"></script>
</body>
</html>
