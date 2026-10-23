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
$_is_head_ticket = $method === 'HEAD' && isset($_GET['d']); // download managers query the size via HEAD
if (!in_array($method, ['GET', 'POST'], true) && !$_is_head_ticket) {
    http_response_code(405);
    header('Allow: GET, POST');
    exit;
}

// Security-Headers (immer setzen, auch bei Fehlern)
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin');

// ── Signed download link: streams here, interruptions can be resumed ──
if (isset($_GET['d'])) {
    $_tk    = (string)$_GET['d'];
    $_check = transfer_ticket_check($_tk, (string)($_GET['e'] ?? ''), (string)($_GET['s'] ?? ''));
    if ($_check !== 'ok') {
        if ($_check === 'invalid') ratelimit_increment('dl', log_ip_hash(log_get_ip()), 60);
        http_response_code($_check === 'expired' ? 410 : 403);
        dl_show_page(null, $_check === 'expired' ? 'ticket_expired' : 'invalid');
        exit;
    }
    $meta   = transfer_load($_tk);
    $status = $meta ? transfer_get_status($meta) : 'invalid';
    // The download was already counted when the link was issued — a limit
    // reached by that does not block resuming
    if (!$meta || in_array($status, ['revoked', 'expired'], true)) {
        http_response_code(410);
        dl_show_page($meta, $meta ? $status : 'invalid');
        exit;
    }
    if (ob_get_level() > 0) ob_end_clean();
    transfer_stream_file($meta);
    exit;
}

// Short code (t=) oder langer Token
$token = '';
$_short = $_GET['t'] ?? '';
if (preg_match('/^[a-zA-Z0-9]{8}$/', $_short)) {
    // Rate limit against short code guessing: max 30 misses per minute
    $_sc_ip = log_ip_hash(log_get_ip());
    if (ratelimit_is_blocked('sc', $_sc_ip, 30, 60)) {
        http_response_code(429);
        dl_show_page(null, 'ratelimit');
        exit;
    }
    $_meta_sc = transfer_load_by_short_code($_short);
    $token = $_meta_sc['token'] ?? '';
    if ($token === '') {
        ratelimit_increment('sc', $_sc_ip, 60);
    }
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

    // Claim download (validity + counter atomically, safe against parallel requests)
    ratelimit_increment('dl', $ip_hash, 60);
    $claimed = transfer_claim_download($token);
    if (!$claimed) {
        $meta   = transfer_load($token);
        $status = ($meta && !transfer_is_valid($meta)) ? transfer_get_status($meta) : 'invalid';
        log_event('dl_invalid_transfer', ['pfx' => substr($token, 0, 8), 'status' => $status]);
        http_response_code(410);
        dl_show_page($meta, $status);
        exit;
    }
    $meta = $claimed;
    log_event('dl_success', ['pfx' => substr($token, 0, 8), 'files' => count($meta['files'])]);

    // Continue to the signed download link (resumable); stream directly without a secret
    $ticket = transfer_ticket_url($token);
    if ($ticket !== null) {
        header('Location: ' . $ticket, true, 303);
        exit;
    }
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
// Helpers: language, texts, formatting
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Language from Accept-Language: the first supported language (German or
 * English) in the browser's order of preference. If neither is listed,
 * or no header is sent: English.
 */
function dl_lang(): string
{
    $langs = [];
    foreach (explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '') as $i => $part) {
        $bits = explode(';', trim($part));
        $tag  = strtolower(trim($bits[0]));
        $q    = 1.0;
        if (isset($bits[1]) && preg_match('/q=([0-9.]+)/', $bits[1], $m)) {
            $q = (float)$m[1];
        }
        if ($tag !== '' && $q > 0) {
            $langs[] = [$tag, $q, $i];
        }
    }
    if (!$langs) return 'en';
    // Sort by weight, keep the browser's order on ties
    usort($langs, fn($a, $b) => [$b[1], $a[2]] <=> [$a[1], $b[2]]);
    foreach ($langs as [$tag]) {
        if (str_starts_with($tag, 'de')) return 'de';
        if (str_starts_with($tag, 'en')) return 'en';
    }
    return 'en';
}

