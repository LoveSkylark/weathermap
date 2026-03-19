<?php

namespace App\Plugins\Weathermap\Console;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class PollMaps extends Command
{
    protected $signature   = 'weathermap:poll {--debug : Enable debug output}';
    protected $description = 'Run Weathermap poller — renders all configured maps to PNG/HTML';

    public function handle(): int
    {
        $pollerScript = base_path('app/Plugins/Weathermap/bin/map-poller');

        if (! file_exists($pollerScript)) {
            $this->error("map-poller not found at $pollerScript");
            return self::FAILURE;
        }

        $cmd = [PHP_BINARY, $pollerScript];
        if ($this->option('debug')) {
            $cmd[] = '-d';
        }

        $process = new Process($cmd);
        $process->setTimeout(300);
        $process->run(function (string $type, string $buffer): void {
            $this->getOutput()->write($buffer);
        });

        return $process->isSuccessful() ? self::SUCCESS : self::FAILURE;
    }
}
