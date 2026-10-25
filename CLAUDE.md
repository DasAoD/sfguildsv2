# sfguildsv2 – Projektkontext für Claude Code

- Cron und sf-api-Sync nur mit Admin-Accounts (S&F-Spielregeln), nie mit regulären User-Accounts.
- Antworten und Fehler über `jsonResponse()` / `jsonError()`; die öffentliche API liefert nur nicht-sensitive Felder.
- `guild_joined` kommt seit S&F v29.500 nicht mehr vom Server ("first seen"-Ansatz).
- Produktiv auf dns1 unter `/var/www/sfguildsv2/`: Owner www-data, Verzeichnis 755, `data/` und `data/uploads` 775.
