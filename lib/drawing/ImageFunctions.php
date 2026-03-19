<?php

// take the same set of points that imagepolygon does, but don't close the shape
function imagepolyline($image, $points, $npoints, $color)
{
	for ($i=0; $i < ($npoints - 1); $i++) 
	{ 
		imageline($image, $points[$i * 2], $points[$i * 2 + 1], $points[$i * 2 + 2], $points[$i * 2 + 3],
		$color); 
	}
}

// draw a filled round-cornered rectangle
function imagefilledroundedrectangle($image  , $x1  , $y1  , $x2  , $y2  , $radius, $color)
{
	imagefilledrectangle($image, $x1,$y1+$radius, $x2,$y2-$radius, $color);
	imagefilledrectangle($image, $x1+$radius,$y1, $x2-$radius,$y2, $color);
	
	imagefilledarc($image, $x1+$radius, $y1+$radius, $radius*2, $radius*2, 0, 360, $color, IMG_ARC_PIE);
	imagefilledarc($image, $x2-$radius, $y1+$radius, $radius*2, $radius*2, 0, 360, $color, IMG_ARC_PIE);
	
	imagefilledarc($image, $x1+$radius, $y2-$radius, $radius*2, $radius*2, 0, 360, $color, IMG_ARC_PIE);
	imagefilledarc($image, $x2-$radius, $y2-$radius, $radius*2, $radius*2, 0, 360, $color, IMG_ARC_PIE);
	
	# bool imagefilledarc  ( resource $image  , int $cx  , int $cy  , int $width  , int $height  , int $start  , int $end  , int $color  , int $style  )
}

// draw a round-cornered rectangle
function imageroundedrectangle( $image  , $x1  , $y1  , $x2  , $y2  , $radius, $color )
{

	imageline($image, $x1+$radius, $y1, $x2-$radius, $y1, $color);
	imageline($image, $x1+$radius, $y2, $x2-$radius, $y2, $color);
	imageline($image, $x1, $y1+$radius, $x1, $y2-$radius, $color);
	imageline($image, $x2, $y1+$radius, $x2, $y2-$radius, $color);
	
	imagearc($image, $x1+$radius, $y1+$radius, $radius*2, $radius*2, 180, 270, $color);
	imagearc($image, $x2-$radius, $y1+$radius, $radius*2, $radius*2, 270, 360, $color);
	imagearc($image, $x1+$radius, $y2-$radius, $radius*2, $radius*2, 90, 180, $color);
	imagearc($image, $x2-$radius, $y2-$radius, $radius*2, $radius*2, 0, 90, $color);
}

function imagecreatefromfile($filename)
{
	$bgimage=NULL;
	$formats = imagetypes();
	if (is_readable($filename))
	{
		list($width, $height, $type, $attr) = getimagesize($filename);
		switch($type)
		{
		case IMAGETYPE_GIF:
			if(imagetypes() & IMG_GIF)
			{
				$bgimage=imagecreatefromgif($filename);
			}
			else
			{
				wm_warn("Image file $filename is GIF, but GIF is not supported by your GD library. [WMIMG01]\n");    
			}
			break;

		case IMAGETYPE_JPEG:
			if(imagetypes() & IMG_JPEG)
			{
				$bgimage=imagecreatefromjpeg($filename);
			}
			else
			{
				wm_warn("Image file $filename is JPEG, but JPEG is not supported by your GD library. [WMIMG02]\n");    
			}
			break;

		case IMAGETYPE_PNG:
			if(imagetypes() & IMG_PNG)
			{
				$bgimage=imagecreatefrompng($filename);
			}
			else
			{
				wm_warn("Image file $filename is PNG, but PNG is not supported by your GD library. [WMIMG03]\n");    
			}
			break;

		default:
			wm_warn("Image file $filename wasn't recognised (type=$type). Check format is supported by your GD library. [WMIMG04]\n");
			break;
		}
	}
	else
	{
		wm_warn("Image file $filename is unreadable. Check permissions. [WMIMG05]\n");    
	}
	return $bgimage;
}

