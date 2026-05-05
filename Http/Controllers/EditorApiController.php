<?php

namespace App\Plugins\Weathermap\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * EditorApiController - AJAX endpoints for editor operations
 * 
 * Phase 2 approach: Provides JSON/image API for editor actions
 * Delegates to legacy editor functions while progressively wrapping in services
 */
class EditorApiController extends Controller
{
    protected $plugin_dir;
    protected $conf_dir;
    protected $mapdir;

    public function __construct()
    {
        $this->plugin_dir = base_path('app/Plugins/Weathermap');
        $this->conf_dir = $this->plugin_dir . '/configs';
        $this->mapdir = $this->conf_dir;
    }

    /**
     * Create a new map
     * POST /plugin/Weathermap/api/editor/new-map
     */
    public function newMap(Request $request): JsonResponse
    {
        $mapname = $request->input('mapname', '');
        $sourcemap = $request->input('sourcemap', null);
        
        // Sanitize the map name
        $mapname = $this->sanitizeConffile($mapname);
        
        if (empty($mapname) || !str_ends_with($mapname, '.conf')) {
            return response()->json(['error' => 'Invalid map name. Must end in .conf'], 400);
        }
        
        $mapfile = $this->mapdir . '/' . $mapname;
        
        // Check if file already exists
        if (file_exists($mapfile)) {
            return response()->json(['error' => 'Map file already exists'], 409);
        }
        
        try {
            // Create new map
            $map = new \Weathermap\Map\WeatherMap();
            
            if ($sourcemap) {
                $sourcemapname = $this->sanitizeConffile($sourcemap);
                $sourcemapfile = $this->mapdir . '/' . $sourcemapname;
                
                if (file_exists($sourcemapfile) && is_readable($sourcemapfile)) {
                    $map->ReadConfig($sourcemapfile);
                }
            }
            
            $map->WriteConfig($mapfile);
            
            return response()->json([
                'success' => true,
                'mapname' => $mapname,
                'edit_url' => url('plugin/Weathermap/editor/' . urlencode($mapname)),
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to create map: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get font samples as image
     * GET /plugin/Weathermap/api/editor/font-samples/{map}
     */
    public function fontSamples($map): Response
    {
        if (!$this->isValidMapName($map)) {
            return response('Invalid map name', 400);
        }
        
        $mapfile = $this->mapdir . '/' . $map;
        if (!file_exists($mapfile) || !is_readable($mapfile)) {
            return response('Map not found', 404);
        }
        
        try {
            $map_obj = new \Weathermap\Map\WeatherMap();
            $map_obj->ReadConfig($mapfile);
            
            // Create font samples image
            $im = $this->generateFontSamples($map_obj);
            
            ob_start();
            imagepng($im);
            $image_data = ob_get_clean();
            imagedestroy($im);
            
            return response($image_data)
                ->header('Content-Type', 'image/png')
                ->header('Cache-Control', 'no-cache, must-revalidate');
        } catch (\Exception $e) {
            return response('Error generating font samples: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Draw/render the map
     * GET /plugin/Weathermap/api/editor/draw/{map}
     */
    public function draw($map): Response
    {
        if (!$this->isValidMapName($map)) {
            return response('Invalid map name', 400);
        }
        
        $mapfile = $this->mapdir . '/' . $map;
        if (!file_exists($mapfile) || !is_readable($mapfile)) {
            return response('Map not found', 404);
        }
        
        try {
            $map_obj = new \Weathermap\Map\WeatherMap();
            $map_obj->ReadConfig($mapfile);
            $map_obj->DrawMap('editor');
            
            ob_start();
            imagepng($map_obj->image);
            $image_data = ob_get_clean();
            
            return response($image_data)
                ->header('Content-Type', 'image/png')
                ->header('Cache-Control', 'no-cache, must-revalidate');
        } catch (\Exception $e) {
            return response('Error drawing map: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get map configuration (for editor form)
     * GET /plugin/Weathermap/api/editor/config/{map}
     */
    public function getConfig($map): JsonResponse
    {
        if (!$this->isValidMapName($map)) {
            return response()->json(['error' => 'Invalid map name'], 400);
        }
        
        $mapfile = $this->mapdir . '/' . $map;
        if (!file_exists($mapfile) || !is_readable($mapfile)) {
            return response()->json(['error' => 'Map not found'], 404);
        }
        
        try {
            $config = file_get_contents($mapfile);
            
            return response()->json([
                'success' => true,
                'mapname' => $map,
                'config' => $config,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error reading config: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Update map configuration
     * POST /plugin/Weathermap/api/editor/config/{map}
     */
    public function updateConfig($map, Request $request): JsonResponse
    {
        if (!$this->isValidMapName($map)) {
            return response()->json(['error' => 'Invalid map name'], 400);
        }
        
        $mapfile = $this->mapdir . '/' . $map;
        if (!file_exists($mapfile) || !is_writable($mapfile)) {
            return response()->json(['error' => 'Map file not writable'], 403);
        }
        
        $config = $request->input('config', '');
        
        try {
            // Validate config by parsing it with WeatherMap
            $map_obj = new \Weathermap\Map\WeatherMap();
            $map_obj->context = 'editor';
            
            // Write to temporary file first to validate
            $tempfile = tempnam(sys_get_temp_dir(), 'wm_');
            file_put_contents($tempfile, $config);
            $map_obj->ReadConfig($tempfile);
            
            // If validation passed, write to actual file
            file_put_contents($mapfile, $config);
            unlink($tempfile);
            
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Config validation failed: ' . $e->getMessage()], 400);
        }
    }

    // Helper methods

    /**
     * Sanitize config filename (prevent directory traversal)
     */
    protected function sanitizeConffile($filename): string
    {
        // Remove path separators and parent directory references
        $filename = str_replace(['/', '\\', '..'], '', $filename);
        // Only allow alphanumeric, dash, underscore, and .conf extension
        if (preg_match('/^[a-zA-Z0-9_\-]+\.conf$/', $filename)) {
            return $filename;
        }
        return '';
    }

    /**
     * Validate map filename
     */
    protected function isValidMapName($name): bool
    {
        return preg_match('/^[a-zA-Z0-9_\-]+\.conf$/', $name) === 1;
    }

    /**
     * Generate font samples image
     */
    protected function generateFontSamples($map): \GdImage
    {
        $keyfont = 2;
        $keyheight = imagefontheight($keyfont) + 2;
        $sampleheight = 32;
        
        $im = imagecreate(2000, $sampleheight);
        $imkey = imagecreate(2000, $keyheight);
        
        $white = imagecolorallocate($im, 255, 255, 255);
        $black = imagecolorallocate($im, 0, 0, 0);
        $whitekey = imagecolorallocate($imkey, 255, 255, 255);
        $blackkey = imagecolorallocate($imkey, 0, 0, 0);
        
        $x = 3;
        $fonts = $map->fonts ?? [];
        ksort($fonts);
        
        foreach ($fonts as $fontnumber => $font) {
            $string = "Abc123%";
            $keystring = "Font $fontnumber";
            list($width, $height) = $map->myimagestringsize($fontnumber, $string);
            list($kwidth, $kheight) = $map->myimagestringsize($keyfont, $keystring);
            
            if ($kwidth > $width) $width = $kwidth;
            
            $y = ($sampleheight / 2) + $height / 2;
            $map->myimagestring($im, $fontnumber, $x, $y, $string, $black);
            $map->myimagestring($imkey, $keyfont, $x, $keyheight, "Font $fontnumber", $blackkey);
            
            $x = $x + $width + 6;
        }
        
        $im2 = imagecreate($x, $sampleheight + $keyheight);
        imagecopy($im2, $im, 0, 0, 0, 0, $x, $sampleheight);
        imagecopy($im2, $imkey, 0, $sampleheight, 0, 0, $x, $keyheight);
        imagedestroy($im);
        imagedestroy($imkey);
        
        return $im2;
    }
}
