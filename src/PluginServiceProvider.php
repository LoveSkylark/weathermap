<?php

namespace App\Plugins\Weathermap;

use App\Plugins\Weathermap\Console\Commands\PollMaps;
use App\Plugins\Weathermap\Http\Controllers\CheckController;
use App\Plugins\Weathermap\Http\Controllers\EditorPageController;
use App\Plugins\Weathermap\Http\Controllers\MapConfigController;
use App\Plugins\Weathermap\Http\Controllers\MapRenderController;
use App\Plugins\Weathermap\Http\Controllers\DataPickerController;
use App\Plugins\Weathermap\Services\ConfigPathResolver;
use App\Plugins\Weathermap\Services\MapRenderService;
use App\Plugins\Weathermap\Services\EditorSanitizerService;
use App\Plugins\Weathermap\Services\EditorValidatorService;
use App\Plugins\Weathermap\Services\GeometryHelperService;
use App\Plugins\Weathermap\Services\EditorUIService;
use App\Plugins\Weathermap\Services\MapEditorService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class PluginServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register core path and rendering services
        $this->app->singleton(ConfigPathResolver::class, function () {
            return new ConfigPathResolver();
        });

        $this->app->singleton(MapRenderService::class, function ($app) {
            return new MapRenderService($app->make(ConfigPathResolver::class));
        });

        // Register editor-related services
        $this->app->singleton(EditorSanitizerService::class, function () {
            return new EditorSanitizerService();
        });

        $this->app->singleton(EditorValidatorService::class, function () {
            return new EditorValidatorService();
        });

        $this->app->singleton(GeometryHelperService::class, function () {
            return new GeometryHelperService();
        });

        $this->app->singleton(EditorUIService::class, function ($app) {
            return new EditorUIService($app->make(ConfigPathResolver::class));
        });

        $this->app->singleton(MapEditorService::class, function ($app) {
            return new MapEditorService(
                $app->make(ConfigPathResolver::class),
                $app->make(EditorSanitizerService::class),
                $app->make(EditorValidatorService::class)
            );
        });

        // Register commands
        $this->commands([PollMaps::class]);
    }

    public function boot(): void
    {
        Route::middleware(['web', 'auth'])->group(function (): void {
            // Check endpoint - environment diagnostics
            Route::get('plugin/Weathermap/check', CheckController::class)
                ->name('weathermap.check');
            
            // Editor endpoints
            Route::get('plugin/Weathermap/editor', [EditorPageController::class, 'index'])
                ->name('weathermap.editor');
            Route::get('plugin/Weathermap/editor/{map}', [EditorPageController::class, 'show'])
                ->name('weathermap.editor.map');
            
            // Map Configuration API endpoints
            Route::prefix('plugin/Weathermap/api/editor')->group(function (): void {
                Route::post('new-map', [MapConfigController::class, 'createMap'])
                    ->name('weathermap.api.newmap');
                Route::get('list', [MapConfigController::class, 'listMaps'])
                    ->name('weathermap.api.list');
                Route::get('info/{map}', [MapConfigController::class, 'getMapInfo'])
                    ->name('weathermap.api.info');
                Route::get('config/{map}', [MapConfigController::class, 'getConfig'])
                    ->name('weathermap.api.config.get');
                Route::post('config/{map}', [MapConfigController::class, 'updateConfig'])
                    ->name('weathermap.api.config.post');
                Route::delete('map/{map}', [MapConfigController::class, 'deleteMap'])
                    ->name('weathermap.api.map.delete');
            });
            
            // Map Rendering API endpoints
            Route::prefix('plugin/Weathermap/api/render')->group(function (): void {
                Route::get('font-samples/{map}', [MapRenderController::class, 'fontSamples'])
                    ->name('weathermap.api.fontsamples');
                Route::get('draw/{map}', [MapRenderController::class, 'draw'])
                    ->name('weathermap.api.draw');
            });
            
            // Data picker API endpoint
            Route::match(['get', 'post'], 'plugin/Weathermap/api/data-picker', DataPickerController::class)
                ->name('weathermap.api.datapicker');
        });

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command('weathermap:poll')
                     ->everyFiveMinutes()
                     ->withoutOverlapping()
                     ->runInBackground();
        });
    }
}
