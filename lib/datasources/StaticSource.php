<?php

namespace Weathermap\DataSources;

use Weathermap\Base\DataSource;

// TARGET static:10M
// TARGET static:2M:256K

class StaticSource extends DataSource {

	function Recognise($targetstring)
	{
		if( preg_match("/^static:(\-?\d+\.?\d*[KMGT]?):(\-?\d+\.?\d*[KMGT]?)$/",$targetstring,$matches) || 
			preg_match("/^static:(\-?\d+\.?\d*[KMGT]?)$/",$targetstring,$matches) )
		{
			return TRUE;
		}
		else
		{
			return FALSE;
		}
	}

	function ReadData($targetstring, &$map, &$item)
	{
		$inbw = NULL;
		$outbw = NULL;
		$data_time=0;

		if(preg_match("/^static:(\-?\d+\.?\d*[KMGT]*):(\-?\d+\.?\d*[KMGT]*)$/",$targetstring,$matches))
		{
			$inbw = unformat_number($matches[1], $map->kilo);
			$outbw = unformat_number($matches[2], $map->kilo);
			$data_time = time();
		}

		if(preg_match("/^static:(\-?\d+\.?\d*[KMGT]*)$/",$targetstring,$matches))
		{
			$inbw = unformat_number($matches[1], $map->kilo);
			$outbw = $inbw;
			$data_time = time();
		}
		wm_debug ("Static ReadData: Returning ($inbw,$outbw,$data_time)\n");

		return ( array($inbw,$outbw,$data_time) );
	}
}