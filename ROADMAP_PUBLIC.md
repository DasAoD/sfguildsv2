# Roadmap – S&F Guilds v2

---

## ✅ Phase 1: Grundsystem (abgeschlossen)

- Login, Session-Management, Logout
- Sichere Verzeichnisstruktur
- Datenbank-Setup (SQLite3)
- Dashboard mit Gilden-Übersicht
- Gilden-Detailseiten mit Mitgliederlisten
- Kampf-Kalender
- Battle-Reports und Statistiken
- Posteingang für Kampfberichte
- Wappen-Upload
- Admin-Panel (User- und Gilden-Verwaltung, Logs, Backup)
- Error Pages (400, 403, 404, 500)
- Dark Gaming Theme

---

## ✅ Phase 2: Rollen-System (abgeschlossen)

- Drei-Rollen-System: Admin / Moderator / User
- Rollenbasierte API-Absicherung für alle Endpunkte
- Rollen-Verwaltung im Admin-Panel
- Passwort-Selbstverwaltung für alle User
- 403-Handling für unberechtigte Zugriffe

---

## ✅ Phase 3: Performance-Optimierungen (abgeschlossen)

- SQLite WAL-Mode aktiviert
- N+1 Query-Problem in Guild-API behoben
- Open Redirect Vulnerability geschlossen
- `SELECT *` durch spezifische Spalten ersetzt
- Performance-PRAGMAs (synchronous, cache_size, foreign_keys, busy_timeout)

---

## ✅ Phase 4: Encryption & Validierung (abgeschlossen)

- HMAC-Integritätsprüfung für verschlüsselte S&F-Zugangsdaten
- Abwärtskompatible Migration bestehender Einträge
- Upload-Validierung (Dateigröße, Mime-Type)
- Native Browser-Dialoge durch Custom-Modals ersetzt

---

## ✅ Automatisierter Import (abgeschlossen)

- Systemd-Services für regelmäßigen CSV-Import
- Paralleler Abruf von Kampfberichten über mehrere Accounts
- Lock-Mechanismus gegen parallele Fetch-Prozesse
- Prozess-Timeouts und Fehlerbehandlung

---

## 🔜 Offen / Nice-to-have

- Concurrency-Limit für parallele Subprozesse beim Report-Fetch  
  *(aktuell starten alle Charaktere gleichzeitig; bei sehr vielen Accounts sinnvoll zu begrenzen)*
- Passwort-Reset-Funktion für User ohne Admin-Zugriff

---

## Stand

**Letzte Aktualisierung:** Februar 2026  
**Status:** Produktiv im Einsatz