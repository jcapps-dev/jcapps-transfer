<?php
/**
 * Auth-Funktionen — Session-Härtung, Login, Logout, CSRF
 */

function auth_session_start(): void
{
    global $config;

    $session_name = $config['session_name'] ?? 'SG_FILETRANSFER';

    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', 1);
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.gc_maxlifetime', 1800); // 30 Minuten

    session_name($session_name);
    session_start();

    // Session-Timeout prüfen
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > 1800) {
        session_unset();
        session_destroy();
        session_start();
    }
    $_SESSION['last_activity'] = time();
}

function auth_check(): void
{
    if (empty($_SESSION['admin_logged_in'])) {
        header('Location: ' . APP_URL . '/admin/index.php');
        exit;
    }
}

function auth_login(string $password): bool
{
    global $config;

    if (password_verify($password, $config['admin_password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['last_activity']   = time();
        return true;
    }
    return false;
}

function auth_logout(): void
{
    session_unset();
    session_destroy();
    header('Location: ' . APP_URL . '/admin/index.php');
    exit;
}

// ── CSRF ─────────────────────────────────────────────────────────────────────

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="'
        . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function csrf_verify(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        die('Ungültige Anfrage (CSRF-Schutz). Bitte Seite neu laden.');
    }
}

// ── Flash-Nachrichten ─────────────────────────────────────────────────────────

function flash_set(string $type, string $text): void
{
    $_SESSION['flash'][] = ['type' => $type, 'text' => $text];
}

function flash_get(): array
{
    if (!isset($_SESSION['flash'])) return [];
    $msgs = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $msgs;
}
