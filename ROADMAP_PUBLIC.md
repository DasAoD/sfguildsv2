# 📋 ROADMAP - S&F Guilds v2

**Projekt:** Guild Management System für Shakes & Fidget  
**Tech-Stack:** PHP 8.3+, SQLite3, Vanilla JavaScript  
**Status:** In aktiver Entwicklung

---

## ✅ **PHASE 1: FOUNDATION & SECURITY** (ABGESCHLOSSEN)

**Status:** 100% ✅

### Umgesetzte Features:
- ✅ Basis-Authentifizierung und Session-Management
- ✅ Logging-System (Activity & Error Logs)
- ✅ Admin-Panel mit System-Übersicht
- ✅ Sicherheits-Hardening (XSS-Schutz, Input-Validierung)
- ✅ Debug-Code entfernt
- ✅ Production-Ready Setup

---

## ✅ **PHASE 2: ACCESS CONTROL SYSTEM** (ABGESCHLOSSEN)

**Status:** 100% ✅

### Umgesetzte Features:
- ✅ Drei-Rollen-System: Admin / Moderator / User
- ✅ `role`-Spalte in `users`-Tabelle, Rolle in Session gespeichert
- ✅ `isAdmin()`, `isModerator()`, `requireAdminAPI()`, `requireModeratorAPI()` in `auth.php`
- ✅ Alle Admin-APIs (`admin_users`, `admin_guilds`, `admin_logs`, `admin_system`, `admin_player_merge`) → Admin-only
- ✅ Schreibende Aktionen (Datum/Notizen, Posteingang, Reports abholen) → Moderator+
- ✅ Destruktive Aktionen (Kämpfe löschen/verschieben/importieren, Mitglieder löschen) → Admin
- ✅ Admin-Link in Navigation für User ausgeblendet
- ✅ Rollen-Verwaltung im Admin-Panel (Badge, Bearbeiten-Modal)
- ✅ Passwort-Änderung für alle User in den Einstellungen
- ✅ 403-Fehlerseite korrekt via nginx

---

## ✅ **PHASE 3: PERFORMANCE OPTIMIZATIONS** (ABGESCHLOSSEN)

**Status:** 100% ✅

### Umgesetzte Optimierungen:
- ✅ SQLite WAL-Mode aktiviert (massiver Performance-Boost)
- ✅ N+1 Query-Problem in `api/guilds.php` behoben (von ~6×N auf 4 Queries)
- ✅ Open Redirect Vulnerability in `api/login.php` geschlossen
- ✅ `SELECT *` durch spezifische Spalten ersetzt
- ✅ Performance PRAGMAs (synchronous=NORMAL, 8MB Cache, Foreign Keys)

---

## ✅ **PHASE 4: ENCRYPTION & VALIDATION** (ABGESCHLOSSEN)

**Status:** 100% ✅

### Umgesetzte Features:
- ✅ HMAC-Integritätsprüfung für verschlüsselte SF-Zugangsdaten
- ✅ Abwärtskompatible Migration (bestehende Einträge bleiben gültig)
- ✅ Upload-Validierung: 2MB Dateigrößen-Limit für Gildenwappen
- ✅ Client-seitige Validierung mit Fehlermeldung direkt im Modal

---

## 🎯 **FEATURE-ÜBERSICHT**

### **Kern-Funktionalität** (✅ Implementiert)
- Dashboard mit Gilden-Übersicht
- Mitglieder-Verwaltung
- Kampf-Kalender
- Posteingang für Battle-Reports
- Import-System (CSV, Mail-Integration)
- Reports & Statistiken
- Admin-Panel
- Spieler-Umbenennen Tool (nach Serverfusionen)

### **Öffentlich zugänglich:**
- Dashboard
- Gilden-Übersichten

### **Login erforderlich:**
- Kampf-Details
- Reports
- Posteingang
- Admin-Funktionen

---

## 📊 **PROJEKT-STATUS**

| Kategorie | Status |
|-----------|--------|
| **Kern-Features** | 90% ✅ |
| **Sicherheit** | 97% ✅ |
| **Performance** | 92% ✅ |
| **Code-Qualität** | 95% ✅ |
| **UI/UX** | 95% ✅ |

**Gesamtbewertung:** ~96/100 ✅

---

## 🚀 **TECHNISCHE HIGHLIGHTS**

- **PHP 8.3+** mit modernen Features
- **SQLite3** mit WAL-Mode für optimale Performance
- **Vanilla JavaScript** - kein Framework-Overhead
- **Custom Dark Theme** - professionelles Design
- **Systemd Services** für automatisierten CSV-Import
- **AES-256-CBC + HMAC** für sichere Credential-Speicherung
- **RESTful API** Struktur

---

## 📅 **CHANGELOG**

### **2026-02-26**
- 13. Security-Audit abgeschlossen
- Legacy SF-Credentials aus `users`-Tabelle entfernt (single source of truth: `sf_accounts`)
- `sf_get_characters.php` komplett überarbeitet: SSH-Tunnel zu Heimserver, Hard-Timeout
- `sf_save_characters.php`: Legacy-Fallback auf nicht mehr existierende `users`-Spalte gefixt
- `runWithEnv()` entfernt (toter Code)
- README: Bootstrap-Pattern und SSH-Tunnel-Architektur dokumentiert

### **2026-02-22**
- Phase 2 abgeschlossen (Rollen-System, API-Absicherung, Passwort-Selbstverwaltung)
- Phase 3 abgeschlossen (WAL-Mode, N+1 Fix, Open Redirect, SELECT *)
- Phase 4 abgeschlossen (HMAC, Upload-Validierung)
- Fehlerseiten-Navigation vereinheitlicht
- Browser-`alert()`/`confirm()` vollständig durch Custom-Modals ersetzt

### **2026-02-14**
- Phase 1 abgeschlossen
- Roadmap erstellt
- Phasen 2-4 geplant

### **2026-02-01**
- Security Audit durchgeführt
- Kritische Fixes implementiert
- Production-Ready Status erreicht

---

**Stand:** 26. Februar 2026 (aktuell)  
**Version:** 2.0-dev  
**Lizenz:** Privates Projekt