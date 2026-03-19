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

trait ConfigReader
{
    function ReadConfig($input, $is_include = false)
    {
        global $weathermap_error_suppress;

        $curnode = null;
        $curlink = null;
        $curobj  = null;
        $nodesseen = 0;
        $linksseen = 0;
        $scalesseen = 0;
        $last_seen = "GLOBAL";
        $filename = "";
        $objectlinecount = 0;
        $linecount = 0;

        $lines = $this->loadConfigLines($input, $is_include, $filename);
        if ($lines === false) {
            return false;
        }

        foreach ($lines as $buffer) {
            $linecount++;

            if (preg_match("/^\s*#/", $buffer)) {
                continue;
            }

            $buffer = trim($buffer);
            $args   = wm_parse_string($buffer);

            // resolve current object reference
            unset($curobj);
            $curobj = null;
            if ($last_seen === 'LINK')   { $curobj = &$curlink; }
            if ($last_seen === 'NODE')   { $curobj = &$curnode; }
            if ($last_seen === 'GLOBAL') { $curobj = &$this;    }

            $objectlinecount++;
            $linematched = 0;

            $linematched += $this->parseObjectDeclaration($buffer, $curobj, $curlink, $curnode, $last_seen, $linecount, $objectlinecount, $filename, $linksseen, $nodesseen);
            $linematched += $this->parseKeywordTable($buffer, $curobj, $last_seen);
            $linematched += $this->parseNodes($buffer, $curlink, $last_seen, $linecount);
            $linematched += $this->parseInclude($buffer, $last_seen);
            $linematched += $this->parseTarget($buffer, $curobj, $args, $last_seen, $filename, $linecount);
            $linematched += $this->parseBwLabel($buffer, $curobj, $last_seen);
            $linematched += $this->parseSet($buffer, $curobj);
            $linematched += $this->parseOverlibgraph($buffer, $curobj, $last_seen);
            $linematched += $this->parseTemplate($buffer, $curobj, $last_seen, $linecount, $objectlinecount);
            $linematched += $this->parseVia($buffer, $curlink, $last_seen);
            $linematched += $this->parseUseScale($buffer, $curnode, $last_seen);
            $linematched += $this->parseScale($buffer, $scalesseen);
            $linematched += $this->parseKeyPos($buffer);
            $linematched += $this->parseFontDefine($buffer, $linecount);
            $linematched += $this->parseKeyStyle($buffer);
            $linematched += $this->parseKilo($buffer);
            $linematched += $this->parseColor($buffer);
            $linematched += $this->parseNodeColor($buffer, $curnode, $last_seen);
            $linematched += $this->parseLinkColor($buffer, $curlink, $last_seen);
            $linematched += $this->parseArrowStyleCustom($buffer, $curlink, $last_seen);

            if ($linematched === 0 && $buffer !== '') {
                wm_warn("Unrecognised config on line $linecount: $buffer\n");
            }
            if ($linematched > 1) {
                wm_warn("Same line ($linecount) interpreted twice. This is a program error. Please report to Howie with your config!\nThe line was: $buffer");
            }
        }

        $this->ReadConfig_Commit($curobj);

        wm_debug("ReadConfig has finished reading the config ($linecount lines)\n");
        wm_debug("------------------------------------------\n");

        $this->applyDefaultScales($scalesseen);
        $this->numscales['DEFAULT'] = $scalesseen;
        $this->configfile = $filename;

        if ($this->has_overlibs && $this->htmlstyle === 'static') {
            wm_warn("OVERLIBGRAPH is used, but HTMLSTYLE is static. This is probably wrong. [WMWARN41]\n");
        }

        $this->buildZLayers();
        $this->resolveRelativePositions();
        $this->runPreProcessors();

        return true;
    }

    // -------------------------------------------------------------------------
    // File loading
    // -------------------------------------------------------------------------

    private function loadConfigLines($input, bool $is_include, string &$filename): array|false
    {
        $lines = [];

        if (strchr($input, "\n") !== false || strchr($input, "\r") !== false) {
            wm_debug("ReadConfig Detected that this is a config fragment.\n");
            $input    = str_replace("\r", "", $input);
            $lines    = explode("\n", $input);
            $filename = "{text insert}";
            return $lines;
        }

        wm_debug("ReadConfig Detected that this is a config filename.\n");
        $filename = $input;

        if ($is_include) {
            wm_debug("ReadConfig Detected that this is an INCLUDED config filename.\n");
            if (in_array($filename, $this->included_files)) {
                wm_warn("Attempt to include '$filename' twice! Skipping it.\n");
                return false;
            }
            $this->included_files[] = $filename;
            $this->has_includes = true;
        }

        $fd = fopen($filename, "r");
        if ($fd) {
            while (!feof($fd)) {
                $buffer = fgets($fd, 4096);
                $lines[] = str_replace("\r", "", $buffer);
            }
            fclose($fd);
        }

        return $lines;
    }

    // -------------------------------------------------------------------------
    // Keyword handlers — each returns 1 if it matched the line, 0 otherwise
    // -------------------------------------------------------------------------

