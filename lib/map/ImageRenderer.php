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

trait ImageRenderer
{

    function myimagestring($image, $fontnumber, $x, $y, $string, $colour, $angle = 0)
    {
        // if it's supposed to be a special font, and it hasn't been defined, then fall through
        if ($fontnumber > 5 && !isset($this->fonts[$fontnumber])) {
            wm_warn("Using a non-existent special font ($fontnumber) - falling back to internal GD fonts [WMWARN03]\n");
            if ($angle != 0) {
                wm_warn("Angled text doesn't work with non-FreeType fonts [WMWARN02]\n");
            }
            $fontnumber = 5;
        }

        if (($fontnumber > 0) && ($fontnumber < 6)) {
            imagestring($image, $fontnumber, (int)$x, (int)$y - imagefontheight($fontnumber), $string, $colour);
            if ($angle != 0) {
                wm_warn("Angled text doesn't work with non-FreeType fonts [WMWARN02]\n");
            }
        } else {
            // look up what font is defined for this slot number
            if ($this->fonts[$fontnumber]->type == 'truetype') {
                wimagettftext($image, $this->fonts[$fontnumber]->size, $angle, $x, $y,
                    $colour, $this->fonts[$fontnumber]->file, $string);
            }

            if ($this->fonts[$fontnumber]->type == 'gd') {
                imagestring($image, $this->fonts[$fontnumber]->gdnumber,
                    $x, $y - imagefontheight($this->fonts[$fontnumber]->gdnumber),
                    $string, $colour);
                if ($angle != 0) {
                    wm_warn("Angled text doesn't work with non-FreeType fonts [WMWARN04]\n");
                }
            }
        }
    }


    function myimagestringsize($fontnumber, $string)
    {
        $linecount = 1;

        $lines = explode("\n", $string);
        $linecount = count($lines);
        $maxlinelength = 0;
        foreach ($lines as $line) {
            $l = strlen($line);
            if ($l > $maxlinelength) {
                $maxlinelength = $l;
            }
        }

        if (($fontnumber > 0) && ($fontnumber < 6)) {
            return array(imagefontwidth($fontnumber) * $maxlinelength, $linecount * imagefontheight($fontnumber));
        } else {
            // look up what font is defined for this slot number
            if (!isset($this->fonts[$fontnumber])) {
                wm_warn("Using a non-existent special font ($fontnumber) - falling back to internal GD fonts [WMWARN36]\n");
                $fontnumber = 5;
                return array(imagefontwidth($fontnumber) * $maxlinelength, $linecount * imagefontheight($fontnumber));
            } else {
                if ($this->fonts[$fontnumber]->type == 'truetype') {
                    $ysize = 0;
                    $xsize = 0;
                    foreach ($lines as $line) {
                        $bounds = imagettfbbox($this->fonts[$fontnumber]->size, 0, $this->fonts[$fontnumber]->file,
                            $line);
                        $cx = $bounds[4] - $bounds[0];
                        $cy = $bounds[1] - $bounds[5];
                        if ($cx > $xsize) {
                            $xsize = $cx;
                        }
                        $ysize += ($cy * 1.2);
                    }

                    return (array($xsize, $ysize));
                }

                if ($this->fonts[$fontnumber]->type == 'gd') {
                    return array(
                        imagefontwidth($this->fonts[$fontnumber]->gdnumber) * $maxlinelength,
                        $linecount * imagefontheight($this->fonts[$fontnumber]->gdnumber)
                    );
                }
            }
        }
    }


