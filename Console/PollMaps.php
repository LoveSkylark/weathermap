<?php

namespace App\Plugins\Weathermap\Console;

use App\Plugins\Weathermap\Jobs\RenderMapJob;
use App\Plugins\Weathermap\Services\ConfigPathResolver;
use App\Plugins\Weathermap\Services\MapRenderService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;

class PollMaps extends Command
{
    protected $signature   = 'weathermap:poll {--debug : Enable debug output} {--sync : Run synchronously instead of queuing}';
    protected $description = 'Run Weathermap poller — renders all configured maps to PNG/HTML';

    public function handle(ConfigPathResolver $pathResolver, MapRenderService $renderService): int
    {
        $this->info('Starting Weathermap polling...');

        try {
            // Ensure all directories are set up
            if (!$pathResolver->ensureDirectories()) {
                $this->error('Failed to ensure plugin directories are writable');
                return self::FAILURE;
            }

            // Get list of maps to render
            $conf_dir = $pathResolver->getConfigDir();
            if (!is_dir($conf_dir)) {
                $this->error("Config directory not found: $conf_dir");
                return self::FAILURE;
            }

            $mapfiles = glob($conf_dir . '/*.conf');
            if (!$mapfiles) {
                $this->info('No map files found');
                return self::SUCCESS;
            }

            $mapcount = count($mapfiles);
            $this->info("Found $mapcount map(s) to render");

            // Prepare rendering options
            $options = [];
            if ($this->option('debug')) {
                $options['debug'] = true;
            }

            if ($this->option('sync')) {
                // Synchronous rendering (for testing or small deployments)
                $this->info('Running synchronously...');
                return $this->renderSync($renderService, $mapfiles, $options);
            } else {
                // Queue-based rendering (production recommended)
                $this->info('Dispatching render jobs to queue...');
                return $this->renderQueued($mapfiles, $options);
            }
        } catch (\Exception $e) {
            $this->error('Error during polling: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    /**
     * Render maps synchronously (blocking)
     */
    private function renderSync(MapRenderService $renderService, array $mapfiles, array $options): int
    {
        $bar = $this->output->createProgressBar(count($mapfiles));
        $bar->start();

        $success_count = 0;
        $failed_count = 0;

        foreach ($mapfiles as $filepath) {
            $mapname = basename($filepath);

            try {
                $result = $renderService->render($mapname, $options);

                if ($result['success']) {
                    $success_count++;
                    $this->getOutput()->writeln("<info>\n✓</info> $mapname");
                } else {
                    $failed_count++;
                    $this->getOutput()->writeln("<error>\n✗</error> $mapname: {$result['error']}");
                }
            } catch (\Exception $e) {
                $failed_count++;
                $this->getOutput()->writeln("<error>\n✗</error> $mapname: {$e->getMessage()}");
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        $this->info("Rendering complete: $success_count succeeded, $failed_count failed");

        return $failed_count > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Render maps using job queue (asynchronous)
     */
    private function renderQueued(array $mapfiles, array $options): int
    {
        $jobs = [];

        foreach ($mapfiles as $filepath) {
            $mapname = basename($filepath);
            $jobs[] = new RenderMapJob($mapname, $options);
        }

        // Dispatch all jobs to queue
        try {
            Bus::batch($jobs)
                ->then(function () {
                    $this->info('All maps have been queued for rendering');
                })
                ->catch(function () {
                    $this->warn('Some maps failed during rendering (check logs)');
                })
                ->finally(function () {
                    $this->info('Batch job complete');
                })
                ->dispatch();

            $this->info("Successfully queued " . count($jobs) . " map(s) for rendering");
            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to queue render jobs: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
