# sfguildsv2 – API-Dokumentation

Alle Endpunkte liegen unter `/api/` und geben JSON zurück (`Content-Type: application/json`), sofern nicht anders angegeben.

## Authentifizierungs-Ebenen

| Level | Funktion | Beschreibung |
|---|---|---|
| `public` | – | Kein Login erforderlich |
| `user` | `checkAuthAPI()` / `checkAuth()` | Eingeloggt (beliebige Rolle) |
| `moderator` | `requireModeratorAPI()` | Rolle `moderator` oder `admin` |
| `admin` | `requireAdminAPI()` | Nur Rolle `admin` |

---

## Authentifizierung

### `POST /api/login.php`
Benutzeranmeldung.

**Auth:** public

**Request Body (JSON):**

    { "username": "string", "password": "string", "return": "/optional/redirect" }

**Response:**

    { "success": true, "redirect": "/" }
    { "success": true, "must_change_password": true, "redirect": "/change_password.php" }
    { "success": false, "message": "Ungueltige Anmeldedaten" }

---

### `POST /api/change_password.php`
Eigenes Passwort aendern.

**Auth:** user

**Request Body (JSON):**

    { "current_password": "string", "new_password": "string (min. 8 Zeichen)" }

**Response:**

    { "success": true, "message": "Passwort erfolgreich geaendert" }

---

## Gilden

### `GET /api/guilds.php`
Alle Gilden mit Statistiken.

**Auth:** public

**Response:**

    {
      "success": true,
      "guilds": [
        {
          "id": 1,
          "name": "Gildenname",
          "server": "fX",
          "tag": "TAG",
          "crest_file": "guild_1.webp",
          "active_members": 25,
          "avg_level": 400,
          "total_battles": 50,
          "participation_last30d": 80.0,
          "completed_raids": 10
        }
      ]
    }

---

### `GET /api/members.php?guild_id=N`
Mitglieder einer Gilde.

**Auth:** public (ausgetretene/entlassene Mitglieder nur fuer eingeloggte User sichtbar)

**Query-Parameter:**
| Parameter | Typ | Beschreibung |
|---|---|---|
| `guild_id` | int | ID der Gilde (Pflicht) |

**Response:**

    {
      "success": true,
      "guild": { "id": 1, "name": "Gildenname", "server": "fX", "...": "..." },
      "members": [
        {
          "id": 1,
          "name": "Spielername",
          "level": 400,
          "rank": "Offizier",
          "last_online": "2026-01-01",
          "joined_at": "2025-01-01",
          "gold": 1000000,
          "mentor": 500000,
          "knight_hall": 10,
          "guild_pet": 100,
          "notes": null,
          "fired_at": null,
          "left_at": null,
          "days_offline_calc": 3
        }
      ]
    }

**Sortierung:** Aktive vor Entlassenen → Aktive >=7 Tage offline am Ende → Rang → Level DESC (bzw. Offline-Tage fuer die Offline-Gruppe).

---

## Mitglieder-Verwaltung

### `POST /api/update_member.php`
Einzelnes Feld eines Mitglieds aendern.

**Auth:** moderator

**Request Body (JSON):**

    { "member_id": 1, "field": "notes|fired_at|left_at|joined_at", "value": "string|null" }

**Erlaubte Felder:** `notes`, `fired_at`, `left_at`, `joined_at`
Datum-Felder: Format `YYYY-MM-DD` oder `null` zum Loeschen.

**Response:**

    { "success": true, "message": "Erfolgreich gespeichert" }

---

### `POST /api/delete_member.php`
Mitglied loeschen.

**Auth:** admin

**Request Body (JSON):**

    { "member_id": 1 }

**Response:**

    { "success": true, "message": "Mitglied \"Spielername\" wurde geloescht" }

---

### `POST /api/import_guild_members.php`
Mitglieder-Import per CSV (Fallback fuer sf-api-Ausfall).

**Auth:** admin

**Request:** `multipart/form-data`
| Feld | Typ | Beschreibung |
|---|---|---|
| `guild_id` | int | Ziel-Gilde |
| `file` | file | CSV-Datei |

