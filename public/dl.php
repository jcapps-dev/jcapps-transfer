<?php
/**
 * Download-Endpoint
 * GET  → Landingpage mit Datei-Info und Download-Button
 * POST → Datei-Streaming (nach Validierung)
 */

umask(0077);
ini_set('display_errors', 0);

require_once dirname(__DIR__) . '/functions/bootstrap.php';

// Nur GET und POST erlaubt
$method = $_SERVER['REQUEST_METHOD'];
if (!in_array($method, ['GET', 'POST'], true)) {
    http_response_code(405);
    header('Allow: GET, POST');
    exit;
}

// Security-Headers (immer setzen, auch bei Fehlern)
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin');

// Short code (t=) oder langer Token
$token = '';
$_short = $_GET['t'] ?? '';
if (preg_match('/^[a-zA-Z0-9]{8}$/', $_short)) {
    $_meta_sc = transfer_load_by_short_code($_short);
    $token = $_meta_sc['token'] ?? '';
} else {
    $token = $_GET['token'] ?? $_POST['token'] ?? '';
}

if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
    dl_show_page(null, 'invalid');
    exit;
}

// ── POST: Datei tatsächlich senden ────────────────────────────────────────────
if ($method === 'POST') {

    $ip_hash = log_ip_hash(log_get_ip());

    // Rate-Limit: max 20 Download-Versuche pro Minute
    if (ratelimit_is_blocked('dl', $ip_hash, 20, 60)) {
        http_response_code(429);
        dl_show_page(null, 'ratelimit');
        exit;
    }

    $meta = transfer_load($token);

    if (!$meta) {
        ratelimit_increment('dl', $ip_hash, 60);
        log_event('dl_invalid_token', ['pfx' => substr($token, 0, 8)]);
        http_response_code(404);
        dl_show_page(null, 'invalid');
        exit;
    }

    if (!transfer_is_valid($meta)) {
        ratelimit_increment('dl', $ip_hash, 60);
        log_event('dl_invalid_transfer', ['pfx' => substr($token, 0, 8), 'status' => transfer_get_status($meta)]);
        http_response_code(410);
        dl_show_page($meta, transfer_get_status($meta));
        exit;
    }

    // Passwort prüfen (wenn gesetzt)
    if ($meta['password_hash'] !== null) {
        // Rate-Limit für Passwort-Versuche: max 5 pro 5 Minuten (pro Token + IP)
        $pw_key = 'pw_' . substr($token, 0, 16);
        if (ratelimit_is_blocked($pw_key, $ip_hash, 5, 300)) {
            http_response_code(429);
            dl_show_page($meta, 'pw_ratelimit');
            exit;
        }

        $pw = $_POST['password'] ?? '';
        if (!password_verify($pw, $meta['password_hash'])) {
            ratelimit_increment($pw_key, $ip_hash, 300);
            log_event('dl_wrong_password', ['pfx' => substr($token, 0, 8)]);
            dl_show_page($meta, 'wrong_password');
            exit;
        }
    }

    // Download durchführen
    ratelimit_increment('dl', $ip_hash, 60);
    transfer_increment_download($token);
    log_event('dl_success', ['pfx' => substr($token, 0, 8), 'files' => count($meta['files'])]);

    // Output-Buffer leeren, dann streamen
    if (ob_get_level() > 0) ob_end_clean();
    transfer_stream_file($meta);
    exit;
}

// ── GET: Landingpage anzeigen ─────────────────────────────────────────────────

$meta   = transfer_load($token);
$status = 'ok';

if (!$meta) {
    $status = 'invalid';
} elseif (!transfer_is_valid($meta)) {
    $status = transfer_get_status($meta);
}

header('Cache-Control: no-store, no-cache');
dl_show_page($meta, $status);

// ─────────────────────────────────────────────────────────────────────────────
// Hilfsfunktion: Download-Seite rendern
// ─────────────────────────────────────────────────────────────────────────────

