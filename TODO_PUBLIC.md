# TODO – S&F Guilds v2

Konkrete anstehende Aufgaben. Sicherheitsrelevante Details sind in der privaten Version dokumentiert.

---

## 🔴 Hohe Priorität

*(Derzeit nichts Kritisches offen)*

---

## 🟡 Mittlere Priorität

### Containerisierung
- [ ] Dockerfile erstellen (Base: php:8.4-fpm-bookworm, supervisord für nginx + php-fpm)
- [ ] docker/nginx.conf, docker/supervisord.conf, docker/entrypoint.sh
- [ ] docker-compose.yml für lokales Testen
- [ ] README.md um Docker-Abschnitt erweitern
- [ ] Docker Hub Secrets (DOCKERHUB_USERNAME, DOCKERHUB_TOKEN) im GitHub-Repo hinterlegen

---

## 🟢 Niedrige Priorität / Nice-to-have

### Setup-System
- [ ] Installations-Assistent (Web-basiert oder CLI) für Erst-Einrichtung

### Dokumentation
- [ ] API-Endpunkte intern dokumentieren
- [ ] Nginx-Konfiguration in install/ auf php8.4-fpm.sock aktualisieren

### UI-Verbesserungen
- [ ] Mobile-Ansicht überarbeiten
- [ ] Ladeanimationen bei API-Calls

---

## ✅ Zuletzt erledigt (Mai 2026)

- [x] **PHP 8.4 Timezone gesetzt** — `date.timezone = Europe/Berlin` in php.ini (FPM + CLI) fehlte nach Upgrade von 8.2; führte zu UTC-Datumsberechnung statt CEST
- [x] **days_offline UTC/CEST-Bug gefixt** — `date()` durch `gmdate()` ersetzt; UTC-Timestamps wurden durch CEST-Serverzeit verfälscht und zeigten -1 Tag offline
- [x] **member_sync Pfad korrigiert** — cron_member_sync.php und api/sf_member_sync.php zeigten auf Build-Pfad statt /opt/sf-api/
- [x] **sf-api auf v0.4.3 aktualisiert** — Kompatibilität mit S&F-Server v30.500 wiederhergestellt; Cargo.toml-Konflikt (tokio 1.51→1.52) gelöst, alle Binaries neu gebaut
- [x] **fetch_guild_reports: robuste systemmessagelist-Behandlung** — leere systemmessagelist (Postfach geleert) wird sofort als "0 Berichte" behandelt; ungültige Seed-Message-IDs werden graceful übersprungen statt mit Fehler abzubrechen
- [x] **cron_runner.php: PHP Warnings gefixt** — `$job`/`$jobs` wurden durch `require`'d Script im selben Scope überschrieben; `$jobKey`/`$jobLabel` werden jetzt vor dem `require` gesichert
- [x] **Mitglieder-Sortierung überarbeitet** — Aktive ≥7 Tage offline als Block ans Ende der Aktiven-Liste, sortiert nach Tagen offline; Offline-Tage datums-basiert berechnet (keine Uhrzeitkomponente)
- [x] **Login-Verhalten optimiert** — nur Ziel-Charakter wird eingeloggt, alle anderen Sessions unberührt
- [x] **Rust-Sources ins Repo** (rust_examples/: fetch_guild_reports.rs, list_chars.rs, member_sync.rs + README)
- [x] **joined_at editierbar** — inline wie fired_at/left_at
- [x] **Member-Sync via sf-api** implementiert (Rust-Binary + PHP-Endpoint + WebUI-Button)
  - "first seen"-Ansatz für joined_at (guild_joined seit v29.500 nicht mehr verfügbar)
  - Wiederbeitritte werden automatisch erkannt
- [x] **Import-Log** im Admin-Bereich (Logs → Import-Tab): letzter Import pro Gilde + gefilterte Activity-Einträge
- [x] **Cron-System** implementiert
  - cli/cron_runner.php (jede Minute via crontab)
  - Admin-UI: Cronjobs-Tab + Cron-Status im Logs-Tab
  - Nur Admin-Accounts (role='admin') werden verwendet
- [x] Rollen-System (Admin / Moderator / User)
- [x] Multi-Account-Support
- [x] Battle-Kalender
- [x] Kampfbericht-Import via sf-api
- [x] Hellevator-Seite (zweisprachig DE/EN)
- [x] Passwort-Reset-System (Web + CLI)

---

*Letzte Aktualisierung: Mai 2026*
