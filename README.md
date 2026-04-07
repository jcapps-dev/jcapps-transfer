# jcapps-transfer

Sicherer Datei-Transfer für Teams. Admins laden Dateien hoch und verschicken Download-Links per E-Mail — Empfänger brauchen keinen Account.

---

## Funktionen

- **Einfaches Hochladen** — Dateien per Drag & Drop, mehrere Dateien gleichzeitig (automatisch als ZIP)
- **Sichere Download-Links** — 256-Bit-Zufallstoken, optional passwortgeschützt
- **Automatisch ablaufend** — Links verfallen nach konfigurierbarer Anzahl von Tagen
- **Download-Limit** — optional: Link sperrt sich nach N Downloads
- **Widerrufen** — Links können jederzeit deaktiviert werden
- **Branding** — eigenes Logo, Firmenname und Footer-Text konfigurierbar
- **Datenschutz** — IP-Adressen werden nur gehasht gespeichert (DSGVO-freundlich)
- **Kein Datenbank** — alle Daten als einfache Dateien, kein MySQL nötig

---

## Installation

### Voraussetzungen

- PHP 8.0 oder neuer
- Apache mit `mod_rewrite` (die meisten Webhoster erfüllen das)
- Kein MySQL, kein Composer, keine weitere Software nötig

### In 3 Schritten

**1.** [`install.php` herunterladen](https://github.com/jcapps-dev/jcapps-transfer/releases/latest)

**2.** Ins Webverzeichnis hochladen (z.B. per FTP oder Dateimanager des Hosters)

**3.** Im Browser aufrufen:
```
https://deine-domain.de/install.php
```

Der Installer lädt automatisch die aktuelle Version herunter und startet den Setup-Assistenten. Dort nur Passwort und Upload-Verzeichnis eingeben — fertig.

---

## Update

Wenn im Admin-Dashboard ein Update angezeigt wird:

**1.** [`update.php` herunterladen](https://github.com/jcapps-dev/jcapps-transfer/releases/latest)

**2.** Ins Webverzeichnis hochladen

**3.** Im Browser aufrufen:
```
https://deine-domain.de/update.php
```

Konfiguration, Uploads und Logs bleiben dabei vollständig erhalten.

---

## Manuelle Installation

Falls der automatische Installer nicht verfügbar ist:

1. [Aktuelle Version als ZIP herunterladen](https://github.com/jcapps-dev/jcapps-transfer/releases/latest)
2. Entpacken und ins Webverzeichnis hochladen
3. `config.example.php` kopieren und anpassen:
   ```bash
   cp config.example.php config.php
   ```
4. Upload-Verzeichnis anlegen (außerhalb des Webroots empfohlen):
   ```bash
   mkdir -p /pfad/ausserhalb/webroot/transfers/logs/ratelimit
   chmod 700 /pfad/ausserhalb/webroot/transfers
   ```
5. Admin-Passwort-Hash erzeugen und in `config.php` eintragen:
   ```bash
   php -r "echo password_hash('MeinPasswort', PASSWORD_BCRYPT, ['cost'=>12]);"
   ```

---

## Konfiguration

Alle Einstellungen in `config.php`:

| Option | Beschreibung | Standard |
|--------|-------------|---------|
| `transfer_base_path` | Pfad für Uploads | — |
| `admin_password_hash` | bcrypt-Hash des Admin-Passworts | — |
| `max_filesize_mb` | Maximale Dateigröße in MB | `1024` |
| `max_files_per_upload` | Maximale Anzahl Dateien pro Transfer | `10` |
| `transfer_lifetime_days` | Ablaufzeit der Links in Tagen | `14` |
| `app_url` | Öffentliche URL der Installation | — |

Branding (Logo, Firmenname, Footer) ist direkt im Admin-Bereich unter **Einstellungen** konfigurierbar.

---

## Cronjob (empfohlen)

Abgelaufene Transfers und Dateien werden automatisch bereinigt wenn ein Cronjob eingerichtet ist:

```
0 4 * * * php /pfad/zum/webverzeichnis/cleanup.php
```

---

## Verzeichnisstruktur

```
jcapps-transfer/
├── install.php          ← einmalig: lädt Release herunter
├── functions/           ← PHP-Funktionen (HTTP-Zugriff gesperrt)
├── config.php           ← deine Konfiguration (nicht im Repo)
└── public/              ← Webroot
    ├── setup.php        ← einmalig: Einrichtungsassistent
    ├── update.php       ← bei Updates hochladen
    ├── dl.php           ← Download-Endpoint für Empfänger
    └── admin/           ← Admin-Interface (Login erforderlich)

/pfad/ausserhalb/webroot/transfers/    ← Uploads (nicht im Webroot!)
    ├── {token}/
    │   ├── meta.json
    │   └── files/
    └── logs/
```

---

## Sicherheit

| Mechanismus | Details |
|---|---|
| Download-Token | 256-Bit Zufallswert |
| Admin-Passwort | bcrypt, cost 12 |
| CSRF-Schutz | Synchronizer Token |
| Rate-Limiting | Filesystem-basiert, IP gehasht |
| Uploads | außerhalb des Webroots empfohlen |
| Content-Security-Policy | aktiv, kein inline-JS |

---

## Lizenz

MIT — frei verwendbar, auch kommerziell.

---

<p align="center">
  <a href="https://jcapps.dev">jcapps.dev</a>
</p>
