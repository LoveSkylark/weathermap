<?php

function mysprintf($format, $value, $kilo = 1000)
{
	$output = "";

	wm_debug("mysprintf: $format $value\n");
	if (preg_match('/%(\d*\.?\d*)k/', $format, $matches)) {
		$spec = $matches[1];
		$places = 2;
		if ($spec != '') {
			preg_match('/(\d*)\.?(\d*)/', $spec, $matches);
			if ($matches[2] != '') {
				$places = $matches[2];
			}
			// we don't really need the justification (pre-.) part...
		}
		wm_debug("KMGT formatting $value with $spec.\n");
		$result = nice_scalar($value, $kilo, $places);
		$output = preg_replace("/%" . $spec . "k/", $format, $result);
	} elseif (preg_match('/%(-*)(\d*)([Tt])/', $format, $matches)) {
		$spec = $matches [3];
		$precision = ($matches [2] == '' ? 10 : intval($matches [2]));
		$joinchar = " ";
		if ($matches [1] == "-") {
			$joinchar = " ";
		}
		// special formatting for time_t (t) and SNMP TimeTicks (T)
		if ($spec == "T") {
			$value = $value / 100;
		}
		$results = array();
		$periods = array(
			"y" => 24 * 60 * 60 * 365,
			"d" => 24 * 60 * 60,
			"h" => 60 * 60,
			"m" => 60,
			"s" => 1
		);
		foreach ($periods as $periodsuffix => $timeperiod) {
			$slot = floor($value / $timeperiod);
			$value = $value - $slot * $timeperiod;
			if ($slot > 0) {
				$results [] = sprintf("%d%s", $slot, $periodsuffix);
			}
		}
		if (sizeof($results) == 0) {
			$results [] = "0s";
		}
		$output = implode($joinchar, array_slice($results, 0, $precision));
	} else {
		wm_debug("Falling through to standard sprintf\n");
		$output = sprintf($format, $value);
	}
	return $output;
}

// These next two are based on perl's Number::Format module
// by William R. Ward, chopped down to just what I needed

function format_number($number, $precision = 2, $trailing_zeroes = 0)
{
	$sign=1;

	if ($number < 0)
	{
		$number=abs($number);
		$sign=-1;
	}

	$number=round($number, $precision);
	$integer=intval($number);

	if (strlen($integer) < strlen($number)) { $decimal=substr($number, strlen($integer) + 1); }

	if (!isset($decimal)) { $decimal=''; }

	$integer=$sign * $integer;

	if ($decimal == '') { return ($integer); }
	else { return ($integer . "." . $decimal); }
}

function nice_bandwidth($number, $kilo = 1000,$decimals=1,$below_one=TRUE)
{
	$suffix='';

        if ($number == 0 || !is_numeric($number))
        {
                return '0';
        }

	$mega=$kilo * $kilo;
	$giga=$mega * $kilo;
	$tera=$giga * $kilo;

        $milli = 1/$kilo;
	$micro = 1/$mega;
	$nano = 1/$giga;

	if ($number >= $tera)
	{
		$number/=$tera;
		$suffix="T";
	}
	elseif ($number >= $giga)
	{
		$number/=$giga;
		$suffix="G";
	}
	elseif ($number >= $mega)
	{
		$number/=$mega;
		$suffix="M";
	}
	elseif ($number >= $kilo)
	{
		$number/=$kilo;
		$suffix="K";
	}
        elseif ($number >= 1)
        {
                $suffix="";
        }
	elseif (($below_one==TRUE) && ($number >= $milli))
	{
		$number/=$milli;
		$suffix="m";
	}
	elseif (($below_one==TRUE) && ($number >= $micro))
	{
		$number/=$micro;
		$suffix="u";
	}
	elseif (($below_one==TRUE) && ($number >= $nano))
	{
		$number/=$nano;
		$suffix="n";
	}

	$result=format_number($number, $decimals) . $suffix;
	return ($result);
}

function nice_scalar($number, $kilo = 1000, $decimals=1)
{
	$suffix = '';
	$prefix = '';

       if ($number == 0 || !is_numeric($number))
        {
                return '0';
        }

	if($number < 0)
	{
		$number = -$number;
		$prefix = '-';
	}

	$mega=$kilo * $kilo;
	$giga=$mega * $kilo;
	$tera=$giga * $kilo;

	if ($number > $tera)
	{
		$number/=$tera;
		$suffix="T";
	}
	elseif ($number > $giga)
	{
		$number/=$giga;
		$suffix="G";
	}
	elseif ($number > $mega)
	{
		$number/=$mega;
		$suffix="M";
	}
	elseif ($number > $kilo)
	{
		$number/=$kilo;
		$suffix="K";
	}
        elseif ($number > 1)
        {
                $suffix="";
        }
	elseif ($number < (1 / ($kilo)))
	{
		$number=$number * $mega;
		$suffix="u";
	}
	elseif ($number < 1)
	{
		$number=$number * $kilo;
		$suffix="m";
	}

	$result = $prefix . format_number($number, $decimals) . $suffix;
	return ($result);
}
