<?php namespace sox\sdk\com;

class security
{
	static $charset = 'UTF-8';

	static $_xss_hash = '';

	static $_never_allowed_str = array(
		'document.cookie'	=> '[removed]',
		'document.write'	=> '[removed]',
		'.parentNode'		=> '[removed]',
		'.innerHTML'		=> '[removed]',
		'window.location'	=> '[removed]',
		'-moz-binding'		=> '[removed]',
		'<!--'				=> '&lt;!--',
		'-->'				=> '--&gt;',
		'<![CDATA['			=> '&lt;![CDATA[',
		'<comment>'			=> '&lt;comment&gt;'
	);

	static $_never_allowed_regex = array(
		'javascript\s*:',
		'expression\s*(\(|&\#40;)', // CSS and IE
		'vbscript\s*:', // IE, surprise!
		'Redirect\s+302',
		"([\"'])?data\s*:[^\\1]*?base64[^\\1]*?,[^\\1]*?\\1?"
	);

	static function xss_clean($str, $is_image = FALSE)
	{
		if (is_array($str))
		{
			while (list($key) = each($str))
			{
				$str[$key] = self::xss_clean($str[$key]);
			}

			return $str;
		}

		$str = self::remove_invisible_characters($str);

		$str = self::_validate_entities($str);

		$str = rawurldecode($str);

		$str = preg_replace_callback("/[a-z]+=([\'\"]).*?\\1/si", array('\\sox\\sdk\\com\\security', '_convert_attribute'), $str);

		$str = preg_replace_callback("/<\w+.*?(?=>|<|$)/si", array('\\sox\\sdk\\com\\security', '_decode_entity'), $str);

		$str = self::remove_invisible_characters($str);

		if (strpos($str, "\t") !== FALSE)
		{
			$str = str_replace("\t", ' ', $str);
		}

		$converted_string = $str;

		$str = self::_do_never_allowed($str);

		if ($is_image === TRUE)
		{
			$str = preg_replace('/<\?(php)/i', "&lt;?\\1", $str);
		}
		else
		{
			$str = str_replace(array('<?', '?'.'>'),  array('&lt;?', '?&gt;'), $str);
		}

		$words = array(
			'javascript', 'expression', 'vbscript', 'script', 'base64',
			'applet', 'alert', 'document', 'write', 'cookie', 'window'
		);

		foreach ($words as $word)
		{
			$temp = '';

			for ($i = 0, $wordlen = strlen($word); $i < $wordlen; $i++)
			{
				$temp .= substr($word, $i, 1)."\s*";
			}

			$str = preg_replace_callback('#('.substr($temp, 0, -3).')(\W)#is', array('\\sox\\sdk\\com\\security', '_compact_exploded_words'), $str);
		}

		do
		{
			$original = $str;

			if (preg_match("/<a/i", $str))
			{
				$str = preg_replace_callback("#<a\s+([^>]*?)(>|$)#si", array('\\sox\\sdk\\com\\security', '_js_link_removal'), $str);
			}

			if (preg_match("/<img/i", $str))
			{
				$str = preg_replace_callback("#<img\s+([^>]*?)(\s?/?>|$)#si", array('\\sox\\sdk\\com\\security', '_js_img_removal'), $str);
			}

			if (preg_match("/script/i", $str) OR preg_match("/xss/i", $str))
			{
				$str = preg_replace("#<(/*)(script|xss)(.*?)\>#si", '[removed]', $str);
			}
		}
		while($original != $str);

		unset($original);

		$str = self::_remove_evil_attributes($str, $is_image);

		$naughty = 'alert|applet|audio|basefont|base|behavior|bgsound|blink|body|embed|expression|form|frameset|frame|head|html|ilayer|iframe|input|isindex|layer|link|meta|object|plaintext|style|script|textarea|title|video|xml|xss';
		$str = preg_replace_callback('#<(/*\s*)('.$naughty.')([^><]*)([><]*)#is', array('\\sox\\sdk\\com\\security', '_sanitize_naughty_html'), $str);

		$str = preg_replace('#(alert|cmd|passthru|eval|exec|expression|system|fopen|fsockopen|file|file_get_contents|readfile|unlink)(\s*)\((.*?)\)#si', "\\1\\2&#40;\\3&#41;", $str);

		$str = self::_do_never_allowed($str);

		if ($is_image === TRUE)
		{
			return ($str == $converted_string) ? TRUE: FALSE;
		}

		return $str;
	}

	static function xss_hash()
	{
		if (self::$_xss_hash == '')
		{
			mt_srand();
			self::$_xss_hash = md5(time() + mt_rand(0, 1999999999));
		}

		return self::$_xss_hash;
	}

	static function entity_decode($str, $charset='UTF-8')
	{
		if (stristr($str, '&') === FALSE)
		{
			return $str;
		}

		$str = html_entity_decode($str, ENT_COMPAT, $charset);
		$str = preg_replace_callback('~&#x(0*[0-9a-f]{2,5})~i',function($match){return chr(hexdec($match[1]));},$str);
		return preg_replace_callback('~&#([0-9]{2,4})~',function($match){return chr($match[1]);},$str);
	}

