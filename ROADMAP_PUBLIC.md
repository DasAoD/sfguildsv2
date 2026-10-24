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
- [x] **sf-api Member-Sync** – Mitgliederdaten direkt via [sf-api](https://github.com/the-marenga/sf-api) abrufen
  - Neues Mitglied: joined_at = heute ("first seen"-Ansatz, da guild_joined seit v29.500 nicht mehr vom Server geliefert wird)
  - Wiederbeitritt: fired_at/left_at automatisch geleert, joined_at = heute
  - Aktive Mitglieder: Stats aktualisiert, joined_at/Notizen/fired_at/left_at unberührt
- [x] Rust-CLI-Tools auf Basis von sf-api (fetch_guild_reports, list_chars, member_sync, character_sync)
- [x] CSV-Import entfernt: sf-api ist die einzige Datenquelle
- [x] Robuste Fehlerbehandlung bei API-Inkompatibilitäten (leere/ungültige systemmessagelist, graceful Seed-Fallback)
- [x] sf-api automatisch auf neue S&F-Serverversionen aktualisierbar (v30.500-kompatibel ab v0.4.3)

### Kampfberichte & Kalender
- [x] Kampfbericht-Import via sf-api (Postkasten-Integration)
- [x] Parser-Logik für Angriff- und Verteidigungs-Berichte
- [x] Battle-Kalender (Monatsansicht mit Tab-Navigation)
- [x] Kampfdetails-Modal (Uhrzeit, Gegner, Teilnehmer)
- [x] Kämpfe verschieben (zwischen Gilden) und löschen
- [x] Teilnehmerliste pro Kampf

### Angriffszeit-Check
- [x] **Gilden-Angriffszeit-Check** mit korrekter UTC-Anzeige und ohne Fallback auf fremde Accounts

### Posteingang (Battle-Inbox)
- [x] Kampfberichte landen zunächst im Posteingang (Review vor Übernahme in den Kalender)
- [x] Übernehmen/Ablehnen einzelner Berichte, Dedup-Abgleich gegen bereits importierte Kämpfe
- [x] Ungelesen-Badge in der Navigation
- [x] Automatisches Markieren von Hydra-/Gildenportal-Systemnachrichten als gelesen

### Charakter-Details
- [x] **Charakter-Modal** auf der Gildenseite: Attribute, Rüstung, Schaden, Ausrüstung mit Gemmen, Runen, Verzauberungen und aktive Tränke
- [x] **Echte Item- und Trank-Icons** aus den Spiel-Assets (Waffen, Rüstung, Schmuck, Tränke)
- [x] **Echte Charakter-Portraits** statt Buchstaben-Platzhalter, auch Sonder- und Streamer-Portraits
- [x] **Treffereffekt neben der Waffe** bei Einzelwaffen-Charakteren (Zuordnung über die Waffenklasse); kein leerer zweiter Slot mehr
- [x] **Charakterdaten-Sync** als eigener Cron-Job (unabhängig vom Member-Sync)
- [x] **Gildenrang der Ehrenhalle** im Dashboard und auf der Gildenseite
- [x] **Asset-Check**: tägliche Prüfung der Spiel-Bundles auf Änderungen (neue Items), Meldung per Telegram und Mail

### Import-Log
- [x] Import-Protokoll in Admin-Bereich (Logs → Import-Tab): letzter Import pro Gilde + gefilterte Activity-Einträge

### Cron-System
- [x] Master-Runner (cli/cron_runner.php) via crontab (jede Minute)
- [x] Automatischer Kampfbericht-Fetch (07:25 + 19:10 Uhr)
- [x] Automatischer Member-Sync (07:30 + 19:15 Uhr)
- [x] Gilde löschen räumt Mitglieder, Kämpfe und Berichtsdateien vollständig auf
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
- [x] **Inaktivitäts-Logout** mit Restzeit-Anzeige und Vorwarnung; der Timer setzt nur bei echten Interaktionen zurück

### Multi-Account & Sicherheit
- [x] Mehrere SF-Accounts pro User, verschlüsselte Credential-Speicherung
- [x] Cron verwendet ausschließlich Admin-Accounts
- [x] Zentralisierte API-Antworten, Whitelist-Ansatz, CLI-Guards, flock()-Locking

### Mobile & Barrierefreiheit
- [x] **Mobile Ansicht** für alle Seiten (Hamburger-Menü, Tabellen, Modals, Querformat), am echten Handy geprüft
- [x] **Barrierefreiheit**: Kontrast nach WCAG AA, Tastatur- und Screenreader-Bedienung, Lighthouse-Läufe mit 100 Punkten bei Performance, Barrierefreiheit und Best Practices
- [x] **Rechtliches**: Impressum und Datenschutzerklärung als pflegbare Seiten im Admin-Bereich, Schalter für die Anzeige
- [x] Seite für Suchmaschinen gesperrt (noindex), HTTP/2 und Caching

### UI & Dokumentation
- [x] Ladeanimationen bei API-Calls
- [x] Interne API-Dokumentation aller Endpunkte (docs/api.md)
- [x] **Website-Statistik** als Admin-Tab (GoAccess)
- [x] Reports und Gilden-Detailseite starten mit der ersten Gilde statt einer festen

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
- Wappen automatisch aus dem Spiel-Code rendern (technisch vorhanden, aber zurückgestellt: das Ergebnis weicht noch zu stark vom Original ab; der manuelle Upload hat Vorrang)
- Pfeil zwischen Waffe und Treffereffekt wie im Spiel
- Icon-Updates nach Spiel-Updates vollständig automatisieren (Zuschneiden; Erkennen und Laden ist schon automatisch)
- Export-Funktionen (CSV, PDF) für Gilden-Reports

---

*Letzte Aktualisierung: Oktober 2026*
