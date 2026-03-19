<?php

// Utility functions
// Check for GD & PNG support This is just in here so that both the editor and CLI can use it without the need for another file
function wm_module_checks()
{
	if (!extension_loaded('gd'))
	{
		wm_warn ("\n\nNo image (gd) extension is loaded. This is required by weathermap. [WMWARN20]\n\n");
		wm_warn ("\nrun check.php to check PHP requirements.\n\n");

		return (FALSE);
	}

	if (!function_exists('imagecreatefrompng'))
	{
		wm_warn ("Your GD php module doesn't support PNG format. [WMWARN21]\n");
		wm_warn ("\nrun check.php to check PHP requirements.\n\n");
		return (FALSE);
	}

	if (!function_exists('imagecreatetruecolor'))
	{
		wm_warn ("Your GD php module doesn't support truecolor. [WMWARN22]\n");
		wm_warn ("\nrun check.php to check PHP requirements.\n\n");
		return (FALSE);
	}

	if (!function_exists('imagecopyresampled'))
	{
		wm_warn ("Your GD php module doesn't support thumbnail creation (imagecopyresampled). [WMWARN23]\n");
	}
	return (TRUE);
}

function wm_debug($string)
{
	global $weathermap_debugging;
	global $weathermap_map;
	global $weathermap_debug_suppress;

	if ($weathermap_debugging)
	{
		$calling_fn = "";
		if(function_exists("debug_backtrace"))
		{
			$bt = debug_backtrace();
			$index = 1;
        		$function = (isset($bt[$index]['function']) ? $bt[$index]['function'] : '');
			$index = 0;
			$file = (isset($bt[$index]['file']) ? basename($bt[$index]['file']) : '');
        		$line = (isset($bt[$index]['line']) ? $bt[$index]['line'] : '');

			$calling_fn = " [$function@$file:$line]";

			if(is_array($weathermap_debug_suppress) && in_array(strtolower($function),$weathermap_debug_suppress)) return;
		}

		// use Cacti's debug log, if we are running from the poller
		if (function_exists('debug_log_insert') && (!function_exists('show_editor_startpage')))
		{ cacti_log("DEBUG:$calling_fn " . ($weathermap_map==''?'':$weathermap_map.": ") . rtrim($string), true, "WEATHERMAP"); }
		else
		{
			$stderr=fopen('php://stderr', 'w');
			fwrite($stderr, "DEBUG:$calling_fn " . ($weathermap_map==''?'':$weathermap_map.": ") . $string);
			fclose ($stderr);

			if(1==0)
			{
				$log=fopen('debug.log', 'a');
				fwrite($log, "DEBUG:$calling_fn " . ($weathermap_map==''?'':$weathermap_map.": ") . $string);
				fclose ($log);
			}
		}
	}
}

function wm_warn($string,$notice_only=FALSE)
{
	global $weathermap_map;
	global $weathermap_warncount;
    global $weathermap_error_suppress;

	$message = "";
	$code = "";

	if(preg_match('/\[(WM\w+)\]/', $string, $matches)) {
        $code = $matches[1];
    }

    if ( (true === is_array($weathermap_error_suppress))
                && ( true === in_array(strtoupper($code), $weathermap_error_suppress))) {

                // This error code has been deliberately disabled.
                return;
    }

	if(!$notice_only)
	{
		$weathermap_warncount++;
		$message .= "WARNING: ";
	}

	$message .= ($weathermap_map==''?'':$weathermap_map.": ") . rtrim($string);

	// use Cacti's debug log, if we are running from the poller
	if (function_exists('cacti_log') && (!function_exists('show_editor_startpage')))
	{ cacti_log($message, true, "WEATHERMAP"); }
	else
	{
		$stderr=fopen('php://stderr', 'w');
		fwrite($stderr, $message."\n");
		fclose ($stderr);
	}
}

function js_escape($str, $wrap=TRUE)
{
	$str=str_replace('\\', '\\\\', $str);
	$str=str_replace('"', '\\"', $str);

	if($wrap) $str='"' . $str . '"';

	return ($str);
}

// ParseString is based on code from:
// http://www.webscriptexpert.com/Php/Space-Separated%20Tag%20Parser/

function wm_parse_string($input)
{
    $output = array();            // Array of Output
    $cPhraseQuote = null;   // Record of the quote that opened the current phrase
    $sPhrase = null;                // Temp storage for the current phrase we are building

    // Define some constants
    $sTokens = " \t";    // Space, Tab
    $sQuotes = "'\"";                // Single and Double Quotes

    // Start the State Machine
    do
    {
        // Get the next token, which may be the first
        $sToken = isset($sToken)? strtok($sTokens) : strtok($input, $sTokens);

        // Are there more tokens?
        if ($sToken === false)
        {
                // Ensure that the last phrase is marked as ended
                $cPhraseQuote = null;
        }
        else
        {
                // Are we within a phrase or not?
                if ($cPhraseQuote !== null)
                {
                        // Will the current token end the phrase?
                        if (substr($sToken, -1, 1) === $cPhraseQuote)
                        {
                                // Trim the last character and add to the current phrase, with a single leading space if necessary
                                if (strlen($sToken) > 1) $sPhrase .= ((strlen($sPhrase) > 0)? ' ' : null) . substr($sToken, 0, -1);
                                $cPhraseQuote = null;
                        }
                        else
                        {
                                // If not, add the token to the phrase, with a single leading space if necessary
                                $sPhrase .= ((strlen($sPhrase) > 0)? ' ' : null) . $sToken;
                        }
                }
                else
                {
                        // Will the current token start a phrase?
                        if (strpos($sQuotes, $sToken[0]) !== false)
                        {
                                // Will the current token end the phrase?
                                if ((strlen($sToken) > 1) && ($sToken[0] === substr($sToken, -1, 1)))
                                {
                                        // The current token begins AND ends the phrase, trim the quotes
                                        $sPhrase = substr($sToken, 1, -1);
                                }
                                else
                                {
                                        // Remove the leading quote
                                        $sPhrase = substr($sToken, 1);
                                        $cPhraseQuote = $sToken[0];
                                }
                        }
                        else
                                $sPhrase = $sToken;
                }
        }

        // If, at this point, we are not within a phrase, the prepared phrase is complete and can be added to the array
        if (($cPhraseQuote === null) && ($sPhrase != null))
        {
            $output[] = $sPhrase;
            $sPhrase = null;
        }
    }
    while ($sToken !== false);      // Stop when we receive FALSE from strtok()

    return $output;
}

