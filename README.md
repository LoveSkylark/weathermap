# PHP Weathermap for LibreNMS

A network weathermap plugin for LibreNMS, based on PHP Weathermap 0.98.

## Quick Install

```bash
# 1. Clone into the LibreNMS plugin directory
cd /opt/librenms/app/Plugins
git clone https://github.com/librenms-plugins/Weathermap.git Weathermap

# 2. Install dependencies
cd Weathermap
composer install --no-dev

# 3. Symlink public assets into the web root
ln -s /opt/librenms/app/Plugins/Weathermap/public /opt/librenms/public/plugins/Weathermap

# 4. Set permissions
chown -R librenms:librenms /opt/librenms/app/Plugins/Weathermap
chmod 775 configs
mkdir -p public/output && chown www-data:www-data public/output

# 5. Add to cron (/etc/cron.d/librenms)
# */5 * * * * librenms php /opt/librenms/app/Plugins/Weathermap/bin/map-poller >> /dev/null 2>&1
```

Then enable the plugin in LibreNMS under **Settings → Plugins → Weathermap**.

See [INSTALL.md](INSTALL.md) for full instructions.

## Structure

```
app/Plugins/Weathermap/
├── bin/              # CLI tools (weathermap, map-poller)
├── configs/          # Map config files (writable by web server)
├── lib/              # PHP library
│   ├── base/         # Abstract base classes
│   ├── datasources/  # Data source plugins (rrd, snmp, fping, …)
│   ├── drawing/      # Image rendering functions
│   ├── editor/       # Editor functions
│   ├── geometry/     # Geometry classes
│   ├── html/         # HTML imagemap classes
│   ├── keywords/     # Map config keyword definitions
│   ├── map/          # Core map classes (WeatherMap, Node, Link)
│   └── util/         # Utility and formatting functions
├── public/           # Web-accessible files (symlinked into LibreNMS web root)
│   ├── editor.php    # Visual map editor
│   ├── images/       # Node icons
│   ├── output/       # Generated map images and HTML (writable)
│   └── editor-resources/
├── resources/views/  # Blade templates for LibreNMS plugin pages
├── Menu.php          # LibreNMS plugin menu entry
├── Page.php          # LibreNMS plugin page handler
├── Settings.php      # LibreNMS plugin settings handler
├── composer.json     # PSR-4 autoload configuration
└── config.php    # Plugin configuration defaults
```

## Data Sources

| Source | Target syntax | Notes |
|---|---|---|
| RRD | `rrd:/path/to/file.rrd:DS_IN:DS_OUT` | Uses LibreNMS rrd_dir by default |
| SNMP v1 | `snmp:community:host:OID_IN:OID_OUT` | |
| SNMP v2c | `snmp2c:community:host:OID_IN:OID_OUT` | |
| SNMP v3 | `snmp3:...` | |
| fping | `fping:hostname` | |
| Static | `static:value_in:value_out` | |
| Tab file | `tabfile:/path/to/file:key` | |
| Time | `time:` | |
| WM Data | `wmdata:/path/to/file:key` | |
