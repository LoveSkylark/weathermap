<?php

namespace Weathermap\DataSources;

use Weathermap\Base\DataSource;

// TARGET dbplug:databasename:username:pass:hostkey

class TabFile extends DataSource {

	function Recognise($targetstring)
	{
		if(preg_match("/\.(tsv|txt)$/",$targetstring,$matches))
		{
			return TRUE;
		}
		else
		{
			return FALSE;
		}
	}

	// function ReadData($targetstring, $configline, $itemtype, $itemname, $map)
	function ReadData($targetstring, &$map, &$item)
	{
		$data[IN] = NULL;
		$data[OUT] = NULL;
		$data_time=0;
		$itemname = $item->name;

		$matches=0;

		$fd=fopen($targetstring, "r");

		if ($fd)
		{
			while (!feof($fd))
			{
				$buffer=fgets($fd, 4096);
				# strip out any Windows line-endings that have gotten in here
				$buffer=str_replace("\r", "", $buffer);

				if (preg_match("/^$itemname\t(\d+\.?\d*[KMGT]*)\t(\d+\.?\d*[KMGT]*)/", $buffer, $matches))
				{
					$data[IN]=unformat_number($matches[1]);
					$data[OUT]=unformat_number($matches[2]);
				}
			}
			$stats = stat($targetstring);
			$data_time = $stats['mtime'];
		}
		else {
			// some error code to go in here
			wm_debug ("TabText ReadData: Couldn't open ($targetstring). \n"); }
		
			wm_debug ("TabText ReadData: Returning (".($data[IN]===NULL?'NULL':$data[IN]).",".($data[OUT]===NULL?'NULL':$data[OUT]).",$data_time)\n");
		
			return( array($data[IN], $data[OUT], $data_time) );
	}
}