# Installation

## Prerequisites

- LibreNMS installed at `/opt/librenms` (or your chosen path)
- PHP 8.0 or newer
- PHP extensions: `gd`, `snmp`, `json`
- Composer

---

## Step 1 — Clone into LibreNMS plugin directory

```bash
cd /opt/librenms/app/Plugins
git clone https://github.com/loveskylark/librenms-weathermap.git Weathermap
```

## Step 2 — Install Composer dependencies

```bash
cd /opt/librenms/app/Plugins/Weathermap
composer install --no-dev
```

## Step 3 — Create the public symlink

This makes the editor, images, and assets web-accessible:

```bash
ln -s /opt/librenms/app/Plugins/Weathermap/public /opt/librenms/public/plugins/Weathermap
```

## Step 4 — Set permissions

```bash
chown -R librenms:librenms /opt/librenms/app/Plugins/Weathermap
chmod 775 /opt/librenms/app/Plugins/Weathermap/configs
chown www-data:www-data /opt/librenms/app/Plugins/Weathermap/public/output
chmod 775 /opt/librenms/app/Plugins/Weathermap/public/output
```

If you are using SELinux:

```bash
chcon -R -t httpd_cache_t /opt/librenms/app/Plugins/Weathermap
```

## Step 5 — Enable the plugin

In LibreNMS go to **Settings → Plugins** and enable **Weathermap**.

The plugin registers an artisan command `weathermap:poll` via `PluginServiceProvider`, which
LibreNMS's scheduler picks up automatically and runs every 5 minutes.
No manual cron entry is required.

---

## Configuration

The plugin reads the following settings automatically from LibreNMS:

| Setting | LibreNMS config key |
|---|---|
| rrdtool binary path | `rrdtool` |
| RRD data directory | `rrd_dir` |
| rrdcached socket | `rrdcached` |
| fping binary path | `fping` |
| SNMP timeout | `snmp.timeout` |
| SNMP retries | `snmp.retries` |


---

## Verify the installation

Navigate to `http://yourserver/plugins/Weathermap/check.php` in a browser, or run from the command line:

```bash
php /opt/librenms/app/Plugins/Weathermap/public/check.php
```
