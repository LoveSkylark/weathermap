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

trait DataProcessor
{

    public function buildAllItemsList()
    {
        if ($this->allItemsCache !== null) {
            return $this->allItemsCache;
        }

        $allItems = array();
        foreach (array(&$this->nodes, &$this->links) as $innerList) {
            foreach ($innerList as $item) {
                $allItems[] = $item;
            }
        }

        $this->allItemsCache = $allItems;
        return $allItems;
    }


    function ProcessString($input, &$context, $include_notes = true, $multiline = false)
    {
        $context_description = strtolower($context->my_type());
        if ($context_description != "map") {
            $context_description .= ":" . $context->name;
        }

        wm_debug("Trace: ProcessString($input, $context_description)\n");

        if ($multiline == true) {
            $i = $input;
            $input = str_replace("\\n", "\n", $i);
        }

        $output = $input;

        while (preg_match('/(\{(?:node|map|link)[^}]+\})/', $input, $matches)) {
            $value = "[UNKNOWN]";
            $format = "";
            $key = $matches[1];
            wm_debug("ProcessString: working on " . $key . "\n");

            if (preg_match('/\{(node|map|link):([^}]+)\}/', $key, $matches)) {
                $type = $matches[1];
                $args = $matches[2];

                if ($type == 'map') {
                    $the_item = $this;
                    if (preg_match("/map:([^:]+):*([^:]*)/", $args, $matches)) {
                        $args = $matches[1];
                        $format = $matches[2];
                    }
                }

                if (($type == 'link') || ($type == 'node')) {
                    if (preg_match("/([^:]+):([^:]+):*([^:]*)/", $args, $matches)) {
                        $itemname = $matches[1];
                        $args = $matches[2];
                        $format = $matches[3];

                        $the_item = null;
                        if (($itemname == "this") && ($type == strtolower($context->my_type()))) {
                            $the_item = $context;
                        } elseif (strtolower($context->my_type()) == "link" && $type == 'node' && ($itemname == '_linkstart_' || $itemname == '_linkend_')) {
                            // this refers to the two nodes at either end of this link
                            if ($itemname == '_linkstart_') {
                                $the_item = $context->a;
                            }

                            if ($itemname == '_linkend_') {
                                $the_item = $context->b;
                            }
                        } elseif (($itemname == "parent") && ($type == strtolower($context->my_type())) && ($type == 'node') && ($context->relative_to != '')) {
                            $the_item = $this->nodes[$context->relative_to];
                        } else {
                            if (($type == 'link') && isset($this->links[$itemname])) {
                                $the_item = $this->links[$itemname];
                            }
                            if (($type == 'node') && isset($this->nodes[$itemname])) {
                                $the_item = $this->nodes[$itemname];
                            }
                        }
                    }
                }

                if (is_null($the_item)) {
                    wm_warn("ProcessString: $key refers to unknown item (context is $context_description) [WMWARN05]\n");
                } else {
                    wm_debug("ProcessString: Found appropriate item: " . get_class($the_item) . " " . $the_item->name . "\n");

                    // SET and notes have precedent over internal properties
                    // this is my laziness - it saves me having a list of reserved words
                    // which are currently used for internal props. You can just 'overwrite' any of them.
                    if (isset($the_item->hints[$args])) {
                        $value = $the_item->hints[$args];
                        wm_debug("ProcessString: used hint\n");
                    }
                    // for some things, we don't want to allow notes to be considered.
                    // mainly - TARGET (which can define command-lines), shouldn't be
                    // able to get data from uncontrolled sources (i.e. data sources rather than SET in config files).
                    elseif ($include_notes && isset($the_item->notes[$args])) {
                        $value = $the_item->notes[$args];
                        wm_debug("ProcessString: used note\n");

                    } elseif (isset($the_item->$args)) {
                        $value = $the_item->$args;
                        wm_debug("ProcessString: used internal property\n");
                    }
                }
            }

            // format, and sanitise the value string here, before returning it

            if ($value === null) {
                $value = 'NULL';
            }
            wm_debug("ProcessString: replacing " . $key . " with $value\n");

            if ($format != '') {
                $value = mysprintf($format, $value, $this->kilo);
            }

            $input = str_replace($key, '', $input);
            $output = str_replace($key, $value, $output);
        }
        return ($output);
    }


    function RandomData()
    {
        foreach ($this->links as $link) {
            $this->links[$link->name]->bandwidth_in = rand(0, $link->max_bandwidth_in);
            $this->links[$link->name]->bandwidth_out = rand(0, $link->max_bandwidth_out);
        }
    }