	static function sanitize_filename($str, $relative_path = FALSE)
	{
		$bad = array(
			"../",
			"<!--",
			"-->",
			"<",
			">",
			"'",
			'"',
			'&',
			'$',
			'#',
			'{',
			'}',
			'[',
			']',
			'=',
			';',
			'?',
			"%20",
			"%22",
			"%3c",		// <
			"%253c",	// <
			"%3e",		// >
			"%0e",		// >
			"%28",		// (
			"%29",		// )
			"%2528",	// (
			"%26",		// &
			"%24",		// $
			"%3f",		// ?
			"%3b",		// ;
			"%3d"		// =
		);

		if ( ! $relative_path)
		{
			$bad[] = './';
			$bad[] = '/';
		}

		$str = self::remove_invisible_characters($str, FALSE);
		return stripslashes(str_replace($bad, '', $str));
	}

	static function _compact_exploded_words($matches)
	{
		return preg_replace('/\s+/s', '', $matches[1]).$matches[2];
	}

	static function _remove_evil_attributes($str, $is_image)
	{
		$evil_attributes = array('on\w*', 'style', 'xmlns', 'formaction');

		if ($is_image === TRUE)
		{
			unset($evil_attributes[array_search('xmlns', $evil_attributes)]);
		}

		do {
			$count = 0;
			$attribs = array();

			preg_match_all('/('.implode('|', $evil_attributes).')\s*=\s*([^\s>]*)/is', $str, $matches, PREG_SET_ORDER);

			foreach ($matches as $attr)
			{

				$attribs[] = preg_quote($attr[0], '/');
			}

			preg_match_all("/(".implode('|', $evil_attributes).")\s*=\s*(\042|\047)([^\\2]*?)(\\2)/is",  $str, $matches, PREG_SET_ORDER);

			foreach ($matches as $attr)
			{
				$attribs[] = preg_quote($attr[0], '/');
			}

			if (count($attribs) > 0)
			{
				$str = preg_replace("/<(\/?[^><]+?)([^A-Za-z<>\-])(.*?)(".implode('|', $attribs).")(.*?)([\s><])([><]*)/i", '<$1 $3$5$6$7', $str, -1, $count);
			}

		} while ($count);

		return $str;
	}

	static function _sanitize_naughty_html($matches)
	{
		$str = '&lt;'.$matches[1].$matches[2].$matches[3];

		$str .= str_replace(array('>', '<'), array('&gt;', '&lt;'),$matches[4]);

		return $str;
	}

	static function _js_link_removal($match)
	{
		return str_replace(
			$match[1],
			preg_replace(
				'#href=.*?(alert\(|alert&\#40;|javascript\:|livescript\:|mocha\:|charset\=|window\.|document\.|\.cookie|<script|<xss|data\s*:)#si',
				'',
				self::_filter_attributes(str_replace(array('<', '>'), '', $match[1]))
			),
			$match[0]
		);
	}

	static function _js_img_removal($match)
	{
		return str_replace(
			$match[1],
			preg_replace(
				'#src=.*?(alert\(|alert&\#40;|javascript\:|livescript\:|mocha\:|charset\=|window\.|document\.|\.cookie|<script|<xss|base64\s*,)#si',
				'',
				self::_filter_attributes(str_replace(array('<', '>'), '', $match[1]))
			),
			$match[0]
		);
	}

	static function _convert_attribute($match)
	{
		return str_replace(array('>', '<', '\\'), array('&gt;', '&lt;', '\\\\'), $match[0]);
	}

	static function _filter_attributes($str)
	{
		$out = '';

		if (preg_match_all('#\s*[a-z\-]+\s*=\s*(\042|\047)([^\\1]*?)\\1#is', $str, $matches))
		{
			foreach ($matches[0] as $match)
			{
				$out .= preg_replace("#/\*.*?\*/#s", '', $match);
			}
		}

		return $out;
	}

	static function _decode_entity($match)
	{
		return self::entity_decode($match[0], self::$charset);
	}

	static function _validate_entities($str)
	{
		$str = preg_replace('|\&([a-z\_0-9\-]+)\=([a-z\_0-9\-]+)|i', self::xss_hash()."\\1=\\2", $str);

		$str = preg_replace('#(&\#?[0-9a-z]{2,})([\x00-\x20])*;?#i', "\\1;\\2", $str);

		$str = preg_replace('#(&\#x?)([0-9A-F]+);?#i',"\\1\\2;",$str);

		$str = str_replace(self::xss_hash(), '&', $str);

		return $str;
	}

	static function _do_never_allowed($str)
	{
		$str = str_replace(array_keys(self::$_never_allowed_str), self::$_never_allowed_str, $str);

		foreach (self::$_never_allowed_regex as $regex)
		{
			$str = preg_replace('#'.$regex.'#is', '[removed]', $str);
		}

		return $str;
	}

	static function remove_invisible_characters($str,$url_encoded = TRUE)
	{
		$non_displayables = array();
		
		// every control character except newline (dec 10)
		// carriage return (dec 13), and horizontal tab (dec 09)
		
		if($url_encoded)
		{
			$non_displayables[] = '/%0[0-8bcef]/';	// url encoded 00-08, 11, 12, 14, 15
			$non_displayables[] = '/%1[0-9a-f]/';	// url encoded 16-31
		}
		
		$non_displayables[] = '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/S';	// 00-08, 11, 12, 14-31, 127

		do
		{
			$str = preg_replace($non_displayables, '', $str, -1, $count);
		}
		while($count);

		return $str;
	}
}

?>