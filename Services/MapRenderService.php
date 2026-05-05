<?php

namespace App\Plugins\Weathermap\Services;

use Weathermap\Map\WeatherMap;
use Exception;

/**
 * MapRenderService - Encapsulates map rendering logic
 * 
 * Provides clean abstraction for rendering individual maps.
 * Handles error cases and output file management.
 */
class MapRenderService
{
    protected $pathResolver;

    public function __construct(ConfigPathResolver $pathResolver)
    {
        $this->pathResolver = $pathResolver;
    }

    /**
     * Render a single map and save output
     * 
     * @param string $mapname The map configuration filename (e.g., 'mymap.conf')
     * @param array $options Rendering options
     * @return array Result status and metadata
     * @throws Exception
     */
    public function render(string $mapname, array $options = []): array
    {
        $mapfile = $this->pathResolver->getMapConfigPath($mapname);
        
        // Verify map file exists
        if (!file_exists($mapfile) || !is_readable($mapfile)) {
            throw new Exception("Map file not found or not readable: $mapfile");
        }

        try {
            // Create map instance
            $map = new WeatherMap();
            
            // Set context based on options
            $context = $options['context'] ?? 'poller';
            $map->context = $context;

            // Read configuration
            $map->ReadConfig($mapfile);

            // Set any runtime options
            if (isset($options['debug'])) {
                $map->debug = $options['debug'];
            }

            // Draw the map (generates output image)
            $map->DrawMap($context);

            // Save output PNG file
            $output_file = $this->pathResolver->getMapOutputPath($mapname, 'png');
            
            // Ensure output directory exists
            $output_dir = dirname($output_file);
            if (!is_dir($output_dir)) {
                @mkdir($output_dir, 0755, true);
            }

            // Write PNG to file
            if (!imagepng($map->image, $output_file)) {
                throw new Exception("Failed to write output PNG: $output_file");
            }

            // Also generate HTML imagemap
            $html_file = $this->pathResolver->getMapOutputPath($mapname, 'html');
            $html_output = $map->MakeHTML();
            
            if (!file_put_contents($html_file, $html_output)) {
                throw new Exception("Failed to write output HTML: $html_file");
            }

            // Clean up image resource
            imagedestroy($map->image);

            return [
                'success' => true,
                'mapname' => $mapname,
                'output_png' => $output_file,
                'output_html' => $html_file,
                'timestamp' => time(),
                'width' => $map->width,
                'height' => $map->height,
                'nodes' => count($map->nodes),
                'links' => count($map->links),
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'mapname' => $mapname,
                'error' => $e->getMessage(),
                'timestamp' => time(),
            ];
        }
    }

    /**
     * Render all maps
     * 
     * @param array $options Rendering options
     * @return array Results for each map
     */
    public function renderAll(array $options = []): array
    {
        $results = [];
        $conf_dir = $this->pathResolver->getConfigDir();
        
        if (!is_dir($conf_dir)) {
            return $results;
        }

        // Find all .conf files
        $files = glob($conf_dir . '/*.conf');
        if (!$files) {
            return $results;
        }

        foreach ($files as $filepath) {
            $mapname = basename($filepath);
            try {
                $result = $this->render($mapname, $options);
                $results[] = $result;
            } catch (Exception $e) {
                $results[] = [
                    'success' => false,
                    'mapname' => $mapname,
                    'error' => $e->getMessage(),
                    'timestamp' => time(),
                ];
            }
        }

        return $results;
    }

    /**
     * Get rendering statistics
     * 
     * @return array Rendering stats (total maps, last render time, etc.)
     */
    public function getStats(): array
    {
        $conf_dir = $this->pathResolver->getConfigDir();
        $output_dir = $this->pathResolver->getOutputDir();
        
        $map_count = 0;
        if (is_dir($conf_dir)) {
            $map_count = count(glob($conf_dir . '/*.conf') ?: []);
        }

        $output_count = 0;
        $last_render = null;
        if (is_dir($output_dir)) {
            $files = glob($output_dir . '/*.png') ?: [];
            $output_count = count($files);
            
            if ($files) {
                $latest = max(array_map('filemtime', $files));
                $last_render = date('Y-m-d H:i:s', $latest);
            }
        }

        return [
            'total_maps' => $map_count,
            'rendered_outputs' => $output_count,
            'last_render' => $last_render,
            'config_dir' => $conf_dir,
            'output_dir' => $output_dir,
        ];
    }
}
