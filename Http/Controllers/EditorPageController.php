<?php

namespace App\Plugins\Weathermap\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;

/**
 * EditorPageController - Main editor interface
 * 
 * Phase 2 approach: Wraps legacy editor.php functionality within Laravel routing.
 * Progressively migrates editor logic to native Laravel controllers/services.
 */
class EditorPageController extends Controller
{
    /**
     * Show editor start page (map picker)
     * GET /plugin/Weathermap/editor
     */
    public function index()
    {
        $plugin_dir = base_path('app/Plugins/Weathermap');
        $conf_dir = $plugin_dir . '/configs';
        $mapdir = $conf_dir;
        
        // Verify directory is writable
        $writable = is_writable($conf_dir);
        
        if (!$writable) {
            return view('Weathermap::editor-error', [
                'error' => 'Configuration directory is not writable: ' . $conf_dir,
            ]);
        }
        
        // Gather list of existing maps
        $maps = [];
        if (is_dir($mapdir)) {
            $files = glob($mapdir . '/*.conf');
            if ($files) {
                foreach ($files as $filepath) {
                    $basename = basename($filepath);
                    $title = '(no title)';
                    
                    // Read first 100 lines to find TITLE
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
                    
                    $readable = is_readable($filepath);
                    $writable_file = is_writable($filepath);
                    
                    $maps[] = [
                        'name' => $basename,
                        'title' => $title,
                        'readable' => $readable,
                        'writable' => $writable_file,
                        'edit_url' => url('plugin/Weathermap/editor/' . urlencode($basename)),
                    ];
                }
            }
        }
        
        return view('Weathermap::editor-start', [
            'maps' => $maps,
            'create_action' => url('plugin/Weathermap/api/editor/new-map'),
        ]);
    }

    /**
     * Show editor for specific map
     * GET /plugin/Weathermap/editor/{map}
     */
    public function show($map)
    {
        // Sanitize map name (prevent directory traversal)
        if (!$this->isValidMapName($map)) {
            return response('Invalid map name', 400);
        }
        
        $plugin_dir = base_path('app/Plugins/Weathermap');
        $conf_dir = $plugin_dir . '/configs';
        $mapfile = $conf_dir . '/' . $map;
        
        // Verify file exists and is readable
        if (!file_exists($mapfile) || !is_readable($mapfile)) {
            return response('Map file not found', 404);
        }
        
        // For Phase 2, we'll render a minimal editor shell that loads editor.js
        // The legacy editor.php handles the actual editing via AJAX
        return view('Weathermap::editor-main', [
            'map' => $map,
            'api_url' => url('plugin/Weathermap/api/editor'),
            'asset_url' => asset('plugins/Weathermap'),
        ]);
    }

    /**
     * Validate map filename
     */
    private function isValidMapName($name): bool
    {
        // Only allow alphanumeric, dash, underscore, and .conf extension
        return preg_match('/^[a-zA-Z0-9_\-]+\.conf$/', $name) === 1;
    }
}
