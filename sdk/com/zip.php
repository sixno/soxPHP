<?php namespace sox\sdk\com;

use \sox\sdk\com\file;

class zip
{
	static $zipdata   = '';
	static $directory = '';
	static $entries   = 0;
	static $file_num  = 0;
	static $offset    = 0;

	static function add_dir($directory) {
		foreach ((array) $directory as $dir) {
			if (!preg_match("|.+/$|", $dir)) {
				$dir .= '/';
			}

			$dir_time = self::_get_mod_time($dir);

			self::_add_dir($dir, $dir_time['file_mtime'], $dir_time['file_mdate']);
		}
	}

	static function _get_mod_time($dir) {
		// filemtime() will return false, but it does raise an error.
		$date = (@filemtime($dir)) ? filemtime($dir) : getdate(time());

		$time['file_mtime'] = ($date['hours'] << 11) + ($date['minutes'] << 5) + $date['seconds'] / 2;
		$time['file_mdate'] = (($date['year'] - 1980) << 9) + ($date['mon'] << 5) + $date['mday'];

		return $time;
	}

	static function _add_dir($dir, $file_mtime, $file_mdate) {
		$dir = str_replace("\\", "/", $dir);

		self::$zipdata .=
			"\x50\x4b\x03\x04\x0a\x00\x00\x00\x00\x00"
			.pack('v', $file_mtime)
			.pack('v', $file_mdate)
			.pack('V', 0) // crc32
			.pack('V', 0) // compressed filesize
			.pack('V', 0) // uncompressed filesize
			.pack('v', strlen($dir)) // length of pathname
			.pack('v', 0) // extra field length
			.$dir
			// below is "data descriptor" segment
			.pack('V', 0) // crc32
			.pack('V', 0) // compressed filesize
			.pack('V', 0); // uncompressed filesize

		self::$directory .=
			"\x50\x4b\x01\x02\x00\x00\x0a\x00\x00\x00\x00\x00"
			.pack('v', $file_mtime)
			.pack('v', $file_mdate)
			.pack('V', 0) // crc32
			.pack('V', 0) // compressed filesize
			.pack('V', 0) // uncompressed filesize
			.pack('v', strlen($dir)) // length of pathname
			.pack('v', 0) // extra field length
			.pack('v', 0) // file comment length
			.pack('v', 0) // disk number start
			.pack('v', 0) // internal file attributes
			.pack('V', 16) // external file attributes - 'directory' bit set
			.pack('V', self::$offset) // relative offset of local header
			.$dir;

		self::$offset = strlen(self::$zipdata);
		self::$entries++;
	}

	static function add_data($filepath, $data = NULL) {
		if (is_array($filepath)) {
			foreach ($filepath as $path => $data) {
				$file_data = self::_get_mod_time($path);

				self::_add_data($path, $data, $file_data['file_mtime'], $file_data['file_mdate']);
			}
		} else {
			$file_data = self::_get_mod_time($filepath);

			self::_add_data($filepath, $data, $file_data['file_mtime'], $file_data['file_mdate']);
		}
	}

	static function _add_data($filepath, $data, $file_mtime, $file_mdate) {
		$filepath = iconv('utf-8', 'gbk//ignore',str_replace("\\", "/", $filepath));

		$uncompressed_size = strlen($data);
		$crc32  = crc32($data);

		$gzdata = gzcompress($data);
		$gzdata = substr($gzdata, 2, -4);
		$compressed_size = strlen($gzdata);

		self::$zipdata .=
			"\x50\x4b\x03\x04\x14\x00\x00\x00\x08\x00"
			.pack('v', $file_mtime)
			.pack('v', $file_mdate)
			.pack('V', $crc32)
			.pack('V', $compressed_size)
			.pack('V', $uncompressed_size)
			.pack('v', strlen($filepath)) // length of filename
			.pack('v', 0) // extra field length
			.$filepath
			.$gzdata; // "file data" segment

		self::$directory .=
			"\x50\x4b\x01\x02\x00\x00\x14\x00\x00\x00\x08\x00"
			.pack('v', $file_mtime)
			.pack('v', $file_mdate)
			.pack('V', $crc32)
			.pack('V', $compressed_size)
			.pack('V', $uncompressed_size)
			.pack('v', strlen($filepath)) // length of filename
			.pack('v', 0) // extra field length
			.pack('v', 0) // file comment length
			.pack('v', 0) // disk number start
			.pack('v', 0) // internal file attributes
			.pack('V', 32) // external file attributes - 'archive' bit set
			.pack('V', self::$offset) // relative offset of local header
			.$filepath;

		self::$offset = strlen(self::$zipdata);
		self::$entries++;
		self::$file_num++;
	}

	static function read_file($path, $preserve_filepath = FALSE) {
		if (!file_exists($path)) {
			return FALSE;
		}

		if (FALSE !== ($data = file_get_contents($path))) {
			$name = str_replace("\\", "/", $path);

			if ($preserve_filepath === FALSE) {
				$name = preg_replace("|.*/(.+)|", "\\1", $name);
			}

			self::add_data($name, $data);
			return TRUE;
		}
		return FALSE;
	}