// taken from here:
// http://www.php.net/manual/en/function.imagefilter.php#62395
// ( with some bugfixes and changes)
// 
// Much nicer colorization than imagefilter does, AND no special requirements.
// Preserves white, black and transparency.
//
function imagecolorize($im, $r, $g, $b)
{
    //We will create a monochromatic palette based on
    //the input color
    //which will go from black to white
    //Input color luminosity: this is equivalent to the
    //position of the input color in the monochromatic
    //palette
    $lum_inp = round(255 * ($r + $g + $b) / 765); //765=255*3

    //We fill the palette entry with the input color at its
    //corresponding position

    $pal[$lum_inp]['r'] = $r;
    $pal[$lum_inp]['g'] = $g;
    $pal[$lum_inp]['b'] = $b;

    //Now we complete the palette, first we'll do it to
    //the black,and then to the white.

    //FROM input to black
    //===================
    //how many colors between black and input
    $steps_to_black = $lum_inp;

    //The step size for each component
    if ($steps_to_black)
    {
        $step_size_red = $r / $steps_to_black;
        $step_size_green = $g / $steps_to_black;
        $step_size_blue = $b / $steps_to_black;
    }

    for ($i = $steps_to_black; $i >= 0; $i--)
    {
        $pal[$steps_to_black - $i]['r'] = $r - round($step_size_red * $i);
        $pal[$steps_to_black - $i]['g'] = $g - round($step_size_green * $i);
        $pal[$steps_to_black - $i]['b'] = $b - round($step_size_blue * $i);
    }

    //From input to white:
    //===================
    //how many colors between input and white
    $steps_to_white = 255 - $lum_inp;

    if ($steps_to_white)
    {
        $step_size_red = (255 - $r) / $steps_to_white;
        $step_size_green = (255 - $g) / $steps_to_white;
        $step_size_blue = (255 - $b) / $steps_to_white;
    }
    else
        $step_size_red = $step_size_green = $step_size_blue = 0;

    //The step size for each component
    for ($i = ($lum_inp + 1); $i <= 255; $i++)
    {
        $pal[$i]['r'] = $r + round($step_size_red * ($i - $lum_inp));
        $pal[$i]['g'] = $g + round($step_size_green * ($i - $lum_inp));
        $pal[$i]['b'] = $b + round($step_size_blue * ($i - $lum_inp));
    }

    //--- End of palette creation

    //Now,let's change the original palette into the one we
    //created
    for ($c = 0; $c < imagecolorstotal($im); $c++)
    {
        $col = imagecolorsforindex($im, $c);
        $lum_src = round(255 * ($col['red'] + $col['green'] + $col['blue']) / 765);
        $col_out = $pal[$lum_src];

   #     printf("%d (%d,%d,%d) -> %d -> (%d,%d,%d)\n", $c,
   #                $col['red'], $col['green'], $col['blue'],
   #                $lum_src,
   #                $col_out['r'], $col_out['g'], $col_out['b']
   #             );

        imagecolorset($im, $c, $col_out['r'], $col_out['g'], $col_out['b']);
    }
   
    return($im);
}
// A series of wrapper functions around all the GD function calls
// - I added these in so I could make a 'metafile' easily of all the
//   drawing commands for a map. I have a basic Perl-Cairo script that makes
//   anti-aliased maps from these, using Cairo instead of GD.

function metadump($string, $truncate=FALSE)
{
	// comment this line to get a metafile for this map
	return;

	if($truncate)
	{
		$fd = fopen("metadump.txt","w+");
	}
	else
	{
		$fd = fopen("metadump.txt","a");
	}
	fputs($fd,$string."\n");
	fclose($fd);
}

function metacolour(&$col)
{
	return ($col['red1']." ".$col['green1']." ".$col['blue1']);
}

function wimagecreate($width,$height)
{
	metadump("NEWIMAGE $width $height");
	return(imagecreate($width,$height));
}

function wimagefilledrectangle( $image ,$x1, $y1, $x2, $y2, $color )
{
	if ($color===NULL) return;
	
	$col = imagecolorsforindex($image, $color);
	$r = $col['red']; $g = $col['green']; $b = $col['blue']; $a = $col['alpha'];
	$r = $r/255; $g=$g/255; $b=$b/255; $a=(127-$a)/127;

	metadump("FRECT $x1 $y1 $x2 $y2 $r $g $b $a");
	return(imagefilledrectangle( $image ,(int)$x1, (int)$y1, (int)$x2, (int)$y2, $color ));
}