    function DrawLabelRotated(
        $im,
        $x,
        $y,
        $angle,
        $text,
        $font,
        $padding,
        $linkname,
        $textcolour,
        $bgcolour,
        $outlinecolour,
        &$map,
        $direction
    ) {
        [$strwidth, $strheight] = $this->myimagestringsize($font, $text);

        if (abs($angle) > 90) {
            $angle -= 180;
        }
        if ($angle < -180) {
            $angle += 360;
        }

        $rangle = -deg2rad($angle);

        $extra = 3;

        $x1 = $x - ($strwidth / 2) - $padding - $extra;
        $x2 = $x + ($strwidth / 2) + $padding + $extra;
        $y1 = $y - ($strheight / 2) - $padding - $extra;
        $y2 = $y + ($strheight / 2) + $padding + $extra;

        // a box. the last point is the start point for the text.
        $points = array($x1, $y1, $x1, $y2, $x2, $y2, $x2, $y1, $x - $strwidth / 2, $y + $strheight / 2 + 1);
        $npoints = count($points) / 2;

        rotateAboutPoint($points, $x, $y, $rangle);

        if ($bgcolour != array
            (
                -1,
                -1,
                -1
            )) {
            $bgcol = myimagecolorallocate($im, $bgcolour[0], $bgcolour[1], $bgcolour[2]);
            wimagefilledpolygon($im, array_slice($points,0,8), 4, $bgcol);
        }

        if ($outlinecolour != array
            (
                -1,
                -1,
                -1
            )) {
            $outlinecol = myimagecolorallocate($im, $outlinecolour[0], $outlinecolour[1], $outlinecolour[2]);
            wimagepolygon($im, array_slice($points,0,8), 4, $outlinecol);
        }

        $textcol = myimagecolorallocate($im, $textcolour[0], $textcolour[1], $textcolour[2]);
        $this->myimagestring($im, $font, $points[8], $points[9], $text, $textcol, $angle);

        $areaname = "LINK:L" . $map->links[$linkname]->id . ':' . ($direction + 2);

        // the rectangle is about half the size in the HTML, and easier to optimise/detect in the browser
        if ($angle == 0) {
            $map->imap->addArea("Rectangle", $areaname, '', array($x1, $y1, $x2, $y2));
            wm_debug("Adding Rectangle imagemap for $areaname\n");
        } else {
            $map->imap->addArea("Polygon", $areaname, '', $points);
            wm_debug("Adding Poly imagemap for $areaname\n");
        }

    }


    function DrawLegend_Horizontal($im, $scalename = "DEFAULT", $width = 400)
    {
        $title = $this->keytext[$scalename];
        $nscales = $this->numscales[$scalename];

        wm_debug("Drawing $nscales colours into SCALE\n");

        $font = $this->keyfont;
        $scalefactor = $width / 100;

        [$tilewidth, $tileheight] = $this->myimagestringsize($font, "100%");
        $box_left = 0;
        $scale_left = $box_left + 4 + $scalefactor / 2;
        $box_right = $scale_left + $width + $tilewidth + 4 + $scalefactor / 2;

        $box_top = 0;
        $scale_top = $box_top + $tileheight + 6;
        $scale_bottom = $scale_top + $tileheight * 1.5;
        $box_bottom = $scale_bottom + $tileheight * 2 + 6;

        $scale_im = imagecreatetruecolor($box_right + 1, $box_bottom + 1);
        $scale_ref = 'gdref_legend_' . $scalename;

        // Start with a transparent box, in case the fill or outline colour is 'none'
        imageSaveAlpha($scale_im, true);
        $nothing = imagecolorallocatealpha($scale_im, 128, 0, 0, 127);
        imagefill($scale_im, 0, 0, $nothing);

        $this->AllocateScaleColours($scale_im, $scale_ref);

        if (!is_none($this->colours['DEFAULT']['KEYBG'])) {
            wimagefilledrectangle($scale_im, $box_left, $box_top, $box_right, $box_bottom,
                $this->colours['DEFAULT']['KEYBG'][$scale_ref]);
        }
        if (!is_none($this->colours['DEFAULT']['KEYOUTLINE'])) {
            wimagerectangle($scale_im, $box_left, $box_top, $box_right, $box_bottom,
                $this->colours['DEFAULT']['KEYOUTLINE'][$scale_ref]);
        }
        $this->myimagestring($scale_im, $font, $scale_left, $scale_bottom + $tileheight * 2 + 2, $title,
            $this->colours['DEFAULT']['KEYTEXT'][$scale_ref]);

        for ($p = 0; $p <= 100; $p++) {
            $dx = $p * $scalefactor;

            if (($p % 25) == 0) {
                imageline($scale_im, $scale_left + $dx, $scale_top - $tileheight,
                    $scale_left + $dx, $scale_bottom + $tileheight,
                    $this->colours['DEFAULT']['KEYTEXT'][$scale_ref]);
                $labelstring = sprintf("%d%%", $p);
                $this->myimagestring($scale_im, $font, $scale_left + $dx + 2, $scale_top - 2, $labelstring,
                    $this->colours['DEFAULT']['KEYTEXT'][$scale_ref]);
            }

            [$col] = $this->NewColourFromPercent($p, $scalename);
            if ($col->is_real()) {
                $cc = $col->gdallocate($scale_im);
                wimagefilledrectangle($scale_im, $scale_left + $dx - $scalefactor / 2, $scale_top,
                    $scale_left + $dx + $scalefactor / 2, $scale_bottom,
                    $cc);
            }
        }

        imagecopy($im, $scale_im, $this->keyx[$scalename], $this->keyy[$scalename], 0, 0, imagesx($scale_im),
            imagesy($scale_im));
        $this->keyimage[$scalename] = $scale_im;

        $rx = $this->keyx[$scalename];
        $ry = $this->keyy[$scalename];

        $this->imap->addArea("Rectangle", "LEGEND:$scalename", '',
            array($rx + $box_left, $ry + $box_top, $rx + $box_right, $ry + $box_bottom));
    }


