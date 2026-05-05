<?php

namespace App\Plugins\Weathermap;

use App\Plugins\Weathermap\Console\PollMaps;
use App\Plugins\Weathermap\Http\Controllers\CheckController;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class PluginServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->commands([PollMaps::class]);
    }

    public function boot(): void
    {
        Route::middleware(['web', 'auth'])->group(function (): void {
            // Check endpoint - environment diagnostics
            Route::get('plugin/Weathermap/check', CheckController::class)
                ->name('weathermap.check');
            
            // Editor redirect (temporary - will be replaced with controller in Phase 2)
            Route::redirect('plugin/Weathermap/editor', 'plugins/Weathermap/editor.php')
                ->name('weathermap.editor');
        });

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command('weathermap:poll')
                     ->everyFiveMinutes()
                     ->withoutOverlapping()
                     ->runInBackground();
        });
    }
}
