# 📋 TODO - S&F Guilds v2

**Projekt-Status:** Feature-Complete (Kern-Funktionalität)
**Stand:** 26. Februar 2026

---

## ✅ **ERLEDIGT**

### **Core Features**
- [x] Dashboard mit Gilden-Statistiken
- [x] Mitglieder-Verwaltung mit Inline-Editing
- [x] Kampf-Kalender mit Tab-Navigation
- [x] Posteingang für Battle-Reports
- [x] CSV-Import-System
- [x] Mail-Integration (Gmail API)
- [x] Reports & Statistiken
- [x] Spieler-Detail Modal
- [x] Kampf-Details Modal
- [x] CSV-Export für Reports
- [x] Spieler-Umbenennen Tool (nach Serverfusionen/Namensänderungen)

### **Admin & System**
- [x] Admin-Panel (User, Gilden, System, Logs, Wartung)
- [x] Activity & Error Logging
- [x] System-Backup Funktion
- [x] Automatischer CSV-Import (systemd)

### **Security & Quality (Phase 1)**
- [x] Session-Management
- [x] Input-Validierung & XSS-Schutz
- [x] Debug-Code entfernt
- [x] Passwort-Logging maskiert
- [x] Stacktrace-Leaks entfernt

### **Access Control (Phase 2)**
- [x] Drei-Rollen-System: Admin / Moderator / User
- [x] Alle Admin-APIs abgesichert (`requireAdminAPI`)
- [x] Schreibende Endpunkte auf Moderator+ eingeschränkt
- [x] Destruktive Endpunkte (löschen/verschieben) Admin-only
- [x] Admin-Link in Nav für User ausgeblendet
- [x] Rollen-Vergabe im Admin-Panel (Badge + Bearbeiten-Modal)
- [x] Passwort-Selbstverwaltung in den Einstellungen
- [x] 403-Fehlerseite via nginx korrekt zugestellt

### **Performance (Phase 3)**
- [x] SQLite WAL-Mode
- [x] N+1 Query behoben (guilds.php)
- [x] Open Redirect geschlossen (login.php)
- [x] SELECT * bereinigt

### **Encryption & Validation (Phase 4)**
- [x] HMAC-Integritätsprüfung für Zugangsdaten
- [x] Upload-Größenlimit (2MB) mit Modal-Fehlermeldung

### **Security & Cleanup (13. Audit)**
- [x] Legacy SF-Credentials aus `users`-Tabelle entfernt (konsolidiert in `sf_accounts`)
- [x] `sf_save_credentials.php` gelöscht (toter Code)
- [x] `sf_get_characters.php`: list_chars via SSH auf Heimserver (Residential-IP)
- [x] Hard-Timeout (30s + SIGKILL) in `runListChars()`
- [x] `sf_save_characters.php`: Legacy-Fallback auf `users`-Tabelle entfernt
- [x] `runWithEnv()` entfernt (toter Code)
- [x] README: Bootstrap-Pattern und SSH-Tunnel-Architektur dokumentiert

### **UI/UX**
- [x] Einheitliche Custom-Modals statt Browser-`confirm()`/`alert()`
- [x] Fehlerseiten-Navigation vereinheitlicht (Logo, Links)

---

## 🎨 **OFFEN: PRIORITÄT NIEDRIG**

### **UI/UX**
- [ ] Mobile Optimierung (Touch-friendly Controls)

### **Nice-to-have**
- [ ] Dark/Light Theme Toggle

---

## 🚫 **NICHT GEPLANT**

- ~~Dashboard Charts~~ (Reports reichen aus)
- ~~Email-Benachrichtigungen~~ (Nicht benötigt)
- ~~Externe API~~ (Privates Projekt)
- ~~Keyboard Shortcuts~~
- ~~Bulk-Aktionen~~

---

## 📈 **FINALER STAND**

| Kategorie | Status |
|-----------|--------|
| **Kern-Features** | 95% ✅ |
| **Sicherheit** | 97% ✅ |
| **Performance** | 92% ✅ |
| **Code-Qualität** | 95% ✅ |
| **UI/UX** | 95% ✅ |

**Gesamt: ~95/100** 🎯