    function DrawLegend_Vertical($im, $scalename = "DEFAULT", $height = 400, $inverted = false)
    {
        $title = $this->keytext[$scalename];

        $colours = $this->colours[$scalename];
        $nscales = $this->numscales[$scalename];

        wm_debug("Drawing $nscales colours into SCALE\n");

        $font = $this->keyfont;

        $x = $this->keyx[$scalename];
        $y = $this->keyy[$scalename];

        $scalefactor = $height / 100;

        [$tilewidth, $tileheight] = $this->myimagestringsize($font, "100%");

        $box_left = 0;
        $box_top = 0;

        $scale_left = $box_left + $scalefactor * 2 + 4;
        $scale_right = $scale_left + $tileheight * 2;
        $box_right = $scale_right + $tilewidth + $scalefactor * 2 + 4;

        [$titlewidth, $titleheight] = $this->myimagestringsize($font, $title);
        if (($box_left + $titlewidth + $scalefactor * 3) > $box_right) {
            $box_right = $box_left + $scalefactor * 4 + $titlewidth;
        }

        $scale_top = $box_top + 4 + $scalefactor + $tileheight * 2;
        $scale_bottom = $scale_top + $height;
        $box_bottom = $scale_bottom + $scalefactor + $tileheight / 2 + 4;

        $scale_im = imagecreatetruecolor($box_right + 1, $box_bottom + 1);
        $scale_ref = 'gdref_legend_' . $scalename;

        // Start with a transparent box, in case the fill or outline colour is 'none'
        imageSaveAlpha($scale_im, true);
        $nothing = imagecolorallocatealpha($scale_im, 128, 0, 0, 127);
        imagefill($scale_im, 0, 0, $nothing);

        $this->AllocateScaleColours($scale_im, $scale_ref);

        if (!is_none($this->colours['DEFAULT']['KEYBG'])) {
            wimagefilledrectangle($scale_im, $box_left, $box_top, $box_right, $box_bottom,
                $this->colours['DEFAULT']['KEYBG']['gdref1']);
        }
        if (!is_none($this->colours['DEFAULT']['KEYOUTLINE'])) {
            wimagerectangle($scale_im, $box_left, $box_top, $box_right, $box_bottom,
                $this->colours['DEFAULT']['KEYOUTLINE']['gdref1']);
        }

        $this->myimagestring($scale_im, $font, $scale_left - $scalefactor, $scale_top - $tileheight, $title,
            $this->colours['DEFAULT']['KEYTEXT']['gdref1']);

        $updown = 1;
        if ($inverted) {
            $updown = -1;
        }


        for ($p = 0; $p <= 100; $p++) {
            if ($inverted) {
                $dy = (100 - $p) * $scalefactor;
            } else {
                $dy = $p * $scalefactor;
            }

            if (($p % 25) == 0) {
                imageline($scale_im, $scale_left - $scalefactor, $scale_top + $dy,
                    $scale_right + $scalefactor, $scale_top + $dy,
                    $this->colours['DEFAULT']['KEYTEXT'][$scale_ref]);
                $labelstring = sprintf("%d%%", $p);
                $this->myimagestring($scale_im, $font, $scale_right + $scalefactor * 2,
                    $scale_top + $dy + $tileheight / 2,
                    $labelstring, $this->colours['DEFAULT']['KEYTEXT'][$scale_ref]);
            }

            [$col] = $this->NewColourFromPercent($p, $scalename);
            if ($col->is_real()) {
                $cc = $col->gdallocate($scale_im);
                wimagefilledrectangle($scale_im, $scale_left, $scale_top + $dy - $scalefactor / 2,
                    $scale_right, $scale_top + $dy + $scalefactor / 2,
                    $cc);
            }
        }

        imagecopy($im, $scale_im, $this->keyx[$scalename], $this->keyy[$scalename], 0, 0, imagesx($scale_im),
            imagesy($scale_im));
        $this->keyimage[$scalename] = $scale_im;

        $rx = $this->keyx[$scalename];
        $ry = $this->keyy[$scalename];
        $this->imap->addArea("Rectangle", "LEGEND:$scalename", '',
            array($rx + $box_left, $ry + $box_top, $rx + $box_right, $ry + $box_bottom));
    }