    private function parseObjectDeclaration(
        string $buffer, &$curobj, &$curlink, &$curnode, string &$last_seen,
        int $linecount, int &$objectlinecount, string $filename,
        int &$linksseen, int &$nodesseen
    ): int {
        if (!preg_match("/^\s*(LINK|NODE)\s+(\S+)\s*$/i", $buffer, $matches)) {
            return 0;
        }

        $objectlinecount = 0;
        $this->ReadConfig_Commit($curobj);

        if ($matches[1] === 'LINK') {
            if ($matches[2] === 'DEFAULT') {
                if ($linksseen > 0) {
                    wm_warn("LINK DEFAULT is not the first LINK. Defaults will not apply to earlier LINKs. [WMWARN26]\n");
                }
                unset($curlink);
                wm_debug("Loaded LINK DEFAULT\n");
                $curlink = $this->links['DEFAULT'];
            } else {
                unset($curlink);
                if (isset($this->links[$matches[2]])) {
                    wm_warn("Duplicate link name " . $matches[2] . " at line $linecount - only the last one defined is used. [WMWARN25]\n");
                }
                wm_debug("New LINK " . $matches[2] . "\n");
                $curlink = new Link;
                $curlink->name = $matches[2];
                $curlink->Reset($this);
                $linksseen++;
            }
            $last_seen = "LINK";
            $curlink->configline = $linecount;
            $curobj = &$curlink;
        }

        if ($matches[1] === 'NODE') {
            if ($matches[2] === 'DEFAULT') {
                if ($nodesseen > 0) {
                    wm_warn("NODE DEFAULT is not the first NODE. Defaults will not apply to earlier NODEs. [WMWARN27]\n");
                }
                unset($curnode);
                wm_debug("Loaded NODE DEFAULT\n");
                $curnode = $this->nodes['DEFAULT'];
            } else {
                unset($curnode);
                if (isset($this->nodes[$matches[2]])) {
                    wm_warn("Duplicate node name " . $matches[2] . " at line $linecount - only the last one defined is used. [WMWARN24]\n");
                }
                $curnode = new Node;
                $curnode->name = $matches[2];
                $curnode->Reset($this);
                $nodesseen++;
            }
            $curnode->configline = $linecount;
            $last_seen = "NODE";
            $curobj = &$curnode;
        }

        $curobj->defined_in = $filename;
        return 1;
    }

