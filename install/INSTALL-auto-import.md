# Auto-Import Installation (systemd)

## Überblick
Automatischer CSV-Import für SFGuildsV2 mit systemd file watcher.

## Voraussetzungen
- Root/sudo Zugriff
- systemd (Ubuntu/Debian)
- www-data User existiert

## Installation

### 1. Shell-Script installieren
```bash
sudo cp install/systemd/sfguildsv2-import-incoming.sh /usr/local/bin/
sudo chmod +x /usr/local/bin/sfguildsv2-import-incoming.sh
```

### 2. Systemd Units installieren
```bash
sudo cp install/systemd/sfguildsv2-import-incoming.path /etc/systemd/system/
sudo cp install/systemd/sfguildsv2-import-incoming.service /etc/systemd/system/
```

### 3. Systemd aktivieren
```bash
sudo systemctl daemon-reload
sudo systemctl enable sfguildsv2-import-incoming.path
sudo systemctl start sfguildsv2-import-incoming.path
```

### 4. Status prüfen
```bash
# Watcher-Status
sudo systemctl status sfguildsv2-import-incoming.path

# Service-Logs
journalctl -u sfguildsv2-import-incoming.service -f

# Import-Log
tail -f /var/www/sfguildsv2/storage/import/import.log
```

## Upload-Script anpassen (auf NAS)

Ändere in deinem Upload-Script die Ziel-Pfade:

```bash
# Alt (sfguilds)
REMOTE_DIR="/var/www/sfguilds/storage/import/incoming"

# Neu (sfguildsv2) - ODER beide parallel!
REMOTE_DIR="/var/www/sfguildsv2/storage/import/incoming"
```

**Optional: Beide Projekte parallel:**
```bash
# Upload zu beiden Projekten
rsync ... "$REMOTE_DIR/${guild}.csv"
rsync ... "/var/www/sfguildsv2/storage/import/incoming/${guild}.csv"
```

## Dateiname-Mapping

Upload-Script erstellt:
- `blutzirkel.csv` → Gilde ID 1
- `equilibrium.csv` → Gilde ID 2
- `gluecksbaerchis.csv` → Gilde ID 3
- `gurkistan.csv` → Gilde ID 4

Auto-Erkennung via `slugify()` Funktion in `import_sftools.php`.

## Workflow

```
1. NAS Upload → /var/www/sfguildsv2/storage/import/incoming/blutzirkel.csv
2. systemd .path erkennt neue CSV
3. systemd .service startet Shell-Script
4. Script verschiebt CSV → processing/
5. Script ruft auf: php cli/import_sftools.php --file processing/blutzirkel.csv
6. Bei Erfolg → archive/blutzirkel.csv (überschreibt alte)
7. Bei Fehler → failed/blutzirkel.1769306806585.csv
8. Alles geloggt in import.log
```

## Troubleshooting

### Import funktioniert nicht
```bash
# Permissions prüfen
ls -la /var/www/sfguildsv2/storage/import/

# Manueller Test
sudo -u www-data php /var/www/sfguildsv2/cli/import_sftools.php \
  --file /var/www/sfguildsv2/storage/import/incoming/blutzirkel.csv

# Log checken
tail -100 /var/www/sfguildsv2/storage/import/import.log
```

### Systemd startet nicht
```bash
# Service Status
sudo systemctl status sfguildsv2-import-incoming.service

# Journal-Logs
journalctl -xeu sfguildsv2-import-incoming.service
```

### CSV wird nicht erkannt
```bash
# Path-Status prüfen
systemctl show sfguildsv2-import-incoming.path | grep TriggerPath
```

## Deinstallation

```bash
sudo systemctl stop sfguildsv2-import-incoming.path
sudo systemctl disable sfguildsv2-import-incoming.path
sudo rm /etc/systemd/system/sfguildsv2-import-incoming.{path,service}
sudo rm /usr/local/bin/sfguildsv2-import-incoming.sh
sudo systemctl daemon-reload
```
