<?php namespace sox\sdk\com;

class ico {
	static function convert($img_path, $size = 16, $new_path = '') {
		$_gd_array = self::do_gd($img_path, $size);

		if (!$_gd_array) return FALSE;

		$icon_data = self::to_str($_gd_array);

		if (!$icon_data) return FALSE;

		if (empty($new_path)) {
			$path_arr = explode('/', $img_path);

			$path_str = array_pop($path_arr);

			$path_pos = strpos($path_str,'.');

			$path_str = substr($path_str,0, $path_pos);

			$new_path = implode('/', $path_arr).'/'.$path_str.'.ico';
		} else if (strpos($new_path, '.') === FALSE) {
			$path_arr = explode('/', $img_path);

			$path_str = array_pop($path_arr);

			$path_pos = strpos($path_str, '.');

			$path_str = substr($path_str, 0, $path_pos);

			$new_path .= '/'.$path_str.'.ico';
		}

		$new_dir = explode('/', $new_path);
		array_pop($new_dir);
		$new_dir = implode('/', $new_dir);

		if (!is_dir($new_dir)) mkdir($new_dir,0777,TRUE);

		file_put_contents($new_path, $icon_data);

		return $new_path;
	}

	static function do_gd($img_path, $size = 16) {
		$file_ext = strtolower(substr($img_path,-3));

		switch ($file_ext) {
			case 'gif':  $im = imagecreatefromgif ($img_path);  break;
			case 'png':  $im = imagecreatefrompng($img_path);  break;
			case 'jpg':  $im = imagecreatefromjpeg($img_path); break;
			case 'jpeg': $im = imagecreatefromjpeg($img_path); break;

			default: return ''; break;
		}

		if (!$im) return FALSE;

		$imginfo   = getimagesize($img_path);
		$resize_im = imagecreatetruecolor($size, $size);

		imagesavealpha($im,TRUE);
		imagealphablending($resize_im,FALSE);
		imagesavealpha($resize_im,TRUE);
		imagecopyresampled($resize_im, $im,0,0,0,0, $size, $size, $imginfo[0], $imginfo[1]);

		return array($resize_im);
	}

	static function to_str(&$gd_ico_array) {
		foreach ($gd_ico_array as $key => $gd_image) {
			$IcoWidths[$key]   = ImageSX($gd_image);
			$IcoHeights[$key]  = ImageSY($gd_image);
			$bpp[$key]         = ImageIsTrueColor($gd_image) ? 32 : 24;
			$totalcolors[$key] = ImageColorsTotal($gd_image);
			$icXOR[$key]       = '';

			for ($y = $IcoHeights[$key] - 1; $y >= 0; $y--) {
				for ($x = 0; $x < $IcoWidths[$key]; $x++) {
					$argb = self::gpc($gd_image, $x, $y);
					$a = round(255 * ((127 - $argb['alpha']) / 127));
					$r = $argb['red'];
					$g = $argb['green'];
					$b = $argb['blue'];

					// $a = ($r == 0 && $g == 0 && $b == 0) ? 0 : 255;

					if ($bpp[$key] == 32) {
						$icXOR[$key] .= chr($b).chr($g).chr($r).chr($a);
					} else if ($bpp[$key] == 24) {
						$icXOR[$key] .= chr($b).chr($g).chr($r);
					}

					if ($a < 128) {
						$icANDmask[$key][$y] = isset($icANDmask[$key][$y]) ? $icANDmask[$key][$y].'1' : '1';
					} else {
						$icANDmask[$key][$y] = isset($icANDmask[$key][$y]) ? $icANDmask[$key][$y].'0' : '0';
					}
				}

				while (strlen($icANDmask[$key][$y]) % 32) {
					$icANDmask[$key][$y] .= '0';
				}
			}

			$icAND[$key] = '';

			foreach ($icANDmask[$key] as $y => $scanlinemaskbits) {
				for ($i = 0; $i < strlen($scanlinemaskbits); $i += 8) {
					$icAND[$key] .= chr(bindec(str_pad(substr($scanlinemaskbits, $i, 8), 8, '0', STR_PAD_LEFT)));
				}
			}
		}

		foreach ($gd_ico_array as $key => $gd_image) {
			$biSizeImage = $IcoWidths[$key] * $IcoHeights[$key] * ($bpp[$key] / 8);

			$bfh[$key]  = '';
			$bfh[$key] .= "\x28\x00\x00\x00";
			$bfh[$key] .= self::le2s($IcoWidths[$key], 4);
			$bfh[$key] .= self::le2s($IcoHeights[$key] * 2, 4);
			$bfh[$key] .= "\x01\x00";
			$bfh[$key] .= chr($bpp[$key])."\x00";
			$bfh[$key] .= "\x00\x00\x00\x00";
			$bfh[$key] .= self::le2s($biSizeImage, 4);
			$bfh[$key] .= "\x00\x00\x00\x00";
			$bfh[$key] .= "\x00\x00\x00\x00";
			$bfh[$key] .= "\x00\x00\x00\x00";
			$bfh[$key] .= "\x00\x00\x00\x00";
		}

		$icondata  = "\x00\x00";
		$icondata .= "\x01\x00";
		$icondata .= self::le2s(count($gd_ico_array), 2);

		$dwImageOffset = 6 + (count($gd_ico_array) * 16);

		foreach ($gd_ico_array as $key => $gd_image) {
			$icondata .= chr($IcoWidths[$key]);
			$icondata .= chr($IcoHeights[$key]);
			$icondata .= chr($totalcolors[$key]);
			$icondata .= "\x00";
			$icondata .= "\x01\x00";
			$icondata .= chr($bpp[$key])."\x00";

			$dwBytesInRes = 40 + strlen($icXOR[$key]) + strlen($icAND[$key]);

			$icondata .= self::le2s($dwBytesInRes, 4);
			$icondata .= self::le2s($dwImageOffset, 4);

			$dwImageOffset += strlen($bfh[$key]);
			$dwImageOffset += strlen($icXOR[$key]);
			$dwImageOffset += strlen($icAND[$key]);
		}

		foreach ($gd_ico_array as $key => $gd_image) {
			$icondata .= $bfh[$key];
			$icondata .= $icXOR[$key];
			$icondata .= $icAND[$key];
		}

		return $icondata;
	}

	static function le2s($number, $minbytes=1) {
		$intstring = '';

		while ($number > 0) {
			$intstring = $intstring.chr($number & 255);
			$number >>= 8;
		}

		return str_pad($intstring, $minbytes, "\x00", STR_PAD_RIGHT);
	}

	static function gpc(&$img, $x, $y) {
		if (!is_resource($img)) {
			return FALSE;
		}

		return ImageColorsForIndex($img, ImageColorAt($img, $x, $y));
	}
}