**Response:**

    { "success": true, "inserted": 5, "updated": 18, "skipped": 2 }

---

### `POST /api/sf_member_sync.php`
Mitglieder-Sync via sf-api Rust-Binary.

**Auth:** moderator

**Request Body (JSON):**

    { "guild_id": 1 }

**Response:**

    { "success": true, "inserted": 2, "updated": 20, "rejoined": 0, "skipped": 0 }

**Hinweis:** Sucht automatisch den passenden Charakter aus `sf_accounts` des eingeloggten Users anhand des Gildennamens.

---

## Kampfberichte

### `GET /api/inbox_list.php?status=pending`
Kampfberichte im Posteingang.

**Auth:** moderator

**Query-Parameter:**
| Parameter | Typ | Beschreibung |
|---|---|---|
| `status` | string | `pending` (Standard), `imported`, `rejected` |

**Response:**

    {
      "success": true,
      "count": 3,
      "reports": [
        {
          "id": 1,
          "message_id": "abc123",
          "opponent_guild": "Gegner-Gilde",
          "battle_type": "attack",
          "battle_date": "2026-01-01",
          "battle_time": "19:30",
          "server": "fX.sfgame.net",
          "character_name": "Charaktername",
          "status": "pending",
          "won": 1,
          "guild_id": 1,
          "guild_name": "Gildenname"
        }
      ]
    }

**`won`-Feld:** `1` = Gewonnen, `0` = Verloren, `null` = Unbekannt.

---

### `GET /api/inbox_preview.php?id=N`
Rohen Berichtstext einer Inbox-Nachricht abrufen.

**Auth:** moderator

**Response:**

    { "success": true, "content": "Kampfbericht-Rohtext..." }

---

### `POST /api/inbox_import.php`
Berichte aus dem Posteingang in die Auswertungs-DB importieren.

**Auth:** moderator

**Request Body (JSON):**

    { "report_ids": [1, 2, 3] }

**Response:**

    { "success": true, "imported": 3, "errors": [] }

---

### `POST /api/inbox_reject.php`
Berichte ablehnen (Status -> `rejected`).

**Auth:** moderator

**Request Body (JSON):**

    { "report_ids": [1, 2] }

**Response:**

    { "success": true, "rejected": 2, "message": "2 Berichte abgelehnt" }

---

### `POST /api/import_battle.php`
Kampfbericht manuell importieren (Texteingabe).

**Auth:** admin

**Request Body (JSON):**

    { "guild_id": 1, "date": "2026-01-01", "time": "19:30", "text": "Kampfbericht-Rohtext..." }

**Response:**

    { "success": true, "battle_id": 1 }

---

### `GET /api/get_participants.php?battle_id=N`
Teilnehmer eines Kampfes.

**Auth:** user

**Response:**

    {
      "participated": [
        { "player_name": "Spieler A", "player_level": 400, "player_server_tag": null, "participated": 1 }
      ],
      "not_participated": [
        { "player_name": "Spieler B", "player_level": 350, "player_server_tag": null, "participated": 0 }
      ]
    }

---

### `POST /api/delete_battle.php`
Kampf loeschen (inkl. Inbox-Eintrag und .txt-Datei).

**Auth:** admin

**Request Body (JSON):**

    { "battle_id": 1 }

**Response:**

    { "success": true }

---

### `POST /api/move_battle.php`
Kampf in andere Gilde verschieben.

**Auth:** admin

**Request Body (JSON):**

    { "battle_id": 1, "target_guild_id": 2 }

**Response:**

    { "success": true }

---

## Statistiken

### `GET /api/battle_stats.php?guild_id=N`
Kampfstatistiken einer Gilde (Spieler-Uebersicht).

**Auth:** user

**Response:**

    {
      "success": true,
      "guild": { "id": 1, "name": "Gildenname", "...": "..." },
      "battle_counts": { "total_battles": 50, "attacks": 20, "defenses": 20, "raids": 10 },
      "players": [
        {
          "player_name": "Spielername",
          "current_level": 400,
          "current_rank": "Offizier",
          "total_fights": 50,
          "participated": 45,
          "missed": 5,
          "participation_pct": 90.0,
          "attacks": 18, "defenses": 18, "raids_participated": 9
        }
      ]
    }

