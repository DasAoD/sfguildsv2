# Auto-Deploy per Forgejo-Webhook

Merge auf `main` → Forgejo schickt einen Push-Webhook an dns1 → ein kleiner
Receiver prüft die Signatur und schreibt eine Trigger-Datei → eine systemd
`.path`-Unit startet `deploy-sfguilds.sh` (Lint im Worktree, `git reset --hard
origin/main`, Rechte). Kein SSH, kein manuelles `git pull` mehr.

**Bewusst ohne `sudo` / `shell_exec`:** der PHP-Prozess (www-data) berührt nur
eine Datei. Die Privilegien-Trennung macht systemd.

```
Forgejo ──POST /deploy──▶ nginx ──fastcgi──▶ /var/www/sfguilds-deploy/webhook.php
                                                    │ HMAC ok & ref=refs/heads/main
                                                    ▼
                                   /var/www/sfguilds-deploy/deploy.trigger  (write)
                                                    │
                        sfguildsv2-deploy.path ─────┘
                                                    ▼
                        sfguildsv2-deploy.service ──▶ /usr/local/bin/deploy-sfguilds.sh
```

## Dateien in diesem Repo (Vorlagen)

| Vorlage | Ziel auf dns1 |
|---|---|
| `install/var/www/sfguilds-deploy/webhook.php` | `/var/www/sfguilds-deploy/webhook.php` |
| `install/systemd/sfguildsv2-deploy.path` | `/etc/systemd/system/sfguildsv2-deploy.path` |
| `install/systemd/sfguildsv2-deploy.service` | `/etc/systemd/system/sfguildsv2-deploy.service` |
| `install/nginx/sfguilds.uliana.de` (Block `location = /deploy`) | in die aktive vHost-Config übernehmen |

---

## Einrichtung (einmalig, als root auf dns1)

### 0. Voraussetzung: Deploy-Script läuft sauber

```bash
test -x /usr/local/bin/deploy-sfguilds.sh || echo "FEHLT – erst install/usr/local/bin/deploy-sfguilds.sh ausbringen"
/usr/local/bin/deploy-sfguilds.sh          # muss grün durchlaufen
tail -n 20 /var/log/sfguilds-deploy.log
```

> Die aktuelle Repo-Vorlage von `deploy-sfguilds.sh` setzt die Rechte über
> `CODE_DIRS=(api cli config includes public)`. Falls auf dns1 noch eine
> ältere Kopie mit `$REPO_DIR/app` liegt: durch die Vorlage aus dem Repo
> ersetzen. Erst weitermachen, wenn der manuelle Lauf fehlerfrei ist.

### 1. Receiver-Verzeichnis + Secret

```bash
install -d -m 0750 -o root -g www-data /var/www/sfguilds-deploy
install -m 0644 -o root -g root \
  /var/www/sfguildsv2/install/var/www/sfguilds-deploy/webhook.php \
  /var/www/sfguilds-deploy/webhook.php

umask 077
openssl rand -hex 32 > /var/www/sfguilds-deploy/.webhook-secret
chown root:www-data /var/www/sfguilds-deploy/.webhook-secret
chmod 0640 /var/www/sfguilds-deploy/.webhook-secret
cat /var/www/sfguilds-deploy/.webhook-secret     # für Schritt 4 merken
```

`www-data` muss in `/var/www/sfguilds-deploy/` schreiben dürfen (Trigger-Datei)
– durch `-g www-data` + `0750` gegeben.

### 2. systemd

```bash
cp /var/www/sfguildsv2/install/systemd/sfguildsv2-deploy.path \
   /var/www/sfguildsv2/install/systemd/sfguildsv2-deploy.service \
   /etc/systemd/system/
systemctl daemon-reload
systemctl enable --now sfguildsv2-deploy.path
systemctl status sfguildsv2-deploy.path
```

Der `.service` wird **nicht** enabled – ihn startet nur die `.path`-Unit.

### 3. nginx

