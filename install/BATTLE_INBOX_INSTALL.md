# Battle Inbox System - Installation Guide

> ⚠️ **Dieses Dokument ist veraltet (Stand: Januar 2026).**  
> Die aktuelle Build- und Deploy-Anleitung für die Rust-Binaries befindet sich in  
> **`rust_examples/README.md`**. Die Binaries liegen in `/opt/sf-api/`, nicht in `/opt/sf-api/target/release/examples/`.

Automated battle report fetching system using Rust sf-api integration.

## Features

- ✅ Automated battle report fetching from Shakes & Fidget
- ✅ Secure encrypted storage of SF credentials
- ✅ Review reports before importing (Inbox system)
- ✅ Support for multiple characters/guilds
- ✅ Duplicate detection via message_id
- ✅ Backward compatible with manual copy-paste import

## Prerequisites

1. **Rust sf-api installed**
   ```bash
   # Should be installed at /opt/sf-api/
   ls /opt/sf-api/fetch_guild_reports
   ls /opt/sf-api/list_chars
   ```

2. **PHP 8.0+ with OpenSSL extension**
   ```bash
   php -m | grep openssl
   ```

3. **Write permissions for storage directory**
   ```bash
   chown -R www-data:www-data /var/www/sfguildsv2/storage
   ```

## Installation Steps

### 1. Generate Encryption Key

```bash
# Generate a secure random key
php -r "echo base64_encode(random_bytes(32)) . PHP_EOL;"
```

Copy the output (e.g., `abcd1234efgh5678...`)

### 2. Set Environment Variable

**Option A: System-wide (recommended)**
```bash
# Add to /etc/environment
echo 'ENCRYPTION_KEY="your_generated_key_here"' | sudo tee -a /etc/environment

# Reload
source /etc/environment
```

**Option B: Per-user**
```bash
# Add to ~/.bashrc or ~/.profile
echo 'export ENCRYPTION_KEY="your_generated_key_here"' >> ~/.bashrc
source ~/.bashrc
```

**Option C: Config file**
```bash
# Create config/.env
cd /var/www/sfguildsv2/config
cat > .env << 'EOF'
ENCRYPTION_KEY="your_generated_key_here"
EOF

chmod 600 .env
chown www-data:www-data .env
```

### 3. Run Database Migration

```bash
cd /var/www/sfguildsv2
php install/migrate_battle_inbox.php
```

Expected output:
```
🔄 Starting Battle Inbox System Migration...

1️⃣  Adding SF Account columns to users table...
   ✅ SF Account columns added

2️⃣  Adding message_id column to sf_eval_battles...
   ✅ message_id column and indexes added

3️⃣  Creating battle_inbox table...
   ✅ battle_inbox table created with indexes

4️⃣  Creating storage directories...
   ✅ Created /var/www/sfguildsv2/storage/sf_reports

✅ Migration completed successfully!
```

### 4. Set File Permissions

```bash
# Ensure www-data can write to storage
chown -R www-data:www-data /var/www/sfguildsv2/storage
chmod -R 775 /var/www/sfguildsv2/storage

# Ensure www-data can execute Rust binaries
chmod +x /opt/sf-api/fetch_guild_reports
chmod +x /opt/sf-api/list_chars
```

### 5. Test the Installation

1. **Login to the application**
2. **Go to Settings** (gear icon in navbar)
3. **Add your S&F credentials**
4. **Click "Verbindung testen"** - should show your characters
5. **Go to Kämpfe** → Click **"📥 Berichte abholen"**
6. **Select a character** → should fetch reports to inbox
7. **Go to Posteingang** → review and import reports

## Usage

### For Users

1. **One-time setup:**
   - Go to **Settings** (⚙️ icon)
   - Enter your S&F username and password
   - Click **Save**

2. **Fetching reports:**
   - Go to **Kämpfe** page
   - Click **"📥 Berichte abholen"**
   - Select a character
   - Wait for fetch to complete

