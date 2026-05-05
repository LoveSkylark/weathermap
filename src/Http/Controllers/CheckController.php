<?php

namespace App\Plugins\Weathermap\Http\Controllers;

use Illuminate\Routing\Controller;

class CheckController extends Controller
{
    public function __invoke()
    {
        // Collect PHP environment diagnostics
        $diagnostics = $this->gatherDiagnostics();
        
        return view('Weathermap::check', $diagnostics);
    }

    /**
     * Gather PHP environment diagnostics for display
     */
    private function gatherDiagnostics(): array
    {
        $php_version = phpversion();
        $mem_allowed = ini_get("memory_limit");
        $php_os = php_uname();
        
        $mem_warning = "";
        $mem_allowed_int = $this->returnBytes($mem_allowed);
        if (($mem_allowed_int > 0) && ($mem_allowed_int < 32000000)) {
            $mem_warning = 'You should increase this value to at least 32M.';
        }
        
        // Capture the PHP "General Info" table
        ob_start();
        phpinfo(INFO_GENERAL);
        $s = ob_get_contents();
        ob_end_clean();

        // Parse PHP info output
        $php_general = [];
        foreach (explode("\n", $s) as $line) {
            $line = str_replace('<tr><td class="e">', '', $line);
            $line = str_replace('</td></tr>', '', $line);
            $line = str_replace(' </td><td class="v">', ' => ', $line);
            $sep_pos = strpos($line, " => ");
            if ($sep_pos !== FALSE) {
                $name = substr($line, 0, $sep_pos);
                $value = substr($line, $sep_pos + 4);
                $php_general[$name] = $value;
            }
        }
        
        $ini_file = $php_general['Loaded Configuration File'] ?? 'Unknown';
        $extra_ini = php_ini_scanned_files();
        if ($extra_ini != '') {
            $extra_ini = "The following additional ini files were read: $extra_ini";
        } else {
            $extra_ini = "There were no additional ini files, according to PHP.";
        }

        // Check GD library
        $gdversion = "";
        $gdbuiltin = FALSE;
        $gdstring = "";
        if (function_exists('gd_info')) {
            $gdinfo = gd_info();
            $gdversion = $gdinfo['GD Version'];
            if (strpos($gdversion, "bundled") !== FALSE) {
                $gdbuiltin = TRUE;
                $gdstring = "This PHP uses the 'bundled' GD library, which doesn't have alpha-blending bugs. That's good!\n";
            } else {
                $gdstring = "This PHP uses the system GD library. Check that you have at least GD 2.0.34 installed.\n";
            }
        } else {
            $gdstring = "The gdinfo() function is not available, which means that either the GD extension is not available, not enabled, or not installed.\n";
        }

        // Test required and optional functions
        $critical = 0;
        $noncritical = 0;
        $functions = [
            'imagepng' => [TRUE, FALSE, 'all of Weathermap', 'part of the GD library and the "gd" PHP extension'],
            'imagecreatetruecolor' => [TRUE, FALSE, 'all of Weathermap', 'part of the GD library and the "gd" PHP extension'],
            'imagealphablending' => [TRUE, FALSE, 'all of Weathermap', 'part of the GD library and the "gd" PHP extension'],
            'imageSaveAlpha' => [TRUE, FALSE, 'all of Weathermap', 'part of the GD library and the "gd" PHP extension'],
            'preg_match' => [TRUE, FALSE, 'configuration reading', 'provided by the "pcre" extension'],
            'imagecreatefrompng' => [TRUE, FALSE, 'all of Weathermap', 'part of the GD library and the "gd" PHP extension'],
            'imagecreatefromjpeg' => [FALSE, FALSE, 'JPEG input support for ICON and BACKGROUND', 'an optional part of the GD library and the "gd" PHP extension'],
            'imagecreatefromgif' => [FALSE, FALSE, 'GIF input support for ICON and BACKGROUND', 'an optional part of the GD library and the "gd" PHP extension'],
            'imagejpeg' => [FALSE, FALSE, 'JPEG output support', 'an optional part of the GD library and the "gd" PHP extension'],
            'imagegif' => [FALSE, FALSE, 'GIF output support', 'an optional part of the GD library and the "gd" PHP extension'],
            'imagecopyresampled' => [FALSE, FALSE, 'Thumbnail creation', 'an optional part of the GD library and the "gd" PHP extension'],
            'imagettfbbox' => [FALSE, FALSE, 'TrueType font support', 'an optional part of the GD library and the "gd" PHP extension'],
            'memory_get_usage' => [FALSE, TRUE, 'memory-usage debugging', 'not supported on all PHP versions and platforms']
        ];
        
        $results = [];
        foreach ($functions as $function => $details) {
            $exists = function_exists($function);
            $is_critical = $details[0];
            $is_minor = $details[1];
            $affects = $details[2];
            $description = $details[3];
            
            if (!$exists) {
                if ($is_critical) {
                    $critical++;
                } elseif (!$is_minor) {
                    $noncritical++;
                }
            }
            
            $results[$function] = [
                'exists' => $exists,
                'critical' => $is_critical,
                'minor' => $is_minor,
                'affects' => $affects,
                'description' => $description,
            ];
        }

        $status = 'ok';
        if ($critical > 0) {
            $status = 'critical';
        } elseif ($noncritical > 0) {
            $status = 'warning';
        }

        return [
            'php_version' => $php_version,
            'php_os' => $php_os,
            'mem_allowed' => $mem_allowed,
            'mem_warning' => $mem_warning,
            'ini_file' => $ini_file,
            'extra_ini' => $extra_ini,
            'gd_version' => $gdversion,
            'gd_string' => $gdstring,
            'functions' => $results,
            'critical_count' => $critical,
            'noncritical_count' => $noncritical,
            'status' => $status,
        ];
    }

    /**
     * Convert memory string (e.g., "256M") to bytes
     */
    private function returnBytes($val): int
    {
        $val = trim($val);
        if ($val != '') {
            $last = strtolower($val[strlen($val) - 1]);
            switch ($last) {
                case 'g':
                    $val *= 1024;
                case 'm':
                    $val *= 1024;
                case 'k':
                    $val *= 1024;
            }
        } else {
            $val = 0;
        }

        return (int)$val;
    }
}
