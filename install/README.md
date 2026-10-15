# Install – Installationsvorlagen & Hilfsdateien

Dieser Ordner enthält Vorlagen und Hilfsdateien für die Installation und Einrichtung von sfguildsv2.

> ⚠️ **Wichtig:** Alle Dateien in diesem Ordner sind **Vorlagen**. Pfade, Hostnamen und
> Konfigurationswerte müssen vor der Verwendung an die eigene Umgebung angepasst werden.

---

## Inhalt

### `nginx/sfguilds.uliana.de`
Nginx Virtual Host Konfiguration.

**Anpassen:**
- `server_name` – eigene Domain
- `root` – Pfad zum Projektverzeichnis (`/var/www/sfguildsv2/public`)
- SSL-Zertifikatspfade (Let's Encrypt oder eigene Zertifikate)
- `fastcgi_pass` – PHP-FPM Socket (aktuell: `php8.4-fpm.sock`)

### `usr/local/bin/deploy-sfguilds.sh`
Deployment-Script für automatisierte Updates via Git.

**Anpassen:** `REPO_DIR`, `LOCK`, `LOG` und `WEB_GROUP` im Kopfbereich der Datei.

### `BATTLE_INBOX_INSTALL.md`
> ⚠️ Veraltet (Stand Januar 2026). Aktuelle Build- und Deploy-Anleitung für
> die Rust-Binaries: `rust_examples/README.md`

### `CREATE_FAVICON.md`
Anleitung zur Erstellung eines Favicons aus dem S&F-Logo.

---

## Schnellstart

1. Nginx-Konfiguration anpassen und nach `/etc/nginx/sites-available/` kopieren
2. `php cli/reset_password.php` für den ersten Admin-User
3. Cron einrichten (siehe Hauptdokumentation in `README.md`)
