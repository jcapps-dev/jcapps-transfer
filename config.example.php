<?php
/**
 * Konfigurationsvorlage — NICHT mit echten Daten committen!
 * Kopieren als /home/www/filetransfer_config.php (Server)
 * oder als filetransfer/config.php (lokale Entwicklung, in .gitignore)
 *
 * Bcrypt-Hash erzeugen:
 *   php -r "echo password_hash('MeinPasswort', PASSWORD_BCRYPT, ['cost'=>12]);"
 */
return [
    // Verzeichnis für Uploads — AUSSERHALB des Webroots!
    'transfer_base_path'     => '/home/www/transfers',

    // Bcrypt-Hash des Admin-Passworts (cost 12)
    'admin_password_hash'    => '$2y$12$REPLACE_WITH_ACTUAL_BCRYPT_HASH_HERE_xxxxxxxxxxxxx',

    // Upload-Limits
    'max_filesize_mb'        => 1024,
    'max_files_per_upload'   => 10,

    // Ablaufzeit für Transfer-Links (in Tagen)
    'transfer_lifetime_days' => 14,

    // Salt für IP-Hashing im Rate-Limit und Logging (DSGVO: kein Klartext-IP)
    // Mindestens 32 zufällige Zeichen — einmalig generieren und nie ändern!
    'rate_limit_salt'        => 'ZUFAELLIG-ERSETZEN-MINDESTENS-32-ZEICHEN-HIER',

    // Öffentliche URL des Tools (kein trailing slash)
    'app_url'                => 'https://transfer.example.com',

    // Session-Name (eindeutig halten wenn mehrere Apps auf gleichem Server)
    'session_name'           => 'JCAPPS_FILETRANSFER',
];
