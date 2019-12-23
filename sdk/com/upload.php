<?php namespace sox\sdk\com;

use \sox\sdk\com\mime;
use \sox\sdk\com\security;

class upload
{
	static $max_size            = 0;
	static $max_width           = 0;
	static $max_height          = 0;
	static $max_filename        = 0;
	static $allowed_types       = "";
	static $file_temp           = "";
	static $file_name           = "";
	static $orig_name           = "";
	static $file_type           = "";
	static $file_size           = "";
	static $file_ext            = "";
	static $upload_path         = "";
	static $overwrite           = FALSE;
	static $encrypt_name        = FALSE;
	static $is_image            = FALSE;
	static $image_width         = '';
	static $image_height        = '';
	static $image_type          = '';
	static $image_size_str      = '';
	static $error_msg           = array();
	static $mimes               = array();
	static $remove_spaces       = TRUE;
	static $xss_clean           = FALSE;
	static $client_name         = '';
	static $_file_name_override = '';

	static function ini($config = array())
	{
		$defaults = array(
			'max_size'			=> 0,
			'max_width'			=> 0,
			'max_height'		=> 0,
			'max_filename'		=> 0,
			'allowed_types'		=> "",
			'file_temp'			=> "",
			'file_name'			=> "",
			'orig_name'			=> "",
			'file_type'			=> "",
			'file_size'			=> "",
			'file_ext'			=> "",
			'upload_path'		=> "",
			'overwrite'			=> FALSE,
			'encrypt_name'		=> FALSE,
			'is_image'			=> FALSE,
			'image_width'		=> '',
			'image_height'		=> '',
			'image_type'		=> '',
			'image_size_str'	=> '',
			'error_msg'			=> array(),
			'mimes'				=> array(),
			'remove_spaces'		=> TRUE,
			'xss_clean'			=> TRUE,
			'client_name'		=> ''
		);


		foreach ($defaults as $key => $val)
		{
			if(isset($config[$key]))
			{
				$method = 'set_'.$key;

				if(method_exists('\\sox\\sdk\\com\\upload', $method))
				{
					self::$method($config[$key]);
				}
				else
				{
					self::$$key = $config[$key];
				}
			}
			else
			{
				self::$$key = $val;
			}
		}

		self::$_file_name_override = self::$file_name;
	}

	static function do($field = 'userfile')
	{
		if ( ! isset($_FILES[$field]))
		{
			self::set_error('upload_no_file_selected');
			return FALSE;
		}

		if ( ! self::validate_upload_path())
		{
			return FALSE;
		}

		$_FILES[$field]['tmp_name'] = preg_replace('/\/+/', '/', $_FILES[$field]['tmp_name']);
		if ( ! is_uploaded_file($_FILES[$field]['tmp_name']))
		{
			$error = ( ! isset($_FILES[$field]['error'])) ? 4 : $_FILES[$field]['error'];

			switch($error)
			{
				case 1:	// UPLOAD_ERR_INI_SIZE
					self::set_error('upload_file_exceeds_limit');
					break;
				case 2: // UPLOAD_ERR_FORM_SIZE
					self::set_error('upload_file_exceeds_form_limit');
					break;
				case 3: // UPLOAD_ERR_PARTIAL
					self::set_error('upload_file_partial');
					break;
				case 4: // UPLOAD_ERR_NO_FILE
					self::set_error('upload_no_file_selected');
					break;
				case 6: // UPLOAD_ERR_NO_TMP_DIR
					self::set_error('upload_no_temp_directory');
					break;
				case 7: // UPLOAD_ERR_CANT_WRITE
					self::set_error('upload_unable_to_write_file');
					break;
				case 8: // UPLOAD_ERR_EXTENSION
					self::set_error('upload_stopped_by_extension');
					break;
				default :   self::set_error('upload_no_file_selected');
					break;
			}

			return FALSE;
		}

		self::$file_temp = $_FILES[$field]['tmp_name'];
		self::$file_size = $_FILES[$field]['size'];

		self::_file_mime_type($_FILES[$field]);

		self::$file_name   = self::_prep_filename($_FILES[$field]['name']);
		self::$file_ext    = self::get_extension(self::$file_name);
		self::$client_name = self::$file_name;

		if ( ! self::is_allowed_filetype())
		{
			self::set_error('upload_invalid_filetype');
			return FALSE;
		}

		if (self::$_file_name_override != '')
		{
			self::$file_name = self::_prep_filename(self::$_file_name_override);

			if (strpos(self::$_file_name_override, '.') === FALSE)
			{
				self::$file_name .= self::$file_ext;
			}

			else
			{
				self::$file_ext	 = self::get_extension(self::$_file_name_override);
			}

			if ( ! self::is_allowed_filetype(TRUE))
			{
				self::set_error('upload_invalid_filetype');
				return FALSE;
			}
		}

		if (self::$file_size > 0)
		{
			self::$file_size = round(self::$file_size/1024, 2);
		}

		if ( ! self::is_allowed_filesize())
		{
			self::set_error('upload_invalid_filesize');
			return FALSE;
		}

		if ( ! self::is_allowed_dimensions())
		{
			self::set_error('upload_invalid_dimensions');
			return FALSE;
		}

		self::$file_name = self::clean_file_name(self::$file_name);

		if (self::$max_filename > 0)
		{
			self::$file_name = self::limit_filename_length(self::$file_name, self::$max_filename);
		}

		if (self::$remove_spaces == TRUE)
		{
			self::$file_name = preg_replace("/\s+/", "_", self::$file_name);
		}

		self::$orig_name = self::$file_name;

		if (self::$overwrite == FALSE)
		{
			self::$file_name = self::set_filename(self::$upload_path, self::$file_name);

			if (self::$file_name === FALSE)
			{
				return FALSE;
			}
		}

		if (self::$xss_clean)
		{
			if (self::do_xss_clean() === FALSE)
			{
				self::set_error('upload_unable_to_write_file');
				return FALSE;
			}
		}

		if ( ! @copy(self::$file_temp, self::$upload_path.self::$file_name))
		{
			if ( ! @move_uploaded_file(self::$file_temp, self::$upload_path.self::$file_name))
			{
				self::set_error('upload_destination_error');
				return FALSE;
			}
		}

		self::set_image_properties(self::$upload_path.self::$file_name);

		return TRUE;
	}

