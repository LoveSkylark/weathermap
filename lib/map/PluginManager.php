<?php

namespace Weathermap\Map;

use Weathermap\Base\MapBase;
use Weathermap\Base\DataSource;
use Weathermap\Base\PreProcessor;
use Weathermap\Base\PostProcessor;
use Weathermap\Base\InternalException;
use Weathermap\Html\ImageMap;
use Weathermap\Util\Font;
use Weathermap\Util\Colour;
use Weathermap\Geometry\Point;
use Weathermap\Geometry\Vector;
use Weathermap\Geometry\Line;
use Weathermap\Geometry\LineSegment;
use Weathermap\Map\Node;
use Weathermap\Map\Link;

trait PluginManager
{

    function LoadPlugins($type = "data", $dir = "lib/datasources")
    {
        wm_debug("Beginning to load $type plugins from $dir\n");

        if (!file_exists($dir)) {
            $dir = dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . $dir;
            wm_debug("Relative path didn't exist. Trying $dir\n");
        }

        if (!is_dir($dir)) {    // try to find it with the script, if the relative path fails
            $srcdir = substr($_SERVER['argv'][0], 0, strrpos($_SERVER['argv'][0], DIRECTORY_SEPARATOR));
            if (is_dir($srcdir . DIRECTORY_SEPARATOR . $dir)) {
                $dir = $srcdir . DIRECTORY_SEPARATOR . $dir;
            }
        }

        if (!is_dir($dir)) {
            wm_warn("Couldn't open $type Plugin directory ($dir). Things will probably go wrong. [WMWARN06]\n");
            return;
        }

        foreach (scandir($dir) as $file) {
            $realfile = $dir . DIRECTORY_SEPARATOR . $file;

            if (is_file($realfile) && preg_match('/\.php$/', $realfile)) {
                wm_debug("Loading $type Plugin class from $file\n");

                $class = preg_replace("/\.php$/", "", $file);
                if ($type === 'data') {
                    $fqcn = 'Weathermap\\DataSources\\' . $class;
                } elseif ($type === 'pre') {
                    $fqcn = 'Weathermap\\PreProcessors\\' . $class;
                } elseif ($type === 'post') {
                    $fqcn = 'Weathermap\\PostProcessors\\' . $class;
                } else {
                    $fqcn = $class;
                }
                if ($type == 'data') {
                    $this->datasourceclasses [$fqcn] = $fqcn;
                    $this->activedatasourceclasses[$fqcn] = 1;
                }
                if ($type == 'pre') {
                    $this->preprocessclasses [$fqcn] = $fqcn;
                }
                if ($type == 'post') {
                    $this->postprocessclasses [$fqcn] = $fqcn;
                }

                wm_debug("Loaded $type Plugin class $fqcn from $file\n");
                $this->plugins[$type][$fqcn] = new $fqcn;
                if (!isset($this->plugins[$type][$fqcn])) {
                    wm_debug("** Failed to create an object for plugin $type/$fqcn\n");
                } else {
                    wm_debug("Instantiated $fqcn.\n");
                }
            } else {
                wm_debug("Skipping $file\n");
            }
        }
    }


    function DumpStats($filename = "")
    {
        $report = "Feature Statistics:\n\n";
        foreach ($this->usage_stats as $key => $val) {
            $report .= sprintf("%70s => %d\n", $key, $val);
        }

        if ($filename == "") {
            print $report;
        }
    }


    function SeedCoverage()
    {
        global $WM_config_keywords2;

        foreach (array_keys($WM_config_keywords2) as $context) {
            foreach (array_keys($WM_config_keywords2[$context]) as $keyword) {
                foreach ($WM_config_keywords2[$context][$keyword] as $patternarray) {
                    $key = sprintf("%s:%s:%s", $context, $keyword, $patternarray[1]);
                    $this->coverage[$key] = 0;
                }
            }
        }
    }


    function LoadCoverage($file)
    {
        return 0;
    }


    function SaveCoverage($file)
    {
        $i = 0;
        $fd = fopen($file, "w+");
        foreach ($this->coverage as $key => $val) {
            fputs($fd, "$val\t$key\n");
            if ($val > 0) {
                $i++;
            }
        }
        fclose($fd);
    }


    function CleanUp()
    {
        $all_layers = array_keys($this->seen_zlayers);

        foreach ($all_layers as $z) {
            $this->seen_zlayers[$z] = null;
        }

        foreach ($this->links as $link) {
            $link->owner = null;
            $link->a = null;
            $link->b = null;

            unset($link);
        }

        foreach ($this->nodes as $node) {
            // destroy all the images we created, to prevent memory leaks

            if (isset($node->image)) {
                imagedestroy($node->image);
            }
            $node->owner = null;
            unset($node);
        }

        // Clear up the other random hashes of information
        $this->dsinfocache = null;
        $this->colourtable = null;
        $this->usage_stats = null;
        $this->scales = null;

    }

}
