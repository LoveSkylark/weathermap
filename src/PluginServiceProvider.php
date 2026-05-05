<?php

namespace App\Plugins\Weathermap;

use App\Plugins\Weathermap\Console\PollMaps;
use App\Plugins\Weathermap\Http\Controllers\CheckController;
use App\Plugins\Weathermap\Http\Controllers\EditorPageController;
use App\Plugins\Weathermap\Http\Controllers\EditorApiController;
use App\Plugins\Weathermap\Http\Controllers\DataPickerController;
use App\Plugins\Weathermap\Services\ConfigPathResolver;
use App\Plugins\Weathermap\Services\MapRenderService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class PluginServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register services
        $this->app->singleton(ConfigPathResolver::class, function () {
            return new ConfigPathResolver();
        });

        $this->app->singleton(MapRenderService::class, function ($app) {
            return new MapRenderService($app->make(ConfigPathResolver::class));
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
            
            // Editor API endpoints
            Route::prefix('plugin/Weathermap/api/editor')->group(function (): void {
                Route::post('new-map', [EditorApiController::class, 'newMap'])
                    ->name('weathermap.api.newmap');
                Route::get('font-samples/{map}', [EditorApiController::class, 'fontSamples'])
                    ->name('weathermap.api.fontsamples');
                Route::get('draw/{map}', [EditorApiController::class, 'draw'])
                    ->name('weathermap.api.draw');
                Route::get('config/{map}', [EditorApiController::class, 'getConfig'])
                    ->name('weathermap.api.config.get');
                Route::post('config/{map}', [EditorApiController::class, 'updateConfig'])
                    ->name('weathermap.api.config.post');
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