	static function data()
	{
		return array (
			'file_name'			=> self::$file_name,
			'file_type'			=> self::$file_type,
			'file_path'			=> self::$upload_path,
			'full_path'			=> self::$upload_path.self::$file_name,
			'raw_name'			=> str_replace(self::$file_ext, '', self::$file_name),
			'orig_name'			=> self::$orig_name,
			'client_name'		=> self::$client_name,
			'file_ext'			=> self::$file_ext,
			'file_size'			=> self::$file_size,
			'is_image'			=> self::is_image(),
			'image_width'		=> self::$image_width,
			'image_height'		=> self::$image_height,
			'image_type'		=> self::$image_type,
			'image_size_str'	=> self::$image_size_str,
		);
	}

	static function set_upload_path($path)
	{
		// Make sure it has a trailing slash
		self::$upload_path = rtrim($path, '/').'/';
	}

	static function set_filename($path, $filename)
	{
		if (self::$encrypt_name == TRUE)
		{
			mt_srand();
			$filename = md5(uniqid(mt_rand())).self::$file_ext;
		}

		if ( ! file_exists($path.$filename))
		{
			return $filename;
		}

		$filename = str_replace(self::$file_ext, '', $filename);

		$new_filename = '';
		for ($i = 1; $i < 100; $i++)
		{
			if ( ! file_exists($path.$filename.$i.self::$file_ext))
			{
				$new_filename = $filename.$i.self::$file_ext;
				break;
			}
		}

		if ($new_filename == '')
		{
			self::set_error('upload_bad_filename');
			return FALSE;
		}
		else
		{
			return $new_filename;
		}
	}

	static function set_max_filesize($n)
	{
		self::$max_size = ((int) $n < 0) ? 0: (int) $n;
	}

	static function set_max_filename($n)
	{
		self::$max_filename = ((int) $n < 0) ? 0: (int) $n;
	}

	static function set_max_width($n)
	{
		self::$max_width = ((int) $n < 0) ? 0: (int) $n;
	}

	static function set_max_height($n)
	{
		self::$max_height = ((int) $n < 0) ? 0: (int) $n;
	}

	static function set_allowed_types($types)
	{
		if ( ! is_array($types) && $types == '*')
		{
			self::$allowed_types = '*';
			return ;
		}
		self::$allowed_types = explode('|', $types);
	}

	static function set_image_properties($path = '')
	{
		if ( ! self::is_image())
		{
			return ;
		}

		if (function_exists('getimagesize'))
		{
			if (FALSE !== ($D = @getimagesize($path)))
			{
				$types = array(1 => 'gif', 2 => 'jpeg', 3 => 'png');

				self::$image_width		= $D['0'];
				self::$image_height		= $D['1'];
				self::$image_type		= ( ! isset($types[$D['2']])) ? 'unknown' : $types[$D['2']];
				self::$image_size_str	= $D['3'];  // string containing height and width
			}
		}
	}

