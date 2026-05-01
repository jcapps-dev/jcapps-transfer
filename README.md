# jcapps-transfer

Self-hosted file sharing. Upload a file, get a link, the recipient downloads without an account. It started because we didn't want someone else's branding on links going to customers.

---

## Features

- **Drag & drop upload** — multiple files at once, automatically zipped
- **Large file support** — chunked upload bypasses server limits (tested up to 5 GB)
- **Secure download links** — 256-bit random tokens, optional password protection, short URLs (`/dl/abc12345`)
- **Auto-expiry** — links expire after a configurable number of days
- **Download limit** — optionally lock a link after N downloads
- **Revoke anytime** — disable any link from the admin dashboard
- **In-app updates** — one-click update from the admin dashboard
- **Branding** — configure your own logo, company name and footer text
- **Privacy** — IP addresses stored as hashed values only (GDPR-friendly)
- **No database** — flat-file storage, no MySQL required

---

## Installation

**Requirements:** PHP 8.0+, Apache with `mod_rewrite`. No Composer, no npm, nothing else.

**1.** [Download `install.php`](https://github.com/jcapps-dev/jcapps-transfer/releases/latest)

**2.** Upload it to your webroot (FTP, file manager, rsync — whatever works)

**3.** Open it in your browser:
```
https://your-domain.com/install.php
```

The installer downloads the latest release and walks you through setup. Set a password, confirm the upload directory — done.

---

## Updating

When a new version is available, the admin dashboard shows an update banner. Click **Update now** — config, uploads and logs are fully preserved.

If the in-app update is not available on your host, use the manual updater:

**1.** [Download `update.php`](https://github.com/jcapps-dev/jcapps-transfer/releases/latest)

**2.** Upload it to your webroot

**3.** Open it in your browser:
```
https://your-domain.com/update.php
```

The file deletes itself after a successful update.

---

## Manual installation

If the installer is not an option:

1. [Download the latest release as ZIP](https://github.com/jcapps-dev/jcapps-transfer/releases/latest)
2. Extract and upload to your webroot
3. Copy and edit the config:
   ```bash
   cp config.example.php config.php
   ```
4. Create the upload directory (outside webroot recommended):
   ```bash
   mkdir -p /path/outside/webroot/transfers/logs/ratelimit
   chmod 700 /path/outside/webroot/transfers
   ```
5. Generate the admin password hash and add it to `config.php`:
   ```bash
   php -r "echo password_hash('yourpassword', PASSWORD_BCRYPT, ['cost'=>12]);"
   ```

---

## Configuration

All settings in `config.php`:

| Option | Description | Default |
|--------|-------------|---------|
| `transfer_base_path` | Path for uploads | — |
| `admin_password_hash` | bcrypt hash of admin password | — |
| `max_filesize_mb` | Max file size in MB | `1024` |
| `max_files_per_upload` | Max files per transfer | `10` |
| `transfer_lifetime_days` | Link expiry in days | `14` |
| `app_url` | Public URL of the installation | — |

Branding (logo, company name, footer) is configurable in the admin panel under **Settings**.

---

## Cron job (recommended)

Expired transfers and files are cleaned up automatically if a cron job is set up:

```
0 4 * * * php /path/to/webroot/cleanup.php
```

---

## Directory structure

```
jcapps-transfer/
├── install.php          ← one-time: downloads the release
├── functions/           ← PHP functions (HTTP access blocked)
├── config.php           ← your config (not in repo)
└── public/              ← webroot
    ├── setup.php        ← one-time: setup wizard
    ├── update.php       ← upload when updating
    ├── dl.php           ← download endpoint for recipients
    └── admin/           ← admin interface (login required)

/path/outside/webroot/transfers/    ← uploads (not in webroot)
    ├── {token}/
    │   ├── meta.json
    │   └── files/
    └── logs/
```

---

## Security

| Mechanism | Details |
|---|---|
| Download token | 256-bit random value |
| Admin password | bcrypt, cost 12 |
| CSRF protection | Synchronizer token |
| Rate limiting | Filesystem-based, IP hashed |
| Uploads | Outside webroot recommended |
| Content-Security-Policy | Active, no inline JS |

---

## Screenshots

[jcapps.dev/screenshots.html](https://jcapps.dev/screenshots.html)

---

## License

MIT — free to use, including commercially.

---

<p align="center">
  <a href="https://jcapps.dev">jcapps.dev</a>
</p>