    private function parseKeywordTable(string $buffer, &$curobj, string $last_seen): int
    {
        $config_keywords = [
            ['LINK', '/^\s*(MAXVALUE|BANDWIDTH)\s+(\d+\.?\d*[KMGT]?)\s+(\d+\.?\d*[KMGT]?)\s*$/i', ['max_bandwidth_in_cfg' => 2, 'max_bandwidth_out_cfg' => 3]],
            ['LINK', '/^\s*(MAXVALUE|BANDWIDTH)\s+(\d+\.?\d*[KMGT]?)\s*$/i',                        ['max_bandwidth_in_cfg' => 2, 'max_bandwidth_out_cfg' => 2]],
            ['NODE', '/^\s*(MAXVALUE)\s+(\d+\.?\d*[KMGT]?)\s+(\d+\.?\d*[KMGT]?)\s*$/i',            ['max_bandwidth_in_cfg' => 2, 'max_bandwidth_out_cfg' => 3]],
            ['NODE', '/^\s*(MAXVALUE)\s+(\d+\.?\d*[KMGT]?)\s*$/i',                                  ['max_bandwidth_in_cfg' => 2, 'max_bandwidth_out_cfg' => 2]],
            ['GLOBAL', '/^\s*BACKGROUND\s+(.*)\s*$/i',         ['background' => 1]],
            ['GLOBAL', '/^\s*HTMLOUTPUTFILE\s+(.*)\s*$/i',     ['htmloutputfile' => 1]],
            ['GLOBAL', '/^\s*HTMLSTYLESHEET\s+(.*)\s*$/i',     ['htmlstylesheet' => 1]],
            ['GLOBAL', '/^\s*IMAGEOUTPUTFILE\s+(.*)\s*$/i',    ['imageoutputfile' => 1]],
            ['GLOBAL', '/^\s*DATAOUTPUTFILE\s+(.*)\s*$/i',     ['dataoutputfile' => 1]],
            ['GLOBAL', '/^\s*IMAGEURI\s+(.*)\s*$/i',           ['imageuri' => 1]],
            ['GLOBAL', '/^\s*TITLE\s+(.*)\s*$/i',              ['title' => 1]],
            ['GLOBAL', '/^\s*HTMLSTYLE\s+(static|overlib)\s*$/i', ['htmlstyle' => 1]],
            ['GLOBAL', '/^\s*KEYFONT\s+(\d+)\s*$/i',           ['keyfont' => 1]],
            ['GLOBAL', '/^\s*TITLEFONT\s+(\d+)\s*$/i',         ['titlefont' => 1]],
            ['GLOBAL', '/^\s*TIMEFONT\s+(\d+)\s*$/i',          ['timefont' => 1]],
            ['GLOBAL', '/^\s*TITLEPOS\s+(-?\d+)\s+(-?\d+)\s*$/i',        ['titlex' => 1, 'titley' => 2]],
            ['GLOBAL', '/^\s*TITLEPOS\s+(-?\d+)\s+(-?\d+)\s+(.*)\s*$/i', ['titlex' => 1, 'titley' => 2, 'title' => 3]],
            ['GLOBAL', '/^\s*TIMEPOS\s+(-?\d+)\s+(-?\d+)\s*$/i',         ['timex' => 1, 'timey' => 2]],
            ['GLOBAL', '/^\s*TIMEPOS\s+(-?\d+)\s+(-?\d+)\s+(.*)\s*$/i',  ['timex' => 1, 'timey' => 2, 'stamptext' => 3]],
            ['GLOBAL', '/^\s*MINTIMEPOS\s+(-?\d+)\s+(-?\d+)\s*$/i',       ['mintimex' => 1, 'mintimey' => 2]],
            ['GLOBAL', '/^\s*MINTIMEPOS\s+(-?\d+)\s+(-?\d+)\s+(.*)\s*$/i',['mintimex' => 1, 'mintimey' => 2, 'minstamptext' => 3]],
            ['GLOBAL', '/^\s*MAXTIMEPOS\s+(-?\d+)\s+(-?\d+)\s*$/i',       ['maxtimex' => 1, 'maxtimey' => 2]],
            ['GLOBAL', '/^\s*MAXTIMEPOS\s+(-?\d+)\s+(-?\d+)\s+(.*)\s*$/i',['maxtimex' => 1, 'maxtimey' => 2, 'maxstamptext' => 3]],
            ['NODE', "/^\s*LABEL\s*$/i",   ['label' => '']],
            ['NODE', "/^\s*LABEL\s+(.*)\s*$/i", ['label' => 1]],
            ['(LINK|GLOBAL)', "/^\s*WIDTH\s+(\d+)\s*$/i",    ['width' => 1]],
            ['(LINK|GLOBAL)', "/^\s*HEIGHT\s+(\d+)\s*$/i",   ['height' => 1]],
            ['LINK', "/^\s*WIDTH\s+(\d+\.\d+)\s*$/i",        ['width' => 1]],
            ['LINK', '/^\s*ARROWSTYLE\s+(classic|compact)\s*$/i',  ['arrowstyle' => 1]],
            ['LINK', '/^\s*VIASTYLE\s+(curved|angled)\s*$/i',      ['viastyle' => 1]],
            ['LINK', '/^\s*INCOMMENT\s+(.*)\s*$/i',                ['comments[IN]' => 1]],
            ['LINK', '/^\s*OUTCOMMENT\s+(.*)\s*$/i',               ['comments[OUT]' => 1]],
            ['LINK', '/^\s*BWFONT\s+(\d+)\s*$/i',                  ['bwfont' => 1]],
            ['LINK', '/^\s*COMMENTFONT\s+(\d+)\s*$/i',             ['commentfont' => 1]],
            ['LINK', '/^\s*COMMENTSTYLE\s+(edge|center)\s*$/i',    ['commentstyle' => 1]],
            ['LINK', '/^\s*DUPLEX\s+(full|half)\s*$/i',            ['duplex' => 1]],
            ['LINK', '/^\s*BWSTYLE\s+(classic|angled)\s*$/i',      ['labelboxstyle' => 1]],
            ['LINK', '/^\s*LINKSTYLE\s+(twoway|oneway)\s*$/i',     ['linkstyle' => 1]],
            ['LINK', '/^\s*BWLABELPOS\s+(\d+)\s(\d+)\s*$/i',      ['labeloffset_in' => 1, 'labeloffset_out' => 2]],
            ['LINK', '/^\s*COMMENTPOS\s+(\d+)\s(\d+)\s*$/i',      ['commentoffset_in' => 1, 'commentoffset_out' => 2]],
            ['LINK', '/^\s*USESCALE\s+([A-Za-z][A-Za-z0-9_]*)\s*$/i', ['usescale' => 1]],
            ['LINK', '/^\s*USESCALE\s+([A-Za-z][A-Za-z0-9_]*)\s+(absolute|percent)\s*$/i', ['usescale' => 1, 'scaletype' => 2]],
            ['LINK', '/^\s*SPLITPOS\s+(\d+)\s*$/i',               ['splitpos' => 1]],
            ['NODE', '/^\s*LABELOFFSET\s+([-+]?\d+)\s+([-+]?\d+)\s*$/i',    ['labeloffsetx' => 1, 'labeloffsety' => 2]],
            ['NODE', '/^\s*LABELOFFSET\s+(C|NE|SE|NW|SW|N|S|E|W)\s*$/i',     ['labeloffset' => 1]],
            ['NODE', '/^\s*LABELOFFSET\s+((C|NE|SE|NW|SW|N|S|E|W)\d+)\s*$/i',['labeloffset' => 1]],
            ['NODE', '/^\s*LABELOFFSET\s+(-?\d+r\d+)\s*$/i',                  ['labeloffset' => 1]],
            ['NODE', '/^\s*LABELFONT\s+(\d+)\s*$/i',     ['labelfont' => 1]],
            ['NODE', '/^\s*LABELANGLE\s+(0|90|180|270)\s*$/i', ['labelangle' => 1]],
            ['LINK', '/^\s*OUTBWFORMAT\s+(.*)\s*$/i',  ['bwlabelformats[OUT]' => 1, 'labelstyle' => '--']],
            ['LINK', '/^\s*INBWFORMAT\s+(.*)\s*$/i',   ['bwlabelformats[IN]' => 1,  'labelstyle' => '--']],
            ['NODE', '/^\s*ICON\s+(\S+)\s*$/i', ['iconfile' => 1, 'iconscalew' => '#0', 'iconscaleh' => '#0']],
            ['NODE', '/^\s*ICON\s+(\d+)\s+(\d+)\s+(inpie|outpie|box|rbox|round|gauge|nink)\s*$/i', ['iconfile' => 3, 'iconscalew' => 1, 'iconscaleh' => 2]],
            ['NODE', '/^\s*ICON\s+(\d+)\s+(\d+)\s+(\S+)\s*$/i', ['iconfile' => 3, 'iconscalew' => 1, 'iconscaleh' => 2]],
            ['NODE', '/^\s*NOTES\s+(.*)\s*$/i',  ['notestext[IN]' => 1, 'notestext[OUT]' => 1]],
            ['LINK', '/^\s*NOTES\s+(.*)\s*$/i',  ['notestext[IN]' => 1, 'notestext[OUT]' => 1]],
            ['LINK', '/^\s*INNOTES\s+(.*)\s*$/i', ['notestext[IN]' => 1]],
            ['LINK', '/^\s*OUTNOTES\s+(.*)\s*$/i',['notestext[OUT]' => 1]],
            ['NODE', '/^\s*INFOURL\s+(.*)\s*$/i', ['infourl[IN]' => 1, 'infourl[OUT]' => 1]],
            ['LINK', '/^\s*INFOURL\s+(.*)\s*$/i', ['infourl[IN]' => 1, 'infourl[OUT]' => 1]],
            ['LINK', '/^\s*ININFOURL\s+(.*)\s*$/i',  ['infourl[IN]' => 1]],
            ['LINK', '/^\s*OUTINFOURL\s+(.*)\s*$/i',  ['infourl[OUT]' => 1]],
            ['NODE', '/^\s*OVERLIBCAPTION\s+(.*)\s*$/i', ['overlibcaption[IN]' => 1, 'overlibcaption[OUT]' => 1]],
            ['LINK', '/^\s*OVERLIBCAPTION\s+(.*)\s*$/i', ['overlibcaption[IN]' => 1, 'overlibcaption[OUT]' => 1]],
            ['LINK', '/^\s*INOVERLIBCAPTION\s+(.*)\s*$/i',  ['overlibcaption[IN]' => 1]],
            ['LINK', '/^\s*OUTOVERLIBCAPTION\s+(.*)\s*$/i', ['overlibcaption[OUT]' => 1]],
            ['(NODE|LINK)', "/^\s*ZORDER\s+([-+]?\d+)\s*$/i",     ['zorder' => 1]],
            ['(NODE|LINK)', "/^\s*OVERLIBWIDTH\s+(\d+)\s*$/i",    ['overlibwidth' => 1]],
            ['(NODE|LINK)', "/^\s*OVERLIBHEIGHT\s+(\d+)\s*$/i",   ['overlibheight' => 1]],
            ['NODE', "/^\s*POSITION\s+([-+]?\d+)\s+([-+]?\d+)\s*$/i", ['x' => 1, 'y' => 2]],
            ['NODE', "/^\s*POSITION\s+(\S+)\s+([-+]?\d+)\s+([-+]?\d+)\s*$/i",
                ['x' => 2, 'y' => 3, 'original_x' => 2, 'original_y' => 3, 'relative_to' => 1, 'relative_resolved' => false]],
            ['NODE', "/^\s*POSITION\s+(\S+)\s+([-+]?\d+)r(\d+)\s*$/i",
                ['x' => 2, 'y' => 3, 'original_x' => 2, 'original_y' => 3, 'relative_to' => 1, 'polar' => true, 'relative_resolved' => false]],
        ];

        foreach ($config_keywords as $keyword) {
            if (!preg_match("/" . $keyword[0] . "/", $last_seen)) {
                continue;
            }

            $statskey = str_replace(['/^\s*', '\s*$/i'], ['', ''], $last_seen . "-" . $keyword[1]);
            $this->usage_stats[$statskey] = $this->usage_stats[$statskey] ?? 0;

            if (!preg_match($keyword[1], $buffer, $matches)) {
                continue;
            }

            $this->usage_stats[$statskey]++;

            foreach ($keyword[2] as $key => $val) {
                if (preg_match("/^#(.*)/", $val, $m)) {
                    $val = $m[1];
                } elseif (is_numeric($val)) {
                    $val = $matches[$val];
                }

                if (preg_match('/^(.*)\[([^\]]+)\]$/', $key, $m)) {
                    $index = constant($m[2]);
                    $key = $m[1];
                    $curobj->{$key}[$index] = $val;
                } else {
                    $curobj->$key = $val;
                }
            }
            return 1;
        }

        return 0;
    }

