<?php

namespace App\Plugins\Weathermap\Services;

/**
 * ConfigPathResolver - Centralized path configuration
 * 
 * Eliminates hard-coded path assumptions throughout the plugin.
 * Provides consistent path resolution for plugin directories and resources.
 */
class ConfigPathResolver
{
    protected $plugin_dir;
    protected $conf_dir;
    protected $output_dir;
    protected $cache_dir;
    protected $lib_dir;

    public function __construct()
    {
        // Base plugin directory in LibreNMS
        $this->plugin_dir = base_path('app/Plugins/Weathermap');
        
        // Configuration directory (writable by web server)
        $this->conf_dir = $this->plugin_dir . '/configs';
        
        // Output directory for generated maps
        $this->output_dir = public_path('plugins/Weathermap/output');
        
        // Cache directory for temporary data
        $this->cache_dir = storage_path('weathermap');
        
        // Library directory containing core Weathermap engine
        $this->lib_dir = $this->plugin_dir . '/lib';
    }

    /**
     * Get the main plugin directory
     */
    public function getPluginDir(): string
    {
        return $this->plugin_dir;
    }

    /**
     * Get the configuration directory (where .conf files are stored)
     */
    public function getConfigDir(): string
    {
        return $this->conf_dir;
    }

    /**
     * Get the configuration file path for a specific map
     */
    public function getMapConfigPath(string $mapname): string
    {
        return $this->conf_dir . '/' . $this->sanitizeMapname($mapname);
    }

    /**
     * Get the output directory (where rendered PNGs are stored)
     */
    public function getOutputDir(): string
    {
        return $this->output_dir;
    }

    /**
     * Get the output file path for a specific map
     */
    public function getMapOutputPath(string $mapname, string $ext = 'png'): string
    {
        $basename = pathinfo($this->sanitizeMapname($mapname), PATHINFO_FILENAME);
        return $this->output_dir . '/' . $basename . '.' . $ext;
    }

    /**
     * Get the cache directory
     */
    public function getCacheDir(): string
    {
        return $this->cache_dir;
    }

    /**
     * Get the library directory
     */
    public function getLibDir(): string
    {
        return $this->lib_dir;
    }

    /**
     * Get the public web path for assets
     */
    public function getAssetPath($resource = ''): string
    {
        $base = '/plugins/Weathermap';
        return $resource ? $base . '/' . ltrim($resource, '/') : $base;
    }

    /**
     * Get the asset URL for web use
     */
    public function getAssetUrl($resource = ''): string
    {
        return asset($this->getAssetPath($resource));
    }

    /**
     * Ensure all necessary directories exist and are writable
     */
    public function ensureDirectories(): bool
    {
        $dirs = [
            $this->conf_dir,
            $this->output_dir,
            $this->cache_dir,
        ];

        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                if (!@mkdir($dir, 0755, true)) {
                    return false;
                }
            }
            if (!is_writable($dir)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Sanitize map filename (prevent directory traversal)
     */
    private function sanitizeMapname(string $name): string
    {
        // Remove path separators
        $name = str_replace(['/', '\\', '..'], '', $name);
        // Only allow alphanumeric, dash, underscore, and .conf extension
        if (preg_match('/^[a-zA-Z0-9_\-]+\.conf$/', $name)) {
            return $name;
        }
        return 'map.conf';
    }
}