3. **Reviewing reports:**
   - Go to **Posteingang** (Inbox)
   - Review fetched reports
   - Select reports to import
   - Click **"✅ Ausgewählte importieren"**

### For Administrators

**Check logs:**
```bash
# Fetch logs
ls -lh /var/www/sfguildsv2/storage/sf_reports/fetch_*.log

# View latest log
tail -f /var/www/sfguildsv2/storage/sf_reports/fetch_*.log | tail -1
```

**Manual cleanup:**
```bash
# Remove old fetch logs (older than 7 days)
find /var/www/sfguildsv2/storage/sf_reports/ -name "fetch_*.log" -mtime +7 -delete

# Remove rejected reports (older than 30 days)
sqlite3 /var/www/sfguildsv2/data/sfguilds.sqlite <<EOF
DELETE FROM battle_inbox 
WHERE status = 'rejected' 
AND datetime(reviewed_at, '+30 days') < datetime('now');
EOF
```

## Troubleshooting

### "ENCRYPTION_KEY not configured"

**Solution:** Ensure ENCRYPTION_KEY is set in environment or config/.env

```bash
# Check if set
echo $ENCRYPTION_KEY

# If empty, set it (see step 2 above)
```

### "Rust-Script fehlgeschlagen"

**Check:**
```bash
# Test Rust scripts manually
export SSO_USERNAME="your_username"
export SSO_PASSWORD="your_password"
/opt/sf-api/list_chars
```

**Common issues:**
- Rust binaries not executable: `chmod +x /opt/sf-api/*`
- Missing dependencies: `Siehe rust_examples/README.md für Build-Anleitung`
- Wrong credentials: Re-enter in Settings

### "Permission denied" when fetching

**Solution:** Ensure www-data can write to storage
```bash
chown -R www-data:www-data /var/www/sfguildsv2/storage
chmod -R 775 /var/www/sfguildsv2/storage
```

### Reports not appearing in Posteingang

**Check:**
1. Guild exists in system (check in Admin → Gilden)
2. File was created: `ls /var/www/sfguildsv2/storage/sf_reports/*/`
3. Database entry: `sqlite3 /var/www/sfguildsv2/data/sfguilds.sqlite "SELECT COUNT(*) FROM battle_inbox;"`

## Security Notes

- ✅ Passwords are encrypted using AES-256-CBC
- ✅ Each password has unique IV (Initialization Vector)
- ✅ Encryption key never stored in database
- ✅ Temp directories cleaned after each fetch
- ✅ All file operations scoped to user

**Best practices:**
- Use strong ENCRYPTION_KEY (32+ random bytes)
- Keep ENCRYPTION_KEY secret and backed up
- Rotate ENCRYPTION_KEY periodically (requires re-entering passwords)
- Use environment variables over config files

## Rollback

If you need to remove the system:

```bash
# 1. Remove SF credentials from all users
sqlite3 /var/www/sfguildsv2/data/sfguilds.sqlite <<EOF
UPDATE users SET 
    sf_username = NULL,
    sf_password_encrypted = NULL,
    sf_iv = NULL,
    sf_updated_at = NULL;
EOF

# 2. Drop new tables
sqlite3 /var/www/sfguildsv2/data/sfguilds.sqlite <<EOF
DROP TABLE IF EXISTS battle_inbox;
DROP INDEX IF EXISTS idx_message_id;
DROP INDEX IF EXISTS idx_guild_message;
EOF

# 3. Remove message_id column (optional)
sqlite3 /var/www/sfguildsv2/data/sfguilds.sqlite <<EOF
-- SQLite doesn't support DROP COLUMN easily
-- You would need to recreate the table
EOF

# 4. Remove storage directory
rm -rf /var/www/sfguildsv2/storage/sf_reports
```

## Support

For issues or questions:
1. Check logs in `/var/www/sfguildsv2/storage/sf_reports/`
2. Verify Rust scripts work independently
3. Check file permissions
4. Review this guide

---

**Version:** 1.0.0  
**Date:** 2026-01-27  
**Author:** Claude & DasAoD