**Hinweis:** Nur aktive Mitglieder (ohne `fired_at`/`left_at`) werden gelistet.

---

### `GET /api/player_details.php?guild_id=N&player_name=NAME`
Detaillierte Kampfhistorie eines Spielers.

**Auth:** user

**Response:**

    {
      "success": true,
      "player": { "name": "Spielername", "level": 400, "rank": "Offizier", "...": "..." },
      "stats": {
        "total_battles": 50, "participated": 45, "missed": 5, "participation_pct": 90.0,
        "attacks": 20, "defenses": 20, "raids": 10,
        "attacks_participated": 18, "defenses_participated": 18, "raids_participated": 9
      },
      "battles": [
        {
          "battle_id": 1, "battle_date": "2026-01-01", "battle_time": "19:30",
          "battle_type": "attack", "opponent_guild": "Gegner-Gilde", "participated": 1, "player_level": 400
        }
      ],
      "monthly": { "2026-01": { "total": 8, "participated": 7 } }
    }

---

## S&F-Account-Verwaltung

### `GET /api/sf_account_manage.php`
Alle S&F-Accounts des eingeloggten Users.

**Auth:** user

**Response:**

    {
      "success": true,
      "accounts": [
        {
          "id": 1, "account_name": "Mein Account", "sf_username": "user@example.com",
          "selected_characters": [
            { "name": "Charaktername", "server": "fX.sfgame.net", "guild": "Gildenname" }
          ],
          "character_count": 2, "guild_count": 1, "is_default": true
        }
      ]
    }

---

### `POST /api/sf_account_manage.php`
S&F-Account anlegen oder aktualisieren.

**Auth:** user

**Request Body (JSON):**

    { "id": null, "account_name": "Mein Account", "sf_username": "user@example.com", "sf_password": "..." }

**Response:**

    { "success": true, "account_id": 1 }

**Hinweis:** Passwort wird AES-verschluesselt gespeichert.

---

### `DELETE /api/sf_account_manage.php`
S&F-Account loeschen.

**Auth:** user

**Request Body (JSON):**

    { "id": 1 }

**Response:**

    { "success": true }

---

### `GET /api/sf_get_characters.php[?account_id=N]`
Gespeicherte Charaktere eines Accounts abrufen.

**Auth:** user

**Response:**

    {
      "success": true,
      "characters": [
        { "name": "Charaktername", "server": "fX.sfgame.net", "guild": "Gildenname", "level": 400 }
      ]
    }

---

### `POST /api/sf_get_characters.php`
Charaktere live von S&F abrufen (Verbindungstest).

**Auth:** user

**Request Body (JSON):**

    { "username": "user@example.com", "password": "...", "account_id": 1 }

**Response:**

    {
      "success": true,
      "characters": [
        { "name": "Charaktername", "server": "fX.sfgame.net", "guild": "Gildenname", "level": 400 }
      ]
    }

**Hinweis:** Ruft das Rust-Binary `list_chars` auf. Timeout: 30 Sekunden.

---

### `POST /api/sf_save_characters.php`
Ausgewaehlte Charaktere eines Accounts speichern.

**Auth:** user

**Request Body (JSON):**

    { "account_id": 1, "characters": [ { "name": "Charaktername", "server": "fX.sfgame.net", "guild": "Gildenname" } ] }

**Response:**

    { "success": true, "count": 1 }

---

### `POST /api/sf_disconnect.php`
S&F-Zugangsdaten aus dem User-Profil entfernen *(Legacy, primaer durch `sf_account_manage` ersetzt)*.

**Auth:** user

**Response:**

    { "success": true, "message": "Verbindung getrennt" }

---

### `POST /api/sf_fetch_reports.php`
Kampfberichte von S&F abrufen (alle Accounts des Users).

**Auth:** moderator

**Request Body (JSON, optional):**

    { "account_ids": [1, 2] }

