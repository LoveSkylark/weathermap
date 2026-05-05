<?php

namespace App\Plugins\Weathermap;

use App\Plugins\Weathermap\Console\PollMaps;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;

class PluginServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->commands([PollMaps::class]);
    }

    public function boot(): void
    {
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command('weathermap:poll')
                     ->everyFiveMinutes()
                     ->withoutOverlapping()
                     ->runInBackground();
        });
    }
}