    function DrawLegend_Classic($im, $scalename = "DEFAULT", $use_tags = false)
    {
        $title = $this->keytext[$scalename];

        $colours = $this->colours[$scalename];
        usort($colours, array("Weathermap", "coloursort"));

        $nscales = $this->numscales[$scalename];

        wm_debug("Drawing $nscales colours into SCALE\n");

        $hide_zero = intval($this->get_hint("key_hidezero_" . $scalename));
        $hide_percent = intval($this->get_hint("key_hidepercent_" . $scalename));

        // did we actually hide anything?
        $hid_zero = false;
        if (($hide_zero == 1) && isset($colours['0_0'])) {
            $nscales--;
            $hid_zero = true;
        }

        $font = $this->keyfont;

        $x = $this->keyx[$scalename];
        $y = $this->keyy[$scalename];

        [$tilewidth, $tileheight] = $this->myimagestringsize($font, "MMMM");
        $tileheight = $tileheight * 1.1;
        $tilespacing = $tileheight + 2;

        if (($this->keyx[$scalename] >= 0) && ($this->keyy[$scalename] >= 0)) {

            [$minwidth, $junk] = $this->myimagestringsize($font, 'MMMM 100%-100%');
            [$minminwidth, $junk] = $this->myimagestringsize($font, 'MMMM ');
            [$boxwidth, $junk] = $this->myimagestringsize($font, $title);

            if ($use_tags) {
                $max_tag = 0;
                foreach ($colours as $colour) {
                    if (isset($colour['tag'])) {
                        [$w, $junk] = $this->myimagestringsize($font, $colour['tag']);
                        if ($w > $max_tag) {
                            $max_tag = $w;
                        }
                    }
                }

                // now we can tweak the widths, appropriately to allow for the tag strings
                if (($max_tag + $minminwidth) > $minwidth) {
                    $minwidth = $minminwidth + $max_tag;
                }
            }

            $minwidth += 10;
            $boxwidth += 10;

            if ($boxwidth < $minwidth) {
                $boxwidth = $minwidth;
            }

            $boxheight = $tilespacing * ($nscales + 1) + 10;

            $boxx = $x;
            $boxy = $y;
            $boxx = 0;
            $boxy = 0;

            // allow for X11-style negative positioning
            if ($boxx < 0) {
                $boxx += $this->width;
            }

            if ($boxy < 0) {
                $boxy += $this->height;
            }

            $scale_im = imagecreatetruecolor($boxwidth + 1, $boxheight + 1);
            $scale_ref = 'gdref_legend_' . $scalename;

            // Start with a transparent box, in case the fill or outline colour is 'none'
            imageSaveAlpha($scale_im, true);
            $nothing = imagecolorallocatealpha($scale_im, 128, 0, 0, 127);
            imagefill($scale_im, 0, 0, $nothing);

            $this->AllocateScaleColours($scale_im, $scale_ref);

            if (!is_none($this->colours['DEFAULT']['KEYBG'])) {
                wimagefilledrectangle($scale_im, $boxx, $boxy, $boxx + $boxwidth, $boxy + $boxheight,
                    $this->colours['DEFAULT']['KEYBG'][$scale_ref]);
            }
            if (!is_none($this->colours['DEFAULT']['KEYOUTLINE'])) {
                wimagerectangle($scale_im, $boxx, $boxy, $boxx + $boxwidth, $boxy + $boxheight,
                    $this->colours['DEFAULT']['KEYOUTLINE'][$scale_ref]);
            }

            $this->myimagestring($scale_im, $font, $boxx + 4, $boxy + 4 + $tileheight, $title,
                $this->colours['DEFAULT']['KEYTEXT'][$scale_ref]);

            $i = 1;

            foreach ($colours as $colour) {
                if (!isset($colour['special']) || $colour['special'] == 0) // if ( 1==1 || $colour['bottom'] >= 0)
                {
                    // pick a value in the middle...
                    $value = ($colour['bottom'] + $colour['top']) / 2;
                    wm_debug(sprintf("%f-%f (%f)  %d %d %d\n", $colour['bottom'], $colour['top'], $value,
                        $colour['red1'], $colour['green1'], $colour['blue1']));

                    if (($hide_zero == 0) || $colour['key'] != '0_0') {
                        $y = $boxy + $tilespacing * $i + 8;
                        $x = $boxx + 6;

                        $fudgefactor = 0;
                        if ($hid_zero && $colour['bottom'] == 0) {
                            // calculate a small offset that can be added, which will hide the zero-value in a
                            // gradient, but not make the scale incorrect. A quarter of a pixel should do it.
                            $fudgefactor = ($colour['top'] - $colour['bottom']) / ($tilewidth * 4);
                        }

                        // if it's a gradient, red2 is defined, and we need to sweep the values
                        if (isset($colour['red2'])) {
                            for ($n = 0; $n <= $tilewidth; $n++) {
                                $value
                                    = $fudgefactor + $colour['bottom'] + ($n / $tilewidth) * ($colour['top'] - $colour['bottom']);
                                [$ccol, $junk] = $this->NewColourFromPercent($value, $scalename, "", false);
                                $col = $ccol->gdallocate($scale_im);
                                wimagefilledrectangle($scale_im, $x + $n, $y, $x + $n, $y + $tileheight,
                                    $col);
                            }
                        } else {
                            // pick a value in the middle...
                            [$ccol, $junk] = $this->NewColourFromPercent($value, $scalename, "", false);
                            $col = $ccol->gdallocate($scale_im);
                            wimagefilledrectangle($scale_im, $x, $y, $x + $tilewidth, $y + $tileheight,
                                $col);
                        }

                        if ($use_tags) {
                            $labelstring = "";
                            if (isset($colour['tag'])) {
                                $labelstring = $colour['tag'];
                            }
                        } else {
                            $labelstring = sprintf("%s-%s", $colour['bottom'], $colour['top']);
                            if ($hide_percent == 0) {
                                $labelstring .= "%";
                            }
                        }

                        $this->myimagestring($scale_im, $font, $x + 4 + $tilewidth, $y + $tileheight, $labelstring,
                            $this->colours['DEFAULT']['KEYTEXT'][$scale_ref]);
                        $i++;
                    }
                    imagecopy($im, $scale_im, $this->keyx[$scalename], $this->keyy[$scalename], 0, 0,
                        imagesx($scale_im), imagesy($scale_im));
                    $this->keyimage[$scalename] = $scale_im;

                }
            }

            $this->imap->addArea("Rectangle", "LEGEND:$scalename", '',
                array(
                    $this->keyx[$scalename],
                    $this->keyy[$scalename],
                    $this->keyx[$scalename] + $boxwidth,
                    $this->keyy[$scalename] + $boxheight
                ));

        }
    }


