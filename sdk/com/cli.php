<?php namespace sox\sdk\com;

class cli
{
	static function color($code = '')
	{
		if(PHP_OS != 'Linux') return '';

		list($color,$depth) = str_split($code) + ['',1]; // depth: 1(default) | 0

		switch($color)
		{
			case 'd': $color = 0; break; // gray(dark)
			case 'r': $color = 1; break; // red
			case 'g': $color = 2; break; // green
			case 'y': $color = 3; break; // yellow
			case 'b': $color = 4; break; // blue
			case 'p': $color = 5; break; // purple
			case 'c': $color = 6; break; // cyan
			case 'l': $color = 7; break; // white(light)
			
			default: return '';
		}

		return '\\033['.$depth.';3'.$color.'m';
	}

	static function tip($tip,$br = 1,$color = '')
	{
		if(is_string($br))
		{
			$color = $br;

			$br = 1;
		}

		$color = self::color($color);

		if(!$color)
		{
			echo '['.date('Y-m-d H:i:s').'] '.$tip;
		}
		else
		{
			print exec('echo -ne "'.$color.'['.date('Y-m-d H:i:s').']\\033[0m: '.str_replace('"','\\"',$tip).'"');
		}

		for($i = 0;$i < $br;$i++)
		{
			echo "\n";
		}
	}

	static function msg($msg,$br = 1,$color = '')
	{
		if(is_string($br))
		{
			$color = $br;

			$br = 1;
		}

		$color = self::color($color);

		if(!$color)
		{
			echo $msg;
		}
		else
		{
			print exec('echo -ne "'.$color.str_replace('"','\\"',$msg).'\\033[0m"');
		}

		for($i = 0;$i < $br;$i++)
		{
			echo "\n";
		}
	}

	static function err($err,$br = 1)
	{
		self::msg($err,$br,'r');

		exit;
	}

	static function end($err,$br = 1)
	{
		self::tip($err,$br,'r');

		exit;
	}

	static function set($str,$color)
	{
		$color = self::color($color);

		return $color ? $color.$str.'\\033[0m' : $str;
	}
}

?>