	static function read_dir($path, $preserve_filepath = TRUE, $root_path = NULL) {
		if (!$fp = @opendir($path)) {
			return FALSE;
		}

		// Set the original directory root for child dir's to use as relative
		if ($root_path === NULL) {
			$root_path = dirname($path).'/';
		}

		while (FALSE !== ($file = readdir($fp))) {
			if (substr($file, 0, 1) == '.') {
				continue;
			}

			if (@is_dir($path.$file)) {
				self::read_dir($path.$file."/", $preserve_filepath, $root_path);
			} else {
				if (FALSE !== ($data = file_get_contents($path.$file))) {
					$name = str_replace("\\", "/", $path);

					if ($preserve_filepath === FALSE) {
						$name = str_replace($root_path, '', $name);
					}

					self::add_data($name.$file, $data);
				}
			}
		}

		return TRUE;
	}

	static function get_zip() {
		// Is there any data to return?
		if (self::$entries == 0) {
			return FALSE;
		}

		$zip_data  = self::$zipdata;
		$zip_data .= self::$directory."\x50\x4b\x05\x06\x00\x00\x00\x00";
		$zip_data .= pack('v', self::$entries); // total # of entries "on this disk"
		$zip_data .= pack('v', self::$entries); // total # of entries overall
		$zip_data .= pack('V', strlen(self::$directory)); // size of central dir
		$zip_data .= pack('V', strlen(self::$zipdata)); // offset to start of central dir
		$zip_data .= "\x00\x00"; // .zip file comment length

		return $zip_data;
	}

	static function archive($filepath) {
		if (!($fp = fopen($filepath, 'wb'))) {
			return FALSE;
		}

		flock($fp, LOCK_EX);
		fwrite($fp, self::get_zip());
		flock($fp, LOCK_UN);
		fclose($fp);

		self::clear_data();

		return TRUE;
	}

	static function for_download($filename = 'backup.zip') {
		if (!preg_match("|.+?\.zip$|", $filename)) {
			$filename .= '.zip';
		}

		$zip_content = self::get_zip();

		self::clear_data();

		file::for_download($filename, $zip_content);
	}

	static function clear_data() {
		self::$zipdata   = '';
		self::$directory = '';
		self::$entries   = 0;
		self::$file_num  = 0;
		self::$offset    = 0;
	}

	static function _read_central_dir($zip, $zipfile) {
		$size     = filesize($zipfile);
		$max_size = ($size < 277) ? $size : 277;
		
		@fseek($zip, $size - $max_size);
		$pos   = ftell($zip);
		$bytes = 0x00000000;
		
		while ($pos < $size) {
			$byte  = @fread($zip, 1);
			$bytes = ($bytes << 8) | Ord($byte);
			$pos++;
			if (substr(dechex($bytes),-8,8) == '504b0506') break;
		}
		
		$data = unpack('vdisk/vdisk_start/vdisk_entries/ventries/Vsize/Voffset/vcomment_size', fread($zip, 18));

		$centd['comment']      = ($data['comment_size'] != 0) ? fread($zip, $data['comment_size']) : '';
		$centd['entries']      = $data['entries'];
		$centd['disk_entries'] = $data['disk_entries'];
		$centd['offset']       = $data['offset'];
		$centd['disk_start']   = $data['disk_start'];
		$centd['size']         = $data['size'];
		$centd['disk']         = $data['disk'];
		return $centd;
	}

	static function _read_central_file_headers($zip) {
		$binary_data = fread($zip, 46);
		$header      = unpack('vchkid/vid/vversion/vversion_extracted/vflag/vcompression/vmtime/vmdate/Vcrc/Vcompressed_size/Vsize/vfilename_len/vextra_len/vcomment_len/vdisk/vinternal/Vexternal/Voffset', $binary_data);

		$header['filename'] = ($header['filename_len'] != 0) ? fread($zip, $header['filename_len']) : '';
		$header['extra']    = ($header['extra_len']    != 0) ? fread($zip, $header['extra_len'])    : '';
		$header['comment']  = ($header['comment_len']  != 0) ? fread($zip, $header['comment_len'])  : '';


		if ($header['mdate'] && $header['mtime']) {
			$hour    = ($header['mtime']  & 0xF800) >> 11;
			$minute  = ($header['mtime']  & 0x07E0) >> 5;
			$seconde = ($header['mtime']  & 0x001F) * 2;
			$year    = (($header['mdate'] & 0xFE00) >> 9) + 1980;
			$month   = ($header['mdate']  & 0x01E0) >> 5;
			$day     = $header['mdate']   & 0x001F;
			$header['mtime'] = mktime($hour, $minute, $seconde, $month, $day, $year);
		} else {
			$header['mtime'] = time();
		}
		$header['stored_filename'] = $header['filename'];
		$header['status'] = 'ok';
		if (substr($header['filename'], -1) == '/') $header['external'] = 0x41FF0010;

		return $header;
	}