    private function parseNodes(string $buffer, &$curlink, string &$last_seen, int $linecount): int
    {
        if (!preg_match("/^\s*NODES\s+(\S+)\s+(\S+)\s*$/i", $buffer, $matches)) {
            return 0;
        }
        if ($last_seen !== 'LINK') {
            return 0;
        }

        $valid_nodes = 2;
        $endoffset = [];
        $nodenames = [];

        foreach ([1, 2] as $i) {
            $endoffset[$i] = 'C';
            $nodenames[$i] = $matches[$i];

            if (preg_match("/:(NE|SE|NW|SW|N|S|E|W|C)(\d+)$/i", $matches[$i], $sub)) {
                $endoffset[$i] = $sub[1] . $sub[2];
                $nodenames[$i] = preg_replace("/:(NE|SE|NW|SW|N|S|E|W|C)\d+$/i", '', $matches[$i]);
                $this->need_size_precalc = true;
            }
            if (preg_match("/:(NE|SE|NW|SW|N|S|E|W|C)$/i", $matches[$i], $sub)) {
                $endoffset[$i] = $sub[1];
                $nodenames[$i] = preg_replace("/:(NE|SE|NW|SW|N|S|E|W|C)$/i", '', $matches[$i]);
                $this->need_size_precalc = true;
            }
            if (preg_match("/:(-?\d+r\d+)$/i", $matches[$i], $sub)) {
                $endoffset[$i] = $sub[1];
                $nodenames[$i] = preg_replace("/:(-?\d+r\d+)$/i", '', $matches[$i]);
                $this->need_size_precalc = true;
            }
            if (preg_match("/:([-+]?\d+):([-+]?\d+)$/i", $matches[$i], $sub)) {
                $xoff = $sub[1];
                $yoff = $sub[2];
                $endoffset[$i] = "$xoff:$yoff";
                $nodenames[$i] = preg_replace("/:$xoff:$yoff$/i", '', $matches[$i]);
                $this->need_size_precalc = true;
            }

            if (!array_key_exists($nodenames[$i], $this->nodes)) {
                wm_warn("Unknown node '" . $nodenames[$i] . "' on line $linecount of config\n");
                $valid_nodes--;
            }
        }

        if ($valid_nodes === 2) {
            $curlink->a = $this->nodes[$nodenames[1]];
            $curlink->b = $this->nodes[$nodenames[2]];
            $curlink->a_offset = $endoffset[1];
            $curlink->b_offset = $endoffset[2];
        } else {
            $last_seen = "broken";
        }

        return 1;
    }

    private function parseInclude(string $buffer, string &$last_seen): int
    {
        if ($last_seen !== 'GLOBAL') {
            return 0;
        }
        if (!preg_match("/^\s*INCLUDE\s+(.*)\s*$/i", $buffer, $matches)) {
            return 0;
        }

        if (file_exists($matches[1])) {
            wm_debug("Including '{$matches[1]}'\n");
            $this->ReadConfig($matches[1], true);
            $last_seen = "GLOBAL";
        } else {
            wm_warn("INCLUDE File '{$matches[1]}' not found!\n");
        }

        return 1;
    }