function wimagerectangle( $image ,$x1, $y1, $x2, $y2, $color )
{
	if ($color===NULL) return;
	
	$col = imagecolorsforindex($image, $color);
	$r = $col['red']; $g = $col['green']; $b = $col['blue']; $a = $col['alpha'];
	$r = $r/255; $g=$g/255; $b=$b/255; $a=(127-$a)/127;

	metadump("RECT $x1 $y1 $x2 $y2 $r $g $b $a");
	return(imagerectangle( $image ,$x1, $y1, $x2, $y2, $color ));
}

function wimagepolygon($image, $points, $num_points, $color)
{
	if ($color===NULL) return;
	
	$col = imagecolorsforindex($image, $color);
	$r = $col['red']; $g = $col['green']; $b = $col['blue']; $a = $col['alpha'];
	$r = $r/255; $g=$g/255; $b=$b/255; $a=(127-$a)/127;
	
	$pts = "";
	for ($i=0; $i < $num_points; $i++)
        {
		$pts .= $points[$i * 2]." ";
		$pts .= $points[$i * 2+1]." ";
        }
	
	metadump("POLY $num_points ".$pts." $r $g $b $a");

	return(imagepolygon($image, $points, $color));
}

function wimagefilledpolygon($image, $points, $num_points, $color)
{
	if ($color===NULL) return;
	
	$col = imagecolorsforindex($image, $color);
	$r = $col['red']; $g = $col['green']; $b = $col['blue']; $a = $col['alpha'];
	$r = $r/255; $g=$g/255; $b=$b/255; $a=(127-$a)/127;
	
	$pts = "";
	for ($i=0; $i < $num_points; $i++)
        {
		$pts .= $points[$i * 2]." ";
		$pts .= $points[$i * 2+1]." ";
        }
	
	metadump("FPOLY $num_points ".$pts." $r $g $b $a");

	return(imagefilledpolygon($image, $points, $color));
}

function wimagecreatetruecolor($width, $height)
{
	

	metadump("BLANKIMAGE $width $height");

	return imagecreatetruecolor($width,$height);

}

function wimagettftext($image, $size, $angle, $x, $y, $color, $file, $string)
{
	if ($color===NULL) return;

	$col = imagecolorsforindex($image, $color);
	$r = $col['red']; $g = $col['green']; $b = $col['blue']; $a = $col['alpha'];
	$r = $r/255; $g=$g/255; $b=$b/255; $a=(127-$a)/127;

	metadump("TEXT $x $y $angle $size $file $r $g $b $a $string");

	return(imagettftext($image, $size, $angle, $x, $y, $color, $file, $string));
}

function wm_draw_marker_diamond($im, $col, $x, $y, $size=10)
{
	$points = array();
	
	$points []= $x-$size;
	$points []= $y;
	
	$points []= $x;
	$points []= $y-$size;
	
	$points []= $x+$size;
	$points []= $y;
	
	$points []= $x;
	$points []= $y+$size;
		
	$num_points = 4;

	imagepolygon($im, $points, $num_points, $col);
}

function wm_draw_marker_box($im, $col, $x, $y, $size=10)
{
	$points = array();
	
	$points []= $x-$size;
	$points []= $y-$size;
	
	$points []= $x+$size;
	$points []= $y-$size;
	
	$points []= $x+$size;
	$points []= $y+$size;
	
	$points []= $x-$size;
	$points []= $y+$size;
		
	$num_points = 4;

	imagepolygon($im, $points, $num_points, $col);
}

function wm_draw_marker_circle($im, $col, $x, $y, $size=10)
{
	imagearc($im,$x, $y ,$size,$size,0,360,$col);
}

function draw_spine_chain($im,$spine,$col, $size=10)
{
    $newn = count($spine);
        
    for ($i=0; $i < $newn; $i++)
    {   
		imagearc($im,$spine[$i][X],$spine[$i][Y],$size,$size,0,360,$col);
    }
}

function dump_spine($spine)
{
	print "===============\n";
	for($i=0; $i<count($spine); $i++)
	{
		printf ("  %3d: %d,%d (%d)\n", $i, $spine[$i][X], $spine[$i][Y], $spine[$i][DISTANCE] );		
	}
	print "===============\n";
}