	static function set_xss_clean($flag = FALSE)
	{
		self::$xss_clean = ($flag == TRUE) ? TRUE : FALSE;
	}

	static function is_image()
	{
		$png_mimes  = array('image/x-png');
		$jpeg_mimes = array('image/jpg', 'image/jpe', 'image/jpeg', 'image/pjpeg');

		if (in_array(self::$file_type, $png_mimes))
		{
			self::$file_type = 'image/png';
		}

		if (in_array(self::$file_type, $jpeg_mimes))
		{
			self::$file_type = 'image/jpeg';
		}

		$img_mimes = array(
							'image/gif',
							'image/jpeg',
							'image/png',
						);

		return (in_array(self::$file_type, $img_mimes, TRUE)) ? TRUE : FALSE;
	}

	static function is_allowed_filetype($ignore_mime = FALSE)
	{
		if (self::$allowed_types == '*')
		{
			return TRUE;
		}

		if (count(self::$allowed_types) == 0 OR ! is_array(self::$allowed_types))
		{
			self::set_error('upload_no_file_types');
			return FALSE;
		}

		$ext = strtolower(ltrim(self::$file_ext, '.'));

		if ( ! in_array($ext, self::$allowed_types))
		{
			return FALSE;
		}

		$image_types = array('gif', 'jpg', 'jpeg', 'png', 'jpe');

		if (in_array($ext, $image_types))
		{
			if (getimagesize(self::$file_temp) === FALSE)
			{
				return FALSE;
			}
		}

		if ($ignore_mime === TRUE)
		{
			return TRUE;
		}

		$mime = self::mimes_types($ext);

		if (is_array($mime))
		{
			if (in_array(self::$file_type, $mime, TRUE))
			{
				return TRUE;
			}
		}
		elseif ($mime == self::$file_type)
		{
				return TRUE;
		}

		return FALSE;
	}

	static function is_allowed_filesize()
	{
		if (self::$max_size != 0  AND  self::$file_size > self::$max_size)
		{
			return FALSE;
		}
		else
		{
			return TRUE;
		}
	}

	static function is_allowed_dimensions()
	{
		if ( ! self::is_image())
		{
			return TRUE;
		}

		if (function_exists('getimagesize'))
		{
			$D = @getimagesize(self::$file_temp);

			if (self::$max_width > 0 AND $D['0'] > self::$max_width)
			{
				return FALSE;
			}

			if (self::$max_height > 0 AND $D['1'] > self::$max_height)
			{
				return FALSE;
			}

			return TRUE;
		}

		return TRUE;
	}

	static function validate_upload_path()
	{
		if (self::$upload_path == '')
		{
			self::set_error('upload_no_filepath');
			return FALSE;
		}

		if (function_exists('realpath') AND @realpath(self::$upload_path) !== FALSE)
		{
			self::$upload_path = str_replace("\\", "/", realpath(self::$upload_path));
		}

		if ( ! @is_dir(self::$upload_path))
		{
			self::set_error('upload_no_filepath');
			return FALSE;
		}

		if ( ! is_writable(self::$upload_path))
		{
			self::set_error('upload_not_writable');
			return FALSE;
		}

		self::$upload_path = preg_replace("/(.+?)\/*$/", "\\1/",  self::$upload_path);
		return TRUE;
	}

	static function get_extension($filename)
	{
		$x = explode('.', $filename);
		return '.'.end($x);
	}

