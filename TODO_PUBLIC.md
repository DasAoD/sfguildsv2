# TODO – S&F Guilds v2

---

## Offen

### Nice-to-have

- [ ] **Concurrency-Limit beim Report-Fetch**  
  Beim parallelen Abruf von Kampfberichten starten aktuell alle Charaktere gleichzeitig als Subprozesse.  
  Bei sehr vielen Accounts wäre ein Pool mit max. N parallelen Prozessen sinnvoll.

- [ ] **Passwort-Reset für User**  
  Aktuell kann ein User sein Passwort nur selbst in den Einstellungen ändern (solange er eingeloggt ist).  
  Ein Reset-Flow für gesperrte oder vergessene Zugänge wäre eine Ergänzung.

---

## Erledigt

- [x] Login, Session, Logout
- [x] Datenbank-Setup und Migrations-System
- [x] Dashboard, Gilden, Mitglieder
- [x] Kampf-Kalender und Battle-Reports
- [x] Posteingang und automatisierter Report-Fetch
- [x] Admin-Panel (User, Gilden, Logs, Backup)
- [x] Drei-Rollen-System (Admin / Moderator / User)
- [x] API-Absicherung nach Rollen
- [x] AES-256-CBC + HMAC Verschlüsselung für S&F-Credentials
- [x] SQLite WAL-Mode + Performance-PRAGMAs
- [x] N+1 Query-Problem behoben
- [x] Open Redirect geschlossen
- [x] Upload-Validierung (Größe + Mime-Type)
- [x] Lock-Mechanismus für parallele Fetch-Prozesse
- [x] Prozess-Timeouts beim Report-Fetch
- [x] Error Pages (400, 403, 404, 500)
- [x] Custom-Modals statt nativer Browser-Dialoge
- [x] Systemd-Services für automatisierten CSV-Import
- [x] Spieler-Umbenennen nach Serverfusionen
- [x] Wappen-Upload mit Validierung