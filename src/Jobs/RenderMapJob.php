<?php

namespace App\Plugins\Weathermap\Jobs;

use App\Plugins\Weathermap\Services\ConfigPathResolver;
use App\Plugins\Weathermap\Services\MapRenderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * RenderMapJob - Queue job for rendering individual maps
 * 
 * Provides per-map job control, better error handling, and retry logic.
 * Replaces subprocess-based rendering in PollMaps command.
 */
class RenderMapJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $mapname;
    protected $options;

    /**
     * Create a new job instance.
     *
     * @param string $mapname The map configuration filename to render
     * @param array $options Rendering options
     */
    public function __construct(string $mapname, array $options = [])
    {
        $this->mapname = $mapname;
        $this->options = $options;
        
        // Configure job for weathermap rendering
        $this->queue = 'default';
        $this->tries = 3;
        $this->timeout = 300; // 5 minutes timeout
    }

    /**
     * Execute the job.
     *
     * @param ConfigPathResolver $pathResolver
     * @param MapRenderService $renderService
     * @return void
     */
    public function handle(ConfigPathResolver $pathResolver, MapRenderService $renderService)
    {
        Log::info("Rendering map: {$this->mapname}");

        try {
            // Ensure directories exist
            if (!$pathResolver->ensureDirectories()) {
                throw new \Exception('Failed to ensure plugin directories are writable');
            }

            // Render the map
            $result = $renderService->render($this->mapname, $this->options);

            if ($result['success']) {
                Log::info("Successfully rendered map: {$this->mapname}", $result);
            } else {
                Log::error("Failed to render map: {$this->mapname}", $result);
                
                // Fail the job if rendering failed (will retry based on tries)
                throw new \Exception($result['error'] ?? 'Unknown rendering error');
            }
        } catch (\Exception $e) {
            Log::error("Exception rendering map {$this->mapname}: " . $e->getMessage());
            
            // Re-throw to trigger retry logic
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     *
     * @param \Exception $exception
     * @return void
     */
    public function failed(\Exception $exception)
    {
        Log::error("RenderMapJob permanently failed for {$this->mapname}: " . $exception->getMessage());
    }
}