**Response:**

    { "success": true, "fetched": 5, "skipped": 2, "errors": [] }

**Hinweise:**
- Startet parallele Subprozesse (`sf_fetch_single.php`) pro Charakter.
- Lock verhindert parallele Fetches desselben Users (Stale-Lock nach 180s).
- Timeout: 300 Sekunden.

---

## Admin-Endpunkte

### `GET /api/admin_users.php`
Alle Benutzer.

**Auth:** admin

**Response:**

    { "success": true, "users": [ { "id": 1, "username": "admin", "role": "admin", "created_at": "2026-01-01T00:00:00Z" } ] }

---

### `POST /api/admin_users.php`
Benutzer anlegen.

**Auth:** admin

**Request Body (JSON):**

    { "username": "neuername", "password": "...", "role": "user|moderator|admin" }

**Response:**

    { "success": true, "message": "Benutzer erfolgreich angelegt" }

---

### `PUT /api/admin_users.php`
Benutzer bearbeiten (Passwort, Rolle).

**Auth:** admin

**Request Body (JSON):**

    { "user_id": 2, "role": "moderator", "password": "..." }

**Response:**

    { "success": true }

---

### `DELETE /api/admin_users.php`
Benutzer loeschen.

**Auth:** admin

**Request Body (JSON):**

    { "user_id": 2 }

**Response:**

    { "success": true }

---

### `GET /api/admin_guilds.php`
Alle Gilden (Admin-Ansicht).

**Auth:** admin

**Response:**

    { "success": true, "guilds": [ { "id": 1, "name": "Gildenname", "...": "..." } ] }

---

### `POST /api/admin_guilds.php`
Gilde anlegen (inkl. optionalem Wappen-Upload).

**Auth:** admin

**Request:** `multipart/form-data`
| Feld | Typ | Beschreibung |
|---|---|---|
| `name` | string | Gildenname (max. 30 Zeichen) |
| `server` | string | Servername, z. B. `fX` (max. 20 Zeichen) |
| `tag` | string | Kuerzel (max. 10 Zeichen, optional) |
| `notes` | string | Notizen (max. 1000 Zeichen, optional) |
| `crest` | file | Wappen-Bild (max. 2,5 MB, max. 4096x4096, optional) |

**Response:**

    { "success": true, "guild_id": 1 }

---

### `PUT /api/admin_guilds.php`
Gilde bearbeiten.

**Auth:** admin
**Request:** `multipart/form-data` (gleiche Felder wie POST, zusaetzlich `guild_id`)

---

### `DELETE /api/admin_guilds.php`
Gilde loeschen.

**Auth:** admin

**Request Body (JSON):**

    { "guild_id": 1 }

**Response:**

    { "success": true }

---

### `GET /api/admin_logs.php?action=read&type=activity&lines=100`
Log-Eintraege lesen.

**Auth:** admin

**Query-Parameter:**
| Parameter | Typ | Beschreibung |
|---|---|---|
| `action` | string | `read` (Standard), `clear`, `info` |
| `type` | string | `activity` (Standard) oder `error` |
| `lines` | int | Max. Zeilen (Standard 100, max. 500) |
| `filter` | string | Freitext-Filter |

**Response (`action=read`):**

    { "success": true, "type": "activity", "entries": ["..."], "info": { "size": "12 KB", "lines": 100 } }

**Response (`action=info`):**

    { "success": true, "activity": { "size": "12 KB", "lines": 100 }, "error": { "size": "2 KB", "lines": 10 } }

---

### `POST /api/admin_logs.php?action=clear&type=activity`
Log leeren.

**Auth:** admin

**Response:**

    { "success": true, "message": "Activity-Log wurde geleert" }

---

### `GET /api/admin_import_log.php`
Import-Status pro Gilde + gefilterte Activity-Log-Eintraege.

**Auth:** admin

**Response:**

    {
      "success": true,
      "guilds": [ { "id": 1, "name": "Gildenname", "server": "fX", "last_import_at": "2026-01-01T07:30:00Z" } ],
      "entries": ["2026-01-01 07:30:12 | Member-Sync | ..."]
    }

