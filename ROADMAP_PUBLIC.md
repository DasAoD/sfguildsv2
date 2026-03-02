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
- [x] Mitglieder-Statistiken (Level, Klasse, Beitrittsdatum, Gold-Donationen)
- [x] Gilden-Notizen (sichtbar auf der Gildenseite)
- [x] Wappen-Upload und Darstellung
- [x] Server-Informationen pro Gilde
- [x] Tab-Navigation zwischen Gilden
- [x] Öffentliche Gilden-Profilseiten (ohne Login einsehbar)

### Daten-Import
- [x] CSV-Import via SFTools-Export
- [x] Automatisierter Import per systemd-Service
- [x] Rust-CLI-Tools auf Basis von [sf-api (The Marenga)](https://github.com/the-marenga/sf-api)
- [x] Robuste Fehlerbehandlung bei API-Inkompatibilitäten (serverspezifisch)
- [x] Fallback-Mechanismus für unterschiedliches API-Verhalten je Server/Charakter

### Kampfberichte & Kalender
- [x] Kampfbericht-Import via sf-api (Postkasten-Integration)
- [x] Parser-Logik für Angriff- und Verteidigungs-Berichte
- [x] Battle-Kalender (Monatsansicht mit Tab-Navigation)
- [x] Kampfdetails-Modal (Uhrzeit, Gegner, Teilnehmer)
- [x] Kämpfe verschieben (zwischen Gilden) und löschen
- [x] Teilnehmerliste pro Kampf

### Hellevator
- [x] Informationsseite mit Etagen-Anforderungen
- [x] Spieler-Empfehlungen basierend auf Charakterwerten
- [x] Zweisprachig (DE/EN) mit dynamischem Switch
- [x] Hintergrundmusik

### Benutzerverwaltung & Rollen
- [x] Login mit sicherer Passwort-Hashing (bcrypt)
- [x] Session-Management
- [x] **Rollen-System (Admin / Moderator / User)**
  - Admin: Vollzugriff inkl. Benutzerverwaltung, Gilden, Import
  - Moderator: Kämpfe & Mitglieder bearbeiten, kein Admin-Bereich
  - User: Nur-Lese-Zugriff
- [x] Admin-Bereich mit User-Verwaltung (anlegen, löschen, Rolle zuweisen)
- [x] Passwort-Reset (Admin-initiiert via Web + CLI-Notfalltool)

### Multi-Account
- [x] Mehrere SF-Accounts pro User
- [x] Account-Verwaltung im Settings-UI
- [x] Separate Charakterauswahl pro Account
- [x] Standard-Account Markierung
- [x] Verschlüsselte Credential-Speicherung
- [x] Automatischer Fetch von allen angelegten Accounts

### Sicherheit
- [x] Zentralisierte API-Antworten (`jsonResponse()` / `jsonError()`)
- [x] Vollständige Exception-Behandlung mit `Throwable`
- [x] Input-Validierung mit `JSON_THROW_ON_ERROR`
- [x] Whitelist-Ansatz für öffentliche API-Endpunkte
- [x] CLI-Only-Guards für interne Prozesse
- [x] Atomares File-Locking (`flock()`)
- [x] SSH-Wrapper-Skripte mit Eingabe-Validierung
- [x] Strikte Verschlüsselungsvalidierung
- [x] 16 systematische Sicherheits-Audits abgeschlossen

---

## 🚧 In Arbeit / Geplant

### sf-api Integration (Mitgliederdaten)
- [ ] Mitgliederdaten direkt via sf-api abrufen (statt CSV-Umweg)
- [ ] CSV-Import als Fallback beibehalten


### Import-Transparenz
- [ ] Import-Log in der UI (letzte Imports, Fehler, Zeilenanzahl)

---

## 💡 Ideen / Langfristig

- Setup-Assistent für Erstinstallation
- Mitglieder-Entwicklung über Zeit (Graphen)
- Export-Funktionen (CSV, PDF) für Gilden-Reports

---

*Letzte Aktualisierung: März 2026*
