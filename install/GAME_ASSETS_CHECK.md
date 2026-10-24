# Spiel-Assets prüfen (check_game_assets.php)

Das Skript merkt, wenn sich die Item-Bundles im Spiel ändern, und legt geänderte Bundles in `storage/game_assets/staging/<sfprod…>/` ab. Es lädt nur und entpackt oder führt nichts aus. Zuschneiden und PR bleiben manuell.

## Cron einrichten (dns1)

Einmalig `/etc/cron.d/sfguilds-assets` anlegen (täglich, kurz nach 06:00):

```
17 6 * * * www-data php -d memory_limit=512M /var/www/sfguildsv2/cli/check_game_assets.php >> /var/log/sfguilds-assets.log 2>&1
```

`storage/game_assets` muss für `www-data` beschreibbar sein:

```bash
chown -R www-data:www-data /var/www/sfguildsv2/storage/game_assets
chmod 775 /var/www/sfguildsv2/storage/game_assets
```

Der erste Lauf speichert nur den Ausgangszustand (`storage/game_assets/state.json`) und lädt nichts. Mit `--download` lädt er stattdessen alle 25 Bundles.

## Was passiert bei einer Änderung

- Neuer Client-Build (`productVersion` oder `dataUrl` in `sfgame.net/js/build.json`): das Skript lädt die `data`-Datei (ca. 25 MB), liest den Asset-Pfad (`sfprodX.Y.Z`) aus und vergleicht danach pro Bundle den Hash.
- Geänderte Bundles landen in `storage/game_assets/staging/<Pfad>/`; der Eintrag steht im Aktivitätslog ("Spiel-Assets geändert"), Fehler im Fehlerlog.
- Danach: Bundles aus dem Staging in AssetRipper laden ("Export Unity Project", nicht "Primary Content"), die Sprites anhand von `m_Rect` und Textur-GUID zuschneiden und nur fehlende Dateien per PR unter `public/assets/images/items/` ergänzen.

## Prüfen

```bash
php /var/www/sfguildsv2/cli/check_game_assets.php
cat /var/www/sfguildsv2/storage/game_assets/state.json
```

Ohne Änderung gibt das Skript "Keine Änderungen." aus. Bei Fehlern endet es mit Exit-Code 1 und schreibt ins Fehlerlog.