function dl_show_page(?array $meta, string $status): void
{
    $settings = settings_load();
    $company  = htmlspecialchars($settings['company_name'], ENT_QUOTES, 'UTF-8');

    // Logo als Base64-Data-URI vorbereiten (keine öffentliche Logo-URL)
    $_logo_b64  = null;
    $_logo_mime = null;
    if ($settings['has_logo']) {
        $_logo_path    = TRANSFER_BASE . '/logo.dat';
        $_logo_mime_wl = ['image/png', 'image/jpeg', 'image/gif', 'image/webp', 'image/svg+xml'];
        $_logo_mime_raw = $settings['logo_mime'] ?? '';
        if (in_array($_logo_mime_raw, $_logo_mime_wl, true) && is_file($_logo_path)) {
            $_logo_mime = $_logo_mime_raw;
            $_logo_b64  = base64_encode(file_get_contents($_logo_path));
        }
    }
    $footer       = $settings['footer_text'] !== ''
        ? htmlspecialchars($settings['footer_text'], ENT_QUOTES, 'UTF-8')
        : null;
    $impressum_url = htmlspecialchars($settings['impressum_url'] ?? '', ENT_QUOTES, 'UTF-8');

    $token = $meta['token'] ?? htmlspecialchars($_GET['token'] ?? $_POST['token'] ?? '', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Download file – <?= $company ?></title>
    <link rel="stylesheet" href="<?= rtrim(APP_URL, '/') ?>/assets/style.css">
</head>
<body class="dl-page">

<div class="dl-card">
    <div class="dl-logo">
        <?php if ($_logo_b64 !== null): ?>
        <div class="dl-logo-img" style="background-image:url('data:<?= htmlspecialchars($_logo_mime, ENT_QUOTES, 'UTF-8') ?>;base64,<?= $_logo_b64 ?>');background-size:contain;background-repeat:no-repeat;background-position:center;"></div>
        <div class="dl-logo-subtext"><?= $company ?></div>
        <?php else: ?>
        <span class="dl-logo-icon">
            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="var(--color-navy)" stroke-width="1.5">
                <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                <polyline points="3.27 6.96 12 12.01 20.73 6.96"/>
                <line x1="12" y1="22.08" x2="12" y2="12"/>
            </svg>
        </span>
        <div class="dl-logo-text"><?= $company ?></div>
        <?php endif; ?>
    </div>

    <?php if ($status === 'ok' && isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on'): ?>
    <div class="dl-security-hint">Secure transfer</div>
    <?php endif; ?>

    <?php if (in_array($status, ['ok', 'wrong_password'], true) && $meta): ?>

        <?php if ($status === 'wrong_password'): ?>
        <div class="msg msg-error">Wrong password. Please try again.</div>
        <?php endif; ?>

        <div class="dl-file-info">
            <div class="dl-filename">
                <?php
                $files = $meta['files'];
                if (count($files) === 1) {
                    echo htmlspecialchars($files[0]['original_name'], ENT_QUOTES, 'UTF-8');
                } else {
                    echo count($files) . ' files (downloaded as ZIP)';
                }
                ?>
            </div>
            <div class="dl-meta">
                <span>Size: <?= htmlspecialchars(transfer_format_size(transfer_total_size($meta)), ENT_QUOTES, 'UTF-8') ?></span>
                <?php
                $days_left = (int)ceil((strtotime($meta['expires_at']) - time()) / 86400);
                $expires_str = date('Y-m-d', strtotime($meta['expires_at']));
                $expires_label = $days_left > 0
                    ? "Valid until $expires_str ($days_left day" . ($days_left === 1 ? '' : 's') . " remaining)"
                    : "Valid until $expires_str";
                ?>
                <span><?= htmlspecialchars($expires_label, ENT_QUOTES, 'UTF-8') ?></span>
                <?php if (!empty($meta['max_downloads'])): ?>
                <span>Downloads remaining: <?= (int)($meta['max_downloads'] - ($meta['download_count'] ?? 0)) ?></span>
                <?php endif; ?>
            </div>
        </div>

        <form method="POST" action="">
            <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">

            <?php if ($meta['password_hash'] !== null): ?>
            <div class="form-group">
                <label for="dl_password">Password</label>
                <input type="password" id="dl_password" name="password" class="form-control"
                       placeholder="Enter transfer password"
                       autofocus required autocomplete="current-password">
                <div class="form-hint">The password was provided to you by the sender.</div>
            </div>
            <?php endif; ?>

            <div class="dl-actions">
                <button type="submit" class="btn" id="dl-btn">Download</button>
            </div>
        </form>

    <?php elseif ($status === 'pw_ratelimit'): ?>
        <div class="dl-error">
            <span class="dl-error-icon">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="color:var(--color-gray-400)">
                    <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><circle cx="12" cy="16" r=".5" fill="currentColor"/>
                </svg>
            </span>
            <h3>Too many attempts</h3>
            <p>Please wait 5 minutes and try again.</p>
        </div>

    <?php elseif ($status === 'ratelimit'): ?>
        <div class="dl-error">
            <span class="dl-error-icon">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="color:var(--color-gray-400)">
                    <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><circle cx="12" cy="16" r=".5" fill="currentColor"/>
                </svg>
            </span>
            <h3>Too many requests</h3>
            <p>Please wait a moment and try again.</p>
        </div>

    <?php elseif ($status === 'revoked'): ?>
        <div class="dl-error">
            <span class="dl-error-icon">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="color:var(--color-gray-400)">
                    <circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>
                </svg>
            </span>
            <h3>Link revoked</h3>
            <p>This download link has been deactivated by the sender.</p>
        </div>

    <?php elseif ($status === 'expired'): ?>
        <div class="dl-error">
            <span class="dl-error-icon">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="color:var(--color-gray-400)">
                    <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                </svg>
            </span>
            <h3>Link expired</h3>
            <p>This download link is no longer valid.</p>
            <p class="dl-error-action">Please contact the sender to receive a new link.</p>
        </div>

    <?php elseif ($status === 'maxdownloads'): ?>
        <div class="dl-error">
            <span class="dl-error-icon">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="color:var(--color-gray-400)">
                    <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><circle cx="12" cy="16" r=".5" fill="currentColor"/>
                </svg>
            </span>
            <h3>Download limit reached</h3>
            <p>The maximum number of downloads has been reached.</p>
            <p class="dl-error-action">Please contact the sender.</p>
        </div>

    <?php else: ?>
        <div class="dl-error">
            <span class="dl-error-icon">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="color:var(--color-gray-400)">
                    <circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>
                </svg>
            </span>
            <h3>Invalid link</h3>
            <p>This download link does not exist or is invalid.</p>
        </div>
    <?php endif; ?>
</div>

<?php if ($footer !== null): ?>
<div class="dl-footer"><?= $footer ?><?php if ($impressum_url !== ''): ?> · <a href="<?= $impressum_url ?>" target="_blank" rel="noopener noreferrer">Legal notice</a><?php endif; ?></div>
<?php else: ?>
<div class="dl-footer"><?= $company ?> · Secure File Transfer<?php if ($impressum_url !== ''): ?> · <a href="<?= $impressum_url ?>" target="_blank" rel="noopener noreferrer">Legal notice</a><?php endif; ?></div>
<?php endif; ?>

<script src="<?= rtrim(APP_URL, '/') ?>/assets/dl.js"></script>
</body>
</html>
<?php
}