    function DatasourceInit()
    {
        wm_debug("Running Init() for Data Source Plugins...\n");
        foreach ($this->datasourceclasses as $ds_class) {
            // make an instance of the class
            $dsplugins[$ds_class] = new $ds_class;
            wm_debug("Running $ds_class" . "->Init()\n");
            $ret = $this->plugins['data'][$ds_class]->Init($this);

            if (!$ret) {
                wm_debug("Removing $ds_class from Data Source list, since Init() failed\n");
                $this->activedatasourceclasses[$ds_class] = 0;
            }
        }
        wm_debug("Finished Initialising Plugins...\n");
    }


    function ProcessTargets()
    {
        wm_debug("Preprocessing targets\n");

        $allitems = $this->buildAllItemsList();

        foreach ($allitems as $myobj) {
            $type = $myobj->my_type();
            $name = $myobj->name;


            if (($type == 'LINK' && isset($myobj->a)) || ($type == 'NODE' && !is_null($myobj->x))) {
                if (count($myobj->targets) > 0) {
                    $tindex = 0;
                    foreach ($myobj->targets as $target) {
                        wm_debug("ProcessTargets: New Target: $target[4]\n");
                        // processstring won't use notes (only hints) for this string

                        $targetstring = $this->ProcessString($target[4], $myobj, false, false);
                        if ($target[4] != $targetstring) {
                            wm_debug("Targetstring is now $targetstring\n");
                        }

                        // if the targetstring starts with a -, then we're taking this value OFF the aggregate
                        $multiply = 1;
                        if (preg_match("/^-(.*)/", $targetstring, $matches)) {
                            $targetstring = $matches[1];
                            $multiply = -1 * $multiply;
                        }

                        // if the remaining targetstring starts with a number and a *-, then this is a scale factor
                        if (preg_match("/^(\d+\.?\d*)\*(.*)/", $targetstring, $matches)) {
                            $targetstring = $matches[2];
                            $multiply = $multiply * floatval($matches[1]);
                        }

                        $matched = false;
                        $matched_by = '';
                        foreach ($this->datasourceclasses as $ds_class) {
                            if (!$matched) {
                                $recognised = $this->plugins['data'][$ds_class]->Recognise($targetstring);

                                if ($recognised) {
                                    $matched = true;
                                    $matched_by = $ds_class;

                                    if ($this->activedatasourceclasses[$ds_class]) {
                                        $this->plugins['data'][$ds_class]->Register($targetstring, $this, $myobj);
                                        if ($type == 'NODE') {
                                            $this->nodes[$name]->targets[$tindex][1] = $multiply;
                                            $this->nodes[$name]->targets[$tindex][0] = $targetstring;
                                            $this->nodes[$name]->targets[$tindex][5] = $matched_by;
                                        }
                                        if ($type == 'LINK') {
                                            $this->links[$name]->targets[$tindex][1] = $multiply;
                                            $this->links[$name]->targets[$tindex][0] = $targetstring;
                                            $this->links[$name]->targets[$tindex][5] = $matched_by;
                                        }
                                    } else {
                                        wm_warn("ProcessTargets: $type $name, target: $targetstring on config line $target[3] of $target[2] was recognised as a valid TARGET by a plugin that is unable to run ($ds_class) [WMWARN07]\n");
                                    }
                                }
                            }
                        }
                        if (!$matched) {
                            wm_warn("ProcessTargets: $type $name, target: $target[4] on config line $target[3] of $target[2] was not recognised as a valid TARGET [WMWARN08]\n");
                        }

                        $tindex++;
                    }
                }
            }
        }
    }


