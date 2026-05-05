# PHP Weathermap for LibreNMS

A network weathermap plugin for LibreNMS, based on PHP Weathermap 0.98.

## ⚡ Modernization Notice

**Version 0.98c introduces a major modernization to fully integrate with LibreNMS's Laravel framework:**

- ✅ Proper routing via `/plugin/Weathermap/*` (not `/plugins/...`)
- ✅ Queue-based map rendering instead of subprocess polling
- ✅ Modular controllers and services for better maintainability
- ✅ Blade templates for UI rendering
- ✅ Centralized path configuration and error handling
- ⚠️ Legacy PHP scripts (`public/editor.php`, etc.) remain but are **deprecated**

**See [DEPRECATION.md](DEPRECATION.md) for migration guide and backward compatibility info.**

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

# 5. Enable the plugin in LibreNMS
# - Go to Settings → Plugins → Weathermap and enable it
# - Automatic scheduling via Laravel scheduler (no cron job needed)

# Optional: Manual rendering (for testing)
php artisan weathermap:poll --sync
```

Then the plugin will appear in LibreNMS under **Settings → Plugins → Weathermap**.

See [INSTALL.md](INSTALL.md) for full instructions.

## New Features (v0.98c+)

- **Queue-based rendering**: Maps render via Laravel queue jobs for better scalability
- **Modular architecture**: Separate controllers, services, and jobs for cleaner code
- **Centralized configuration**: All paths managed by `ConfigPathResolver` service
- **Better error handling**: Comprehensive logging and retry logic for failed renders
- **Proper authentication**: Access control via LibreNMS role-based permissions

## Structure

```
app/Plugins/Weathermap/
├── Http/
│   └── Controllers/       # Laravel controllers (NEW in v0.98c)
│       ├── CheckController.php
│       ├── EditorPageController.php
│       ├── EditorApiController.php
│       └── DataPickerController.php
├── Services/              # Business logic services (NEW in v0.98c)
│   ├── ConfigPathResolver.php
│   └── MapRenderService.php
├── Jobs/                  # Queue jobs (NEW in v0.98c)
│   └── RenderMapJob.php
├── bin/                   # CLI tools (weathermap, map-poller)
├── configs/               # Map config files (writable by web server)
├── lib/                   # Core PHP library (unchanged)
│   ├── base/
│   ├── datasources/
│   ├── drawing/
│   ├── editor/
│   ├── geometry/
│   ├── html/
│   ├── keywords/
│   ├── map/
│   └── util/
├── public/                # Web-accessible files (symlinked to web root)
│   ├── editor.php         # Deprecated - use /plugin/Weathermap/editor
│   ├── output/            # Generated map images
│   └── editor-resources/
├── resources/views/       # Blade templates
│   ├── page.blade.php
│   ├── check.blade.php
│   ├── editor-start.blade.php
│   ├── editor-main.blade.php
│   └── ...
├── Console/
│   └── PollMaps.php       # Refactored to use queue jobs
├── Menu.php               # LibreNMS plugin menu entry
├── Page.php               # LibreNMS plugin page handler
├── Settings.php           # LibreNMS plugin settings handler
├── composer.json
├── src/
│   ├── config.php
│   └── PluginServiceProvider.php
├── DEPRECATION.md         # Migration guide
└── README.md              # This file
```

## Usage

### Accessing the Editor

**New URL (recommended):**
```
/plugin/Weathermap/editor
```

**Legacy URL (deprecated):**
```
/plugins/Weathermap/editor.php
```

### Checking PHP Environment

**New URL (recommended):**
```
/plugin/Weathermap/check
```

**Legacy URL (deprecated):**
```
/plugins/Weathermap/check.php
```

### Map Rendering

Maps are automatically rendered every 5 minutes via the Laravel scheduler.

To manually render all maps:
```bash
# Synchronous (wait for completion)
php artisan weathermap:poll --sync

# Asynchronous (queue jobs)
php artisan weathermap:poll
```

To process queue jobs:
```bash
php artisan queue:work
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
