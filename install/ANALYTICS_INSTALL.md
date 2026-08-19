# Website-Statistik (GoAccess) - Installation Guide

Wertet die bestehenden nginx-Access-Logs von sfguildsv2 aus (Seiten, Browser,
Referrer, Herkunftsland) und zeigt das Ergebnis als neuen "Statistik"-Tab im
Admin-Bereich. Kein neuer Dienst mit offenem Port, kein zusätzliches Login -
GoAccess läuft als Cronjob und schreibt eine statische HTML-Datei, die nur
über den bestehenden admin-geschützten API-Endpoint ausgeliefert wird.

## Voraussetzungen

- nginx loggt bereits im Standard-`combined`-Format nach
  `/var/log/nginx/sfguilds_access.log` (Debian-Default, keine Anpassung
  nötig - siehe `access_log`-Zeile im Vhost)
- `www-data` ist Mitglied der Gruppe `adm` (Debian-Standard), kann die
  Logdatei also lesen

## Installationsschritte

### 1. GoAccess installieren

```bash
apt-get install -y goaccess
```

### 2. Verzeichnisse anlegen

```bash
mkdir -p /var/www/sfguildsv2/data/analytics /var/www/sfguildsv2/data/geoip
chown www-data:www-data /var/www/sfguildsv2/data/analytics /var/www/sfguildsv2/data/geoip
```

Beide liegen unter `data/` (außerhalb von `public/`) - nginx liefert sie
nie direkt aus.

### 3. GeoIP-Datenbank (DB-IP Lite, Country)

Kein Account/Lizenzschlüssel nötig (im Gegensatz zu MaxMind GeoLite2) -
einfacher monatlicher Download.

`/usr/local/sbin/sfguilds-geoip-update.sh`:
```bash
#!/bin/bash
set -euo pipefail

DEST_DIR="/var/www/sfguildsv2/data/geoip"
DEST_FILE="${DEST_DIR}/dbip-country-lite.mmdb"
TMP="$(mktemp)"
trap 'rm -f "$TMP"' EXIT

try_month() {
    local month="$1"
    local url="https://download.db-ip.com/free/dbip-country-lite-${month}.mmdb.gz"
    curl -fsSL "$url" -o "$TMP"
}

CURRENT_MONTH="$(date +%Y-%m)"
PREV_MONTH="$(date -d 'last month' +%Y-%m)"

if try_month "$CURRENT_MONTH"; then
    echo "$(date '+%F %T') OK: geladen fuer ${CURRENT_MONTH}"
elif try_month "$PREV_MONTH"; then
    echo "$(date '+%F %T') OK: geladen fuer ${PREV_MONTH} (Fallback, ${CURRENT_MONTH} noch nicht verfuegbar)"
else
    echo "$(date '+%F %T') FEHLER: Download fuer ${CURRENT_MONTH} und ${PREV_MONTH} fehlgeschlagen" >&2
    exit 1
fi

gunzip -c "$TMP" > "${DEST_FILE}.new"
mv "${DEST_FILE}.new" "$DEST_FILE"
echo "$(date '+%F %T') GeoIP-DB aktualisiert: $DEST_FILE"
```

```bash
chmod 750 /usr/local/sbin/sfguilds-geoip-update.sh
chown root:www-data /usr/local/sbin/sfguilds-geoip-update.sh
sudo -u www-data /usr/local/sbin/sfguilds-geoip-update.sh   # einmal manuell testen
```

DB-IP veröffentlicht monatlich eine neue Datei (Anfang des Monats); der
Fallback auf den Vormonat fängt die paar Tage ab, in denen die neue Datei
noch nicht online ist.

### 4. Report-Script

`/usr/local/sbin/sfguilds-analytics.sh`:
```bash
#!/bin/bash
set -euo pipefail

LOG="/var/log/nginx/sfguilds_access.log"
GEOIP="/var/www/sfguildsv2/data/geoip/dbip-country-lite.mmdb"
OUT="/var/www/sfguildsv2/data/analytics/report.html"
TMP="${OUT%.html}.tmp.html"   # GoAccess verlangt .html als Dateiendung fuer -o

GEOIP_ARGS=()
if [ -f "$GEOIP" ]; then
    GEOIP_ARGS=(--geoip-database="$GEOIP")
fi

goaccess "$LOG" \
    --log-format=COMBINED \
    --ignore-crawlers \
    -a \
    "${GEOIP_ARGS[@]}" \
    -o "$TMP"

mv "$TMP" "$OUT"
echo "$(date '+%F %T') Report aktualisiert: $OUT"
```

```bash
chmod 750 /usr/local/sbin/sfguilds-analytics.sh
chown root:www-data /usr/local/sbin/sfguilds-analytics.sh
sudo -u www-data /usr/local/sbin/sfguilds-analytics.sh   # einmal manuell testen
```

### 5. Cronjobs (als `www-data`, nicht root)

```bash
crontab -u www-data -e
```

```cron
*/30 * * * * /usr/local/sbin/sfguilds-analytics.sh >> /var/www/sfguildsv2/data/logs/analytics_cron.log 2>&1
17 4 3 * * /usr/local/sbin/sfguilds-geoip-update.sh >> /var/www/sfguildsv2/data/logs/geoip_cron.log 2>&1
```

Logs landen bewusst unter `data/logs/` statt `/var/log/` - `www-data` darf
dort ohne Root-Rechte schreiben (gleiches Verzeichnis wie `activity.log`/
`error.log` aus `includes/logger.php`).

### 6. Test

1. Als Admin einloggen, `admin.php` öffnen, Tab **Statistik**
2. Report sollte im iframe erscheinen (nach dem ersten Cronlauf, max. 30 Min)
3. Bei Bedarf manuell antriggern: `sudo -u www-data /usr/local/sbin/sfguilds-analytics.sh`

## Sicherheit / Datenschutz

- Der Report enthält Besucher-IPs (auch von nicht eingeloggten Besuchern,
  z.B. der öffentlichen Hellevator-Seite) - das ist personenbezogene Daten
  im Sinne der DSGVO.
- Zugriffsschutz: identisch zum Rest von `admin.php` - Login + Admin-Rolle
  (`requireAdminAPI()`), keine zusätzliche IP-Sperre (bewusste Entscheidung,
  siehe Commit-Historie).
- Die Report-Datei liegt außerhalb von `public/`, ist also nie direkt über
  eine URL erreichbar, nur über `api/admin_stats.php`.

## Rollback

```bash
crontab -u www-data -e   # beide sfguilds-Zeilen entfernen
rm -f /usr/local/sbin/sfguilds-analytics.sh /usr/local/sbin/sfguilds-geoip-update.sh
rm -rf /var/www/sfguildsv2/data/analytics /var/www/sfguildsv2/data/geoip
apt-get remove -y goaccess
```

---

**Version:** 1.0.0
**Date:** 2026-08-19
**Author:** Claude & DasAoD
