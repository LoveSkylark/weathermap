<?php

/**
 * Legacy Configuration File
 * 
 * This file is maintained for backward compatibility with standalone Weathermap usage.
 * New code should use the ConfigPathResolver service for path management.
 * 
 * See: App\Plugins\Weathermap\Services\ConfigPathResolver
 */

// Absolute paths based on this file's location (works when included from any subdirectory).
$plugin_dir = __DIR__;
$conf_dir = $plugin_dir . '/configs';
$mapdir = $conf_dir . '/';

// LibreNMS root: app/Plugins/Weathermap is 3 levels inside the LibreNMS tree.
$librenms_base = dirname($plugin_dir, 3);
$ignore_librenms = false;
$configerror = '';

// Editor overlay settings (changed via the Editor Settings dialog).
$use_overlay = false;
$use_relative_overlay = false;
$grid_snap_value = 0;

// Base href for generated map HTML files (overlib.js, images/, etc.).
// The symlink {librenms}/public/plugins/Weathermap -> {plugin}/public/ must exist.
$basehref = '/plugins/Weathermap/';

// rrdtool binary path — overridden at runtime from \LibreNMS\Config::get('rrdtool') when available.
$rrdtool = '/usr/bin/rrdtool';


