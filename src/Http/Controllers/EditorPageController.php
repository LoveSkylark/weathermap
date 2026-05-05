<?php

namespace App\Plugins\Weathermap\Http\Controllers;

use App\Plugins\Weathermap\Services\ConfigPathResolver;
use App\Plugins\Weathermap\Services\MapEditorService;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;

/**
 * EditorPageController - Main editor interface
 * 
 * Provides editor UI with map picker and editor shell.
 * Delegates to MapEditorService for map operations.
 */
class EditorPageController extends Controller
{
    protected $configPathResolver;
    protected $mapEditorService;

    public function __construct(ConfigPathResolver $configPathResolver, MapEditorService $mapEditorService)
    {
        $this->configPathResolver = $configPathResolver;
        $this->mapEditorService = $mapEditorService;
    }

    /**
     * Show editor start page (map picker)
     * GET /plugin/Weathermap/editor
     */
    public function index()
    {
        $conf_dir = $this->configPathResolver->getConfigDir();
        
        // Verify directory is writable
        if (!is_writable($conf_dir)) {
            return view('Weathermap::editor-error', [
                'error' => 'Configuration directory is not writable: ' . $conf_dir,
            ]);
        }
        
        try {
            // Get list of existing maps with metadata
            $maplist = $this->mapEditorService->listMaps();
            $maps = [];

            foreach ($maplist as $mapname => $title) {
                $mapfile = $this->configPathResolver->getMapConfigPath($mapname);
                $maps[] = [
                    'name' => $mapname,
                    'title' => $title,
                    'readable' => is_readable($mapfile),
                    'writable' => is_writable($mapfile),
                    'edit_url' => url('plugin/Weathermap/editor/' . urlencode($mapname)),
                ];
            }

            return view('Weathermap::editor-start', [
                'maps' => $maps,
                'create_action' => url('plugin/Weathermap/api/editor/new-map'),
            ]);
        } catch (\Exception $e) {
            return view('Weathermap::editor-error', [
                'error' => 'Error loading maps: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Show editor for specific map
     * GET /plugin/Weathermap/editor/{map}
     */
    public function show($map)
    {
        // Validate map name
        if (!$this->isValidMapName($map)) {
            return response('Invalid map name', 400);
        }
        
        try {
            // Verify map exists
            $mapinfo = $this->mapEditorService->getMapInfo($map);
            if (!$mapinfo || empty($mapinfo['exists'])) {
                return response('Map file not found', 404);
            }
            
            // Render editor shell
            return view('Weathermap::editor-main', [
                'map' => $map,
                'api_url' => url('plugin/Weathermap/api/editor'),
                'asset_url' => asset('plugins/Weathermap'),
            ]);
        } catch (\Exception $e) {
            return response('Error loading map: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Validate map filename
     */
    private function isValidMapName($name): bool
    {
        return preg_match('/^[a-zA-Z0-9_\-]+\.conf$/', $name) === 1;
    }
}
