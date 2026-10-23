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
// Company name for header and footer (same as on the download page)
$_company = $settings['company_name'] !== '' ? $settings['company_name'] : $settings['site_name'];

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
        $error = 'Too many attempts. Please wait 5 minutes.';
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
            $error = 'Wrong password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Sign in – <?= htmlspecialchars($_company, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body class="dl-page">

<header class="dl-header">
    <div class="dl-shell">
        <?php if ($_logo_b64 !== null): ?>
        <img class="dl-header-logo" src="data:<?= htmlspecialchars($_logo_mime, ENT_QUOTES, 'UTF-8') ?>;base64,<?= $_logo_b64 ?>" alt="<?= htmlspecialchars($_company, ENT_QUOTES, 'UTF-8') ?>">
        <?php else: ?>
        <span class="dl-header-name"><?= htmlspecialchars($_company, ENT_QUOTES, 'UTF-8') ?></span>
        <?php endif; ?>
    </div>
</header>

<main class="dl-main">
    <div class="dl-shell">
        <h1 class="dl-title">Sign in</h1>
        <p class="dl-intro">Please sign in with the admin password.</p>

        <form method="POST" action="" class="login-form">
            <?= csrf_field() ?>

            <?php if ($error): ?>
            <div class="dl-alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password"
                       class="form-control"
                       autofocus required autocomplete="current-password">
            </div>
            <button type="submit" class="btn dl-btn">Sign in</button>
        </form>
    </div>
</main>

<?php
$_footer_text = $settings['footer_text'] !== '' ? $settings['footer_text'] : '© ' . date('Y') . ' ' . $_company;
$_legal = array_filter([
    'Legal notice'   => $settings['impressum_url'] ?? '',
    'Privacy policy' => $settings['datenschutz_url'] ?? '',
]);
?>
<footer class="dl-footer">
    <div class="dl-shell">
        <span><?= htmlspecialchars($_footer_text, ENT_QUOTES, 'UTF-8') ?></span>
        <?php if ($_legal): ?>
        <nav class="dl-legal">
            <?php foreach ($_legal as $_label => $_url): ?>
            <a href="<?= htmlspecialchars($_url, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer"><?= htmlspecialchars($_label, ENT_QUOTES, 'UTF-8') ?></a>
            <?php endforeach; ?>
        </nav>
        <?php endif; ?>
    </div>
</footer>

</body>
</html>
