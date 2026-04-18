<?php
/**
 * Configuration template — do NOT commit with real values!
 * Copy as /home/www/filetransfer_config.php (server)
 * or as filetransfer/config.php (local development, in .gitignore)
 *
 * Generate a bcrypt hash:
 *   php -r "echo password_hash('MyPassword', PASSWORD_BCRYPT, ['cost'=>12]);"
 */
return [
    // Directory for uploads — OUTSIDE the web root!
    'transfer_base_path'     => '/home/www/transfers',

    // Bcrypt hash of the admin password (cost 12)
    'admin_password_hash'    => '$2y$12$REPLACE_WITH_ACTUAL_BCRYPT_HASH_HERE_xxxxxxxxxxxxx',

    // Upload limits
    'max_filesize_mb'        => 1024,
    'max_files_per_upload'   => 10,

    // Expiry time for transfer links (in days)
    'transfer_lifetime_days' => 14,

    // Salt for IP hashing in rate limiting and logging (GDPR: no plain-text IP)
    // At least 32 random characters — generate once and never change!
    'rate_limit_salt'        => 'RANDOM-REPLACE-AT-LEAST-32-CHARS-HERE',

    // Public URL of the tool (no trailing slash)
    'app_url'                => 'https://transfer.example.com',

    // Session name (keep unique if multiple apps run on the same server)
    'session_name'           => 'JCAPPS_FILETRANSFER',
];