    private function formatTimestamp(string $format, int $timestamp): string
    {
        $map = ['%b'=>'M','%B'=>'F','%d'=>'d','%e'=>'j','%Y'=>'Y','%y'=>'y',
                '%H'=>'H','%I'=>'h','%M'=>'i','%S'=>'s','%p'=>'A','%m'=>'m',
                '%j'=>'z','%A'=>'l','%a'=>'D','%Z'=>'T','%n'=>"\n",'%%'=>'%'];
        return date(strtr($format, $map), $timestamp);
    }


    function DrawTimestamp($im, $font, $colour, $which = "")
    {
        switch ($which) {
            case "MIN":
                $stamp = $this->formatTimestamp($this->minstamptext, $this->min_data_time);
                $pos_x = $this->mintimex;
                $pos_y = $this->mintimey;
                break;
            case "MAX":
                $stamp = $this->formatTimestamp($this->maxstamptext, $this->max_data_time);
                $pos_x = $this->maxtimex;
                $pos_y = $this->maxtimey;
                break;
            default:
                $stamp = $this->datestamp;
                $pos_x = $this->timex;
                $pos_y = $this->timey;
                break;
        }

        [$boxwidth, $boxheight] = $this->myimagestringsize($font, $stamp);

        $x = $this->width - $boxwidth;
        $y = $boxheight;

        if (($pos_x != 0) && ($pos_y != 0)) {
            $x = $pos_x;
            $y = $pos_y;
        }

        $this->myimagestring($im, $font, $x, $y, $stamp, $colour);
        $this->imap->addArea("Rectangle", $which . "TIMESTAMP", '', array($x, $y, $x + $boxwidth, $y - $boxheight));
    }


