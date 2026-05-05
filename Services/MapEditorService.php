<?php

namespace App\Plugins\Weathermap\Services;

use Weathermap\Map\WeatherMap;
use Exception;

/**
 * MapEditorService - High-level map editing operations
 * 
 * Provides abstraction for map manipulation, validation, and persistence
 */
class MapEditorService
{
    protected $pathResolver;
    protected $sanitizer;
    protected $validator;

    public function __construct(
        ConfigPathResolver $pathResolver,
        EditorSanitizerService $sanitizer,
        EditorValidatorService $validator
    ) {
        $this->pathResolver = $pathResolver;
        $this->sanitizer = $sanitizer;
        $this->validator = $validator;
    }

    /**
     * Create a new map
     */
    public function createMap(string $mapname, string $from_template = null): array
    {
        $mapname = $this->sanitizer->sanitizeConffile($mapname);

        if (empty($mapname)) {
            return [
                'success' => false,
                'error' => 'Invalid map name',
            ];
        }

        $mapfile = $this->pathResolver->getMapConfigPath($mapname);

        if (file_exists($mapfile)) {
            return [
                'success' => false,
                'error' => 'Map already exists',
            ];
        }

        try {
            $map = new WeatherMap();

            // Copy from template if provided
            if ($from_template) {
                $template_name = $this->sanitizer->sanitizeConffile($from_template);
                $template_file = $this->pathResolver->getMapConfigPath($template_name);

                if (file_exists($template_file) && is_readable($template_file)) {
                    $map->ReadConfig($template_file);
                }
            }

            $map->WriteConfig($mapfile);

            return [
                'success' => true,
                'mapname' => $mapname,
                'path' => $mapfile,
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to create map: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Read map configuration
     */
    public function readMap(string $mapname): array
    {
        $mapname = $this->sanitizer->sanitizeConffile($mapname);

        if (empty($mapname)) {
            return [
                'success' => false,
                'error' => 'Invalid map name',
            ];
        }

        $mapfile = $this->pathResolver->getMapConfigPath($mapname);

        if (!file_exists($mapfile) || !is_readable($mapfile)) {
            return [
                'success' => false,
                'error' => 'Map not found or not readable',
            ];
        }

        try {
            $map = new WeatherMap();
            $map->context = 'editor';
            $map->ReadConfig($mapfile);

            return [
                'success' => true,
                'mapname' => $mapname,
                'map' => $map,
                'config' => file_get_contents($mapfile),
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to read map: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Update map configuration
     */
    public function updateMap(string $mapname, string $config): array
    {
        $mapname = $this->sanitizer->sanitizeConffile($mapname);

        if (empty($mapname)) {
            return [
                'success' => false,
                'error' => 'Invalid map name',
            ];
        }

        $mapfile = $this->pathResolver->getMapConfigPath($mapname);

        if (!file_exists($mapfile) || !is_writable($mapfile)) {
            return [
                'success' => false,
                'error' => 'Map not found or not writable',
            ];
        }

        try {
            // Validate by parsing config
            $map = new WeatherMap();
            $map->context = 'editor';

            // Write to temp file for validation
            $tempfile = tempnam(sys_get_temp_dir(), 'wm_');
            file_put_contents($tempfile, $config);
            $map->ReadConfig($tempfile);
            unlink($tempfile);

            // If validation passed, write to actual file
            file_put_contents($mapfile, $config);

            return [
                'success' => true,
                'mapname' => $mapname,
                'message' => 'Map configuration updated successfully',
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Configuration validation failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Delete a map
     */
    public function deleteMap(string $mapname): array
    {
        $mapname = $this->sanitizer->sanitizeConffile($mapname);

        if (empty($mapname)) {
            return [
                'success' => false,
                'error' => 'Invalid map name',
            ];
        }

        $mapfile = $this->pathResolver->getMapConfigPath($mapname);

        if (!file_exists($mapfile)) {
            return [
                'success' => false,
                'error' => 'Map not found',
            ];
        }

        try {
            if (!unlink($mapfile)) {
                return [
                    'success' => false,
                    'error' => 'Failed to delete map file',
                ];
            }

            return [
                'success' => true,
                'mapname' => $mapname,
                'message' => 'Map deleted successfully',
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to delete map: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * List all available maps
     */
    public function listMaps(): array
    {
        $maps = [];
        $conf_dir = $this->pathResolver->getConfigDir();

        if (!is_dir($conf_dir)) {
            return [];
        }

        $files = glob($conf_dir . '/*.conf');
        if (!$files) {
            return [];
        }

        foreach ($files as $filepath) {
            $basename = basename($filepath);
            $title = '(no title)';
            $readable = is_readable($filepath);
            $writable = is_writable($filepath);

            if ($readable) {
                $fd = fopen($filepath, 'r');
                if ($fd) {
                    $count = 0;
                    while (!feof($fd) && $count < 100) {
                        $line = fgets($fd, 4096);
                        if (preg_match('/^\s*TITLE\s+(.*)/i', $line, $matches)) {
                            $title = trim($matches[1]);
                            break;
                        }
                        $count++;
                    }
                    fclose($fd);
                }
            }

            $maps[] = [
                'name' => $basename,
                'title' => $title,
                'readable' => $readable,
                'writable' => $writable,
                'path' => $filepath,
            ];
        }

        usort($maps, fn($a, $b) => strcmp($a['name'], $b['name']));
        return $maps;
    }

    /**
     * Get map metadata without loading full config
     */
    public function getMapInfo(string $mapname): array
    {
        $mapname = $this->sanitizer->sanitizeConffile($mapname);

        if (empty($mapname)) {
            return [
                'success' => false,
                'error' => 'Invalid map name',
            ];
        }

        $mapfile = $this->pathResolver->getMapConfigPath($mapname);

        if (!file_exists($mapfile)) {
            return [
                'success' => false,
                'error' => 'Map not found',
            ];
        }

        $title = '(no title)';
        $width = null;
        $height = null;
        $background = null;

        $fd = fopen($mapfile, 'r');
        if ($fd) {
            $count = 0;
            while (!feof($fd) && $count < 100) {
                $line = fgets($fd, 4096);
                if (preg_match('/^\s*TITLE\s+(.*)/i', $line, $matches)) {
                    $title = trim($matches[1]);
                }
                if (preg_match('/^\s*WIDTH\s+(\d+)/i', $line, $matches)) {
                    $width = intval($matches[1]);
                }
                if (preg_match('/^\s*HEIGHT\s+(\d+)/i', $line, $matches)) {
                    $height = intval($matches[1]);
                }
                if (preg_match('/^\s*BACKGROUND\s+(.*)/i', $line, $matches)) {
                    $background = trim($matches[1]);
                }
                $count++;
            }
            fclose($fd);
        }

        return [
            'success' => true,
            'mapname' => $mapname,
            'title' => $title,
            'width' => $width,
            'height' => $height,
            'background' => $background,
            'readable' => is_readable($mapfile),
            'writable' => is_writable($mapfile),
            'path' => $mapfile,
        ];
    }
}
