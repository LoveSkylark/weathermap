# Changelog - Weathermap for LibreNMS

## [0.98c] - 2026-05-05

### ⭐ Major Enhancements

#### Architecture Modernization
- **Complete Laravel Integration**: Plugin now fully leverages LibreNMS's modern Laravel framework
- **Modular Controller Structure**: Separate controllers for check, editor, and API endpoints
- **Service Layer**: New services for path resolution and map rendering with centralized configuration
- **Queue-Based Rendering**: Maps render via Laravel queue jobs instead of subprocesses for better scalability
- **Dependency Injection**: All services use Laravel's container for cleaner, testable code

#### New Components (Phase 1-3 Modernization)
- `Http/Controllers/CheckController.php` - Environment diagnostics
- `Http/Controllers/EditorPageController.php` - Editor UI main page
- `Http/Controllers/EditorApiController.php` - AJAX API endpoints for editor
- `Http/Controllers/DataPickerController.php` - Datasource picker API
- `Services/ConfigPathResolver.php` - Centralized path management
- `Services/MapRenderService.php` - Map rendering abstraction
- `Jobs/RenderMapJob.php` - Queue job for per-map rendering
- `Console/PollMaps.php` - Refactored to use queue jobs (maintains backward compatibility)

#### New Views (Blade Templates)
- `resources/views/check.blade.php` - PHP environment check interface
- `resources/views/editor-start.blade.php` - Map picker/welcome page
- `resources/views/editor-main.blade.php` - Main editor interface shell
- `resources/views/editor-error.blade.php` - Error handling UI

### 🔐 Security Improvements
- **Proper Authentication**: All routes require Laravel authentication
- **Role-Based Access Control**: Access to maps/editor now requires `global-read` capability
- **Parameter Sanitization**: All user inputs sanitized with dedicated helper methods
- **CSRF Protection**: All form submissions protected by Laravel's CSRF middleware

### 📈 Performance Improvements
- **Queue-Based Rendering**: Maps now render via queue jobs for better scalability and error isolation
- **Job Retry Logic**: Failed renders automatically retry up to 3 times
- **Batch Job Support**: Multiple maps can be queued together via `Bus::batch()`
- **Asynchronous Polling**: `weathermap:poll` command supports both sync and async modes

### 🛠️ Developer Experience
- **Centralized Configuration**: `ConfigPathResolver` service eliminates hard-coded paths
- **Testable Services**: Business logic separated from controllers for easier unit testing
- **Better Error Logging**: Comprehensive logging to `storage/logs/laravel.log`
- **Clean API Endpoints**: RESTful JSON APIs for all editor operations

### 📋 Breaking Changes

#### Routing Changes
- **Old**: `/plugins/Weathermap/editor.php?mapname=mymap.conf` (direct PHP script)
- **New**: `/plugin/Weathermap/editor` and `/plugin/Weathermap/editor/mymap.conf` (proper routes)

**Note**: Legacy URLs still work but are deprecated. See [DEPRECATION.md](DEPRECATION.md)

#### Configuration
- Map config directory must be writable by web server (`www-data` user)
- Public output directory location unchanged (`public/plugins/Weathermap/output/`)

### ✨ New Features
- **Environment Check**: Visit `/plugin/Weathermap/check` for styled PHP environment diagnostics
- **Queue-Based Rendering**: Distributed rendering with `php artisan weathermap:poll`
- **Synchronous Fallback**: Use `php artisan weathermap:poll --sync` for testing or small deployments
- **Centralized Path Management**: All paths resolved via `ConfigPathResolver` service

### 🐛 Bug Fixes
- Fixed 500 errors on plugin landing page
- Fixed routing inconsistency between `/plugin/` and `/plugins/` paths
- Fixed permission checks now properly verify access levels
- Fixed temporary file cleanup in rendering process

### 📚 Documentation
- **DEPRECATION.md**: Complete migration guide from legacy to new architecture
- **Updated README.md**: New structure documentation and feature highlights
- **Inline Code Comments**: Comprehensive documentation of new services and controllers

### 🔄 Backward Compatibility

#### Maintained
- All map configuration files (`.conf`) remain unchanged
- RRD, SNMP, fping, and other data sources work identically
- Editor functionality preserved with new UI
- Output files (`PNG`, `HTML`) in same locations

#### Deprecated (Still Working)
- `public/editor.php` - Redirect to new controller-based editor
- `public/data-pick.php` - Redirect to new API endpoint
- `public/check.php` - Redirect to new controller-based checker
- `bin/map-poller` subprocess polling (now via queue jobs)

**Note**: Deprecated scripts will be removed in v1.0. See [DEPRECATION.md](DEPRECATION.md) for migration.

### 🚀 Installation & Upgrade

#### New Installations
```bash
cd /opt/librenms/app/Plugins
git clone https://github.com/librenms-plugins/Weathermap.git Weathermap
cd Weathermap && composer install --no-dev
# Plugin auto-registers via composer.json extra.laravel.providers
```

#### Upgrading from v0.98b
1. Backup your `configs/` directory
2. Pull latest code: `git pull`
3. Run `composer install --no-dev`
4. No database migrations needed
5. Visit `/plugin/Weathermap/check` to verify environment
6. Maps will render automatically via scheduler

No cron job needed - Laravel scheduler handles polling automatically!

### 📊 Technical Details

#### Service Container Registration
Services are registered in `PluginServiceProvider::register()`:
```php
$this->app->singleton(ConfigPathResolver::class);
$this->app->singleton(MapRenderService::class);
```

#### Queue Configuration
Default queue connection uses database. For Redis:
```bash
QUEUE_CONNECTION=redis
# Then: php artisan queue:work
```

#### Routes
All routes under `/plugin/Weathermap/` with middleware:
- `web` - Session, cookies, CSRF
- `auth` - Laravel authentication required

### 🔗 Related Issues
- Resolves plugin not showing with new LibreNMS plugin system
- Fixes 500 errors on plugin landing page
- Improves scalability of map rendering
- Enables proper role-based access control

### 🙏 Acknowledgments
Built on legacy PHP Weathermap v0.98 engine while modernizing the LibreNMS integration layer.

---

## [0.98b] - Previous Version
See git history for earlier changes