    function DrawTitle($im, $font, $colour)
    {
        $string = $this->ProcessString($this->title, $this);

        if ($this->get_hint('screenshot_mode') == 1) {
            $string = screenshotify($string);
        }

        [$boxwidth, $boxheight] = $this->myimagestringsize($font, $string);

        $x = 10;
        $y = $this->titley - $boxheight;

        if (($this->titlex >= 0) && ($this->titley >= 0)) {
            $x = $this->titlex;
            $y = $this->titley;
        }

        $this->myimagestring($im, $font, $x, $y, $string, $colour);

        $this->imap->addArea("Rectangle", "TITLE", '', array($x, $y, $x + $boxwidth, $y - $boxheight));
    }


    function AllocateScaleColours($im, $refname = 'gdref1')
    {
        foreach ($this->colours as $scalename => $colours) {
            foreach ($colours as $key => $colour) {
                if ((!isset($this->colours[$scalename][$key]['red2'])) && (!isset($this->colours[$scalename][$key][$refname]))) {
                    $r = $colour['red1'];
                    $g = $colour['green1'];
                    $b = $colour['blue1'];
                    wm_debug("AllocateScaleColours: $scalename/$refname $key ($r,$g,$b)\n");
                    $this->colours[$scalename][$key][$refname] = myimagecolorallocate($im, $r, $g, $b);
                }
            }
        }
    }