    private function parseTarget(string $buffer, &$curobj, array $args, string $last_seen, string $filename, int $linecount): int
    {
        if ($last_seen !== 'NODE' && $last_seen !== 'LINK') {
            return 0;
        }
        if (!preg_match("/^\s*TARGET\s+(.*)\s*$/i", $buffer)) {
            return 0;
        }
        if ($args[0] !== 'TARGET') {
            return 1;
        }

        $curobj->targets = [];
        array_shift($args);

        foreach ($args as $arg) {
            $newtarget = ['', '', $filename, $linecount, $arg, '', ''];
            if ($curobj) {
                wm_debug("  TARGET: $arg\n");
                $curobj->targets[] = $newtarget;
            }
        }

        return 1;
    }

    private function parseBwLabel(string $buffer, &$curobj, string $last_seen): int
    {
        if ($last_seen !== 'LINK') {
            return 0;
        }
        if (!preg_match("/^\s*BWLABEL\s+(bits|percent|unformatted|none)\s*$/i", $buffer, $matches)) {
            return 0;
        }

        $style = strtolower($matches[1]);
        $format_in  = '';
        $format_out = '';

        if ($style === 'percent')     { $format_in = FMT_PERC_IN;   $format_out = FMT_PERC_OUT; }
        if ($style === 'bits')        { $format_in = FMT_BITS_IN;   $format_out = FMT_BITS_OUT; }
        if ($style === 'unformatted') { $format_in = FMT_UNFORM_IN; $format_out = FMT_UNFORM_OUT; }

        $curobj->labelstyle = $style;
        $curobj->bwlabelformats[IN]  = $format_in;
        $curobj->bwlabelformats[OUT] = $format_out;

        return 1;
    }

    private function parseSet(string $buffer, &$curobj): int
    {
        global $weathermap_error_suppress;

        if (preg_match("/^\s*SET\s+(\S+)\s+(.*)\s*$/i", $buffer, $matches)) {
            $curobj->add_hint($matches[1], trim($matches[2]));
            if ($curobj->my_type() === 'map' && substr($matches[1], 0, 7) === 'nowarn_') {
                $weathermap_error_suppress[$matches[1]] = 1;
            }
            return 1;
        }

        if (preg_match("/^\s*SET\s+(\S+)\s*$/i", $buffer, $matches)) {
            $curobj->add_hint($matches[1], '');
            if ($curobj->my_type() === 'map' && substr($matches[1], 0, 7) === 'nowarn_') {
                $weathermap_error_suppress[$matches[1]] = 1;
            }
            return 1;
        }

        return 0;
    }

    private function parseOverlibgraph(string $buffer, &$curobj, string $last_seen): int
    {
        if (!preg_match("/^\s*(IN|OUT)?OVERLIBGRAPH\s+(.+)$/i", $buffer, $matches)) {
            return 0;
        }

        $this->has_overlibs = true;

        if ($last_seen === 'NODE' && $matches[1] !== '') {
            wm_warn("IN/OUTOVERLIBGRAPH make no sense for a NODE! [WMWARN42]\n");
            return 1;
        }

        if ($last_seen !== 'LINK' && $last_seen !== 'NODE') {
            return 0;
        }

        $urls = preg_split('/\s+/', $matches[2], -1, PREG_SPLIT_NO_EMPTY);

        if ($matches[1] === '') {
            $curobj->overliburl[IN]  = $urls;
            $curobj->overliburl[OUT] = $urls;
        } elseif ($matches[1] === 'IN') {
            $curobj->overliburl[IN]  = $urls;
        } elseif ($matches[1] === 'OUT') {
            $curobj->overliburl[OUT] = $urls;
        }

        return 1;
    }

    private function parseTemplate(string $buffer, &$curobj, string $last_seen, int $linecount, int $objectlinecount): int
    {
        if ($last_seen !== 'NODE' && $last_seen !== 'LINK') {
            return 0;
        }
        if (!preg_match("/^\s*TEMPLATE\s+(\S+)\s*$/i", $buffer, $matches)) {
            return 0;
        }

        $tname = $matches[1];
        $exists = ($last_seen === 'NODE' && isset($this->nodes[$tname]))
               || ($last_seen === 'LINK' && isset($this->links[$tname]));

        if ($exists) {
            $curobj->template = $tname;
            wm_debug("Resetting to template $last_seen $tname\n");
            $curobj->Reset($this);

            if ($objectlinecount > 1) {
                wm_warn("line $linecount: TEMPLATE is not first line of object. Some data may be lost. [WMWARN39]\n");
            }

            if ($last_seen === 'NODE') {
                $this->node_template_tree[$tname][] = $curobj->name;
            } else {
                $this->link_template_tree[$tname][] = $curobj->name;
            }
        } else {
            wm_warn("line $linecount: $last_seen TEMPLATE '$tname' doesn't exist! (if it does exist, check it's defined first) [WMWARN40]\n");
        }

        return 1;
    }

    private function parseVia(string $buffer, &$curlink, string $last_seen): int
    {
        if ($last_seen !== 'LINK') {
            return 0;
        }

        if (preg_match("/^\s*VIA\s+([-+]?\d+)\s+([-+]?\d+)\s*$/i", $buffer, $matches)) {
            $curlink->vialist[] = [$matches[1], $matches[2]];
            return 1;
        }

        if (preg_match("/^\s*VIA\s+(\S+)\s+([-+]?\d+)\s+([-+]?\d+)\s*$/i", $buffer, $matches)) {
            $curlink->vialist[] = [$matches[2], $matches[3], $matches[1]];
            return 1;
        }

        return 0;
    }

