<?php
/**
 * PHP-Versionsprüfung für den Admin-Bereich
 *
 * 1. Support-Ende des PHP-Zweigs (fest hinterlegt, funktioniert offline)
 * 2. Patch-Stand: neuester Patch des Zweigs laut php.net (max. 1× täglich abgefragt,
 *    gecacht). Gewarnt wird, wenn ein neuerer Patch seit über 30 Tagen verfügbar
 *    ist oder mindestens zwei Patches fehlen — der Hoster spielt Patches mit etwas
 *    Verzögerung ein, ein einzelner frischer Patch löst daher noch keine Warnung aus.
 *
 * Geprüft wird PHP_VERSION, also die Version, mit der die Weboberfläche läuft
 * (kann auf Shared Hosting von `php -v` auf der Kommandozeile abweichen).
 */

// Ende der Sicherheitsupdates je Zweig — Quelle: https://www.php.net/supported-versions.php
// Neue Zweige bei Bedarf ergänzen; unbekannte, neuere Zweige gelten als unterstützt.
const PHPCHECK_SECURITY_EOL = [
    '8.1' => '2025-12-31',
    '8.2' => '2026-12-31',
    '8.3' => '2027-12-31',
    '8.4' => '2028-12-31',
    '8.5' => '2029-12-31',
];

const PHPCHECK_WARN_DAYS_BEFORE_EOL = 180;
const PHPCHECK_PATCH_GRACE_DAYS     = 30;

/**
 * Liefert null (alles in Ordnung) oder ['level' => 'error'|'warning', 'text' => string].
 */
function phpcheck_status(): ?array
{
    $branch  = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
    $current = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . '.' . PHP_RELEASE_VERSION;
    $oldest  = array_key_first(PHPCHECK_SECURITY_EOL);

    // 1. Support-Ende
    if (version_compare($branch, $oldest, '<')) {
        return ['level' => 'error', 'text' =>
            "This server runs PHP {$current}, which no longer receives security updates. Please switch to a supported PHP version in your hosting control panel."];
    }
    if (isset(PHPCHECK_SECURITY_EOL[$branch])) {
        $eol      = strtotime(PHPCHECK_SECURITY_EOL[$branch] . ' 23:59:59');
        $eol_text = date('Y-m-d', $eol);
        if (time() > $eol) {
            return ['level' => 'error', 'text' =>
                "This server runs PHP {$current}. PHP {$branch} has not received security updates since {$eol_text}. Please switch to a supported PHP version in your hosting control panel."];
        }
        if ($eol - time() < PHPCHECK_WARN_DAYS_BEFORE_EOL * 86400) {
            return ['level' => 'warning', 'text' =>
                "This server runs PHP {$current}. Security updates for PHP {$branch} end on {$eol_text} — plan to switch to a newer PHP version in your hosting control panel."];
        }
    }

    // 2. Patch-Stand
    $latest = phpcheck_latest_patch($branch);
    // Warnung, wenn der neueste Patch seit über 30 Tagen verfügbar ist oder schon
    // mindestens zwei Patches fehlen (PHP veröffentlicht etwa alle 4 Wochen)
    $behind = $latest !== null && version_compare($latest['version'], $current, '>')
        ? (int)explode('.', $latest['version'])[2] - PHP_RELEASE_VERSION : 0;
    if ($behind > 0
        && ($behind >= 2 || time() - $latest['date'] > PHPCHECK_PATCH_GRACE_DAYS * 86400)) {
        return ['level' => 'warning', 'text' =>
            "This server runs PHP {$current}. PHP {$latest['version']} with bug and security fixes has been available since " . date('Y-m-d', $latest['date']) . ", but your host has not installed it yet. Consider asking your host or re-selecting the PHP version in the control panel."];
    }

    return null;
}

/**
 * Neuester Patch eines Zweigs laut php.net: ['version' => '8.4.x', 'date' => timestamp] oder null.
 * Ergebnis wird 24 h gecacht — auch Fehlschläge, damit das Dashboard nicht bei jedem
 * Aufruf auf einen Timeout wartet.
 */
function phpcheck_latest_patch(string $branch): ?array
{
    $cache = TRANSFER_BASE . '/php_check.json';
    $data  = is_file($cache) ? json_decode((string)@file_get_contents($cache), true) : null;

    if (!is_array($data) || ($data['branch'] ?? '') !== $branch || time() - ($data['checked'] ?? 0) > 86400) {
        $data = ['branch' => $branch, 'checked' => time(), 'version' => null, 'date' => null];
        if (filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN)) {
            $ctx  = stream_context_create(['http' => [
                'header'  => "User-Agent: filetransfer-phpcheck\r\n",
                'timeout' => 3,
            ]]);
            $json = @file_get_contents('https://www.php.net/releases/?json&version=' . rawurlencode($branch), false, $ctx);
            $rel  = $json ? json_decode($json, true) : null;
            $ver  = is_array($rel) ? (string)($rel['version'] ?? '') : '';
            $ts   = is_array($rel) ? strtotime((string)($rel['date'] ?? '')) : false;
            if (preg_match('/^\d+\.\d+\.\d+$/', $ver) && $ts) {
                $data['version'] = $ver;
                $data['date']    = $ts;
            }
        }
        @file_put_contents($cache, json_encode($data), LOCK_EX);
        @chmod($cache, 0600);
    }

    return $data['version'] !== null ? ['version' => $data['version'], 'date' => (int)$data['date']] : null;
}