function draw_spine($im, $spine,$col)
{
    $max_i = count($spine)-1;
    
    for ($i=0; $i <$max_i; $i++)
    {
        imageline($im,
                    $spine[$i][X],$spine[$i][Y],
                    $spine[$i+1][X],$spine[$i+1][Y],
                    $col
                    );
    }
}

/**
 * A duplicate of the HTML output code in the weathermap CLI utility,
 * for use by the test-output stuff.
 *
 * @global string $WEATHERMAP_VERSION
 * @param string $htmlfile
 * @param WeatherMap $map
 */
    function TestOutput_HTML($htmlfile, &$map)
    {
        global $WEATHERMAP_VERSION;

        $fd=fopen($htmlfile, 'w');
        fwrite($fd,
                '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd"><html xmlns="http://www.w3.org/1999/xhtml"><head>');
        if($map->htmlstylesheet != '') fwrite($fd,'<link rel="stylesheet" type="text/css" href="'.$map->htmlstylesheet.'" />');
        fwrite($fd,'<meta http-equiv="refresh" content="300" /><title>' . $map->ProcessString($map->title, $map) . '</title></head><body>');

        if ($map->htmlstyle == "overlib")
        {
                fwrite($fd,
                        "<div id=\"overDiv\" style=\"position:absolute; visibility:hidden; z-index:1000;\"></div>\n");
                fwrite($fd,
                        "<script type=\"text/javascript\" src=\"overlib.js\"><!-- overLIB (c) Erik Bosrup --></script> \n");
        }

        fwrite($fd, $map->MakeHTML());
        fwrite($fd,
                '<hr /><span id="byline">Network Map created with <a href="http://www.network-weathermap.com/?vs='
                . $WEATHERMAP_VERSION . '">PHP Network Weathermap v' . $WEATHERMAP_VERSION
                . '</a></span></body></html>');
        fclose ($fd);
    }

	    /**
     * Run a config-based test.
     * Read in config from $conffile, and produce an image and HTML output
     * Optionally Produce a new config file in $newconffile (for testing WriteConfig)
     * Optionally collect config-keyword-coverage stats about this config file
     *
     *
     *
     * @param string $conffile
     * @param string $imagefile
     * @param string $htmlfile
     * @param string $newconffile
     * @param string $coveragefile
     */

    function TestOutput_RunTest($conffile, $imagefile, $htmlfile, $newconffile, $coveragefile)
    {
        global $weathermap_map;
	global $WEATHERMAP_VERSION;

        $map = new WeatherMap();
        if($coveragefile != '') {
            $map->SeedCoverage();
            if(file_exists($coveragefile) ) {
                $map->LoadCoverage($coveragefile);
            }
        }
        $weathermap_map = $conffile;
        $map->ReadConfig($conffile);
	$skip = 0;
	$nwarns = 0;

	if( ! strstr($WEATHERMAP_VERSION, "dev" )) {
		# Allow tests to be from the future. Global SET in test file can excempt test from running
		# SET REQUIRES_VERSION 0.98
		# but don't check if the current version is a dev version
		$required_version = $map->get_hint("REQUIRES_VERSION");

		if($required_version != "") {	
			// doesan't need to be complete, just in the right order
			$known_versions = array("0.97","0.97a","0.97b","0.98","0.98a");
			$my_version = array_search($WEATHERMAP_VERSION,$known_versions);	
			$req_version = array_search($required_version,$known_versions);	
			if($req_version > $my_version) {
				$skip = 1;
				$nwarns = -1;
			}
		}
	}

	if( $skip == 0) {
       		$map->ReadData();
       		$map->DrawMap($imagefile);
        	$map->imagefile=$imagefile;
        	if($htmlfile != '') {
        	    TestOutput_HTML($htmlfile, $map);
        	}
        	if($newconffile != '') {
        	    $map->WriteConfig($newconffile);
        	}
        	if($coveragefile != '') {
        	    $map->SaveCoverage($coveragefile);
        	}
        	$nwarns = $map->warncount;
	}
	
        $map->CleanUp();
        unset ($map);

        return intval($nwarns);
    }