// wrapper around imagecolorallocate to try and re-use palette slots where possible
function myimagecolorallocate($image, $red, $green, $blue)
{
	// it's possible that we're being called early - just return straight away, in that case
	if(!isset($image)) return(-1);

	// Make sure color values are in a sane range
	if($red > 255) { $red = 255; }
	if($green > 255) { $green = 255; }
	if($blue > 255) { $blue = 255; }
	if($red < 0) { $red = 0; }
	if($green < 0) { $green = 0; }
	if($blue < 0) { $blue = 0; }

	$existing=imagecolorexact($image, $red, $green, $blue);

	if ($existing > -1)
		return $existing;

	return (imagecolorallocate($image, $red, $green, $blue));
}

// PHP < 5.3 doesn't support anonymous functions, so here's a little function for screenshotify
function screenshotify_xxx($matches)
{
	return str_repeat('x',strlen($matches[1]));
}


function screenshotify($input)
{
	$output = $input;
	$output = preg_replace ( '/\b\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\b/', "127.0.0.1", $output );
	$output = preg_replace_callback ( '/([A-Za-z]{3,})/', "screenshotify_xxx", $output );
	return ($output);
}

function is_copy($arr)
{
	if ($arr['red1'] == -2 && $arr['green1'] == -2 && $arr['blue1'] == -2) {
		return true;
	}
	return false;
}

function is_contrast($arr)
{
	if ($arr['red1'] == -3 && $arr['green1'] == -3 && $arr['blue1'] == -3) {
		return true;
	}
	return false;
}

function is_none($arr)
{
	if ($arr['red1'] == -1 && $arr['green1'] == -1 && $arr['blue1'] == -1) {
		return true;
	}
	return false;
}

function render_colour($col)
{
	if (($col[0] == -1) && ($col[1] == -1) && ($col[1] == -1)) { return 'none'; }
	else if (($col[0] == -2) && ($col[1] == -2) && ($col[1] == -2)) { return 'copy'; }
	else if (($col[0] == -3) && ($col[1] == -3) && ($col[1] == -3)) { return 'contrast'; }
	else { return sprintf("%d %d %d", $col[0], $col[1], $col[2]); }
}

// given a compass-point, and a width & height, return a tuple of the x,y offsets
function calc_offset($offsetstring, $width, $height)
{
	if(preg_match("/^([-+]?\d+):([-+]?\d+)$/",$offsetstring,$matches))
	{
		wm_debug("Numeric Offset found\n");
		return(array($matches[1],$matches[2]));
	}
	elseif(preg_match("/(NE|SE|NW|SW|N|S|E|W|C)(\d+)?$/i",$offsetstring,$matches))
	{
		$multiply = 1;
		if( isset($matches[2] ) )
		{
			$multiply = intval($matches[2])/100;
			wm_debug("Percentage compass offset: multiply by $multiply");
		}

		$height = $height * $multiply;
		$width = $width * $multiply;

		switch (strtoupper($matches[1]))
		{
		case 'N':
			return (array(0, -$height / 2));
			break;

		case 'S':
			return (array(0, $height / 2));
			break;

		case 'E':
			return (array(+$width / 2, 0));
			break;

		case 'W':
			return (array(-$width / 2, 0));
			break;

		case 'NW':
			return (array(-$width / 2, -$height / 2));
			break;

		case 'NE':
			return (array($width / 2, -$height / 2));
			break;

		case 'SW':
			return (array(-$width / 2, $height / 2));
			break;

		case 'SE':
			return (array($width / 2, $height / 2));
			break;

		case 'C':
		default:
			return (array(0, 0));
			break;
		}
	}
	elseif( preg_match("/(-?\d+)r(\d+)$/i",$offsetstring,$matches) )
	{
		$angle = intval($matches[1]);
		$distance = intval($matches[2]);

		$x = $distance * sin(deg2rad($angle));
		$y = - $distance * cos(deg2rad($angle));

		return (array($x,$y));
	}
	else
	{
		wm_warn("Got a position offset that didn't make sense ($offsetstring).");
		return (array(0, 0));
	}
}

function unformat_number($instring, $kilo = 1000)
{
	$matches=0;
	$number=0;

	if (preg_match("/([0-9\.]+)(M|G|K|T|m|u)/", $instring, $matches))
	{
		$number=floatval($matches[1]);

		if ($matches[2] == 'K') { $number=$number * $kilo; }
		if ($matches[2] == 'M') { $number=$number * $kilo * $kilo; }
		if ($matches[2] == 'G') { $number=$number * $kilo * $kilo * $kilo; }
		if ($matches[2] == 'T') { $number=$number * $kilo * $kilo * $kilo * $kilo; }
		// new, for absolute datastyle. Think seconds.
		if ($matches[2] == 'm') { $number=$number / $kilo; }
		if ($matches[2] == 'u') { $number=$number / ($kilo * $kilo); }
	}
	else { $number=floatval($instring); }

	return ($number);
}
