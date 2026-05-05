<?php

namespace App\Plugins\Weathermap\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * DataPickerController - AJAX endpoint for datasource selection
 * 
 * Phase 2 approach: Replaces legacy public/data-pick.php
 * Provides datasource picker UI via AJAX for editor
 */
class DataPickerController extends Controller
{
    /**
     * Show datasource picker as JSON
     * GET /plugin/Weathermap/api/data-picker
     * POST /plugin/Weathermap/api/data-picker
     */
    public function __invoke(Request $request): JsonResponse
    {
        $type = $request->input('type', '');
        $query = $request->input('query', '');
        
        $results = [];
        
        switch ($type) {
            case 'rrd':
                $results = $this->searchRrdFiles($query);
                break;
            case 'snmp':
                $results = $this->searchSnmpDevices($query);
                break;
            case 'fping':
                $results = $this->getFpingHosts();
                break;
            default:
                return response()->json(['error' => 'Unknown datasource type'], 400);
        }
        
        return response()->json([
            'type' => $type,
            'query' => $query,
            'results' => $results,
        ]);
    }

    /**
     * Search for RRD files
     */
    protected function searchRrdFiles($query): array
    {
        $results = [];
        
        // Try to find RRD files in common locations
        $rrd_paths = [
            '/opt/librenms/rra',
            '/var/lib/librenms/rra',
            '/opt/cacti/rra',
            '/var/www/cacti/rra',
        ];
        
        foreach ($rrd_paths as $path) {
            if (!is_dir($path)) continue;
            
            // Search for matching RRD files
            $pattern = $path . '/*' . $query . '*.rrd';
            $files = glob($pattern);
            
            if ($files) {
                foreach ($files as $file) {
                    $relative = str_replace($path . '/', '', $file);
                    $results[] = [
                        'type' => 'rrd',
                        'path' => 'rrd:' . $relative,
                        'label' => $relative,
                        'value' => 'rrd:' . $relative,
                    ];
                }
            }
        }
        
        return array_slice($results, 0, 50); // Limit to 50 results
    }

    /**
     * Search for SNMP devices
     */
    protected function searchSnmpDevices($query): array
    {
        $results = [];
        
        try {
            // Try to query LibreNMS devices if available
            if (function_exists('app')) {
                // This is a placeholder - would integrate with LibreNMS device API
                // For now, return empty results
            }
        } catch (\Exception $e) {
            // Silently fail
        }
        
        return $results;
    }

    /**
     * Get fping hosts
     */
    protected function getFpingHosts(): array
    {
        $results = [];
        
        // Placeholder for fping host list
        // This would be populated from LibreNMS devices or user configuration
        
        return $results;
    }
}