Den Block `location = /deploy { … }` aus
`install/nginx/sfguilds.uliana.de` in die aktive Config übernehmen
(`/etc/nginx/sites-available/…`), dann:

```bash
nginx -t && systemctl reload nginx
```

Prüfen, dass GET geblockt ist (POST-only):

```bash
curl -sS -o /dev/null -w '%{http_code}\n' https://sfguilds.uliana.de/deploy      # -> 403
curl -sS -X POST -d '{}' -w ' %{http_code}\n' https://sfguilds.uliana.de/deploy  # -> 403 bad signature
```

### 4. Webhook in Forgejo

Repo → **Einstellungen → Webhooks → Hinzufügen → Forgejo**:

| Feld | Wert |
|---|---|
| Ziel-URL | `https://sfguilds.uliana.de/deploy` |
| HTTP-Methode | `POST` |
| POST Content Type | `application/json` |
| Secret | der Hex-String aus Schritt 1 |
| Trigger | **nur** „Push-Ereignisse" |
| Branch-Filter | `main` |
| Aktiv | ✔ |

Dann **„Zustellung testen"** klicken.

### 5. Kontrolle

```bash
tail -f /var/log/sfguilds-webhook.log     # HMAC ok? ref ok?
journalctl -u sfguildsv2-deploy.service -f
tail -f /var/log/sfguilds-deploy.log      # Lint OK / deploy ok
```

Der Test-Push von Forgejo hat `ref` = Default-Branch → löst einen echten
Deploy aus (idempotent, `flock`-geschützt). Danach `git rev-parse --short HEAD`
in `/var/www/sfguildsv2` gegen `main` prüfen.

---

## Betrieb

- **Normalfall:** PR mergen → nach ein paar Sekunden ist dns1 aktuell. Das
  manuelle `git pull && chown` entfällt.
- **Logs:** `/var/log/sfguilds-webhook.log` (Empfang), `/var/log/sfguilds-deploy.log`
  (Deploy), `journalctl -u sfguildsv2-deploy.service`.
- **Manuell auslösen:** `systemctl start sfguildsv2-deploy.service` –
  oder weiterhin direkt `/usr/local/bin/deploy-sfguilds.sh`.
- **Parallele Trigger:** `deploy-sfguilds.sh` nimmt `flock -n`; ein zweiter
  Lauf beendet sich sofort mit „Deploy läuft schon."

## Störungssuche

| Symptom | Ursache / Fix |
|---|---|
| `/deploy` liefert 403 bei echtem Webhook | Secret in Forgejo ≠ `/var/www/sfguilds-deploy/.webhook-secret` |
| `/deploy` liefert 500 „server misconfigured" | Secret-Datei fehlt / für www-data nicht lesbar |
| `/deploy` liefert 500 „cannot write trigger" | `/var/www/sfguilds-deploy/` nicht von www-data beschreibbar (Gruppe/Modus prüfen) |
| 202, aber kein Deploy | `.path`-Unit nicht enabled/aktiv (`systemctl status sfguildsv2-deploy.path`); `daemon-reload` vergessen |
| `webhook.php` wird als Text ausgeliefert / 404 | nginx-Block nicht aktiv, oder `SCRIPT_FILENAME`-Pfad falsch; `nginx -t` |
| php-fpm-Fehler „open_basedir" | Pool hat `open_basedir` gesetzt → `/var/www/sfguilds-deploy/` ergänzen (`/etc/php/8.4/fpm/pool.d/www.conf`) |
| Deploy bricht bei `find … app` ab | veraltete `deploy-sfguilds.sh` auf dns1 – durch die Repo-Vorlage ersetzen (`CODE_DIRS`, siehe Schritt 0) |

## Rückbau

```bash
systemctl disable --now sfguildsv2-deploy.path
rm /etc/systemd/system/sfguildsv2-deploy.{path,service}
systemctl daemon-reload
# location = /deploy aus der nginx-Config entfernen, nginx -t && systemctl reload nginx
# Webhook in Forgejo löschen oder deaktivieren
```
