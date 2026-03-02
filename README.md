# S&F Guilds v2

Ein privates Guild-Management-System für das Browsergame [Shakes & Fidget](https://sfgame.net), das mehrere Gilden auf verschiedenen Servern verwaltet.

## Features

- 🏰 **Multi-Gilden-Verwaltung** – Verwaltung mehrerer Gilden auf verschiedenen S&F-Servern
- 👥 **Mitglieder-Tracking** – Statistiken, Beitrittsdaten, Gold-Donationen, Tags und Notizen
- ⚔️ **Kampfberichte** – Import und Auswertung von Gildenkampf-Berichten
- 📊 **Statistiken & Analysen** – Mitglieder-Entwicklung, Kampf-Performance
- 📅 **Battle-Kalender** – Übersicht über vergangene und geplante Gildenkämpfe
- 🔱 **Hellevator-Übersicht** – Etagen-Anforderungen und Spieler-Empfehlungen
- 🔄 **Automatischer CSV-Import** – Datenimport via SFTools-Export und systemd-Service
- 🛡️ **Wappen-Upload** – Gilden-Wappen hochladen und verwalten
- 🔐 **Benutzerverwaltung** – Login, Sessions, Passwort-Reset (inkl. CLI-Admin-Tool)

## Technischer Stack

| Komponente | Version |
|---|---|
| PHP | 8.3 |
| Datenbank | SQLite3 |
| Webserver | nginx |
| OS | Ubuntu (VPS) |
| CLI-Tools | Rust (sf-api Integration) |
| Prozess-Management | systemd |

## Verzeichnisstruktur

```
/var/www/sfguildsv2/
├── api/                    # REST-API-Endpunkte
├── cli/                    # Kommandozeilen-Tools (PHP & Rust)
├── config/
│   └── database.php        # Datenbank-Konfiguration
├── data/
│   ├── sfguilds.sqlite     # SQLite-Datenbank
│   └── uploads/            # Gilden-Wappen
├── includes/
│   ├── auth.php            # Authentifizierung & Sessions
│   └── functions.php       # Helper-Funktionen (inkl. jsonResponse/jsonError)
├── install/                # Installations-Skripte & Schema
├── public/                 # Document Root (nginx)
│   ├── assets/
│   │   ├── css/            # Stylesheets (Dark Theme)
│   │   ├── js/             # Vanilla JavaScript
│   │   └── images/
│   ├── index.php           # Login
│   ├── dashboard.php       # Übersicht
│   ├── guild.php           # Gilden-Detailseite
│   ├── members.php         # Mitgliederverwaltung
│   ├── battles.php         # Kampfberichte
│   ├── hellevator.php      # Hellevator-Übersicht
│   └── ...
└── storage/
    └── import/             # Temporärer CSV-Import-Ordner
```

## Installation

### Voraussetzungen

- PHP >= 8.3 mit SQLite3-Extension
- nginx
- Rust (für CLI-Tools, optional)

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
chmod 775 /var/www/sfguildsv2/storage/import
```

### 3. Nginx Virtual Host

```nginx
server {
    listen 443 ssl;
    server_name sfguildsv2.example.com;

    root /var/www/sfguildsv2/public;
    index index.php;

    access_log /var/log/nginx/sfguildsv2_access.log;
    error_log /var/log/nginx/sfguildsv2_error.log;

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }

    location ~ /\. {
        deny all;
    }

    location ~ \.(db|sqlite|log|ini)$ {
        deny all;
    }

    location / {
        try_files $uri $uri/ $uri.php?$query_string;
    }
}
```

### 4. Datenbank initialisieren

```bash
php /var/www/sfguildsv2/install/setup.php
```

### 5. Ersten Admin-User anlegen

```bash
php /var/www/sfguildsv2/cli/create_user.php --username=admin --role=admin
```

### 6. SSL aktivieren

```bash
certbot --nginx -d sfguildsv2.example.com
```

## Daten-Import

Kampfberichte werden automatisch über die [sf-api von The Marenga](https://github.com/the-marenga/sf-api) abgeholt – eine Rust-basierte API, die direkt mit den S&F-Game-Servern kommuniziert. Die zugehörigen CLI-Tools sind in Rust geschrieben und nutzen diese Bibliothek.

Mitgliederdaten werden via [SFTools](https://sftools.mar21.eu) als CSV exportiert und über den automatisierten Import-Prozess eingelesen. Ein systemd-Service überwacht das `storage/import/`-Verzeichnis und verarbeitet neue Dateien automatisch.

## Sicherheit

Das System wurde mehrfachen systematischen Sicherheits-Audits unterzogen:

- Zentralisierte Fehlerbehandlung (`jsonResponse()` / `jsonError()`)
- `JSON_THROW_ON_ERROR` für alle User-Inputs
- `catch (Throwable)` statt `catch (Exception)` in kritischen Pfaden
- Whitelist-basierte API-Antworten (kein `SELECT *` an öffentliche Endpunkte)
- Atomares `flock()`-Locking für konkurrierende Prozesse
- CLI-Only-Guards für interne Skripte
- Strikte `base64_decode()`-Validierung

## Notizen

- Dieses System ist für den privaten Gebrauch konzipiert
- Die öffentliche API gibt nur nicht-sensitive Felder zurück
- SF-API-Verhalten variiert je nach Server und Charakter – Fallback-Mechanismen sind implementiert

## Lizenz

Privates Projekt – keine öffentliche Lizenz.
