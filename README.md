# sfguildsv2

**EN (short):** Web-based guild management & reporting for *Shakes & Fidget*.  
Built with PHP + SQLite, German UI. This repository is currently private and primarily intended for internal use and tooling/AI-assisted development.

---

## Überblick (DE)

`sfguildsv2` ist eine schlanke Web-App zur Verwaltung von Shakes & Fidget Gilden (Mitglieder, Kämpfe, Auswertungen),
als Ersatz für Excel-Listen. Ziel ist eine übersichtliche Oberfläche, eine einfache Import-Pipeline und aussagekräftige Reports.

---

## Tech-Stack

- PHP (empfohlen: 8.3+)
- SQLite (PDO / pdo_sqlite)
- Nginx oder Apache (Webroot zeigt auf `public/`)
- Vanilla JS + CSS (kein Build-System nötig)

---

## Projektstruktur

- `public/` – Webroot (Pages, Assets, Errorpages)
- `api/` – HTTP-Endpunkte für UI/Admin/Import/Fetch
- `includes/` – Auth, Helper, Logger, Encryption, Templates
- `config/` – Konfiguration (`.env.example`, `database.php`)
- `data/` *(runtime, nicht versioniert)* – Uploads/Logs
- `storage/` *(runtime, weitgehend nicht versioniert)* – Import-Pipeline, Report-Ablage (`sf_reports/temp` bleibt als Struktur sichtbar)

Runtime-Daten (SQLite, Logs, Uploads, Roh-Reports) sind absichtlich per `.gitignore` ausgeschlossen.

---

## Quickstart (minimal)

### 1) Deploy / Webserver
- Projekt nach z. B. `/var/www/sfguildsv2` deployen
- Webserver-Docroot auf `public/` setzen

### 2) Konfiguration
- Example kopieren:
  - `cp config/.env.example config/.env`
- In `.env` mindestens setzen:
  - `ENCRYPTION_KEY` (zufälliger, langer Key)

> Die `.env` gehört nicht ins Repo.

### 3) Schreibrechte (runtime)
Diese Ordner müssen vom Webserver beschreibbar sein (z. B. `www-data`):

- `data/uploads/`
- `data/logs/`
- `storage/import/`
- `storage/sf_reports/temp/`

---

## Import-Formate (in Arbeit)

Die endgültigen CSV-Formate und Import-Regeln sind noch nicht final.
Sobald die Felder/Layouts feststehen, folgt eine eigene Doku.

---

## Bootstrap / Wie die App startet

Es gibt **kein zentrales Bootstrap-File** und kein `auto_prepend_file`.  
Jeder API-Endpunkt in `api/` lädt seine Abhängigkeiten explizit per `require_once`:

| Include | Zweck |
|---|---|
| `config/database.php` | PDO-Verbindung, `getDB()` |
| `includes/auth.php` | Session, `checkAuth()`, `requireAdminAPI()` etc. |
| `includes/functions.php` | Shared Helpers (`jsonResponse()`, etc.) |
| `includes/logger.php` | `logError()`, `logActivity()` |
| `includes/encryption.php` | AES-256-CBC + HMAC |
| `includes/sf_helpers.php` | Rust-Output-Parser, Battle-Report-Parser |

**Reihenfolge:** `database.php` → `auth.php` → weitere Includes → eigene Logik.  
Neue Endpunkte sollten diesem Muster folgen.

---

## Optional: sf-api (Rust) Integration (Fetch/Characters)

Einige Endpunkte nutzen externe Rust-Tools aus dem Projekt **sf-api**.  
Da der VPS von Playa Games geblockt wird, laufen die Binaries auf einem **Heimserver mit Residential-IP**  
und werden via SSH über einen WireGuard-Tunnel aufgerufen.

**Infrastruktur:**
- `/opt/sfetch/run_fetch.sh` – SSH-Wrapper (ruft `sudo -u sfetch` auf den Heimserver)
- `/root/sf-api/run_fetch_wrapper.sh` – Wrapper auf Heimserver für `fetch_guild_reports`
- `/root/sf-api/run_list_chars_wrapper.sh` – Wrapper auf Heimserver für `list_chars`
- Credentials werden **via stdin** übergeben (nie in argv / Prozessliste sichtbar)

**Betroffene PHP-Dateien:**
- `api/sf_get_characters.php` – Characters via `runListChars()` → SSH → `list_chars`
- `api/sf_fetch_single.php` – Reports via SSH → `fetch_guild_reports`
- `includes/sf_helpers.php` – `parseRustBattleReport()`

Wenn sf-api oder der SSH-Tunnel nicht verfügbar sind, funktionieren diese Endpunkte nicht.  
Der Kern der Web-App (UI, Admin, lokale Daten) läuft unabhängig davon.

---

## Credits / Acknowledgements

- **sf-api** (Rust CLI/API) by **the-marenga**: https://github.com/the-marenga/sf-api
  - Wird in diesem Projekt für Report-Fetching genutzt.

---

## Entwicklungsstand & Roadmap

Aktuelle Roadmap und offene Punkte:
- [`ROADMAP_PUBLIC.md`](ROADMAP_PUBLIC.md) – Phasen-Übersicht (Phase 1-4, alle abgeschlossen)
- [`TODO_PUBLIC.md`](TODO_PUBLIC.md) – Detaillierte Aufgabenliste

**Aktuell offen:** Mobile-Optimierung und optionaler Theme-Toggle (Low Priority)