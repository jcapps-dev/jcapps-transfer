<?php
/**
 * PHP-Versionsprüfung für den Admin-Bereich
 *
 * 1. Support-Ende des PHP-Zweigs laut php.net — neue Zweige kommen damit automatisch
 *    dazu. Ohne Verbindung zu php.net greift die eingebaute Tabelle.
 * 2. Patch-Stand: neuester Patch des Zweigs laut php.net.
 *    Beides wird max. 1× täglich abgefragt und gecacht. Gewarnt wird, wenn ein neuerer Patch seit über 30 Tagen verfügbar
 *    ist oder mindestens zwei Patches fehlen — der Hoster spielt Patches mit etwas
 *    Verzögerung ein, ein einzelner frischer Patch löst daher noch keine Warnung aus.
 *
 * Geprüft wird PHP_VERSION, also die Version, mit der die Weboberfläche läuft
 * (kann auf Shared Hosting von `php -v` auf der Kommandozeile abweichen).
 */

// Ersatz-Tabelle (nur wenn php.net nicht erreichbar ist): Ende der Sicherheitsupdates
// je Zweig — Quelle: https://www.php.net/supported-versions.php. Muss nicht gepflegt
// werden; unbekannte, neuere Zweige gelten ohne Verbindung als unterstützt.
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
    $remote  = phpcheck_remote($branch);

    // 1. Support-Ende: Daten von php.net, ohne Verbindung die eingebaute Tabelle
    $eol_date = $remote['eol'] ?? PHPCHECK_SECURITY_EOL[$branch] ?? null;
    if ($eol_date === null && version_compare($branch, array_key_first(PHPCHECK_SECURITY_EOL), '<')) {
        return ['level' => 'error', 'text' =>
            "This server runs PHP {$current}, which no longer receives security updates. Please switch to a supported PHP version in your hosting control panel."];
    }
    if ($eol_date !== null) {
        $eol      = strtotime($eol_date . ' 23:59:59');
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
    $latest = $remote['version'] !== null ? $remote : null;
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
 * Daten von php.net für einen Zweig:
 * ['version' => neuester Patch|null, 'date' => Timestamp|null, 'eol' => 'YYYY-MM-DD'|null]
 * - Patch-Stand:  https://www.php.net/releases/?json&version=8.4
 * - Support-Ende: https://www.php.net/releases/states.php?json (alle Zweige, auch neue)
 * Ergebnis wird 24 h gecacht — auch Fehlschläge, damit das Dashboard nicht bei jedem
 * Aufruf auf einen Timeout wartet.
 */
function phpcheck_remote(string $branch): array
{
    $cache = TRANSFER_BASE . '/php_check.json';
    $data  = is_file($cache) ? json_decode((string)@file_get_contents($cache), true) : null;

    if (is_array($data) && ($data['branch'] ?? '') === $branch && array_key_exists('eol', $data)
        && time() - (int)($data['checked'] ?? 0) <= 86400) {
        return $data;
    }

    $data = ['branch' => $branch, 'checked' => time(), 'version' => null, 'date' => null, 'eol' => null];
    if (filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN)) {
        $ctx = stream_context_create(['http' => [
            'header'  => "User-Agent: filetransfer-phpcheck\r\n",
            'timeout' => 3,
        ]]);

        $rel = json_decode((string)@file_get_contents('https://www.php.net/releases/?json&version=' . rawurlencode($branch), false, $ctx), true);
        $ver = is_array($rel) ? (string)($rel['version'] ?? '') : '';
        $ts  = is_array($rel) ? strtotime((string)($rel['date'] ?? '')) : false;
        if (preg_match('/^\d+\.\d+\.\d+$/', $ver) && $ts) {
            $data['version'] = $ver;
            $data['date']    = $ts;
        }

        $states = json_decode((string)@file_get_contents('https://www.php.net/releases/states.php?json', false, $ctx), true);
        $major  = explode('.', $branch)[0];
        $eol    = is_array($states) ? ($states[$major][$branch]['security_support_end'] ?? '') : '';
        if (is_string($eol) && preg_match('/^\d{4}-\d{2}-\d{2}/', $eol, $m)) {
            $data['eol'] = $m[0];
        }
    }
    @file_put_contents($cache, json_encode($data), LOCK_EX);
    @chmod($cache, 0600);

    return $data;
}
