# Gildenrang (Ehrenhalle) - Installation

Der Sync schreibt den Ehrenhallen-Platz der Gilde in `guilds.hof_rank`, das Dashboard zeigt ihn neben dem Server an.

## 1. DB-Spalte anlegen

Einmalig auf dns1 **vor** dem Deploy ausführen (Script danach löschen, siehe `.gitignore`-Regel `migrate_*.php`):

```php
<?php
require_once '/var/www/sfguildsv2/config/database.php';
getDB()->exec('ALTER TABLE guilds ADD COLUMN hof_rank INTEGER');
echo "OK\n";
```

```bash
php migrate_add_hof_rank.php && rm migrate_add_hof_rank.php
```

## 2. member_sync neu bauen

`rust_examples/member_sync.rs` liefert jetzt `guild_rank`. Build und Deploy nach `/opt/sf-api/member_sync` wie in `rust_examples/README.md`. Bis das Binary getauscht ist, bleibt `hof_rank` NULL und das Dashboard zeigt keinen Platz an.
