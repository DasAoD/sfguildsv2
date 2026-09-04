# Gildenwappen Auto-Generierung - Installation

Rendert das Gildenwappen automatisch aus dem `/coa`-Code, den der S&F-Server
mit jedem Login/Poll in `owngroupsave.groupSave` mitschickt -- statt dass ein
Gildenleiter das Wappen manuell screenshotten/zuschneiden/hochladen muss.

**Der manuelle Upload (`guilds.crest_file`) bleibt unverändert bestehen und
hat immer Vorrang.** Diese Funktion ist reines Fallback/Zusatzfeature: sie
greift nur, wenn `crest_file` NULL ist. Fällt die Auto-Generierung aus
(Assets fehlen, Code ungültig, Rendering-Fehler), zeigt die UI einfach den
bisherigen Platzhalter -- kein Breaking Change.

## 1. DB-Spalte anlegen

Einmalig auf dns1 ausführen (Migrationsscript liegt NICHT im Repo, siehe
`.gitignore`-Regel `migrate_*.php` -- Datei danach löschen):

```php
<?php
require_once '/var/www/sfguildsv2/config/database.php';
getDB()->exec('ALTER TABLE guilds ADD COLUMN coa_code TEXT');
echo "OK\n";
```

```bash
php migrate_add_coa_code.php && rm migrate_add_coa_code.php
```

## 2. Wappen-Rohgrafiken deployen

Die 221 aus den Spiel-Assets extrahierten PNGs (7 Kategorien: helm,
supporter, shield, banner, helmet, order, emblem) gehören **nicht ins
Repo** (Urheberrecht) und liegen nur lokal/im Scratchpad. Einmalig nach
`data/guildcrests/` auf dns1 kopieren:

```bash
scp -r guild_crest_final/* root@dns1:/var/www/sfguildsv2/data/guildcrests/
chown -R www-data:www-data /var/www/sfguildsv2/data/guildcrests
```

Erwartete Struktur:
```
data/guildcrests/
  helm/coa_1_1.png ... coa_1_21.png
  supporter/coa_2_1.png ... coa_2_34.png
  shield/coa_3_1.png ... coa_3_46.png, coa_3_*_color.png
  banner/coa_4_1.png ... coa_4_12.png
  helmet/coa_5_1.png ... coa_5_24.png
  order/coa_6_1.png ... coa_6_16.png
  emblem/coa_7_1.png ... coa_7_68.png
```

Ohne diesen Ordner liefert `resolveGuildCrestImage()` einfach `null` --
UI fällt automatisch auf den Platzhalter zurück, kein Fehler.

## 3. Sync erweitern (`coa_code` befüllen)

**Noch offen / TODO:** einer der bestehenden `rust_examples/*.rs`-Sync-Binaries
(vermutlich `member_sync.rs`, da dieser bereits pro Gilde mit dem
Admin-Account einloggt) muss erweitert werden:

1. Nach dem Login `Response::raw_response()` verwenden (NICHT
   `gs.guild.emblem` -- das ist ein anderes, meist leeres Feld, siehe
   Memory `sfguildsv2-guild-crest-reverse-engineering`).
2. Im Rohtext nach `owngroupsave.groupSave:` suchen, an `/` splitten,
   Index 1 nehmen (der 22-stellige Hex-Code).
3. `UPDATE guilds SET coa_code = ? WHERE id = ?`.

Bis das steht, bleibt `coa_code` NULL und die Auto-Generierung inaktiv
(reiner Fallback auf den Platzhalter -- keine Regression).

## Bekannte Einschränkungen (Stand 2026-09-04)

- **Farb-Palette ist eine Näherung** (`COA_PALETTE_GUESS` in
  `includes/guild_crest.php`) -- nur Index 4 ist an einem echten
  Screenshot verifiziert (`#009900`). Alle anderen Werte sind Platzhalter.
- **Layout/Skalierung/Reihenfolge der Ebenen** basiert auf Augenmaß
  gegen einen einzigen Referenz-Screenshot (Gilde "Gurkistan"), keine
  Positionsdaten aus den Spiel-Assets verfügbar.
- **Gildenname wird nicht auf's Banner gerendert** (im Original ist das
  clientseitiger Text, keine Grafik-Ebene).
- Details, offene Fragen und der komplette Recherche-Stand: siehe Memory
  `sfguildsv2-guild-crest-reverse-engineering`.
