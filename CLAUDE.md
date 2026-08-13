# sfguildsv2 – Projektkontext für Claude Code

Privates Guild-Management-System für das Browsergame Shakes & Fidget – verwaltet mehrere Gilden auf verschiedenen Servern.

## Tech-Stack
- PHP 8.4, SQLite3, nginx
- CLI-Tools teils in Rust (sf-api-Integration, siehe `rust_examples/`)
- Läuft produktiv auf dns1 (Ubuntu, `/var/www/sfguildsv2/`)
- Cron für automatisierten Kampfbericht-Fetch (07:25 + 19:10 Uhr) und Member-Sync

## Struktur
- `api/` – REST-API-Endpunkte
- `cli/` – Kommandozeilen-Tools (PHP & Rust) inkl. Cron-Runner
- `config/` – Datenbank-Konfiguration
- `data/` – SQLite-DB + Uploads (Gilden-Wappen)
- `includes/` – Shared PHP-Funktionen
- `install/` – Installations-Skripte & Schema
- `public/` – Document Root (nginx), Dark-Theme-Assets
- `rust_examples/` – Rust-Quellcode für sf-api-Binaries (fetch_guild_reports, member_sync, guild_battle_info, list_chars)
- `storage/` – CSV-Import (Fallback), temporäre Kampfbericht-Dateien

## Wichtige Konventionen
- Zentrale Fehlerbehandlung über `jsonResponse()` / `jsonError()`
- `JSON_THROW_ON_ERROR` für alle User-Inputs, `catch (Throwable)` in kritischen Pfaden
- Whitelist-basierte API-Antworten
- Atomares `flock()`-Locking für konkurrierende Prozesse
- CLI-Only-Guards für interne Skripte
- Cron und sf-api-Sync verwenden **ausschließlich Admin-Accounts** (Compliance mit S&F-Spielregeln) – niemals reguläre User-Accounts für automatisierte Zugriffe verwenden

## Datenquellen
- **Primär**: sf-api (the-marenga/sf-api) für Member-Sync und Kampfberichte
- **Fallback**: CSV-Import via SFTools-Export, wenn sf-api nach einem S&F-Update vorübergehend nicht funktioniert

## Besonderheiten
- `guild_joined` wird seit S&F v29.500 nicht mehr vom Server geliefert → "first seen"-Ansatz implementiert
- Öffentliche API liefert nur nicht-sensitive Felder zurück
- `last_online` als ISO-8601-Timestamp gespeichert – Offline-Tage werden datumsbasiert berechnet (ohne Uhrzeit)
- Mitglieder-Sortierung: Aktive <7 Tage offline nach Rang/Level, dann alle ≥7 Tage offline als Block nach Tagen offline, dann Entlassene/Verlassene

## Deployment (Kurzreferenz)
```bash
chmod 755 /var/www/sfguildsv2
chown -R www-data:www-data /var/www/sfguildsv2
chmod 775 /var/www/sfguildsv2/data /var/www/sfguildsv2/data/uploads
```
Rust-Binaries: siehe `rust_examples/README.md` für Build- und Deploy-Anleitung.

## Offene Baustellen
Aktuell 4 offene Issues im Repo – vor größeren Änderungen kurz gegenchecken, ob eine Änderung mit einem bestehenden Issue kollidiert.
