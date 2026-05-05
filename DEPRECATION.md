# Phase 4: Modernization - Migration from Legacy Scripts

## Overview

The Weathermap plugin has undergone a complete modernization to fully integrate with the LibreNMS Laravel framework. This document guides you through the changes and migration path.

## What Changed

### 1. Authentication & Authorization
- **Old**: All authenticated users could access editor and check page
- **New**: Access now requires `global-read` capability (configurable per role)

**Impact**: Admin must grant Weathermap access via role permissions

### 2. Routing System
- **Old**: Direct access to PHP scripts via `/plugins/Weathermap/editor.php`
- **New**: Proper Laravel routes via `/plugin/Weathermap/*` (note: singular "plugin")

**Impact**: Update bookmarks and documentation

### 3. Editor Interface
- **Old**: Monolithic `public/editor.php` script
- **New**: Modular `EditorPageController` with AJAX API endpoints

**Location**: `/plugin/Weathermap/editor`

### 4. Map Rendering
- **Old**: Subprocess-based polling via `bin/map-poller`
- **New**: Queue-based job system via `weathermap:poll` with `--sync` option

**Impact**: Better scalability and error handling

### 5. Configuration
- **Old**: Hard-coded paths throughout codebase
- **New**: Centralized `ConfigPathResolver` service

**Impact**: Plugin is now more portable across different LibreNMS installations

## Migration Guide

### For Users

#### Accessing the Editor
```
OLD:  /plugins/Weathermap/editor.php?mapname=mymap.conf
NEW:  /plugin/Weathermap/editor/mymap.conf
```

#### Checking PHP Environment
```
OLD:  /plugins/Weathermap/check.php
NEW:  /plugin/Weathermap/check
```

#### Viewing Maps
The map gallery remains the same:
```
OLD:  /plugin/Weathermap
NEW:  /plugin/Weathermap (same URL)
```

### For Administrators

#### Granting Access
Users need the `global-read` permission to access Weathermap. This is controlled via LibreNMS role settings.

#### Scheduling Rendering
The poller automatically runs every 5 minutes via Laravel scheduler. For manual rendering:

```bash
# Synchronous rendering (wait for completion)
php artisan weathermap:poll --sync

# Asynchronous rendering via queue
php artisan weathermap:poll

# Check queue status
php artisan queue:work
```

#### Verifying Installation
Visit `/plugin/Weathermap/check` to verify your PHP environment has all required extensions.

### For Developers

#### Creating Custom Extensions
- **Map Rendering**: Use `MapRenderService` instead of direct `WeatherMap` instantiation
- **Path Resolution**: Use `ConfigPathResolver` for all directory paths
- **Jobs**: Queue map rendering via `RenderMapJob` for better scalability

Example:
```php
use App\Plugins\Weathermap\Services\MapRenderService;

$renderService = app(MapRenderService::class);
$result = $renderService->render('mymap.conf');
```

## Backward Compatibility

### Legacy Scripts
The original `public/editor.php` and `public/data-pick.php` scripts remain for backward compatibility but are deprecated. They will be removed in a future major release.

**Status**: ⚠️ **DEPRECATED** - Use new Laravel controllers instead

### Legacy API Endpoints
Direct access to legacy endpoints is still supported:
- `/plugins/Weathermap/editor.php` → redirects to `/plugin/Weathermap/editor`
- `/plugins/Weathermap/check.php` → redirects to `/plugin/Weathermap/check`

**Note**: Static asset paths like `/plugins/Weathermap/output/` remain unchanged.

## Troubleshooting

### Routes Not Found
Ensure you're using `/plugin/` (singular) not `/plugins/` (plural):
```
✓ /plugin/Weathermap/editor
✗ /plugins/Weathermap/editor (this is a static asset path)
```

### Permission Denied
Verify the user has `global-read` permission in their role settings.

### Queue Jobs Not Processing
If using asynchronous rendering:
```bash
# Check queue status
php artisan queue:work

# Configure queue in .env
QUEUE_CONNECTION=database  # or redis, sync, etc.
```

### Maps Not Rendering
- Check config directory is writable: `ls -la app/Plugins/Weathermap/configs/`
- Visit `/plugin/Weathermap/check` to verify PHP extensions
- Review logs: `tail -f storage/logs/laravel.log`

## Directory Structure

```
Weathermap/
├── Http/Controllers/
│   ├── CheckController.php           (new)
│   ├── EditorPageController.php       (new)
│   ├── EditorApiController.php        (new)
│   └── DataPickerController.php       (new)
├── Services/
│   ├── ConfigPathResolver.php         (new)
│   └── MapRenderService.php           (new)
├── Jobs/
│   └── RenderMapJob.php              (new)
├── Console/
│   └── PollMaps.php                  (refactored)
├── resources/views/
│   ├── check.blade.php               (new)
│   ├── editor-start.blade.php        (new)
│   ├── editor-main.blade.php         (new)
│   └── ...
├── public/
│   ├── editor.php                    (deprecated)
│   ├── data-pick.php                 (deprecated)
│   └── check.php                     (deprecated)
└── ...
```

## Timeline for Removal

| Version | Change |
|---------|--------|
| 0.98c   | Current - all scripts functional |
| 0.99    | Legacy scripts marked as deprecated |
| 1.0     | Legacy scripts may be removed |

## Getting Help

- Check the [Installation Guide](docs/pages/install-librenms.html)
- Review the [Configuration Reference](docs/pages/config-reference.html)
- Visit the [FAQ](docs/pages/faq.html)
- Review plugin logs: `tail storage/logs/weathermap.log`