function dl_texts(string $lang): array
{
    $t = [
        'de' => [
            'page_title'      => 'Datei herunterladen',
            'title_one'       => 'Datei herunterladen',
            'title_many'      => 'Dateien herunterladen',
            'intro_one'       => '%s hat Ihnen eine Datei bereitgestellt.',
            'intro_many'      => '%s hat Ihnen %d Dateien bereitgestellt.',
            'zip_hint'        => 'Die Dateien werden zusammen als ZIP-Archiv heruntergeladen.',
            'size'            => 'Größe',
            'total_size'      => 'Gesamtgröße',
            'available_until' => 'Verfügbar bis',
            'days_left_one'   => 'noch 1 Tag',
            'days_left_many'  => 'noch %d Tage',
            'downloads_left'  => 'Verbleibende Downloads',
            'password'        => 'Passwort',
            'password_ph'     => 'Passwort eingeben',
            'password_hint'   => 'Das Passwort erhalten Sie separat vom Absender.',
            'wrong_password'  => 'Das Passwort ist nicht korrekt. Bitte versuchen Sie es erneut.',
            'download'        => 'Herunterladen',
            'downloading'     => 'Download wird gestartet …',
            'contact'         => 'Bitte wenden Sie sich an Ihren Ansprechpartner bei %s.',
            'impressum'       => 'Impressum',
            'datenschutz'     => 'Datenschutz',
            'states' => [
                'pw_ratelimit' => ['Zu viele Versuche', 'Bitte warten Sie 5 Minuten und versuchen Sie es dann erneut.'],
                'ratelimit'    => ['Zu viele Anfragen', 'Bitte warten Sie einen Moment und versuchen Sie es dann erneut.'],
                'revoked'      => ['Link deaktiviert', 'Dieser Download-Link wurde vom Absender deaktiviert.'],
                'expired'      => ['Link abgelaufen', 'Dieser Download-Link ist nicht mehr gültig.'],
                'maxdownloads' => ['Download-Limit erreicht', 'Die maximale Anzahl an Downloads für diesen Link wurde erreicht.'],
                'invalid'      => ['Link ungültig', 'Dieser Download-Link existiert nicht oder ist unvollständig.'],
                'ticket_expired' => ['Download-Sitzung abgelaufen', 'Bitte öffnen Sie den ursprünglichen Link erneut, um den Download neu zu starten.'],
            ],
        ],
        'en' => [
            'page_title'      => 'Download file',
            'title_one'       => 'Download file',
            'title_many'      => 'Download files',
            'intro_one'       => '%s has shared a file with you.',
            'intro_many'      => '%s has shared %d files with you.',
            'zip_hint'        => 'The files will be downloaded together as a ZIP archive.',
            'size'            => 'Size',
            'total_size'      => 'Total size',
            'available_until' => 'Available until',
            'days_left_one'   => '1 day left',
            'days_left_many'  => '%d days left',
            'downloads_left'  => 'Downloads remaining',
            'password'        => 'Password',
            'password_ph'     => 'Enter password',
            'password_hint'   => 'The sender will provide the password separately.',
            'wrong_password'  => 'The password is incorrect. Please try again.',
            'download'        => 'Download',
            'downloading'     => 'Starting download …',
            'contact'         => 'Please get in touch with your contact person at %s.',
            'impressum'       => 'Legal notice',
            'datenschutz'     => 'Privacy policy',
            'states' => [
                'pw_ratelimit' => ['Too many attempts', 'Please wait 5 minutes and try again.'],
                'ratelimit'    => ['Too many requests', 'Please wait a moment and try again.'],
                'revoked'      => ['Link deactivated', 'This download link has been deactivated by the sender.'],
                'expired'      => ['Link expired', 'This download link is no longer valid.'],
                'maxdownloads' => ['Download limit reached', 'The maximum number of downloads for this link has been reached.'],
                'invalid'      => ['Invalid link', 'This download link does not exist or is incomplete.'],
                'ticket_expired' => ['Download session expired', 'Please open the original link again to restart the download.'],
            ],
        ],
    ];
    return $t[$lang];
}

function dl_size(int $bytes, string $lang): string
{
    $s = transfer_format_size($bytes);
    return $lang === 'de' ? str_replace('.', ',', $s) : $s;
}

function dl_date(string $iso, string $lang): string
{
    return date($lang === 'de' ? 'd.m.Y' : 'j M Y', strtotime($iso));
}

/** Label for the file type badge (max. 4 chars, e.g. PDF, STEP, ZIP) */
function dl_ext(string $name): string
{
    $ext = strtoupper(pathinfo($name, PATHINFO_EXTENSION));
    return $ext !== '' ? mb_substr($ext, 0, 4) : '—';
}

// ─────────────────────────────────────────────────────────────────────────────
// Render download page
// ─────────────────────────────────────────────────────────────────────────────

