<?php
/**
 * Admin-Login
 */

umask(0077);
ini_set('display_errors', 0);

require_once dirname(dirname(__DIR__)) . '/functions/bootstrap.php';

auth_session_start();

// Bereits eingeloggt → zum Dashboard
if (!empty($_SESSION['admin_logged_in'])) {
    header('Location: ' . APP_URL . '/admin/dashboard.php');
    exit;
}

$error    = '';
$settings = settings_load();

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $ip_hash = log_ip_hash(log_get_ip());

    // Rate-Limit: max 10 Login-Versuche pro 5 Minuten
    if (ratelimit_is_blocked('login', $ip_hash, 10, 300)) {
        $error = 'Zu viele Versuche. Bitte 5 Minuten warten.';
    } else {
        $password = $_POST['password'] ?? '';
        if (auth_login($password)) {
            log_event('admin_login');
            header('Location: ' . APP_URL . '/admin/dashboard.php');
            exit;
        } else {
            ratelimit_increment('login', $ip_hash, 300);
            log_event('admin_login_failed');
            // Kurze Verzögerung gegen Timing-Angriffe
            usleep(random_int(100000, 400000));
            $error = 'Falsches Passwort.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Login – <?= htmlspecialchars($settings['company_name'], ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body class="login-page">

<div class="login-card">
    <div class="login-logo">
        <?php if ($_logo_b64 !== null): ?>
        <div class="login-company-logo">
            <div style="width:200px;height:72px;background-image:url('data:<?= htmlspecialchars($_logo_mime, ENT_QUOTES, 'UTF-8') ?>;base64,<?= $_logo_b64 ?>');background-size:contain;background-repeat:no-repeat;background-position:center;"></div>
        </div>
        <div class="login-logo-sub"><?= htmlspecialchars($settings['company_name'], ENT_QUOTES, 'UTF-8') ?></div>
        <?php else: ?>
        <div class="login-product">
            <span class="login-logo-icon">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="var(--color-navy)" stroke-width="1.5">
                    <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                    <polyline points="3.27 6.96 12 12.01 20.73 6.96"/>
                    <line x1="12" y1="22.08" x2="12" y2="12"/>
                </svg>
            </span>
            <div class="login-logo-text"><?= htmlspecialchars($settings['company_name'], ENT_QUOTES, 'UTF-8') ?></div>
        </div>
        <?php endif; ?>
    </div>
    <div class="login-security-hint">Sichere Übertragung</div>

    <?php if ($error): ?>
    <div class="msg msg-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <?= csrf_field() ?>
        <div class="form-group">
            <label for="password">Passwort</label>
            <input type="password" id="password" name="password"
                   class="form-control"
                   placeholder="Admin-Passwort"
                   autofocus required autocomplete="current-password">
        </div>
        <button type="submit" class="btn">Anmelden</button>
    </form>
</div>

<?php if ($settings['footer_text'] !== '' || $settings['impressum_url'] !== ''): ?>
<div class="login-footer">
    <?php if ($settings['footer_text'] !== ''): ?>
        <?= htmlspecialchars($settings['footer_text'], ENT_QUOTES, 'UTF-8') ?>
    <?php endif; ?>
    <?php if ($settings['impressum_url'] !== ''): ?>
        <?php if ($settings['footer_text'] !== ''): ?> · <?php endif; ?>
        <a href="<?= htmlspecialchars($settings['impressum_url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer">Impressum</a>
    <?php endif; ?>
</div>
<?php endif; ?>

</body>
</html>
