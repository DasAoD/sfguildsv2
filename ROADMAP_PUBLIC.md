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

### Gilden-Verwaltung
- [x] Multi-Gilden-Support (mehrere Gilden auf verschiedenen Servern)
- [x] Gildenseite mit Mitgliederliste
- [x] Mitglieder-Statistiken (Level, Klasse, Beitrittsdatum, Gold-Donationen)
- [x] Gilden-Notizen (sichtbar auf der öffentlichen Gildenseite)
- [x] Wappen-Upload und Darstellung
- [x] Server-Informationen pro Gilde

### Daten-Import
- [x] CSV-Import via SFTools-Export
- [x] Automatisierter Import per systemd-Service
- [x] Rust-CLI-Tools für SF-API-Integration
- [x] Robuste Fehlerbehandlung bei API-Inkompatibilitäten (serverspezifisch)
- [x] Fallback-Mechanismus für unterschiedliches API-Verhalten je Server/Charakter

### Kampfberichte
- [x] Import von Gildenkampf-Berichten
- [x] Auswertung und Darstellung
- [x] Unterstützung für server-spezifische API-Eigenheiten (z. B. s6.sfgame.eu)

### Hellevator
- [x] Informationsseite mit Etagen-Anforderungen
- [x] Spieler-Empfehlungen basierend auf Charakterwerten
- [x] Zweisprachig (DE/EN) mit dynamischem Switch
- [x] Hintergrundmusik

### Benutzerverwaltung
- [x] Login mit sicherer Passwort-Hashing (bcrypt)
- [x] Session-Management
- [x] Passwort-Reset-System (Admin-initiiert via Web + CLI-Notfalltool)
- [x] CLI-basierter Admin-Passwort-Reset (für "Wer setzt das Admin-Passwort zurück?"-Problem)
- [x] Datenbank-Schema für Passwort-Reset-Tokens

### Sicherheit
- [x] Zentralisierte API-Antworten (`jsonResponse()` / `jsonError()`)
- [x] Vollständige Exception-Behandlung mit `Throwable`
- [x] Input-Validierung mit `JSON_THROW_ON_ERROR`
- [x] Whitelist-Ansatz für öffentliche API-Endpunkte
- [x] CLI-Only-Guards für interne Prozesse
- [x] Atomares File-Locking (`flock()`)
- [x] SSH-Wrapper-Skripte mit Eingabe-Validierung (Home-Server-Integration)
- [x] Strikte Verschlüsselungsvalidierung
- [x] 16 systematische Sicherheits-Audits (abgeschlossen)

---

## 🚧 In Arbeit

### Rollen & Berechtigungen
- [ ] Drei-Stufen-Rollen-System: **Admin / Moderator / User**
  - Admin: Vollzugriff inkl. Benutzerverwaltung
  - Moderator: Import, Mitgliederverwaltung, keine User-Verwaltung
  - User: Nur-Lese-Zugriff auf Gildendaten
- [ ] Middleware für rollenbasierte Zugriffskontrolle auf allen Seiten und API-Endpunkten

---

## 📋 Geplant

### Erweiterte Analytics
- [ ] Battle-Kalender mit Kampf-Visualisierung
- [ ] Mitglieder-Entwicklung über Zeit (Graphen)
- [ ] Kampf-Performance-Statistiken pro Mitglied
- [ ] Vergleichsansichten zwischen Gilden

### Multi-Account-Support
- [ ] Mehrere S&F-Accounts pro Benutzer verwalten
- [ ] Account-Switching ohne Re-Login

### Import & Automatisierung
- [ ] Verbesserte SF-API-Integration (direkter Datenabruf ohne CSV-Umweg, wo möglich)
- [ ] Import-Protokoll mit Fehler-Übersicht in der UI

### Setup & Dokumentation
- [ ] Setup-Assistent für Erstinstallation
- [ ] Verbesserte Entwickler-Dokumentation

### UI / UX
- [ ] Mobile-Optimierung
- [ ] Benachrichtigungen bei Import-Fehlern

---

## 💡 Ideen / Langfristig

- Integration mit SF-API für vollautomatischen Datenabruf (aktuell durch CSV-Import abgedeckt)
- Öffentliche Gilden-Profilseiten (ohne Login einsehbar)
- Export-Funktionen (CSV, PDF) für Gilden-Reports
- Discord-Webhook-Benachrichtigungen bei wichtigen Events

---

*Letzte Aktualisierung: März 2026*