    private function parseUseScale(string $buffer, &$curnode, string $last_seen): int
    {
        if ($last_seen !== 'NODE') {
            return 0;
        }
        if (!preg_match("/^\s*USE(ICON)?SCALE\s+([A-Za-z][A-Za-z0-9_]*)(\s+(in|out))?(\s+(absolute|percent))?\s*$/i", $buffer, $matches)) {
            return 0;
        }

        $svar  = isset($matches[3]) ? trim($matches[3]) : '';
        $stype = isset($matches[6]) ? strtolower(trim($matches[6])) : 'percent';

        switch ($matches[1]) {
            case 'ICON':
                $varname  = 'iconscalevar';
                $uvarname = 'useiconscale';
                $tvarname = 'iconscaletype';
                break;
            default:
                $varname  = 'scalevar';
                $uvarname = 'usescale';
                $tvarname = 'scaletype';
                break;
        }

        if ($svar !== '') {
            $curnode->$varname = $svar;
        }
        $curnode->$tvarname = $stype;
        $curnode->$uvarname = $matches[2];

        return 1;
    }

    private function parseScale(string $buffer, int &$scalesseen): int
    {
        if (!preg_match("/^\s*SCALE\s+([A-Za-z][A-Za-z0-9_]*\s+)?(\-?\d+\.?\d*[munKMGT]?)\s+(\-?\d+\.?\d*[munKMGT]?)\s+(?:(\d+)\s+(\d+)\s+(\d+)(?:\s+(\d+)\s+(\d+)\s+(\d+))?|(none))\s*(.*)$/i",
            $buffer, $matches)) {
            return 0;
        }

        $scaleName = $matches[1] === '' ? 'DEFAULT' : trim($matches[1]);
        $key = $matches[2] . '_' . $matches[3];
        $entry = &$this->colours[$scaleName][$key];

        $entry['key']     = $key;
        $entry['tag']     = $matches[11];
        $entry['bottom']  = unformat_number($matches[2], $this->kilo);
        $entry['top']     = unformat_number($matches[3], $this->kilo);
        $entry['special'] = 0;

        if (isset($matches[10]) && $matches[10] === 'none') {
            $entry['red1'] = $entry['green1'] = $entry['blue1'] = -1;
        } else {
            $entry['red1']   = (int)$matches[4];
            $entry['green1'] = (int)$matches[5];
            $entry['blue1']  = (int)$matches[6];
        }

        if (isset($matches[7]) && $matches[7] !== '') {
            $entry['red2']   = (int)$matches[7];
            $entry['green2'] = (int)$matches[8];
            $entry['blue2']  = (int)$matches[9];
        }

        $this->numscales[$scaleName] = ($this->numscales[$scaleName] ?? 0) + 1;

        if ($scaleName === 'DEFAULT') {
            $scalesseen++;
        }

        return 1;
    }

    private function parseKeyPos(string $buffer): int
    {
        if (!preg_match("/^\s*KEYPOS\s+([A-Za-z][A-Za-z0-9_]*\s+)?(-?\d+)\s+(-?\d+)(.*)/i", $buffer, $matches)) {
            return 0;
        }

        $whichkey = trim($matches[1]) ?: 'DEFAULT';

        $this->keyx[$whichkey] = $matches[2];
        $this->keyy[$whichkey] = $matches[3];
        $extra = trim($matches[4]);

        if ($extra !== '') {
            $this->keytext[$whichkey] = $extra;
        }
        if (!isset($this->keytext[$whichkey])) {
            $this->keytext[$whichkey] = "DEFAULT TITLE";
        }
        if (!isset($this->keystyle[$whichkey])) {
            $this->keystyle[$whichkey] = "classic";
        }

        return 1;
    }

    private function parseFontDefine(string $buffer, int $linecount): int
    {
        // TrueType: filename + size
        if (preg_match("/^\s*FONTDEFINE\s+(\d+)\s+(\S+)\s+(\d+)\s*$/i", $buffer, $matches)) {
            if (!function_exists('imagettfbbox')) {
                wm_warn("imagettfbbox() is not a defined function. You don't seem to have FreeType compiled into your gd module. [WMWARN31]\n");
                return 1;
            }
            $bounds = @imagettfbbox($matches[3], 0, $matches[2], "Ignore me");
            if (isset($bounds[0])) {
                $this->fonts[$matches[1]] = new Font();
                $this->fonts[$matches[1]]->type = 'truetype';
                $this->fonts[$matches[1]]->file = $matches[2];
                $this->fonts[$matches[1]]->size = $matches[3];
            } else {
                wm_warn("Failed to load ttf font " . $matches[2] . " - at config line $linecount [WMWARN30]");
            }
            return 1;
        }

        // GD font: filename only
        if (preg_match("/^\s*FONTDEFINE\s+(\d+)\s+(\S+)\s*$/i", $buffer, $matches)) {
            $newfont = imageloadfont($matches[2]);
            if ($newfont) {
                $this->fonts[$matches[1]] = new Font();
                $this->fonts[$matches[1]]->type     = 'gd';
                $this->fonts[$matches[1]]->file      = $matches[2];
                $this->fonts[$matches[1]]->gdnumber  = $newfont;
            } else {
                wm_warn("Failed to load GD font: " . $matches[2] . " ($newfont) at config line $linecount [WMWARN32]\n");
            }
            return 1;
        }

        return 0;
    }

    private function parseKeyStyle(string $buffer): int
    {
        if (!preg_match("/^\s*KEYSTYLE\s+([A-Za-z][A-Za-z0-9_]+\s+)?(classic|horizontal|vertical|inverted|tags)\s?(\d+)?\s*$/i",
            $buffer, $matches)) {
            return 0;
        }

        $whichkey = trim($matches[1]) ?: 'DEFAULT';
        $this->keystyle[$whichkey] = strtolower($matches[2]);
        $this->keysize[$whichkey]  = (isset($matches[3]) && $matches[3] !== '')
            ? $matches[3]
            : $this->keysize['DEFAULT'];

        return 1;
    }