---

### `GET /api/admin_cron.php`
Cron-Jobs lesen.

**Auth:** admin

**Response:**

    {
      "success": true,
      "jobs": [
        { "id": 1, "job_key": "fetch_reports", "enabled": 1, "times": ["07:25", "19:10"] },
        { "id": 2, "job_key": "member_sync", "enabled": 1, "times": ["07:30", "19:15"] }
      ]
    }

---

### `POST /api/admin_cron.php`
Cron-Job konfigurieren.

**Auth:** admin

**Request Body (JSON):**

    { "job_key": "fetch_reports", "enabled": true, "times": ["07:25", "19:10"] }

**Response:**

    { "success": true }

---

### `POST /api/admin_cron_run.php`
Cron-Job sofort manuell starten (asynchron).

**Auth:** admin

**Request Body (JSON):**

    { "job_key": "fetch_reports|member_sync" }

**Response:**

    { "success": true, "message": "Job gestartet - Ergebnis in wenigen Minuten im Status sichtbar." }

**Fehler (409):** Job laeuft bereits.

---

### `GET /api/admin_system.php?action=info`
Systeminformationen.

**Auth:** admin

**Response:**

    {
      "success": true,
      "info": {
        "php_version": "8.x.x", "sqlite_version": "3.x.x",
        "db_size": "X MB", "disk_free": "X GB",
        "users": 3, "guilds": 2, "members": 50, "battles": 100
      }
    }

---

### `GET /api/admin_system.php?action=backup`
Datenbank-Backup herunterladen.

**Auth:** admin
**Response:** `application/x-sqlite3` als Datei-Download (`sfguilds_backup_YYYY-MM-DD_HH-MM-SS.sqlite`).
**Hinweis:** Verwendet `VACUUM INTO` fuer konsistentes Backup (WAL-sicher).

---

### `GET /api/admin_player_merge.php?action=orphans`
Spieler in Kampfberichten ohne zugehoeriges aktives Mitglied.

**Auth:** admin

**Response:**

    {
      "success": true,
      "orphans": [
        {
          "guild_id": 1, "guild_name": "Gildenname",
          "players": [ { "player_name_norm": "spielername", "display_name": "Spielername", "battle_count": 5, "last_seen": "2026-01-01" } ]
        }
      ]
    }

---

### `GET /api/admin_player_merge.php?action=suggestions&player_name=NAME&guild_id=N`
Moegliche Mitglied-Zuordnungen fuer einen verwaisten Spieler.

**Auth:** admin

---

### `POST /api/admin_player_merge.php`
Spielernamen in Berichten und Mitgliederliste umbenennen (Merge).

**Auth:** admin

**Request Body (JSON):**

    { "guild_id": 1, "old_name": "AlterName", "new_name": "NeuerName" }

**Response:**

    { "success": true, "updated_participants": 10, "updated_members": 1 }

---

## Interne Endpunkte (CLI only)

### `sf_fetch_single.php`
Kampfberichte fuer einen einzelnen Charakter abrufen. Wird von `sf_fetch_reports.php` als Subprozess gestartet.

**Aufruf:** Nur als CLI-Subprocess (`PHP_SAPI === 'cli'`). HTTP-Zugriff gibt 404 zurueck.

    SF_PASSWORD=... php sf_fetch_single.php '{"name":"Charaktername","server":"fX.sfgame.net"}' USER_ID USERNAME

---

## Fehler-Format

Alle Fehlerantworten folgen diesem Schema:

    { "success": false, "message": "Fehlerbeschreibung" }

| Code | Bedeutung |
|---|---|
| 400 | Ungueltige Parameter / fehlende Pflichtfelder |
| 401 | Nicht authentifiziert |
| 403 | Keine Berechtigung |
| 404 | Ressource nicht gefunden |
| 405 | HTTP-Methode nicht erlaubt |
| 409 | Konflikt (z. B. Duplikat, Lock aktiv) |
| 429 | Zu viele Anfragen (Lock: Fetch laeuft bereits) |
| 500 | Interner Serverfehler |