	static function _read_file_header($zip) {
		$binary_data = fread($zip, 30);
		$data        = unpack('vchk/vid/vversion/vflag/vcompression/vmtime/vmdate/Vcrc/Vcompressed_size/Vsize/vfilename_len/vextra_len', $binary_data);

		$header['filename']        = fread($zip, $data['filename_len']);
		$header['extra']           = ($data['extra_len'] != 0) ? fread($zip, $data['extra_len']) : '';
		$header['compression']     = $data['compression'];
		$header['size']            = $data['size'];
		$header['compressed_size'] = $data['compressed_size'];
		$header['crc']             = $data['crc'];
		$header['flag']            = $data['flag'];
		$header['mdate']           = $data['mdate'];
		$header['mtime']           = $data['mtime'];

		if ($header['mdate'] && $header['mtime']) {
			$hour    = ($header['mtime']  & 0xF800) >> 11;
			$minute  = ($header['mtime']  & 0x07E0) >> 5;
			$seconde = ($header['mtime']  & 0x001F) * 2;
			$year    = (($header['mdate'] & 0xFE00) >> 9) + 1980;
			$month   = ($header['mdate']  & 0x01E0) >> 5;
			$day     = $header['mdate']   & 0x001F;
			$header['mtime'] = mktime($hour, $minute, $seconde, $month, $day, $year);
		} else {
			$header['mtime'] = time();
		}

		$header['stored_filename'] = $header['filename'];
		$header['status']          = "ok";

		return $header;
	}

	static function _extract_data($header, $to, $zip) {
		$header = self::_read_file_header($zip);
		
		if (substr($to, -1) != "/") $to .= "/";
		if (!is_dir($to)) @mkdir($to, 0777);
		
		$pth = explode("/", dirname($header['filename']));
		$pthss = '';

		for($i = 0;isset($pth[$i]);$i++) {
			if (!$pth[$i]) continue;

			$pthss .= $pth[$i]."/";
			if (!is_dir($to.$pthss)) @mkdir($to.$pthss, 0777);
		}
		
		if (empty($header['external']) || (!($header['external'] == 0x41FF0010) && !($header['external'] == 16))) {
			if ($header['compression'] == 0) {
				$fp = @fopen($to.$header['filename'], 'wb');
				if (!$fp){ return(-1); }
				$size = $header['compressed_size'];
				
				while ($size != 0) {
					$read_size   = ($size < 2048 ? $size : 2048);
					$buffer      = fread($zip, $read_size);
					$binary_data = pack('a'.$read_size, $buffer);
					@fwrite($fp, $binary_data, $read_size);
					$size       -= $read_size;
				}
				fclose($fp);
				touch($to.$header['filename'], $header['mtime']);
			} else {
				
				$fp = @fopen($to.$header['filename'].'.gz', 'wb');
				if (!$fp){ return(-1); }
				$binary_data = pack('va1a1Va1a1', 0x8b1f, Chr($header['compression']), Chr(0x00), time(), Chr(0x00), Chr(3));
				
				fwrite($fp, $binary_data, 10);
				$size = $header['compressed_size'];
				
				while ($size != 0) {
					$read_size   = ($size < 1024 ? $size : 1024);
					$buffer      = fread($zip, $read_size);
					$binary_data = pack('a'.$read_size, $buffer);
					@fwrite($fp, $binary_data, $read_size);
					$size       -= $read_size;
				}
				
				$binary_data = pack('VV', $header['crc'], $header['size']);
				fwrite($fp, $binary_data, 8);
				fclose($fp);
				
				$gzp = @gzopen($to.$header['filename'].'.gz', 'rb');
				
				if (!$gzp) return -2;
				$fp = @fopen($to.$header['filename'], 'wb');
				if (!$fp) return -1;

				$size = $header['size'];
				
				while ($size != 0) {
					$read_size   = ($size < 2048 ? $size : 2048);
					$buffer      = gzread($gzp, $read_size);
					$binary_data = pack('a'.$read_size, $buffer);
					@fwrite($fp, $binary_data, $read_size);
					$size       -= $read_size;
				}

				fclose($fp);
				gzclose($gzp);
				
				touch($to.$header['filename'], $header['mtime']);
				@unlink($to.$header['filename'].'.gz');
			}
		}
		return true;
	}

	static function extract_data($zipfile, $to, $index = array(-1)) {
		$ok  = 0;
		$zip = @fopen($zipfile, 'rb');
		if (!$zip){ return(-1); }
		
		$cdir      = self::_read_central_dir($zip, $zipfile);
		$pos_entry = $cdir['offset'];
		
		if (!is_array($index)) $index = array($index);

		for($i = 0;$i < count($index);$i++) {
			if (intval($index[$i]) != $index[$i] || $index[$i] > $cdir['entries']) return -1;
		}

		for($i=0; $i<$cdir['entries']; $i++) {
			@fseek($zip, $pos_entry);
			$header          = self::_read_central_file_headers($zip);
			$header['index'] = $i;
			$pos_entry       = ftell($zip);
			@rewind($zip);
			fseek($zip, $header['offset']);
			if (in_array("-1", $index) || in_array($i, $index)) {
				$stat[$header['filename']] = self::_extract_data($header, $to, $zip);
			}
		}

		fclose($zip);

		return $stat;
	}
}