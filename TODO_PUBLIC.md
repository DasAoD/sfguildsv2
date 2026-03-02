# TODO – S&F Guilds v2

Konkrete anstehende Aufgaben. Sicherheitsrelevante Details sind in der privaten Version dokumentiert.

---

## 🔴 Hohe Priorität

*(Derzeit nichts Kritisches offen)*

---

## 🟡 Mittlere Priorität

### sf-api Integration (Mitgliederdaten)
- [ ] Direkte Mitgliederdaten via [sf-api (The Marenga)](https://github.com/the-marenga/sf-api) abrufen
- [ ] Rust-CLI-Tool für Mitglieder-Import erweitern (analog zur bestehenden Kampfbericht-Abholung)
- [ ] CSV-Import als Fallback beibehalten

### Import-Log
- [ ] Import-Protokoll in der UI einsehbar machen (letzte Imports, Fehler, verarbeitete Zeilen)

---

## 🟢 Niedrige Priorität / Nice-to-have

### Setup-System
- [ ] Installations-Assistent (Web-basiert oder CLI) für Erst-Einrichtung

### Dokumentation
- [ ] API-Endpunkte intern dokumentieren
- [ ] Deployment-Guide aktualisieren

### UI-Verbesserungen
- [ ] Mobile-Ansicht überarbeiten
- [ ] Ladeanimationen bei API-Calls

---

## ✅ Zuletzt erledigt

- [x] **Rollen-System** (Admin / Moderator / User) vollständig implementiert
- [x] Admin-Bereich mit rollenbasierter Zugriffskontrolle
- [x] **Multi-Account-Support** (mehrere SF-Accounts pro User, Account-Verwaltung im Settings)
- [x] **Battle-Kalender** (Monatsansicht, Modals, Verschieben/Löschen, Import-Dialog)
- [x] **Kampfbericht-Import** via sf-api (The Marenga) + Parser-Logik
- [x] Rust-CLI-Tools für SF-API-Integration (Kampfberichte, Charaktere)
- [x] Gilden-Notizen auf der öffentlichen Gildenseite anzeigen
- [x] Hellevator-Seite (zweisprachig DE/EN, Etagen-Anforderungen, Hintergrundmusik)
- [x] Passwort-Reset-System (Web + CLI-Notfalltool)
- [x] Zentralisierte `jsonResponse()` / `jsonError()` in allen API-Endpunkten
- [x] `catch (Throwable)` in allen kritischen Dateien
- [x] `JSON_THROW_ON_ERROR` für alle User-Inputs
- [x] Whitelist-Ansatz für öffentliche API-Endpunkte
- [x] CLI-Only-Guards mit korrektem `PHP_BINDIR`-Pfad
- [x] Atomares `flock()`-Locking
- [x] SSH-Wrapper-Skripte mit Parameter-Validierung
- [x] Bugfix: SF-API-Kompatibilität für s6.sfgame.eu
- [x] 16 systematische Sicherheits-Audits abgeschlossen
- [x] Custom Modals statt Browser-Popups
- [x] Automatischer CSV-Import via systemd-Service
- [x] Dark Theme durchgängig

---

*Letzte Aktualisierung: März 2026*
