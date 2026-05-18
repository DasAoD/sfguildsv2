# Roadmap – S&F Guilds v2

Öffentliche Roadmap des Projekts. Sicherheitsrelevante Implementierungsdetails sind bewusst ausgelassen.

---

## ✅ Abgeschlossen

### Grundsystem
- [x] Login, Sessions, Logout
- [x] Datenbank-Schema (SQLite3)
- [x] Nginx-Konfiguration mit HTTPS
- [x] Dark Theme (CSS-Variablen-System)
- [x] Template-System mit Navbar
- [x] Custom Error-Pages (400, 401, 403, 404, 500)

### Gilden-Verwaltung
- [x] Multi-Gilden-Support (mehrere Gilden auf verschiedenen Servern)
- [x] Gildenseite mit Mitgliederliste
- [x] Mitglieder-Statistiken (Level, Rang, Beitrittsdatum, Gold-Donationen, Lehrmeister, Ritterhalle, Gildenpet)
- [x] Inline-Editing für Beitrittsdatum, Entlassen, Verlassen, Notizen
- [x] Gilden-Notizen (sichtbar auf der Gildenseite)
- [x] Wappen-Upload und Darstellung
- [x] Tab-Navigation zwischen Gilden
- [x] Öffentliche Gilden-Profilseiten (ohne Login einsehbar)
- [x] **Mitglieder-Sortierung** – Aktive <7 Tage offline nach Rang/Level, Aktive ≥7 Tage als Block nach Offline-Tagen, Entlassene/Verlassene am Ende

### Daten-Import
- [x] CSV-Import via SFTools-Export (Fallback)
- [x] **sf-api Member-Sync** – Mitgliederdaten direkt via [sf-api](https://github.com/the-marenga/sf-api) abrufen
  - Neues Mitglied: joined_at = heute ("first seen"-Ansatz, da guild_joined seit v29.500 nicht mehr vom Server geliefert wird)
  - Wiederbeitritt: fired_at/left_at automatisch geleert, joined_at = heute
  - Aktive Mitglieder: Stats aktualisiert, joined_at/Notizen/fired_at/left_at unberührt
- [x] Rust-CLI-Tools auf Basis von sf-api (fetch_guild_reports, list_chars, member_sync)
- [x] Robuste Fehlerbehandlung bei API-Inkompatibilitäten

### Kampfberichte & Kalender
- [x] Kampfbericht-Import via sf-api (Postkasten-Integration)
- [x] Parser-Logik für Angriff- und Verteidigungs-Berichte
- [x] Battle-Kalender (Monatsansicht mit Tab-Navigation)
- [x] Kampfdetails-Modal (Uhrzeit, Gegner, Teilnehmer)
- [x] Kämpfe verschieben (zwischen Gilden) und löschen
- [x] Teilnehmerliste pro Kampf

### Import-Log
- [x] Import-Protokoll in Admin-Bereich (Logs → Import-Tab): letzter Import pro Gilde + gefilterte Activity-Einträge

### Cron-System
- [x] Master-Runner (cli/cron_runner.php) via crontab (jede Minute)
- [x] Automatischer Kampfbericht-Fetch (07:25 + 19:10 Uhr)
- [x] Automatischer Member-Sync (07:30 + 19:15 Uhr)
- [x] Admin-UI: Jobs aktivieren/deaktivieren, Uhrzeiten konfigurieren, manuell starten
- [x] Cron-Status im Admin-Bereich (Logs → Cron-Tab)
- [x] Sicherheit: nur Admin-Accounts werden verwendet

### Hellevator
- [x] Informationsseite mit Etagen-Anforderungen
- [x] Spieler-Empfehlungen basierend auf Charakterwerten
- [x] Zweisprachig (DE/EN) mit dynamischem Switch
- [x] Hintergrundmusik

### Benutzerverwaltung & Rollen
- [x] Login mit sicherer Passwort-Hashing (bcrypt)
- [x] Rollen-System (Admin / Moderator / User)
- [x] Admin-Bereich mit User-Verwaltung
- [x] Passwort-Reset (Web + CLI)

### Multi-Account & Sicherheit
- [x] Mehrere SF-Accounts pro User, verschlüsselte Credential-Speicherung
- [x] Cron verwendet ausschließlich Admin-Accounts
- [x] Zentralisierte API-Antworten, Whitelist-Ansatz, CLI-Guards, flock()-Locking

---

## 🚧 Geplant

### Containerisierung
- [ ] Docker-Image (nginx + php-fpm via supervisord)
- [ ] docker-compose für lokales Testen
- [ ] GitHub Actions bereits vorbereitet (pusht nach Docker Hub)

---

## 💡 Ideen / Langfristig

- Setup-Assistent für Erstinstallation
- Mitglieder-Entwicklung über Zeit (Graphen)
- Mobile-Ansicht überarbeiten
- Export-Funktionen (CSV, PDF) für Gilden-Reports

---

*Letzte Aktualisierung: Mai 2026*