    private function parseKilo(string $buffer): int
    {
        if (!preg_match("/^\s*KILO\s+(\d+)\s*$/i", $buffer, $matches)) {
            return 0;
        }
        $this->kilo = $matches[1];
        return 1;
    }

    private function parseColor(string $buffer): int
    {
        if (!preg_match("/^\s*(TIME|TITLE|KEYBG|KEYTEXT|KEYOUTLINE|BG)COLOR\s+((\d+)\s+(\d+)\s+(\d+)|none)\s*$/i",
            $buffer, $matches)) {
            return 0;
        }

        $key = $matches[1];
        $val = strtolower($matches[2]);
        $matched = 0;

        if (isset($matches[3])) {
            $this->colours['DEFAULT'][$key] = [
                'red1' => $matches[3], 'green1' => $matches[4], 'blue1' => $matches[5],
                'bottom' => -2, 'top' => -1, 'special' => 1,
            ];
            $matched++;
        }

        if ($val === 'none' && ($key === 'KEYBG' || $key === 'KEYOUTLINE')) {
            $this->colours['DEFAULT'][$key] = [
                'red1' => -1, 'green1' => -1, 'blue1' => -1,
                'bottom' => -2, 'top' => -1, 'special' => 1,
            ];
            $matched++;
        }

        return $matched > 0 ? 1 : 0;
    }

    private function parseNodeColor(string $buffer, &$curnode, string $last_seen): int
    {
        if ($last_seen !== 'NODE') {
            return 0;
        }
        if (!preg_match("/^\s*(AICONOUTLINE|AICONFILL|LABELFONT|LABELFONTSHADOW|LABELBG|LABELOUTLINE)COLOR\s+((\d+)\s+(\d+)\s+(\d+)|none|contrast|copy)\s*$/i",
            $buffer, $matches)) {
            return 0;
        }

        $key   = $matches[1];
        $field = strtolower($key) . 'colour';
        $val   = strtolower($matches[2]);
        $matched = 0;

        if (isset($matches[3])) {
            $curnode->$field = [$matches[3], $matches[4], $matches[5]];
            $matched++;
        }
        if ($val === 'none' && in_array($key, ['LABELFONTSHADOW','LABELBG','LABELOUTLINE','AICONFILL','AICONOUTLINE'])) {
            $curnode->$field = [-1, -1, -1];
            $matched++;
        }
        if ($val === 'contrast' && $key === 'LABELFONT') {
            $curnode->$field = [-3, -3, -3];
            $matched++;
        }
        if ($matches[2] === 'copy' && $key === 'AICONFILL') {
            $curnode->$field = [-2, -2, -2];
            $matched++;
        }

        return $matched > 0 ? 1 : 0;
    }

    private function parseLinkColor(string $buffer, &$curlink, string $last_seen): int
    {
        if ($last_seen !== 'LINK') {
            return 0;
        }
        if (!preg_match("/^\s*(COMMENTFONT|BWBOX|BWFONT|BWOUTLINE|OUTLINE)COLOR\s+((\d+)\s+(\d+)\s+(\d+)|none|contrast|copy)\s*$/i",
            $buffer, $matches)) {
            return 0;
        }

        $key   = $matches[1];
        $field = strtolower($key) . 'colour';
        $val   = strtolower($matches[2]);
        $matched = 0;

        if (isset($matches[3])) {
            $curlink->$field = [$matches[3], $matches[4], $matches[5]];
            $matched++;
        }
        if ($val === 'none' && in_array($key, ['BWBOX','BWOUTLINE','OUTLINE','KEYOUTLINE','KEYBG'])) {
            $curlink->$field = [-1, -1, -1];
            $matched++;
        }
        if ($val === 'contrast' && $key === 'COMMENTFONT') {
            $curlink->$field = [-3, -3, -3];
            $matched++;
        }

        return $matched > 0 ? 1 : 0;
    }

    private function parseArrowStyleCustom(string $buffer, &$curlink, string $last_seen): int
    {
        if ($last_seen !== 'LINK') {
            return 0;
        }
        if (!preg_match("/^\s*ARROWSTYLE\s+(\d+)\s+(\d+)\s*$/i", $buffer, $matches)) {
            return 0;
        }
        $curlink->arrowstyle = $matches[1] . ' ' . $matches[2];
        return 1;
    }

    // -------------------------------------------------------------------------
    // Post-loop helpers
    // -------------------------------------------------------------------------

    private function applyDefaultScales(int &$scalesseen): void
    {
        if ($scalesseen > 0) {
            wm_debug("Already have $scalesseen scales, no defaults added.\n");
            return;
        }

        wm_debug("Adding default SCALE colour set (no SCALE lines seen).\n");

        $defaults = [
            '0_0'    => ['bottom' => 0,   'top' => 0,   'red1' => 192, 'green1' => 192, 'blue1' => 192, 'special' => 0],
            '0_1'    => ['bottom' => 0,   'top' => 1,   'red1' => 255, 'green1' => 255, 'blue1' => 255, 'special' => 0],
            '1_10'   => ['bottom' => 1,   'top' => 10,  'red1' => 140, 'green1' => 0,   'blue1' => 255, 'special' => 0],
            '10_25'  => ['bottom' => 10,  'top' => 25,  'red1' => 32,  'green1' => 32,  'blue1' => 255, 'special' => 0],
            '25_40'  => ['bottom' => 25,  'top' => 40,  'red1' => 0,   'green1' => 192, 'blue1' => 255, 'special' => 0],
            '40_55'  => ['bottom' => 40,  'top' => 55,  'red1' => 0,   'green1' => 240, 'blue1' => 0,   'special' => 0],
            '55_70'  => ['bottom' => 55,  'top' => 70,  'red1' => 240, 'green1' => 240, 'blue1' => 0,   'special' => 0],
            '70_85'  => ['bottom' => 70,  'top' => 85,  'red1' => 255, 'green1' => 192, 'blue1' => 0,   'special' => 0],
            '85_100' => ['bottom' => 85,  'top' => 100, 'red1' => 255, 'green1' => 0,   'blue1' => 0,   'special' => 0],
        ];

        foreach ($defaults as $key => $def) {
            $this->colours['DEFAULT'][$key] = $def;
            $this->colours['DEFAULT'][$key]['key'] = $key;
            $scalesseen++;
        }

        $this->add_hint("key_hidezero_DEFAULT", 1);
    }

