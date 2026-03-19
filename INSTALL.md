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
git clone https://github.com/librenms-plugins/Weathermap.git Weathermap
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
```

If you are using SELinux:

```bash
chcon -R -t httpd_cache_t /opt/librenms/app/Plugins/Weathermap
```

## Step 5 — Create the output directory

Map images and HTML output are written here:

```bash
mkdir -p /opt/librenms/app/Plugins/Weathermap/public/output
chown www-data:www-data /opt/librenms/app/Plugins/Weathermap/public/output
```

## Step 6 — Add the poller cron job

Edit `/etc/cron.d/librenms` and add:

```
*/5 * * * * librenms php /opt/librenms/app/Plugins/Weathermap/bin/map-poller >> /dev/null 2>&1
```

## Step 7 — Enable the plugin

In LibreNMS go to **Settings → Plugins** and enable **Weathermap**.

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

To override the rrdtool path or other defaults, copy `editor-config.php-dist` to `editor-config.php` and edit it.

---

## Verify the installation

Navigate to `http://yourserver/plugins/Weathermap/check.php` in a browser, or run from the command line:

```bash
php /opt/librenms/app/Plugins/Weathermap/public/check.php
```
