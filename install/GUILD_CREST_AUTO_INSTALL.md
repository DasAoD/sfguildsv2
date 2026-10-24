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

**Umgesetzt** in `rust_examples/member_sync.rs` und `cli/cron_member_sync.php` (Beschreibung unten als Hintergrund). Der Sync befüllt `coa_code` (Cron und Button auf der Gildenseite); manuell hochgeladene Wappen haben Vorrang.

Ursprünglich offen: einer der bestehenden `rust_examples/*.rs`-Sync-Binaries
(vermutlich `member_sync.rs`, da dieser bereits pro Gilde mit dem
Admin-Account einloggt) musste erweitert werden:

1. Nach dem Login `Response::raw_response()` verwenden (NICHT
   `gs.guild.emblem` -- das ist ein anderes, meist leeres Feld, siehe
   Memory `sfguildsv2-guild-crest-reverse-engineering`).
2. Im Rohtext nach `owngroupsave.groupSave:` suchen, an `/` splitten,
   Index 1 nehmen (der 22-stellige Hex-Code).
3. `UPDATE guilds SET coa_code = ? WHERE id = ?`.


## 4. Schrift für den Gildennamen (optional)

Der Gildenname auf dem Banner ist im Spiel Client-Text und wird mit einer
TrueType-Schrift auf das Banner gezeichnet. Die Schrift liegt wie die
Wappen-Grafiken nur auf dem Server (Lizenz), nicht im Repo:

```
data/guildcrests/name_font.ttf
```

Ohne diese Datei bleibt das Banner leer. Die Schriftgröße ist im Spiel für
alle Namen gleich (`COA_NAME_REF_*` in `includes/guild_crest.php`, geeicht
an "SchattendrachenA"), nur zu lange Namen werden verkleinert. Eine runde
Fettschrift passt am besten. Die bereits gerenderten Wappen enthalten die
Schrift in ihrem Cache-Namen, nach dem Installieren der Datei entstehen sie
beim nächsten Aufruf neu.

## Layout und Farben

- **Layout:** Die Position aller Ebenen kommt aus den Pivot-Offsets der
  Sprites (`public/assets/data/coa_offsets.json`, `m_Offset` aus den
  Spiel-Assets), die Reihenfolge ist aus den Spiel-Screenshots abgeleitet:
  Helm, Schildhalter, Schild, Figur, Helmzier, Banner, Zusatz, Name.
- **Farben:** `COA_PALETTE_GUESS` enthält gemessene Werte für die Indizes
  2, 3, 4, 8, 9 und 11. Alle anderen Indizes sind Näherungen und müssen
  ergänzt werden, sobald ein Wappen mit der Farbe vorliegt.
- Schildzonen werden flach mit der Palettenfarbe gefüllt, die Zonen-Maske
  trägt auch in transparenten Bereichen Farbwerte (Alpha mit einrechnen).
