# 📋 TODO - S&F Guilds v2

**Projekt-Status:** In aktiver Entwicklung  
**Stand:** 22. Februar 2026

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

### **Performance (Phase 3)**
- [x] SQLite WAL-Mode
- [x] N+1 Query behoben (guilds.php)
- [x] Open Redirect geschlossen (login.php)
- [x] SELECT * bereinigt

### **Encryption & Validation (Phase 4)**
- [x] HMAC-Integritätsprüfung für Zugangsdaten
- [x] Upload-Größenlimit (2MB) mit Modal-Fehlermeldung

### **UI/UX**
- [x] Einheitliche Custom-Modals statt Browser-`confirm()`/`alert()`

---

## 🔥 **OFFEN: PRIORITÄT HOCH**

### **Access Control System (Phase 2)**
- [ ] Drei-Rollen-System: Admin / Moderator / User
- [ ] `isAdmin()` / `requireAdmin()` in `auth.php`
- [ ] Admin-APIs absichern
- [ ] Admin-Seite absichern
- [ ] Rollen-Verwaltung im Admin-Panel
- [ ] Passwort-Reset Funktion

---

## 📊 **OFFEN: PRIORITÄT NIEDRIG**

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

## 📈 **FORTSCHRITT**

| Kategorie | Vorher | Jetzt |
|-----------|--------|-------|
| **Kern-Features** | 90% | 90% ✅ |
| **Sicherheit** | 85% | 85% ⏳ → 95% nach Phase 2 |
| **Performance** | 85% | 92% ✅ |
| **Code-Qualität** | 90% | 95% ✅ |
| **UI/UX** | 95% | 95% ✅ |

**Gesamt:** ~92/100 → ~95/100 nach Phase 2