    function DrawMap(
        $filename = '',
        $thumbnailfile = '',
        $thumbnailmax = 250,
        $withnodes = true,
        $use_via_overlay = false,
        $use_rel_overlay = false
    ) {
        wm_debug("Trace: DrawMap()\n");
        metadump("# start", true);
        $bgimage = null;
        if ($this->configfile != "") {
            $this->cachefile_version = crc32(file_get_contents($this->configfile));
        } else {
            $this->cachefile_version = crc32("........");
        }

        wm_debug("Running Post-Processing Plugins...\n");
        foreach ($this->postprocessclasses as $post_class) {
            wm_debug("Running $post_class" . "->run()\n");
            $this->plugins['post'][$post_class]->run($this);

        }
        wm_debug("Finished Post-Processing Plugins...\n");

        wm_debug("=====================================\n");
        wm_debug("Start of Map Drawing\n");


        // if we're running tests, we force the time to a particular value,
        // so the output can be compared to a reference image more easily
        $testmode = intval($this->get_hint("testmode"));

        if ($testmode == 1) {
            $maptime = 1270813792;
            date_default_timezone_set('UTC');
        } else {
            $maptime = time();
        }
        $this->datestamp = $this->formatTimestamp($this->stamptext, $maptime);

        // do the basic prep work
        if ($this->background != '') {
            if (is_readable($this->background)) {
                $bgimage = imagecreatefromfile($this->background);

                if (!$bgimage) {
                    wm_warn
                    ("Failed to open background image.  One possible reason: Is your BACKGROUND really a PNG?\n");
                } else {
                    $this->width = imagesx($bgimage);
                    $this->height = imagesy($bgimage);
                }
            } else {
                wm_warn
                ("Your background image file could not be read. Check the filename, and permissions, for "
                    . $this->background . "\n");
            }
        }

        $image = wimagecreatetruecolor($this->width, $this->height);

        if (!$image) {
            wm_warn
            ("Couldn't create output image in memory (" . $this->width . "x" . $this->height . ").");
        } else {
            ImageAlphaBlending($image, true);

            // by here, we should have a valid image handle

            // save this away, now
            $this->image = $image;

            $this->white = myimagecolorallocate($image, 255, 255, 255);
            $this->black = myimagecolorallocate($image, 0, 0, 0);
            $this->grey = myimagecolorallocate($image, 192, 192, 192);
            $this->selected = myimagecolorallocate($image, 255, 0, 0); // for selections in the editor

            $this->AllocateScaleColours($image);

            // fill with background colour anyway, in case the background image failed to load
            wimagefilledrectangle($image, 0, 0, $this->width, $this->height, $this->colours['DEFAULT']['BG']['gdref1']);

            if ($bgimage) {
                imagecopy($image, $bgimage, 0, 0, 0, 0, $this->width, $this->height);
                imagedestroy($bgimage);
            }

            // Now it's time to draw a map

            // do the node rendering stuff first, regardless of where they are actually drawn.
            // this is so we can get the size of the nodes, which links will need if they use offsets
            foreach ($this->nodes as $node) {
                // don't try and draw template nodes
                wm_debug("Pre-rendering " . $node->name . " to get bounding boxes.\n");
                if (!is_null($node->x)) {
                    $this->nodes[$node->name]->pre_render($image, $this);
                }
            }

            $all_layers = array_keys($this->seen_zlayers);
            sort($all_layers);

            foreach ($all_layers as $z) {
                $z_items = $this->seen_zlayers[$z];
                wm_debug("Drawing layer $z\n");
                // all the map 'furniture' is fixed at z=1000
                if ($z == 1000) {
                    foreach ($this->colours as $scalename => $colours) {
                        wm_debug("Drawing KEY for $scalename if necessary.\n");

                        if ((isset($this->numscales[$scalename])) && (isset($this->keyx[$scalename])) && ($this->keyx[$scalename] >= 0) && ($this->keyy[$scalename] >= 0)) {
                            if ($this->keystyle[$scalename] == 'classic') {
                                $this->DrawLegend_Classic($image, $scalename, false);
                            }
                            if ($this->keystyle[$scalename] == 'horizontal') {
                                $this->DrawLegend_Horizontal($image, $scalename, $this->keysize[$scalename]);
                            }
                            if ($this->keystyle[$scalename] == 'vertical') {
                                $this->DrawLegend_Vertical($image, $scalename, $this->keysize[$scalename]);
                            }
                            if ($this->keystyle[$scalename] == 'inverted') {
                                $this->DrawLegend_Vertical($image, $scalename, $this->keysize[$scalename], true);
                            }
                            if ($this->keystyle[$scalename] == 'tags') {
                                $this->DrawLegend_Classic($image, $scalename, true);
                            }
                        }
                    }

                    $this->DrawTimestamp($image, $this->timefont, $this->colours['DEFAULT']['TIME']['gdref1']);
                    if (!is_null($this->min_data_time)) {
                        $this->DrawTimestamp($image, $this->timefont, $this->colours['DEFAULT']['TIME']['gdref1'],
                            "MIN");
                        $this->DrawTimestamp($image, $this->timefont, $this->colours['DEFAULT']['TIME']['gdref1'],
                            "MAX");
                    }
                    $this->DrawTitle($image, $this->titlefont, $this->colours['DEFAULT']['TITLE']['gdref1']);
                }

                if (is_array($z_items)) {
                    foreach ($z_items as $it) {
                        if (strtolower(get_class($it)) == 'weathermaplink') {
                            // only draw LINKs if they have NODES defined (not templates)
                            // (also, check if the link still exists - if this is in the editor, it may have been deleted by now)
                            if (isset($this->links[$it->name]) && isset($it->a) && isset($it->b)) {
                                wm_debug("Drawing LINK " . $it->name . "\n");
                                $this->links[$it->name]->Draw($image, $this);
                            }
                        }
                        if (strtolower(get_class($it)) == 'weathermapnode') {
                            if ($withnodes) {
                                // don't try and draw template nodes
                                if (isset($this->nodes[$it->name]) && !is_null($it->x)) {
                                    wm_debug("Drawing NODE " . $it->name . "\n");
                                    $this->nodes[$it->name]->NewDraw($image, $this);
                                    $ii = 0;
                                    foreach ($this->nodes[$it->name]->boundingboxes as $bbox) {
                                        $areaname = "NODE:N" . $it->id . ":" . $ii;
                                        $this->imap->addArea("Rectangle", $areaname, '', $bbox);
                                        wm_debug("Adding imagemap area");
                                        $ii++;
                                    }
                                    wm_debug("Added $ii bounding boxes too\n");
                                }
                            }
                        }
                    }
                }
            }

            $overlay = myimagecolorallocate($image, 200, 0, 0);

            // for the editor, we can optionally overlay some other stuff
            if ($this->context == 'editor') {
                if ($use_rel_overlay) {
                    // first, we can show relatively positioned NODEs
                    foreach ($this->nodes as $node) {
                        if ($node->relative_to != '') {
                            $rel_x = $this->nodes[$node->relative_to]->x;
                            $rel_y = $this->nodes[$node->relative_to]->y;
                            imagearc($image, $node->x, $node->y,
                                15, 15, 0, 360, $overlay);
                            imagearc($image, $node->x, $node->y,
                                16, 16, 0, 360, $overlay);

                            imageline($image, $node->x, $node->y,
                                $rel_x, $rel_y, $overlay);
                        }
                    }
                }

                if ($use_via_overlay) {
                    // then overlay VIAs, so they can be seen
                    foreach ($this->links as $link) {
                        foreach ($link->vialist as $via) {
                            if (isset($via[2])) {
                                $x = $this->nodes[$via[2]]->x + $via[0];
                                $y = $this->nodes[$via[2]]->y + $via[1];
                            } else {
                                $x = $via[0];
                                $y = $via[1];
                            }
                            imagearc($image, $x, $y, 10, 10, 0, 360, $overlay);
                            imagearc($image, $x, $y, 12, 12, 0, 360, $overlay);
                        }
                    }
                }
            }

            // Ready to output the results...

            if ($filename == 'null') {
                // do nothing at all - we just wanted the HTML AREAs for the editor or HTML output
            } else {
                if ($filename == '') {
                    imagepng($image);
                } else {
                    $result = false;
                    $functions = true;
                    if (function_exists('imagejpeg') && preg_match("/\.jpg/i", $filename)) {
                        wm_debug("Writing JPEG file to $filename\n");
                        $result = imagejpeg($image, $filename);
                    } elseif (function_exists('imagegif') && preg_match("/\.gif/i", $filename)) {
                        wm_debug("Writing GIF file to $filename\n");
                        $result = imagegif($image, $filename);
                    } elseif (function_exists('imagepng') && preg_match("/\.png/i", $filename)) {
                        wm_debug("Writing PNG file to $filename\n");
                        $result = imagepng($image, $filename);
                    } else {
                        wm_warn("Failed to write map image. No function existed for the image format you requested. [WMWARN12]\n");
                        $functions = false;
                    }

                    if (($result == false) && ($functions == true)) {
                        if (file_exists($filename)) {
                            wm_warn("Failed to overwrite existing image file $filename - permissions of existing file are wrong? [WMWARN13]");
                        } else {
                            wm_warn("Failed to create image file $filename - permissions of output directory are wrong? [WMWARN14]");
                        }
                    }
                }
            }

            if ($this->context == 'editor2') {
                $cachefile = $this->cachefolder . DIRECTORY_SEPARATOR . dechex(crc32($this->configfile)) . "_bg." . $this->cachefile_version . ".png";
                imagepng($image, $cachefile);
                $cacheuri = $this->cachefolder . '/' . dechex(crc32($this->configfile)) . "_bg." . $this->cachefile_version . ".png";
                $this->mapcache = $cacheuri;
            }

            if (function_exists('imagecopyresampled')) {
                // if one is specified, and we can, write a thumbnail too
                if ($thumbnailfile != '') {
                    $result = false;
                    if ($this->width > $this->height) {
                        $factor = ($thumbnailmax / $this->width);
                    } else {
                        $factor = ($thumbnailmax / $this->height);
                    }

                    $this->thumb_width = $this->width * $factor;
                    $this->thumb_height = $this->height * $factor;

                    $imagethumb = imagecreatetruecolor($this->thumb_width, $this->thumb_height);
                    imagecopyresampled($imagethumb, $image, 0, 0, 0, 0, $this->thumb_width, $this->thumb_height,
                        $this->width, $this->height);
                    $result = imagepng($imagethumb, $thumbnailfile);
                    imagedestroy($imagethumb);


                    if (($result == false)) {
                        if (file_exists($filename)) {
                            wm_warn("Failed to overwrite existing image file $filename - permissions of existing file are wrong? [WMWARN15]");
                        } else {
                            wm_warn("Failed to create image file $filename - permissions of output directory are wrong? [WMWARN16]");
                        }
                    }
                }
            } else {
                wm_warn("Skipping thumbnail creation, since we don't have the necessary function. [WMWARN17]");
            }
            imagedestroy($image);
        }
    }

}
