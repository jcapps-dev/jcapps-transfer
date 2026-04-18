# Changelog

## [1.1.1] — 2026-04-18

### Bug fix

- `do_update.php`: use app root instead of `sys_get_temp_dir()` for download temp file — fixes in-app update on shared hosts where the system temp dir is not writable
- Translate remaining German strings in JS files (`admin-dashboard.js`, `admin-upload.js`)

## [1.1.0] — 2026-04-18

### Localization

- All UI text translated to English (download page, admin backend, setup/update/install wizards, error messages)
- `lang="de"` → `lang="en"` in all HTML pages
- Date format changed from `d.m.Y` to `Y-m-d` (ISO 8601)
- "Impressum" link renamed to "Legal notice"
- Default `company_name` fallback changed to "Secure File Transfer"

## [1.0.0] — 2026-04-07

### Erste öffentliche Version

- Datei-Upload mit Drag & Drop, mehrere Dateien (automatisch als ZIP)
- Sichere Download-Links mit 256-Bit-Token
- Optionaler Passwortschutz und Download-Limit
- Automatisch ablaufende Links (konfigurierbar)
- Admin-Dashboard mit Transfer-Übersicht
- Branding: Logo, Firmenname, Footer-Text
- `install.php` — Ein-Klick-Installer
- `setup.php` — Setup-Assistent
- `update.php` — Ein-Klick-Updater mit Versions-Check
- DSGVO-freundliches Logging (IP-Hashing)
- Kein MySQL, kein Composer
