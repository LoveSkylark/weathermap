<?php

namespace App\Plugins\Weathermap\Http\Controllers;

use App\Plugins\Weathermap\Services\MapEditorService;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * MapConfigController - Handle map configuration operations
 * 
 * Extracted from EditorApiController
 * Handles configuration file read/write/create operations
 */
class MapConfigController extends Controller
{
    protected $editorService;

    public function __construct(MapEditorService $editorService)
    {
        $this->editorService = $editorService;
    }

    /**
     * Create a new map
     * POST /plugin/Weathermap/api/editor/new-map
     */
    public function createMap(Request $request): JsonResponse
    {
        $mapname = $request->input('mapname', '');
        $sourcemap = $request->input('sourcemap', null);

        $result = $this->editorService->createMap($mapname, $sourcemap);

        if (!$result['success']) {
            return response()->json($result, 400);
        }

        return response()->json([
            ...$result,
            'edit_url' => url('plugin/Weathermap/editor/' . urlencode($result['mapname'])),
        ]);
    }

    /**
     * Get map configuration
     * GET /plugin/Weathermap/api/editor/config/{map}
     */
    public function getConfig($map): JsonResponse
    {
        $result = $this->editorService->readMap($map);

        if (!$result['success']) {
            return response()->json($result, 404);
        }

        return response()->json([
            'success' => true,
            'mapname' => $map,
            'config' => $result['config'],
        ]);
    }

    /**
     * Update map configuration
     * POST /plugin/Weathermap/api/editor/config/{map}
     */
    public function updateConfig($map, Request $request): JsonResponse
    {
        $config = $request->input('config', '');

        if (empty($config)) {
            return response()->json(['error' => 'Configuration is empty'], 400);
        }

        $result = $this->editorService->updateMap($map, $config);

        if (!$result['success']) {
            return response()->json($result, 400);
        }

        return response()->json($result);
    }

    /**
     * List all maps
     * GET /plugin/Weathermap/api/editor/list
     */
    public function listMaps(): JsonResponse
    {
        $maps = $this->editorService->listMaps();

        return response()->json([
            'success' => true,
            'maps' => $maps,
            'count' => count($maps),
        ]);
    }

    /**
     * Get map metadata
     * GET /plugin/Weathermap/api/editor/info/{map}
     */
    public function getMapInfo($map): JsonResponse
    {
        $result = $this->editorService->getMapInfo($map);

        if (!$result['success']) {
            return response()->json($result, 404);
        }

        return response()->json($result);
    }

    /**
     * Delete a map
     * DELETE /plugin/Weathermap/api/editor/map/{map}
     */
    public function deleteMap($map): JsonResponse
    {
        $result = $this->editorService->deleteMap($map);

        if (!$result['success']) {
            return response()->json($result, 400);
        }

        return response()->json($result);
    }
}
