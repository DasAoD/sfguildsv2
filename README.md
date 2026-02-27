# S&F Guilds v2

Privates Guild-Management-System für das Browsergame **Shakes & Fidget**.  
Verwaltet mehrere Gilden, Mitglieder, Kampfberichte und Statistiken.

---

## Features

### Öffentlich (kein Login erforderlich)
- Dashboard mit Gilden-Übersicht
- Gilden-Detailseiten mit Mitgliederlisten

### Nach Login
- Kampf-Kalender mit Detailansicht
- Battle-Reports und Statistiken
- Posteingang für Kampfberichte
- Automatisierter Abruf von Kampfberichten über S&F-Accounts
- Spieler-Umbenennen nach Serverfusionen

### Admin-Bereich
- User-Verwaltung (anlegen, bearbeiten, löschen)
- Gilden-Verwaltung (erstellen, bearbeiten, löschen)
- System-Backup
- Log-Einsicht
- Spieler zusammenführen

---

## Rollen-System

| Funktion                  | Admin | Moderator | User |
|---------------------------|-------|-----------|------|
| Dashboard & Gilden        | ✅    | ✅        | ✅   |
| Reports & Kämpfe ansehen  | ✅    | ✅        | ✅   |
| Datum & Notizen eintragen | ✅    | ✅        | ❌   |
| Post abholen              | ✅    | ✅        | ❌   |
| Kämpfe löschen/verschieben| ✅    | ❌        | ❌   |
| Spieler umbenennen        | ✅    | ❌        | ❌   |
| Admin-Panel               | ✅    | ❌        | ❌   |
| User & Gilden verwalten   | ✅    | ❌        | ❌   |

---

## Systemanforderungen

- PHP 8.3+
- SQLite3-Extension
- nginx
- systemd (für automatisierten Import)

---

## Installation

### 1. Dateien bereitstellen

```bash
cp -r /path/to/sfguildsv2 /var/www/sfguildsv2
```

### 2. Berechtigungen setzen

```bash
chown -R www-data:www-data /var/www/sfguildsv2
chmod 755 /var/www/sfguildsv2
chmod 755 /var/www/sfguildsv2/data
chmod 755 /var/www/sfguildsv2/data/uploads
chmod 755 /var/www/sfguildsv2/storage
```

### 3. Encryption Key generieren

```bash
php -r "echo base64_encode(random_bytes(32)) . PHP_EOL;"
```

Den generierten Key in `/var/www/sfguildsv2/config/.env` eintragen:

```
ENCRYPTION_KEY=dein_generierter_key
```

### 4. Nginx Virtual Host einrichten

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name deine-domain.de;

    root /var/www/sfguildsv2/public;
    index index.php;

    access_log /var/log/nginx/sfguilds_access.log;
    error_log  /var/log/nginx/sfguilds_error.log;

    location / {
        try_files $uri $uri/ $uri.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }

    # Sensitive Verzeichnisse sperren
    location ~* ^/(cli|includes|config|data|storage)/ {
        return 404;
    }

    location ~ \.(db|sqlite|log|sh|env|ini)$ {
        return 404;
    }

    location ~ /\. {
        deny all;
    }
}
```

Site aktivieren:

```bash
ln -s /etc/nginx/sites-available/deine-domain.de /etc/nginx/sites-enabled/
nginx -t && systemctl reload nginx
```

### 5. SSL einrichten

```bash
certbot --nginx -d deine-domain.de
```

### 6. Datenbank und ersten Admin-User anlegen

```bash
php /var/www/sfguildsv2/install/setup.php
```

Das Setup-Script legt die Datenbank an und führt durch die Erstellung des ersten Admin-Accounts.

---

## Automatisierter CSV-Import

Der CSV-Import läuft über systemd-Services, die regelmäßig Daten aus dem Spiel abholen.  
Details zur Einrichtung: `install/SYSTEMD_SETUP.md`

---

## Technischer Stack

- **Backend:** PHP 8.3
- **Datenbank:** SQLite3 (WAL-Mode)
- **Frontend:** Vanilla JavaScript, Custom Dark Theme
- **Webserver:** nginx
- **Credential-Verschlüsselung:** AES-256-CBC + HMAC-SHA256
- **Automatisierung:** systemd

---

## Hinweise

Dieses Projekt ist für den privaten Einsatz gedacht und nicht für öffentliche Produktivumgebungen ausgelegt.