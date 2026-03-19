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

trait HtmlGenerator
{

    function PreloadMapHTML()
    {
        wm_debug("Trace: PreloadMapHTML()\n");
        //   onmouseover="return overlib('<img src=graph.png>',DELAY,250,CAPTION,'$caption');"  onmouseout="return nd();"

        // find the middle of the map
        $center_x = $this->width / 2;
        $center_y = $this->height / 2;

        // loop through everything. Figure out along the way if it's a node or a link
        $allitems = $this->buildAllItemsList();

        foreach ($allitems as $myobj) {
            $type = $myobj->my_type();
            $prefix = substr($type, 0, 1);

            $dirs = array();
            if ($type == 'LINK') {
                $dirs = array(IN => array(0, 2), OUT => array(1, 3));
            }
            if ($type == 'NODE') {
                $dirs = array(IN => array(0, 1, 2, 3));
            }

            // check to see if any of the relevant things have a value
            $change = "";
            foreach ($dirs as $d => $parts) {
                $change .= join('', $myobj->overliburl[$d]);
                $change .= $myobj->notestext[$d];
            }

            if ($this->htmlstyle == "overlib") {
                // skip all this if it's a template node
                if ($type == 'LINK' && !isset($myobj->a->name)) {
                    $change = '';
                }
                if ($type == 'NODE' && !isset($myobj->x)) {
                    $change = '';
                }

                if ($change != '') {
                    if ($type == 'NODE') {
                        $mid_x = $myobj->x;
                        $mid_y = $myobj->y;
                    }
                    if ($type == 'LINK') {
                        $a_x = $this->nodes[$myobj->a->name]->x;
                        $a_y = $this->nodes[$myobj->a->name]->y;

                        $b_x = $this->nodes[$myobj->b->name]->x;
                        $b_y = $this->nodes[$myobj->b->name]->y;

                        $mid_x = ($a_x + $b_x) / 2;
                        $mid_y = ($a_y + $b_y) / 2;
                    }
                    $left = "";
                    $above = "";
                    $img_extra = "";

                    if ($myobj->overlibwidth != 0) {
                        $left = "WIDTH," . $myobj->overlibwidth . ",";
                        $img_extra .= " WIDTH=$myobj->overlibwidth";

                        if ($mid_x > $center_x) {
                            $left .= "LEFT,";
                        }
                    }

                    if ($myobj->overlibheight != 0) {
                        $above = "HEIGHT," . $myobj->overlibheight . ",";
                        $img_extra .= " HEIGHT=$myobj->overlibheight";

                        if ($mid_y > $center_y) {
                            $above .= "ABOVE,";
                        }
                    }

                    foreach ($dirs as $dir => $parts) {
                        $caption = ($myobj->overlibcaption[$dir] != '' ? $myobj->overlibcaption[$dir] : $myobj->name);
                        $caption = $this->ProcessString($caption, $myobj);

                        $overlibhtml = "onmouseover=\"return overlib('";

                        $n = 0;
                        if (count($myobj->overliburl[$dir]) > 0) {
                            foreach ($myobj->overliburl[$dir] as $url) {
                                if ($n > 0) {
                                    $overlibhtml .= '&lt;br /&gt;';
                                }
                                $overlibhtml .= "&lt;img $img_extra src=" . $this->ProcessString($url, $myobj) . "&gt;";
                                $n++;
                            }
                        }
                        if (trim($myobj->notestext[$dir]) != '') {
                            if ($n > 0) {
                                $overlibhtml .= '&lt;br /&gt;';
                            }
                            $note = $this->ProcessString($myobj->notestext[$dir], $myobj);
                            $note = htmlspecialchars($note, ENT_NOQUOTES);
                            $note = str_replace("'", "\\&apos;", $note);
                            $note = str_replace('"', "&quot;", $note);
                            $overlibhtml .= $note;
                        }
                        $overlibhtml .= "',DELAY,250,${left}${above}CAPTION,'" . $caption
                            . "');\"  onmouseout=\"return nd();\"";

                        foreach ($parts as $part) {
                            $areaname = $type . ":" . $prefix . $myobj->id . ":" . $part;
                            //print "INFOURL for $areaname - ";

                            $this->imap->setProp("extrahtml", $overlibhtml, $areaname);
                        }
                    }
                } // if change
            } // overlib?

            // now look at inforurls
            foreach ($dirs as $dir => $parts) {
                foreach ($parts as $part) {
                    $areaname = $type . ":" . $prefix . $myobj->id . ":" . $part;

                    if (($this->htmlstyle != 'editor') && ($myobj->infourl[$dir] != '')) {
                        $this->imap->setProp("href", $this->ProcessString($myobj->infourl[$dir], $myobj), $areaname);
                    }
                }
            }

        }
//		}


    function asJS()
    {
        $js = '';

        $js .= "var Links = new Array();\n";
        $js .= "var LinkIDs = new Array();\n";

        foreach ($this->links as $link) {
            $js .= $link->asJS();
        }

        $js .= "var Nodes = new Array();\n";
        $js .= "var NodeIDs = new Array();\n";

        foreach ($this->nodes as $node) {
            $js .= $node->asJS();
        }

        return $js;
    }


    function asJSON()
    {
        $json = '';

        $json .= "{ \n";

        $json .= "\"map\": {  \n";
        foreach (array_keys($this->inherit_fieldlist) as $fld) {
            $json .= js_escape($fld) . ": ";
            $json .= js_escape($this->$fld);
            $json .= ",\n";
        }
        $json = rtrim($json, ", \n");
        $json .= "\n},\n";

        $json .= "\"nodes\": {\n";
        $json .= $this->defaultnode->asJSON();
        foreach ($this->nodes as $node) {
            $json .= $node->asJSON();
        }
        $json = rtrim($json, ", \n");
        $json .= "\n},\n";


        $json .= "\"links\": {\n";
        $json .= $this->defaultlink->asJSON();
        foreach ($this->links as $link) {
            $json .= $link->asJSON();
        }
        $json = rtrim($json, ", \n");
        $json .= "\n},\n";

        $json .= "'imap': [\n";
        $json .= $this->imap->subJSON("NODE:");
        // should check if there WERE nodes...
        $json .= ",\n";
        $json .= $this->imap->subJSON("LINK:");
        $json .= "\n]\n";
        $json .= "\n";

        $json .= ", 'valid': 1}\n";

        return ($json);
    }


    function MakeHTML($imagemapname = "weathermap_imap")
    {
        wm_debug("Trace: MakeHTML()\n");
        // PreloadMapHTML fills in the ImageMap info, ready for the HTML to be created.
        $this->PreloadMapHTML();

        $html = '';

        $html .= '<div class="weathermapimage" style="margin-left: auto; margin-right: auto; width: ' . $this->width . 'px;" >';
        if ($this->imageuri != '') {
            $html .= sprintf(
                '<img id="wmapimage" src="%s" width="%d" height="%d" border="0" usemap="#%s"',
                $this->imageuri,
                $this->width,
                $this->height,
                $imagemapname
            );
            $html .= '/>';
        } else {
            $html .= sprintf(
                '<img id="wmapimage" src="%s" width="%d" height="%d" border="0" usemap="#%s"',
                $this->imagefile,
                $this->width,
                $this->height,
                $imagemapname
            );
            $html .= '/>';
        }
        $html .= '</div>';

        $html .= $this->SortedImagemap($imagemapname);

        return ($html);
    }


    function SortedImagemap($imagemapname)
    {
        $html = '<map name="' . $imagemapname . '" id="' . $imagemapname . '">';

        $all_layers = array_keys($this->seen_zlayers);
        rsort($all_layers);

        wm_debug("Starting to dump imagemap in reverse Z-order...\n");
        foreach ($all_layers as $z) {
            wm_debug("Writing HTML for layer $z\n");
            $z_items = $this->seen_zlayers[$z];
            if (is_array($z_items)) {
                wm_debug("   Found things for layer $z\n");

                // at z=1000, the legends and timestamps live
                if ($z == 1000) {
                    wm_debug("     Builtins fit here.\n");
                    $html .= $this->imap->subHTML("LEGEND:", true, ($this->context != 'editor'));
                    $html .= $this->imap->subHTML("TIMESTAMP", true, ($this->context != 'editor'));
                }

                foreach ($z_items as $it) {
                    if ($it->name != 'DEFAULT' && $it->name != ":: DEFAULT ::") {
                        $name = "";
                        if (strtolower(get_class($it)) == 'weathermaplink') {
                            $name = "LINK:L";
                        }
                        if (strtolower(get_class($it)) == 'weathermapnode') {
                            $name = "NODE:N";
                        }
                        $name .= $it->id . ":";
                        wm_debug("      Writing $name from imagemap\n");
                        // skip the linkless areas if we are in the editor - they're redundant
                        $html .= $this->imap->subHTML($name, true, ($this->context != 'editor'));
                    }
                }
            }
        }

        $html .= '</map>';

        return ($html);
    }

}
