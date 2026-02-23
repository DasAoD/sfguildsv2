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

## Optional: sf-api (Rust) Integration (Fetch/Characters)

Einige Endpunkte nutzen externe Rust-Tools aus dem Projekt **sf-api** (liegt aktuell außerhalb dieses Repos).
Die PHP-API ruft dazu vorkompilierte Binaries auf und parst deren Ausgabe.

**Verwendete Binaries (aktuell hardcoded):**
- `/opt/sf-api/target/release/examples/list_chars`
- `/opt/sf-api/target/release/examples/fetch_guild_reports`

**Betroffene Dateien:**
- `api/sf_get_characters.php` (Characters via `list_chars`)
- `api/sf_fetch_single.php` (Reports via `fetch_guild_reports`)
- `includes/sf_helpers.php` (`parseRustBattleReport()`)

Wenn sf-api nicht vorhanden ist, funktionieren diese Endpunkte entsprechend nicht bzw. liefern Fehler.
Der Kern der Web-App (UI, Admin, lokale Daten) kann dennoch unabhängig davon betrieben werden.

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