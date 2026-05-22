# Rust Examples (sf-api)

Diese Dateien sind Examples für [sf-api](https://github.com/the-marenga/sf-api)
und gehören in `/root/sf-api/examples/` auf dns1.

**Aktuell getestete sf-api Version: v0.4.3** (kompatibel mit S&F-Server v30.500)

## Deployment

```bash
cp rust_examples/fetch_guild_reports.rs /root/sf-api/examples/
cp rust_examples/list_chars.rs          /root/sf-api/examples/
cp rust_examples/member_sync.rs         /root/sf-api/examples/
cd /root/sf-api
cargo build --release --example fetch_guild_reports
cargo build --release --example list_chars
cargo build --release --example member_sync
cp target/release/examples/fetch_guild_reports /opt/sf-api/
cp target/release/examples/list_chars          /opt/sf-api/
cp target/release/examples/member_sync         /opt/sf-api/
chown root:www-data /opt/sf-api/fetch_guild_reports /opt/sf-api/list_chars /opt/sf-api/member_sync
chmod 750           /opt/sf-api/fetch_guild_reports /opt/sf-api/list_chars /opt/sf-api/member_sync
```

## Nach S&F-Spielupdates

sf-api muss ggf. aktualisiert und neu gebaut werden:

```bash
cd /root/sf-api
git pull origin main
# Cargo.toml prüfen und ggf. anpassen:
#   tokio: features müssen ["sync","time","macros","rt-multi-thread"] enthalten
#   unidecode = "0.3" muss in [dependencies] stehen
#   Bei Merge-Konflikten: upstream-Version behalten, unsere Features ergänzen
cargo build --release --example fetch_guild_reports
cargo build --release --example list_chars
cargo build --release --example member_sync
# danach wie oben nach /opt/sf-api/ kopieren
```
