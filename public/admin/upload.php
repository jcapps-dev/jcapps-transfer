<?php
umask(0077);
ini_set('display_errors', 0);

require_once dirname(dirname(__DIR__)) . '/functions/bootstrap.php';

auth_session_start();
auth_check();

$settings  = settings_load();
$max_mb    = (int)($config['max_filesize_mb'] ?? 200);
$max_files = (int)($config['max_files_per_upload'] ?? 10);
$lifetime  = (int)($config['transfer_lifetime_days'] ?? 14);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>New Transfer – <?= htmlspecialchars($settings['site_name'], ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="../assets/style.css">
    <meta name="csrf-token" content="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
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
                <span class="link-text">Overview</span>
            </a>
            <a class="sidebar-link active" href="upload.php">
                <span class="link-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 16 12 12 8 16"/><line x1="12" y1="12" x2="12" y2="21"/><path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"/></svg></span>
                <span class="link-text">New Transfer</span>
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

    <!-- Content -->
    <div class="app-content">
        <header class="app-toolbar">
            <div class="toolbar-left">
                <div class="breadcrumbs">
                    <a href="dashboard.php" style="color:var(--color-gray-600);text-decoration:none;">Transfers</a>
                    <span class="breadcrumb-sep">›</span>
                    <span class="breadcrumb-item">New Transfer</span>
                </div>
            </div>
            <div class="toolbar-right">
                <a href="dashboard.php" class="toolbar-btn">← Back</a>
            </div>
        </header>

        <main class="app-main">
            <div class="container">

                <!-- Result section (filled and shown by JS) -->
                <div id="result-section" style="display:none">
                    <div class="msg msg-success">Transfer created successfully.</div>
                    <div class="card">
                        <div class="card-header">
                            <h2 style="margin:0;"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-3px"><polyline points="20 6 9 17 4 12"/></svg> Download Link</h2>
                        </div>
                        <div class="link-box">
                            <div class="link-box-label">Download URL (send via email)</div>
                            <div class="link-box-url" id="dl-url"></div>
                            <button id="copy-url-btn" class="btn btn-sm"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg> Copy link</button>
                        </div>
                        <div id="pw-box" class="link-box" style="display:none; background: color-mix(in srgb, var(--color-pastel-yellow) 10%, white); border-color: var(--color-pastel-yellow);">
                            <div class="link-box-label"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg> Password (shown once — save it now!)</div>
                            <div class="link-box-url" id="pw-display" style="font-family:'JetBrains Mono',monospace; letter-spacing:0.1em;"></div>
                            <button id="copy-pw-btn" class="btn btn-sm btn-warning"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg> Copy password</button>
                        </div>
                        <div style="margin-top: var(--spacing-md); display:flex; gap: var(--spacing-sm);">
                            <a href="upload.php" class="btn btn-ghost"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px"><polyline points="16 16 12 12 8 16"/><line x1="12" y1="12" x2="12" y2="21"/><path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"/></svg> Create another transfer</a>
                            <a href="dashboard.php" class="btn btn-ghost">← Back to overview</a>
                        </div>
                    </div>
                    <script type="application/json" id="upload-result">{}</script>
                </div>

                <!-- Form section -->
                <div id="form-section">
                    <div id="error-msg" class="msg msg-error" style="display:none"></div>

                    <div class="card">
                        <div class="card-header">
                            <h2 style="margin:0;">New Transfer</h2>
                        </div>

                        <form id="upload-form" enctype="multipart/form-data">

                            <div class="form-group">
                                <label>Files (max. <?= $max_files ?> files, total max. <?= $max_mb ?> MB)</label>
                                <div class="upload-area" id="upload-area">
                                    <svg class="upload-icon" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                                    <div style="font-weight:600; color:var(--color-navy);">Drag files here or click</div>
                                    <div class="upload-hint">PDF, Word, Excel, ZIP, Images, …</div>
                                    <input type="file" id="file-input" name="files[]" multiple
                                           accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip,.rar,.7z,.gz,.tar,.jpg,.jpeg,.png,.gif,.webp,.tiff,.txt,.csv,.rtf,.mp4,.mov,.mp3,.wav">
                                </div>
                                <div class="file-list" id="file-list"></div>
                                <div class="form-hint">💡 Multiple files will be offered to the recipient as a ZIP download.</div>
                            </div>

                            <div class="form-group">
                                <label>Password <span style="font-weight:400; color:var(--color-gray-500);">(optional)</span></label>
                                <div class="input-group">
                                    <input type="text" name="password" id="pw-field" class="form-control"
                                           placeholder="Leave empty = no password"
                                           autocomplete="new-password">
                                    <button type="button" id="generate-pw-btn" class="btn btn-ghost" title="Generate random password"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px"><polyline points="16 3 21 3 21 8"/><line x1="4" y1="20" x2="21" y2="3"/><polyline points="21 16 21 21 16 21"/><line x1="15" y1="15" x2="21" y2="21"/></svg> Generate</button>
                                </div>
                                <div class="form-hint">💡 A password only makes sense if it is communicated to the recipient separately (e.g. by phone).</div>
                            </div>

                            <div class="form-group">
                                <label>Maximum Downloads <span style="font-weight:400; color:var(--color-gray-500);">(optional)</span></label>
                                <input type="number" name="max_downloads" class="form-control"
                                       placeholder="Leave empty = unlimited" min="1" max="9999"
                                       style="max-width: 200px;">
                                <div class="form-hint">💡 E.g. limit to 1 download → link is automatically blocked afterwards.</div>
                            </div>

                            <div class="form-group">
                                <label>Expiry in days</label>
                                <input type="number" name="lifetime_days" class="form-control"
                                       value="<?= (int)$lifetime ?>" min="1" max="365"
                                       style="max-width: 120px;">
                            </div>

                            <button type="submit" class="btn" id="submit-btn"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px"><polyline points="16 16 12 12 8 16"/><line x1="12" y1="12" x2="12" y2="21"/><path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"/></svg> Create transfer</button>
                        </form>
                    </div>
                </div>

            </div>
        </main>

        <footer class="app-statusbar">
            <div class="statusbar-left">
                <span>New Transfer</span>
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
