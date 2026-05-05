<?php

namespace App\Plugins\Weathermap\Http\Controllers;

/**
 * DEPRECATED - EditorApiController
 * 
 * This controller has been split into:
 * - MapConfigController - Configuration operations (get/set config, create/delete maps, list, info)
 * - MapRenderController - Rendering operations (draw maps, font samples)
 * 
 * This file is kept for backward compatibility but should not be used for new development.
 * All endpoints have been migrated to the new controllers.
 * 
 * Migration guide:
 * - POST /plugin/Weathermap/api/editor/new-map → MapConfigController::createMap
 * - GET /plugin/Weathermap/api/editor/font-samples/{map} → MapRenderController::fontSamples
 * - GET /plugin/Weathermap/api/editor/draw/{map} → MapRenderController::draw
 * - GET /plugin/Weathermap/api/editor/config/{map} → MapConfigController::getConfig
 * - POST /plugin/Weathermap/api/editor/config/{map} → MapConfigController::updateConfig
 */

use Illuminate\Routing\Controller;

class EditorApiController extends Controller
{
    public function __call($method, $arguments)
    {
        abort(404, 'EditorApiController has been deprecated and split. Use MapConfigController or MapRenderController instead.');
    }
}
