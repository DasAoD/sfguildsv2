# Favicon erstellen

Da das S&F Logo bereits vorhanden ist, kann es als favicon verwendet werden.

## Auf dem Server ausführen:

```bash
# Logo kopieren (falls noch nicht geschehen)
cp /var/www/sfguilds/public/assets/sf-logo.png \
   /var/www/sfguildsv2/public/assets/images/sf-logo.png

# ImageMagick verwenden um favicon zu erstellen
convert /var/www/sfguildsv2/public/assets/images/sf-logo.png \
    -resize 32x32 \
    -background transparent \
    -flatten \
    /var/www/sfguildsv2/public/favicon.ico

# Alternative: Mehrere Größen in einer .ico Datei
convert /var/www/sfguildsv2/public/assets/images/sf-logo.png \
    -resize 16x16 \
    -background transparent \
    \( -clone 0 -resize 32x32 \) \
    \( -clone 0 -resize 48x48 \) \
    -delete 0 \
    -flatten \
    /var/www/sfguildsv2/public/favicon.ico

# Rechte setzen
chown www-data:www-data /var/www/sfguildsv2/public/favicon.ico
chmod 644 /var/www/sfguildsv2/public/favicon.ico
```

## Oder: Moderne favicon.png verwenden

```bash
# Einfach das Logo als favicon.png verlinken
cp /var/www/sfguildsv2/public/assets/images/sf-logo.png \
   /var/www/sfguildsv2/public/favicon.png

# In head Tag referenzieren:
# <link rel="icon" type="image/png" href="/favicon.png">
```
