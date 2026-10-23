# Changelog

## [1.3.7] — 2026-10-23

### Improvement

- Download page language: the browser's language list is now checked in order of preference and the first supported language (German or English) wins — e.g. "Chinese, German, English" now shows German instead of English. Falls back to English if neither is listed.

## [1.3.6] — 2026-10-23

### Maintenance

- Default admin session cookie name is now `JCAPPS_FILETRANSFER` (only applies to manual installs without `session_name` in the config — setup always writes its own)
- README: requirements (PHP extensions, Apache 2.4), config table (`rate_limit_salt`, `session_name`, per-transfer size limit), config location outside the webroot, storage directory, cleanup scope and an extended security overview
- README: jcapps banner and inline screenshots

## [1.3.5] — 2026-10-23

### Bug fix

- Dashboard at medium window widths (~1200–1300 px): the table now scrolls horizontally instead of clipping the "Delete" button
- Date and size columns no longer wrap; stat cards fit in one row

## [1.3.4] — 2026-10-23

### Download page redesign

- Calm, corporate layout: header with logo, content without floating card, full-width footer with legal links
- Removed duplicate company name under the logo and the "Secure transfer" line
- File type badge, name and size for every file; "Available until" and remaining downloads as separate facts
- **Bilingual (DE/EN)** based on the browser's Accept-Language (German if preferred, otherwise English) incl. date and number formats
- Error states (expired, revoked, limit reached) in the same layout with a hint to contact the sender
- New setting **Privacy policy URL**; legal links shown on download and login page
- Empty footer text now defaults to "© year company name"

## [1.3.3] — 2026-10-23

### Bug fix

- Inline confirmation for "Revoke" / "Delete" now actually waits for "Yes" — previously the form was submitted immediately while the confirmation was shown
- "Copy link" keeps its icon after copying

### Design

- Mobile: sidebar collapses to an icon rail instead of disappearing; tables keep the actions column (icon-only) and hide secondary columns
- Row actions "Revoke" / "Delete" are now outlined and only fill on hover
- Upload form: password, max downloads and expiry in one row; quick select for expiry (1 / 7 / 14 / 30 days)
- Download page: multi-file transfers list every file with its size

## [1.3.2] — 2026-10-23

### Security hardening

- Download limit is now enforced atomically (validity check + counter increment under one lock) — parallel requests can no longer exceed `max_downloads`
- `meta.json` is written atomically (temp file + `rename()`); persistent `meta.lock` replaces the self-deleting lock file
- Revoking a transfer uses the same lock
- Rate limit for short-link lookups (`/dl/XXXXXXXX`): max. 30 misses per minute and IP
- `session.use_strict_mode` enabled
- Logout requires POST + CSRF token (sidebar button instead of link)
- Legal notice URL accepts only `http://` / `https://`

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
