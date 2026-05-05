<?php

namespace App\Plugins\Weathermap;

use App\Plugins\Weathermap\Console\PollMaps;
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
            Route::redirect('plugin/Weathermap/editor', 'plugins/Weathermap/editor.php')
                ->name('weathermap.editor');
            Route::redirect('plugin/Weathermap/check', 'plugins/Weathermap/check.php')
                ->name('weathermap.check');
        });

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command('weathermap:poll')
                     ->everyFiveMinutes()
                     ->withoutOverlapping()
                     ->runInBackground();
        });
    }
}