    private function buildZLayers(): void
    {
        wm_debug("Building cache of z-layers and finalising bandwidth.\n");

        $allitems = array_merge(array_values($this->nodes), array_values($this->links));

        foreach ($allitems as $ky => $vl) {
            $item = &$allitems[$ky];
            $z = $item->zorder;

            if (!isset($this->seen_zlayers[$z]) || !is_array($this->seen_zlayers[$z])) {
                $this->seen_zlayers[$z] = [];
            }
            $this->seen_zlayers[$z][] = $item;

            if ($item->my_type() === 'LINK') {
                $this->links[$item->name]->max_bandwidth_in  = unformat_number($item->max_bandwidth_in_cfg,  $this->kilo);
                $this->links[$item->name]->max_bandwidth_out = unformat_number($item->max_bandwidth_out_cfg, $this->kilo);
            } elseif ($item->my_type() === 'NODE') {
                $this->nodes[$item->name]->max_bandwidth_in  = unformat_number($item->max_bandwidth_in_cfg,  $this->kilo);
                $this->nodes[$item->name]->max_bandwidth_out = unformat_number($item->max_bandwidth_out_cfg, $this->kilo);
            } else {
                wm_warn("Internal bug - found an item of type: " . $item->my_type() . "\n");
            }

            wm_debug(sprintf("   Setting bandwidth on " . $item->my_type() . " $item->name (%s -> %d bps, %s -> %d bps, KILO = %d)\n",
                $item->max_bandwidth_in_cfg, $item->max_bandwidth_in,
                $item->max_bandwidth_out_cfg, $item->max_bandwidth_out, $this->kilo));
        }

        wm_debug("Found " . count($this->seen_zlayers) . " z-layers including builtins (0,100).\n");
    }

    private function resolveRelativePositions(): void
    {
        wm_debug("Resolving relative positions for NODEs...\n");

        $i = 100;
        do {
            $skipped = 0;
            $set = 0;

            foreach ($this->nodes as $node) {
                if ($node->relative_to === '' || $node->relative_resolved) {
                    continue;
                }

                wm_debug("Resolving relative position for NODE " . $node->name . " to " . $node->relative_to . "\n");

                if (!array_key_exists($node->relative_to, $this->nodes)) {
                    wm_warn("NODE " . $node->name . " has a relative position to an unknown node! [WMWARN10]\n");
                    continue;
                }

                $parent = $this->nodes[$node->relative_to];
                if ($parent->relative_to !== '' && !$parent->relative_resolved) {
                    wm_debug("Skipping unresolved relative_to. Let's hope it's not a circular one\n");
                    $skipped++;
                    continue;
                }

                $rx = $parent->x;
                $ry = $parent->y;

                if ($node->polar) {
                    $angle    = $node->x;
                    $distance = $node->y;
                    $newpos_x = $rx + $distance * sin(deg2rad($angle));
                    $newpos_y = $ry - $distance * cos(deg2rad($angle));
                } else {
                    $newpos_x = $rx + $this->nodes[$node->name]->x;
                    $newpos_y = $ry + $this->nodes[$node->name]->y;
                }

                wm_debug("->$newpos_x,$newpos_y\n");
                $this->nodes[$node->name]->x = $newpos_x;
                $this->nodes[$node->name]->y = $newpos_y;
                $this->nodes[$node->name]->relative_resolved = true;
                $set++;
            }

            wm_debug("Relative Positions Cycle $i - set $set and Skipped $skipped for unresolved dependencies\n");
            $i--;
        } while ($set > 0 && $i !== 0);

        if ($skipped > 0) {
            wm_warn("There are Circular dependencies in relative POSITION lines for $skipped nodes. [WMWARN11]\n");
        }

        wm_debug("-----------------------------------\n");
    }

    private function runPreProcessors(): void
    {
        wm_debug("Running Pre-Processing Plugins...\n");
        foreach ($this->preprocessclasses as $pre_class) {
            wm_debug("Running $pre_class->run()\n");
            $this->plugins['pre'][$pre_class]->run($this);
        }
        wm_debug("Finished Pre-Processing Plugins...\n");
    }

    // -------------------------------------------------------------------------

    function ReadConfig_Commit(&$curobj)
    {
        if (is_null($curobj)) {
            return;
        }

        $last_seen = $curobj->my_type();

        if ($last_seen === 'NODE') {
            $this->nodes[$curobj->name] = $curobj;
            wm_debug("Saving Node: " . $curobj->name . "\n");
            if ($curobj->template === 'DEFAULT') {
                $this->node_template_tree['DEFAULT'][] = $curobj->name;
            }
        }

        if ($last_seen === 'LINK') {
            $this->links[$curobj->name] = $curobj;
            wm_debug(isset($curobj->a) && isset($curobj->b)
                ? "Saving Link: " . $curobj->name . "\n"
                : "Saving Template-Only Link: " . $curobj->name . "\n");
            if ($curobj->template === 'DEFAULT') {
                $this->link_template_tree['DEFAULT'][] = $curobj->name;
            }
        }
    }
}
