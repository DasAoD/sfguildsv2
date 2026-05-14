# S&F Guilds v2

Ein privates Guild-Management-System für das Browsergame [Shakes & Fidget](https://sfgame.net), das mehrere Gilden auf verschiedenen Servern verwaltet.

## Features

- 🏰 **Multi-Gilden-Verwaltung** – Verwaltung mehrerer Gilden auf verschiedenen S&F-Servern
- 👥 **Mitglieder-Tracking** – Statistiken, Beitrittsdaten, Gold-Donationen, Tags und Notizen
- ⚔️ **Kampfberichte** – Import und Auswertung von Gildenkampf-Berichten
- 📊 **Statistiken & Analysen** – Mitglieder-Entwicklung, Kampf-Performance
- 📅 **Battle-Kalender** – Übersicht über vergangene und geplante Gildenkämpfe
- 🔱 **Hellevator-Übersicht** – Etagen-Anforderungen und Spieler-Empfehlungen
- 🔄 **sf-api Member-Sync** – Mitgliederdaten direkt via sf-api abrufen (CSV als Fallback)
- ⏱️ **Cron-System** – Automatischer Fetch von Kampfberichten und Mitglieder-Sync
- 📋 **Import-Log** – Letzter Import pro Gilde und Import-Aktivitäten im Admin-Bereich
- 🛡️ **Wappen-Upload** – Gilden-Wappen hochladen und verwalten
- 🔐 **Benutzerverwaltung** – Login, Sessions, Passwort-Reset (inkl. CLI-Admin-Tool)

## Technischer Stack

| Komponente | Version |
|---|---|
| PHP | 8.4 |
| Datenbank | SQLite3 |
| Webserver | nginx |
| OS | Ubuntu (Heimserver dns1) |
| CLI-Tools | Rust (sf-api Integration) |
| Prozess-Management | cron |

## Verzeichnisstruktur

```
/var/www/sfguildsv2/
├── api/                    # REST-API-Endpunkte
├── cli/                    # Kommandozeilen-Tools (PHP & Rust)
│   ├── cron_runner.php     # Cron Master-Runner
│   ├── cron_fetch_reports.php
│   └── cron_member_sync.php
├── config/
│   └── database.php        # Datenbank-Konfiguration
├── data/
│   ├── sfguilds.sqlite     # SQLite-Datenbank
│   └── uploads/            # Gilden-Wappen
├── includes/               # Shared PHP-Funktionen
├── install/                # Installations-Skripte & Schema
├── public/                 # Document Root (nginx)
│   ├── assets/
│   │   ├── css/            # Stylesheets (Dark Theme)
│   │   └── js/             # Vanilla JavaScript
│   └── ...
├── rust_examples/          # Rust-Quellcode für sf-api Binaries
│   ├── fetch_guild_reports.rs
│   ├── list_chars.rs
│   ├── member_sync.rs
│   └── README.md           # Build- und Deploy-Anleitung
└── storage/
    ├── import/             # CSV-Import-Ordner (Fallback)
    └── sf_reports/         # Temporäre Kampfbericht-Dateien
```

## Installation

### Voraussetzungen

- PHP >= 8.4 mit SQLite3-Extension
- nginx
- Rust (für CLI-Tools — Binaries aus rust_examples/ bauen)

### 1. Dateien deployen

```bash
# Dateien nach /var/www/sfguildsv2/ kopieren
```

### 2. Berechtigungen setzen

```bash
chmod 755 /var/www/sfguildsv2
chown -R www-data:www-data /var/www/sfguildsv2
chmod 775 /var/www/sfguildsv2/data
chmod 775 /var/www/sfguildsv2/data/uploads
```

### 3. Nginx Virtual Host

```nginx
server {
    listen 443 ssl;
    server_name sfguilds.example.com;

    root /var/www/sfguildsv2/public;
    index index.php;

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
    }

    location ~ /\. { deny all; }
    location ~ \.(db|sqlite|log|ini)$ { deny all; }
    location / { try_files $uri $uri/ $uri.php?$query_string; }
}
```

### 4. Datenbank initialisieren

```bash
php /var/www/sfguildsv2/install/setup.php
```

### 5. Ersten Admin-User anlegen

```bash
php /var/www/sfguildsv2/cli/reset_password.php
```

### 6. Cron einrichten

```bash
# Shell-Wrapper kopieren und Crontab-Eintrag setzen (als root)
cp install/sfguilds_cron.sh /usr/local/bin/sfguilds_cron.sh
chmod +x /usr/local/bin/sfguilds_cron.sh
# Crontab:
# * * * * * /usr/local/bin/sfguilds_cron.sh >> /var/log/sfguilds_cron.log 2>&1
```

Cron-Zeiten und aktivierte Jobs werden im Admin-Bereich (Tab: Cronjobs) konfiguriert.

### 7. Rust-Binaries bauen

Siehe `rust_examples/README.md` für Build- und Deploy-Anleitung.

## Daten-Import

### sf-api Member-Sync (primär)
Mitgliederdaten direkt via [sf-api von The Marenga](https://github.com/the-marenga/sf-api) — manuell per Button auf der Gildenseite oder automatisch via Cron. Nur Admin-Accounts werden verwendet.

### CSV-Import (Fallback)
SFTools CSV-Export → manueller Upload oder automatisierter Import via `storage/import/`. Bleibt als Fallback erhalten für den Fall, dass sf-api nach einem S&F-Spielupdate vorübergehend nicht funktioniert.

### Kampfberichte
Automatisch via sf-api aus dem S&F-Postkasten (Cron: 07:25 + 19:10 Uhr).

## Sicherheit

- Zentralisierte Fehlerbehandlung (`jsonResponse()` / `jsonError()`)
- `JSON_THROW_ON_ERROR` für alle User-Inputs
- `catch (Throwable)` in kritischen Pfaden
- Whitelist-basierte API-Antworten
- Atomares `flock()`-Locking für konkurrierende Prozesse
- CLI-Only-Guards für interne Skripte
- Cron verwendet ausschließlich Admin-Accounts (role='admin')

## Notizen

- Dieses System ist für den privaten Gebrauch konzipiert
- `guild_joined` wird seit S&F v29.500 nicht mehr vom Server geliefert — "first seen"-Ansatz implementiert
- Die öffentliche API gibt nur nicht-sensitive Felder zurück

## Lizenz

Privates Projekt – keine öffentliche Lizenz.