function dl_show_page(?array $meta, string $status): void
{
    $lang     = dl_lang();
    $t        = dl_texts($lang);
    $e        = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    $settings = settings_load();
    $company  = $settings['company_name'] !== '' ? $settings['company_name'] : $settings['site_name'];

    // Logo as base64 data URI (no public logo URL)
    $logo_src = null;
    if ($settings['has_logo']) {
        $logo_path = TRANSFER_BASE . '/logo.dat';
        $logo_mime = $settings['logo_mime'] ?? '';
        if (in_array($logo_mime, ['image/png', 'image/jpeg', 'image/gif', 'image/webp'], true) && is_file($logo_path)) {
            $logo_src = 'data:' . $logo_mime . ';base64,' . base64_encode(file_get_contents($logo_path));
        }
    }

    $footer_text = $settings['footer_text'] !== ''
        ? $settings['footer_text']
        : '© ' . date('Y') . ' ' . $company;
    $legal_links = array_filter([
        $t['impressum']   => $settings['impressum_url'] ?? '',
        $t['datenschutz'] => $settings['datenschutz_url'] ?? '',
    ]);

    $token     = $meta['token'] ?? ($_GET['token'] ?? $_POST['token'] ?? '');
    $show_form = in_array($status, ['ok', 'wrong_password'], true) && $meta;
    $files     = $meta['files'] ?? [];
    $is_many   = count($files) > 1;
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title><?= $e($t['page_title']) ?> – <?= $e($company) ?></title>
    <link rel="stylesheet" href="<?= rtrim(APP_URL, '/') ?>/assets/style.css">
</head>
<body class="dl-page">

<header class="dl-header">
    <div class="dl-shell">
        <?php if ($logo_src !== null): ?>
        <img class="dl-header-logo" src="<?= $e($logo_src) ?>" alt="<?= $e($company) ?>">
        <?php else: ?>
        <span class="dl-header-name"><?= $e($company) ?></span>
        <?php endif; ?>
    </div>
</header>

<main class="dl-main">
    <div class="dl-shell">

    <?php if ($show_form): ?>

        <h1 class="dl-title"><?= $e($is_many ? $t['title_many'] : $t['title_one']) ?></h1>
        <p class="dl-intro"><?= $e($is_many ? sprintf($t['intro_many'], $company, count($files)) : sprintf($t['intro_one'], $company)) ?></p>

        <section class="dl-panel">
            <ul class="dl-files">
                <?php foreach ($files as $f): ?>
                <li class="dl-file">
                    <span class="dl-file-type"><?= $e(dl_ext($f['original_name'])) ?></span>
                    <span class="dl-file-body">
                        <span class="dl-file-name"><?= $e($f['original_name']) ?></span>
                        <span class="dl-file-size"><?= $e(dl_size((int)($f['size'] ?? 0), $lang)) ?></span>
                    </span>
                </li>
                <?php endforeach; ?>
            </ul>

            <?php
            $days_left = (int)ceil((strtotime($meta['expires_at']) - time()) / 86400);
            $until     = dl_date($meta['expires_at'], $lang);
            if ($days_left > 0) {
                $until .= ' · ' . ($days_left === 1 ? $t['days_left_one'] : sprintf($t['days_left_many'], $days_left));
            }
            ?>
            <dl class="dl-facts">
                <?php if ($is_many): ?>
                <div><dt><?= $e($t['total_size']) ?></dt><dd><?= $e(dl_size(transfer_total_size($meta), $lang)) ?></dd></div>
                <?php endif; ?>
                <div><dt><?= $e($t['available_until']) ?></dt><dd><?= $e($until) ?></dd></div>
                <?php if (!empty($meta['max_downloads'])): ?>
                <div><dt><?= $e($t['downloads_left']) ?></dt><dd><?= (int)($meta['max_downloads'] - ($meta['download_count'] ?? 0)) ?></dd></div>
                <?php endif; ?>
            </dl>

            <?php if ($is_many): ?>
            <p class="dl-note"><?= $e($t['zip_hint']) ?></p>
            <?php endif; ?>
        </section>

        <form method="POST" action="" class="dl-form">
            <input type="hidden" name="token" value="<?= $e($token) ?>">

            <?php if ($status === 'wrong_password'): ?>
            <div class="dl-alert"><?= $e($t['wrong_password']) ?></div>
            <?php endif; ?>

            <?php if ($meta['password_hash'] !== null): ?>
            <div class="form-group">
                <label for="dl_password"><?= $e($t['password']) ?></label>
                <input type="password" id="dl_password" name="password" class="form-control"
                       placeholder="<?= $e($t['password_ph']) ?>"
                       autofocus required autocomplete="current-password">
                <div class="form-hint"><?= $e($t['password_hint']) ?></div>
            </div>
            <?php endif; ?>

            <button type="submit" class="btn dl-btn" id="dl-btn" data-loading="<?= $e($t['downloading']) ?>">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                <span><?= $e($t['download']) ?></span>
            </button>
        </form>

    <?php else: ?>
        <?php
        [$state_title, $state_text] = $t['states'][$status] ?? $t['states']['invalid'];
        $show_contact = in_array($status, ['revoked', 'expired', 'maxdownloads'], true);
        ?>
        <section class="dl-state">
            <span class="dl-state-icon">
                <?php if ($status === 'expired'): ?>
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                <?php elseif (in_array($status, ['revoked', 'invalid'], true)): ?>
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>
                <?php else: ?>
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <?php endif; ?>
            </span>
            <h1 class="dl-title"><?= $e($state_title) ?></h1>
            <p class="dl-intro"><?= $e($state_text) ?></p>
            <?php if ($show_contact): ?>
            <p class="dl-note"><?= $e(sprintf($t['contact'], $company)) ?></p>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    </div>
</main>

<footer class="dl-footer">
    <div class="dl-shell">
        <span><?= $e($footer_text) ?></span>
        <?php if ($legal_links): ?>
        <nav class="dl-legal">
            <?php foreach ($legal_links as $label => $url): ?>
            <a href="<?= $e($url) ?>" target="_blank" rel="noopener noreferrer"><?= $e($label) ?></a>
            <?php endforeach; ?>
        </nav>
        <?php endif; ?>
    </div>
</footer>

<script src="<?= rtrim(APP_URL, '/') ?>/assets/dl.js"></script>
</body>
</html>
<?php
}
