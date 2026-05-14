# TODO – S&F Guilds v2

Konkrete anstehende Aufgaben. Sicherheitsrelevante Details sind in der privaten Version dokumentiert.

---

## 🔴 Hohe Priorität

*(Derzeit nichts Kritisches offen)*

---

## 🟡 Mittlere Priorität

### Import-Log
- [x] Import-Protokoll in der UI einsehbar machen (letzte Imports pro Gilde + gefilterte Activity-Einträge)

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

- [x] **sf-api nach S&F v30-Update repariert** (Binary-Rebuild auf v0.4.1)
- [x] **Login-Verhalten optimiert** — nur Ziel-Charakter wird eingeloggt, alle anderen Sessions unberührt
- [x] **Rust-Sources ins Repo** (rust_examples/: fetch_guild_reports.rs, list_chars.rs, member_sync.rs + README)
- [x] **joined_at editierbar** — inline wie fired_at/left_at
- [x] **Member-Sync via sf-api** implementiert (Rust-Binary + PHP-Endpoint + WebUI-Button)
  - "first seen"-Ansatz für joined_at (guild_joined seit v29.500 nicht mehr verfügbar)
  - Wiederbeitritte werden automatisch erkannt
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