	static function clean_file_name($filename)
	{
		$bad = array(
			"<!--",
			"-->",
			"'",
			"<",
			">",
			'"',
			'&',
			'$',
			'=',
			';',
			'?',
			'/',
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

		$filename = str_replace($bad, '', $filename);

		return stripslashes($filename);
	}

	static function limit_filename_length($filename, $length)
	{
		if (strlen($filename) < $length)
		{
			return $filename;
		}

		$ext = '';
		if (strpos($filename, '.') !== FALSE)
		{
			$parts		= explode('.', $filename);
			$ext		= '.'.array_pop($parts);
			$filename	= implode('.', $parts);
		}

		return substr($filename, 0, ($length - strlen($ext))).$ext;
	}

	static function do_xss_clean()
	{
		$file = self::$file_temp;

		if (filesize($file) == 0)
		{
			return FALSE;
		}

		if (function_exists('memory_get_usage') && memory_get_usage() && ini_get('memory_limit') != '')
		{
			$current = (int)ini_get('memory_limit') * 1024 * 1024;

			$new_memory = number_format(ceil(filesize($file) + $current), 0, '.', '');

			ini_set('memory_limit', $new_memory);
		}

		if (function_exists('getimagesize') && @getimagesize($file) !== FALSE)
		{
			if (($file = @fopen($file, 'rb')) === FALSE)
			{
				return FALSE;
			}

			$opening_bytes = fread($file, 256);
			fclose($file);

			if ( ! preg_match('/<(a|body|head|html|img|plaintext|pre|script|table|title)[\s>]/i', $opening_bytes))
			{
				return TRUE;
			}
			else
			{
				return FALSE;
			}
		}

		if (($data = @file_get_contents($file)) === FALSE)
		{
			return FALSE;
		}

		return security::xss_clean($data,self::is_image());
	}

	static function set_error($msg)
	{
		if (is_array($msg))
		{
			foreach ($msg as $val)
			{
				$msg = $val;

				self::$error_msg[] = $msg;
			}
		}
		else
		{
			self::$error_msg[] = $msg;
		}
	}

	static function display_errors($open = '', $close = '')
	{
		$str = '';
		foreach (self::$error_msg as $val)
		{
			$str .= $open.$val.$close;
		}

		return $str;
	}

	static function mimes_types($mime)
	{
		if(empty(self::$mimes))
		{
			self::$mimes = mime::list();

			if(empty(self::$mimes)) return FALSE;
		}

		return (!isset(self::$mimes[$mime])) ? FALSE : self::$mimes[$mime];
	}

	static function _prep_filename($filename)
	{
		if (strpos($filename, '.') === FALSE OR self::$allowed_types == '*')
		{
			return $filename;
		}

		$parts		= explode('.', $filename);
		$ext		= array_pop($parts);
		$filename	= array_shift($parts);

		foreach ($parts as $part)
		{
			if ( ! in_array(strtolower($part), self::$allowed_types) OR self::mimes_types(strtolower($part)) === FALSE)
			{
				$filename .= '.'.$part.'_';
			}
			else
			{
				$filename .= '.'.$part;
			}
		}

		$filename .= '.'.$ext;

		return $filename;
	}

	static function _file_mime_type($file)
	{
		$regexp = '/^([a-z\-]+\/[a-z0-9\-\.\+]+)(;\s.+)?$/';

		if (function_exists('finfo_file'))
		{
			$finfo = finfo_open(FILEINFO_MIME);
			if (is_resource($finfo))
			{
				$mime = @finfo_file($finfo, $file['tmp_name']);
				finfo_close($finfo);

				if (is_string($mime) && preg_match($regexp, $mime, $matches))
				{
					self::$file_type = $matches[1];

					if(self::$file_type)
					{
						self::$file_type = strtolower(trim(stripslashes(self::$file_type), '"'));

						return ;
					}
				}
			}
		}

		if (DIRECTORY_SEPARATOR !== '\\')
		{
			$cmd = 'file --brief --mime ' . escapeshellarg($file['tmp_name']) . ' 2>&1';

			if (function_exists('exec'))
			{
				$mime = @exec($cmd, $mime, $return_status);
				if ($return_status === 0 && is_string($mime) && preg_match($regexp, $mime, $matches))
				{
					self::$file_type = $matches[1];

					if(self::$file_type)
					{
						self::$file_type = strtolower(trim(stripslashes(self::$file_type), '"'));

						return ;
					}
				}
			}

			if ( (bool) @ini_get('safe_mode') === FALSE && function_exists('shell_exec'))
			{
				$mime = @shell_exec($cmd);
				if (strlen($mime) > 0)
				{
					$mime = explode("\n", trim($mime));
					if (preg_match($regexp, $mime[(count($mime) - 1)], $matches))
					{
						self::$file_type = $matches[1];

						if(self::$file_type)
						{
							self::$file_type = strtolower(trim(stripslashes(self::$file_type), '"'));

							return ;
						}
					}
				}
			}

			if (function_exists('popen'))
			{
				$proc = @popen($cmd, 'r');
				if (is_resource($proc))
				{
					$mime = @fread($proc, 512);
					@pclose($proc);
					if ($mime !== FALSE)
					{
						$mime = explode("\n", trim($mime));
						if (preg_match($regexp, $mime[(count($mime) - 1)], $matches))
						{
							self::$file_type = $matches[1];

							if(self::$file_type)
							{
								self::$file_type = strtolower(trim(stripslashes(self::$file_type), '"'));

								return ;
							}
						}
					}
				}
			}
		}

		if (function_exists('mime_content_type'))
		{
			self::$file_type = @mime_content_type($file['tmp_name']);

			if(self::$file_type)
			{
				self::$file_type = strtolower(trim(stripslashes(self::$file_type), '"'));

				return ;
			}
		}

		self::$file_type = preg_replace("/^(.+?);.*$/", "\\1",$file['type']);

		self::$file_type = strtolower(trim(stripslashes(self::$file_type), '"'));
	}
}

?>