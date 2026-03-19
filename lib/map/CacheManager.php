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

trait CacheManager
{

    function CacheUpdate($agelimit = 600)
    {
        global $weathermap_lazycounter;

        $cachefolder = $this->cachefolder;
        $configchanged = filemtime($this->configfile);
        // make a unique, but safe, prefix for all cachefiles related to this map config
        // we use CRC32 because it makes for a shorter filename, and collisions aren't the end of the world.
        $cacheprefix = dechex(crc32($this->configfile));

        wm_debug("Comparing files in $cachefolder starting with $cacheprefix, with date of $configchanged\n");

        $dh = opendir($cachefolder);

        if ($dh) {
            while ($file = readdir($dh)) {
                $realfile = $cachefolder . DIRECTORY_SEPARATOR . $file;

                if (is_file($realfile) && (preg_match('/^' . $cacheprefix . '/',
                        $file))) //                                            if (is_file($realfile) )
                {
                    wm_debug("$realfile\n");
                    if ((filemtime($realfile) < $configchanged) || ((time() - filemtime($realfile)) > $agelimit)) {
                        wm_debug("Cache: deleting $realfile\n");
                        unlink($realfile);
                    }
                }
            }
            closedir($dh);

            foreach ($this->nodes as $node) {
                if (isset($node->image)) {
                    $nodefile = $cacheprefix . "_" . dechex(crc32($node->name)) . ".png";
                    $this->nodes[$node->name]->cachefile = $nodefile;
                    imagepng($node->image, $cachefolder . DIRECTORY_SEPARATOR . $nodefile);
                }
            }

            foreach ($this->keyimage as $key => $image) {
                $scalefile = $cacheprefix . "_scale_" . dechex(crc32($key)) . ".png";
                $this->keycache[$key] = $scalefile;
                imagepng($image, $cachefolder . DIRECTORY_SEPARATOR . $scalefile);
            }


            $json = "";
            $fd = fopen($cachefolder . DIRECTORY_SEPARATOR . $cacheprefix . "_map.json", "w");
            foreach (array_keys($this->inherit_fieldlist) as $fld) {
                $json .= js_escape($fld) . ": ";
                $json .= js_escape($this->$fld);
                $json .= ",\n";
            }
            $json = rtrim($json, ", \n");
            fputs($fd, $json);
            fclose($fd);

            $json = "";
            $fd = fopen($cachefolder . DIRECTORY_SEPARATOR . $cacheprefix . "_tree.json", "w");
            $id = 10;    // first ID for user-supplied thing

            $json .= "{ id: 1, text: 'SCALEs'\n, children: [\n";
            foreach ($this->colours as $scalename => $colours) {
                $json .= "{ id: " . $id++ . ", text:" . js_escape($scalename) . ", leaf: true }, \n";
            }
            $json = rtrim($json, ", \n");
            $json .= "]},\n";

            $json .= "{ id: 2, text: 'FONTs',\n children: [\n";
            foreach ($this->fonts as $fontnumber => $font) {
                if ($font->type == 'truetype') {
                    $json .= sprintf("{ id: %d, text: %s, leaf: true}, \n", $id++, js_escape("Font $fontnumber (TT)"));
                }

                if ($font->type == 'gd') {
                    $json .= sprintf("{ id: %d, text: %s, leaf: true}, \n", $id++, js_escape("Font $fontnumber (GD)"));
                }
            }
            $json = rtrim($json, ", \n");
            $json .= "]},\n";

            $json .= "{ id: 3, text: 'NODEs',\n children: [\n";
            $json .= "{ id: " . $id++ . ", text: 'DEFAULT', children: [\n";

            $weathemap_lazycounter = $id;
            // pass the list of subordinate nodes to the recursive tree function
            $json .= $this->MakeTemplateTree($this->node_template_tree);
            $id = $weathermap_lazycounter;

            $json = rtrim($json, ", \n");
            $json .= "]} ]},\n";

            $json .= "{ id: 4, text: 'LINKs',\n children: [\n";
            $json .= "{ id: " . $id++ . ", text: 'DEFAULT', children: [\n";
            $weathemap_lazycounter = $id;
            $json .= $this->MakeTemplateTree($this->link_template_tree);
            $id = $weathermap_lazycounter;
            $json = rtrim($json, ", \n");
            $json .= "]} ]}\n";

            fputs($fd, "[" . $json . "]");
            fclose($fd);

            $fd = fopen($cachefolder . DIRECTORY_SEPARATOR . $cacheprefix . "_nodes.json", "w");
            $json = "";
//		$json = $this->defaultnode->asJSON(TRUE);
            foreach ($this->nodes as $node) {
                $json .= $node->asJSON(true);
            }
            $json = rtrim($json, ", \n");
            fputs($fd, $json);
            fclose($fd);

            $fd = fopen($cachefolder . DIRECTORY_SEPARATOR . $cacheprefix . "_nodes_lite.json", "w");
            $json = "";
//		$json = $this->defaultnode->asJSON(FALSE);
            foreach ($this->nodes as $node) {
                $json .= $node->asJSON(false);
            }
            $json = rtrim($json, ", \n");
            fputs($fd, $json);
            fclose($fd);


            $fd = fopen($cachefolder . DIRECTORY_SEPARATOR . $cacheprefix . "_links.json", "w");
            $json = "";
//		$json = $this->defaultlink->asJSON(TRUE);
            foreach ($this->links as $link) {
                $json .= $link->asJSON(true);
            }
            $json = rtrim($json, ", \n");
            fputs($fd, $json);
            fclose($fd);

            $fd = fopen($cachefolder . DIRECTORY_SEPARATOR . $cacheprefix . "_links_lite.json", "w");
            $json = "";
//		$json = $this->defaultlink->asJSON(FALSE);
            foreach ($this->links as $link) {
                $json .= $link->asJSON(false);
            }
            $json = rtrim($json, ", \n");
            fputs($fd, $json);
            fclose($fd);

            $fd = fopen($cachefolder . DIRECTORY_SEPARATOR . $cacheprefix . "_imaphtml.json", "w");
            $json = $this->imap->subHTML("LINK:");
            fputs($fd, $json);
            fclose($fd);


            $fd = fopen($cachefolder . DIRECTORY_SEPARATOR . $cacheprefix . "_imap.json", "w");
            $json = '';
            $nodejson = trim($this->imap->subJSON("NODE:"));
            if ($nodejson != '') {
                $json .= $nodejson;
                // should check if there WERE nodes...
                $json .= ",\n";
            }
            $json .= $this->imap->subJSON("LINK:");
            fputs($fd, $json);
            fclose($fd);

        } else {
            wm_debug("Couldn't read cache folder.\n");
        }
    }


    function MakeTemplateTree(&$tree_list, $startpoint = "DEFAULT")
    {
        global $weathermap_lazycounter;

        $output = "";
        foreach ($tree_list[$startpoint] as $subnode) {
            $output .= "{ id: " . $weathermap_lazycounter++ . ", text: " . js_escape($subnode);
            if (isset($tree_list[$subnode])) {
                $output .= ", children: [ \n";
                $output .= $this->MakeTemplateTree($tree_list, $subnode);
                $output = rtrim($output, ", \n");
                $output .= "] \n";
            } else {
                $output .= ", leaf: true ";
            }
            $output .= "}, \n";
        }

        return ($output);
    }

}