    function ReadData()
    {
        $this->DatasourceInit();

        wm_debug("======================================\n");
        wm_debug("ReadData: Updating link data for all links and nodes\n");

        // we skip readdata completely in sizedebug mode
        if ($this->sizedebug == 0) {
            $this->ProcessTargets();

            wm_debug("======================================\n");
            wm_debug("Starting prefetch\n");
            foreach ($this->datasourceclasses as $ds_class) {
                $this->plugins['data'][$ds_class]->Prefetch();
            }

            wm_debug("======================================\n");
            wm_debug("Starting main collection loop\n");

            $allitems = $this->buildAllItemsList();

            foreach ($allitems as $myobj) {

                $type = $myobj->my_type();

                $total_in = 0;
                $total_out = 0;
                $name = $myobj->name;
                wm_debug("\n");
                wm_debug("ReadData for $type $name: \n");

                if (($type == 'LINK' && isset($myobj->a)) || ($type == 'NODE' && !is_null($myobj->x))) {
                    if (count($myobj->targets) > 0) {
                        $tindex = 0;
                        foreach ($myobj->targets as $target) {
                            wm_debug("ReadData: New Target: $target[4]\n");
                            $targetstring = $target[0];
                            $multiply = $target[1];

                            $in = 0;
                            $out = 0;
                            $datatime = 0;
                            if ($target[4] != '') {
                                // processstring won't use notes (only hints) for this string

                                $targetstring = $this->ProcessString($target[0], $myobj, false, false);
                                if ($target[0] != $targetstring) {
                                    wm_debug("Targetstring is now $targetstring\n");
                                }
                                if ($multiply != 1) {
                                    wm_debug("Will multiply result by $multiply\n");
                                }

                                if ($target[0] != "") {
                                    $matched_by = $target[5];
                                    [$in, $out, $datatime] = $this->plugins['data'][$target[5]]->ReadData($targetstring,
                                        $this, $myobj);
                                }

                                if (($in === null) && ($out === null)) {
                                    $in = 0;
                                    $out = 0;
                                    wm_warn
                                    ("ReadData: $type $name, target: $targetstring on config line $target[3] of $target[2] had no valid data, according to $matched_by\n");
                                } else {
                                    if ($in === null) {
                                        $in = 0;
                                    }
                                    if ($out === null) {
                                        $out = 0;
                                    }
                                }

                                if ($multiply != 1) {
                                    wm_debug("Pre-multiply: $in $out\n");

                                    $in = $multiply * $in;
                                    $out = $multiply * $out;

                                    wm_debug("Post-multiply: $in $out\n");
                                }

                                $total_in = $total_in + $in;
                                $total_out = $total_out + $out;
                                wm_debug("Aggregate so far: $total_in $total_out\n");
                                if ($datatime > 0) {
                                    if ($this->max_data_time == null || $datatime > $this->max_data_time) {
                                        $this->max_data_time = $datatime;
                                    }
                                    if ($this->min_data_time == null || $datatime < $this->min_data_time) {
                                        $this->min_data_time = $datatime;
                                    }

                                    wm_debug("DataTime MINMAX: " . $this->min_data_time . " -> " . $this->max_data_time . "\n");
                                }

                            }
                            $tindex++;
                        }

                        wm_debug("ReadData complete for $type $name: $total_in $total_out\n");
                    } else {
                        wm_debug("ReadData: No targets for $type $name\n");
                    }
                } else {
                    wm_debug("ReadData: Skipping $type $name that looks like a template\n.");
                }

                $myobj->bandwidth_in = $total_in;
                $myobj->bandwidth_out = $total_out;

                if ($type == 'LINK' && $myobj->duplex == 'half') {
                    // in a half duplex link, in and out share a common bandwidth pool, so percentages need to include both
                    wm_debug("Calculating percentage using half-duplex\n");
                    $myobj->outpercent = (($total_in + $total_out) / ($myobj->max_bandwidth_out)) * 100;
                    $myobj->inpercent = (($total_out + $total_in) / ($myobj->max_bandwidth_in)) * 100;
                    if ($myobj->max_bandwidth_out != $myobj->max_bandwidth_in) {
                        wm_warn("ReadData: $type $name: You're using asymmetric bandwidth AND half-duplex in the same link. That makes no sense. [WMWARN44]\n");
                    }
                } else {
                    $myobj->outpercent = (($total_out) / ($myobj->max_bandwidth_out)) * 100;
                    $myobj->inpercent = (($total_in) / ($myobj->max_bandwidth_in)) * 100;
                }

                $warn_in = true;
                $warn_out = true;
                if ($type == 'NODE' && $myobj->scalevar == 'in') {
                    $warn_out = false;
                }
                if ($type == 'NODE' && $myobj->scalevar == 'out') {
                    $warn_in = false;
                }

                if ($myobj->scaletype == 'percent') {
                    [$incol, $inscalekey, $inscaletag] = $this->NewColourFromPercent($myobj->inpercent,
                        $myobj->usescale, $myobj->name, true, $warn_in);
                    [$outcol, $outscalekey, $outscaletag] = $this->NewColourFromPercent($myobj->outpercent,
                        $myobj->usescale, $myobj->name, true, $warn_out);
                } else {
                    // use absolute values, if that's what is requested
                    [$incol, $inscalekey, $inscaletag] = $this->NewColourFromPercent($myobj->bandwidth_in,
                        $myobj->usescale, $myobj->name, false, $warn_in);
                    [$outcol, $outscalekey, $outscaletag] = $this->NewColourFromPercent($myobj->bandwidth_out,
                        $myobj->usescale, $myobj->name, false, $warn_out);
                }

                $myobj->add_note("inscalekey", $inscalekey);
                $myobj->add_note("outscalekey", $outscalekey);

                $myobj->add_note("inscaletag", $inscaletag);
                $myobj->add_note("outscaletag", $outscaletag);

                $myobj->add_note("inscalecolor", $incol->as_html());
                $myobj->add_note("outscalecolor", $outcol->as_html());

                $myobj->colours[IN] = $incol;
                $myobj->colours[OUT] = $outcol;

                wm_debug("ReadData: Setting $total_in,$total_out\n");
            }
            wm_debug("ReadData Completed.\n");
            wm_debug("------------------------------\n");
        }
    }


    function NewColourFromPercent($value, $scalename = "DEFAULT", $name = "", $is_percent = true, $scale_warning = true)
    {
        $col = new Colour(0, 0, 0);
        $tag = '';
        $matchsize = null;

        $nowarn_clipping = intval($this->get_hint("nowarn_clipping"));
        $nowarn_scalemisses = (!$scale_warning) || intval($this->get_hint("nowarn_scalemisses"));

        if (isset($this->colours[$scalename])) {
            $colours = $this->colours[$scalename];

            if ($is_percent && $value > 100) {
                if ($nowarn_clipping == 0) {
                    wm_warn("NewColourFromPercent: Clipped $value% to 100% for item $name [WMWARN33]\n");
                }
                $value = 100;
            }

            if ($is_percent && $value < 0) {
                if ($nowarn_clipping == 0) {
                    wm_warn("NewColourFromPercent: Clipped $value% to 0% for item $name [WMWARN34]\n");
                }
                $value = 0;
            }

            foreach ($colours as $key => $colour) {
                if ((!isset($colour['special']) || $colour['special'] == 0) and ($value >= $colour['bottom']) and ($value <= $colour['top'])) {
                    $range = $colour['top'] - $colour['bottom'];
                    if (isset($colour['red2'])) {
                        if ($colour["bottom"] == $colour["top"]) {
                            $ratio = 0;
                        } else {
                            $ratio = ($value - $colour["bottom"]) / ($colour["top"] - $colour["bottom"]);
                        }

                        $r = $colour["red1"] + ($colour["red2"] - $colour["red1"]) * $ratio;
                        $g = $colour["green1"] + ($colour["green2"] - $colour["green1"]) * $ratio;
                        $b = $colour["blue1"] + ($colour["blue2"] - $colour["blue1"]) * $ratio;
                    } else {
                        $r = $colour["red1"];
                        $g = $colour["green1"];
                        $b = $colour["blue1"];
                    }

                    // change in behaviour - with multiple matching ranges for a value, the smallest range wins
                    if (is_null($matchsize) || ($range < $matchsize)) {
                        $col = new Colour($r, $g, $b);
                        $matchsize = $range;
                    }

                    if (isset($colour['tag'])) {
                        $tag = $colour['tag'];
                    }
                    wm_debug("NCFPC $name $scalename $value '$tag' $key $r $g $b\n");

                    return (array($col, $key, $tag));
                }
            }
        } else {
            if ($scalename != 'none') {
                wm_warn("ColourFromPercent: Attempted to use non-existent scale: $scalename for item $name [WMWARN09]\n");
            } else {
                return array(new Colour(255, 255, 255), '', '');
            }
        }

        // shouldn't really get down to here if there's a complete SCALE

        // you'll only get grey for a COMPLETELY quiet link if there's no 0 in the SCALE lines
        if ($value == 0) {
            return array(new Colour(192, 192, 192), '', '');
        }

        if ($nowarn_scalemisses == 0) {
            wm_warn("NewColourFromPercent: Scale $scalename doesn't include a line for $value" . ($is_percent ? "%" : "") . " while drawing item $name [WMWARN29]\n");
        }

        // and you'll only get white for a link with no colour assigned
        return array(new Colour(255, 255, 255), '', '');
    }


    function coloursort($a, $b)
    {
        if ($a['bottom'] == $b['bottom']) {
            if ($a['top'] < $b['top']) {
                return -1;
            };
            if ($a['top'] > $b['top']) {
                return 1;
            };
            return 0;
        }

        if ($a['bottom'] < $b['bottom']) {
            return -1;
        }

        return 1;
    }


    function FindScaleExtent($scalename = "DEFAULT")
    {
        $max = -999999999999999999999;
        $min = -$max;

        if (isset($this->colours[$scalename])) {
            $colours = $this->colours[$scalename];

            foreach ($colours as $key => $colour) {
                if (!$colour['special']) {
                    $min = min($colour['bottom'], $min);
                    $max = max($colour['top'], $max);
                }
            }
        } else {
            wm_warn("FindScaleExtent: non-existent SCALE $scalename [WMWARN43]\n");
        }
        return array($min, $max);
    }

}
