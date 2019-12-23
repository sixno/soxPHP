<?php

function run($func,$output = TRUE)
{
	$time = microtime();

	$func();

	$stop = microtime();

	$stop = explode(' ',$stop);
	$stop = $stop[1].sprintf('%06d',$stop[0] * 1000000);

	$time = explode(' ',$time);
	$time = $time[1].sprintf('%06d',$time[0] * 1000000);

	$time = $stop - $time;

	if($output) output('it runs for '.($time / 1000).' (ms). ');

	return $time;
}

function get_caller_info()
{
	$c     = '';
	$file  = '';
	$func  = '';
	$class = '';
	$trace = debug_backtrace();

	if(isset($trace[2]))
	{
		$file = $trace[1]['file'];
		$func = $trace[2]['function'];
		if((substr($func, 0, 7) == 'include') || (substr($func, 0, 7) == 'require'))
		{
			$func = '';
		}
	}
	elseif(isset($trace[1]))
	{
		$file = $trace[1]['file'];
		$func = '';
	}

	if(isset($trace[3]['class']))
	{
		$class = $trace[3]['class'];
		$func  = $trace[3]['function'];
		$file  = $trace[2]['file'];
	}
	elseif(isset($trace[2]['class']))
	{
		$class = $trace[2]['class'];
		$func  = $trace[2]['function'];
		$file  = $trace[1]['file'];
	}

	if($file != '') $file = basename($file);

	$c = $file . ": ";
	$c .= ($class != '') ? ":" . $class . "->" : "";
	$c .= ($func != '') ? $func . "(): " : "";

	return($c);
}

function usec($microtime = NULL,$date_format = '')
{
	if($microtime === NULL)
	{
		$mt = explode(' ',microtime());

		return $mt[1].sprintf('%06d',$mt[0] * 1000000);
	}
	else
	{
		$time = substr($microtime,0,-6);

		if(empty($date_format))
		{
			return $time;
		}
		else
		{
			return date($date_format,$time);
		}
	}
}

function output($v)
{
	if(!is_array($v) && !is_object($v))
	{
		echo $v."\r\n";
	}
	else
	{
		var_dump($v);
	}
}

function strtobin($str)
{
	$arr = preg_split('/(?<!^)(?!$)/u',$str);

	foreach($arr as &$v)
	{
		$temp = unpack('H*',$v);
		$v = base_convert($temp[1],16,2);

		unset($temp);
	}

	return join(' ',$arr);
}

function bintostr($str)
{
	$arr = explode(' ',$str);

	foreach($arr as &$v)
	{
		$v = pack('H'.strlen(base_convert($v,2,16)), base_convert($v,2,16));
	}

	return join('', $arr);
}

function url($uri = '',$base = '')
{
	static $root;

	if(!isset($root)) $root = '';

	if($base)
	{
		$root = $base;
	}

	return strpos($uri,http_protocol()) !== 0 ? '/'.$root.trim($uri,'/') : $uri;
}

function http_protocol()
{
	static $_protocol;

	if(!isset($_protocol))
	{
		$_protocol = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https')) ? 'https://' : 'http://';
	}

	return $_protocol;
}

function http_user_agent($default = 'client')
{
	if(isset($_SERVER["HTTP_USER_AGENT"]))
	{
		return $_SERVER["HTTP_USER_AGENT"];
	}
	else
	{
		return $default;
	}
}

function http_method($def = '')
{
	$method = isset($_SERVER['REQUEST_METHOD']) ? strtolower($_SERVER['REQUEST_METHOD']) : $def;

	return $method;
}

function http_header($ind = '',$def = '')
{
	$header = array();

	if(!empty($_SERVER))
	{
		if(!empty($ind))
		{
			$ins = explode(',',$ind);

			foreach($ins as $in)
			{
				$inh = 'HTTP_'.strtoupper(str_replace('-','_',$in));

				if(isset($_SERVER[$inh]))
				{
					$header[$in] = $_SERVER[$inh];
				}
				else
				{
					$header[$in] = $def;
				}
			}

			if(count($ins) == 1) return $header[$ind];
		}
		else
		{
			foreach($_SERVER as $key => $value)
			{
				$key = strtolower($key);

				if(substr($key,0,5) == 'http_')
				{
					$header[substr($key,5)] = $value;
				}
			}
		}
	}

	return $header;
}

function http_entity($force_array = TRUE)
{
	static $_http_entity;

	if(!isset($_http_entity))
	{
		if($force_array)
		{
			$_http_entity = json_decode(file_get_contents("php://input"),TRUE);

			if(!is_array($_http_entity)) $_http_entity = array();
		}
		else
		{
			$_http_entity = file_get_contents("php://input");
		}
	}

	return $_http_entity;
}

function http_uri($n = 0,$def = '',$index_file = 'index.php')
{
	static $uri;

	if(!isset($uri))
	{
		if(!empty($_SERVER['REQUEST_URI']))
		{
			$url = $_SERVER['REQUEST_URI'];

			$pos = strpos($url,'?');

			if($pos !== FALSE) $url = substr($url,0,$pos);

			if(strpos($url,$index_file) === FALSE)
			{
				$script_url = substr($_SERVER['SCRIPT_FILENAME'],strlen($_SERVER['DOCUMENT_ROOT']));

				if('/'.$index_file != $script_url)
				{
					$url = $script_url;
				}
			}

			$uri = array_values(array_filter(explode('/',$url)));

			$pos = array_search($index_file,$uri);

			if($pos > 0)
			{
				$arr = array_splice($uri,0,$pos);

				if($arr) url('',implode('/',$arr).'/');
			}
			elseif($pos === FALSE)
			{
				array_unshift($uri,$index_file);
			}
		}
		else
		{
			$uri = [$index_file];
		}
	}

	if(is_int($n))
	{
		if($n > 0)
		{
			return isset($uri[$n]) ? $uri[$n] : $def;
		}
		else
		{
			return $uri;
		}
	}
	else
	{
		foreach($n as $k => $v)
		{
			$uri[$k+1] = $v;
		}

		return $uri;
	}
}

function http_get($key = '',$def = '')
{
	if($key !== '')
	{
		return isset($_GET[$key]) ? $_GET[$key] : $def;
	}
	else
	{
		return $_GET;
	}
}

function http_post($key = '',$def = '')
{
	if($key !== '')
	{
		return isset($_POST[$key]) ? $_POST[$key] : $def;
	}
	else
	{
		return $_POST;
	}
}

function http_json($key = '',$def = '')
{
	static $arg;

	if(!isset($arg))
	{
		$arg = json_decode(file_get_contents("php://input"),TRUE,2);
	}

	if($key !== '')
	{
		return isset($arg[$key]) ? $arg[$key] : $def;
	}
	else
	{
		return $arg;
	}
}

function cookie($key = NULL,$def = '')
{
	if(!empty($key))
	{
		if(strpos($key,'.') === FALSE)
		{
			if(isset($_COOKIE[$key]))
			{
				return $_COOKIE[$key];
			}
			else
			{
				return $def;
			}
		}
		else
		{
			$cop = &$_COOKIE;

			foreach(explode('.',$key) as $ckey)
			{
				if(isset($cop[$ckey]))
				{
					$cop = &$cop[$ckey];
				}
				else
				{
					return $def;
				}
			}

			return $cop;
		}
	}
	else
	{
		return $_COOKIE;
	}
}

function set_cookie($data,$path = '/',$time = 0,$doma = NULL)
{
	if(!is_array($data))
	{
		$para = func_get_args();
		$data = array($para[0] => $para[1]);
		$path = isset($para[2]) ? $para[2] : '/';
		$time = isset($para[3]) ? $para[3] : 0;
		$doma = isset($para[4]) ? $para[4] : NULL;
	}

	if(is_int($path))
	{
		$time = $path;
		$path = '/';
	}

	if(is_string($time))
	{
		$doma = $time;
		$time = 0;
	}

	$time = $time == 0 ? $time : time()+$time;

	foreach($data as $key => $value)
	{
		if(!is_array($value))
		{
			if(strpos($key,'.') === FALSE)
			{
				if(isset($_COOKIE[$key]))
				{
					if(is_array($_COOKIE[$key]))
					{
						$cookie_str = http_build_query(array($key => $_COOKIE[$key]));
						$cookie_arr = explode('&',$cookie_str);

						foreach($cookie_arr as $cookie)
						{
							$a_cookie = explode('=',$cookie);
							setcookie(urldecode($a_cookie[0]),NULL,-1,$path,$doma);
						}

						$_COOKIE[$key] = NULL;
					}
				}

				setcookie($key,$value,$time,$path,$doma);

				$_COOKIE[$key] = $value;
			}
			else
			{
				$cop = &$_COOKIE;
				$cox = substr_count($key,'.');

				foreach(explode('.',$key) as $ckk => $ckey)
				{
					if($ckk > 0)
					{
						$cookie_key .= '['.$ckey.']';
					}
					else
					{
						$cookie_key = $ckey;
					}

					if($ckk < $cox)
					{
						if(isset($cop[$ckey]))
						{
							if(!is_array($cop[$ckey]))
							{
								setcookie($cookie_key,NULL,-1,$path,$doma);

								$cop[$ckey] = NULL;
							}
						}
						else
						{
							$cop[$ckey] = NULL;
						}

						$cop = &$cop[$ckey];
					}
					else
					{
						if(isset($cop[$ckey]))
						{
							if(is_array($cop[$ckey]))
							{
								$cookie_str = http_build_query(array($cookie_key => $cop[$ckey]));
								$cookie_arr = explode('&',$cookie_str);

								foreach($cookie_arr as $cookie)
								{
									$a_cookie = explode('=',$cookie);
									setcookie(urldecode($a_cookie[0]),NULL,-1,$path,$doma);
								}

								$cop[$ckey] = NULL;
							}
						}
						else
						{
							$cop[$ckey] = NULL;
						}

						$cop = &$cop[$ckey];
					}
				}

				setcookie($cookie_key,$value,$time,$path,$doma);

				$cop = $value;
			}
		}
		else
		{
			$x_cookie_str = http_build_query($value);
			$x_cookie_arr = explode('&',$x_cookie_str);

			foreach($x_cookie_arr as $x_cookie)
			{
				$a_cookie = explode('=',$x_cookie);

				if(isset($a_cookie[1]))
				{
					set_cookie($key.'.'.str_replace(array('[',']'),array('.',''),urldecode($a_cookie[0])),urldecode($a_cookie[1]),$time,$path,$doma);
				}
			}
		}
	}
}

function del_cookie($key = NULL,$path = '/',$doma = NULL)
{
	if(!empty($key))
	{
		if(strpos($key,'.') === FALSE)
		{
			if(isset($_COOKIE[$key]))
			{
				if(!is_array($_COOKIE[$key]))
				{
					setcookie($key,NULL,-1,$path,$doma);
				}
				else
				{
					$cookie_str = http_build_query(array($key => $_COOKIE[$key]));
					$cookie_arr = explode('&',$cookie_str);

					foreach($cookie_arr as $cookie)
					{
						$a_cookie = explode('=',$cookie);
						setcookie(urldecode($a_cookie[0]),NULL,-1,$path,$doma);
					}
				}

				unset($_COOKIE[$key]);
			}
		}
		else
		{
			$cop = &$_COOKIE;
			$ckeys = explode('.',$key);
			$pop_ckey = array_pop($ckeys);

			foreach($ckeys as $ckk => $ckey)
			{
				if($ckk > 0)
				{
					$cookie_key .= '['.$ckey.']';
				}
				else
				{
					$cookie_key = $ckey;
				}

				if(isset($cop[$ckey]))
				{
					$cop = &$cop[$ckey];
				}
				else
				{
					return;
				}
			}

			if(isset($cop[$pop_ckey]))
			{
				if(!is_array($cop[$pop_ckey]))
				{
					setcookie($cookie_key.'['.$pop_ckey.']',NULL,-1,$path,$doma);
				}
				else
				{
					$cookie_str = http_build_query(array($cookie_key.'['.$pop_ckey.']' => $cop[$pop_ckey]));
					$cookie_arr = explode('&',$cookie_str);

					foreach($cookie_arr as $cookie)
					{
						$a_cookie = explode('=',$cookie);
						setcookie(urldecode($a_cookie[0]),NULL,-1,$path,$doma);
					}
				}

				unset($cop[$pop_ckey]);
			}
		}
	}
	else
	{
		if(!empty($_COOKIE))
		{
			$cookie_str = http_build_query($_COOKIE);
			$cookie_arr = explode('&',$cookie_str);

			foreach($cookie_arr as $cookie)
			{
				$a_cookie = explode('=',$cookie);
				setcookie(urldecode($a_cookie[0]),NULL,-1,$path,$doma);
			}

			$_COOKIE = array();
		}
	}
}

function send_request($url,$entity = array(),$header = array(),$method = '',$is_json = TRUE,$timeout = 30)
{
	if(is_array($url))
	{
		foreach($url as $k => $v)
		{
			${$k} = $v;
		}
	}
	else
	{
		if(is_string($entity) && $method == '')
		{
			$method = $entity;
			$entity = array();
		}
		elseif(is_bool($entity))
		{
			$is_json = $entity;
			$entity  = array();
		}
		elseif(is_int($entity))
		{
			$timeout = $entity;
			$entity  = array();
		}

		if(is_string($header))
		{
			$method = $header;
			$header = array();
		}
		elseif(is_bool($header))
		{
			$is_json = $header;
			$header  = array();
		}
		elseif(is_int($header))
		{
			$timeout = $header;
			$header  = array();
		}

		if(is_bool($method))
		{
			$is_json = $method;
			$method  = '';
		}
		elseif(is_int($method))
		{
			$timeout = $method;
			$method  = '';
		}

		if(is_int($is_json))
		{
			$timeout = $is_json;
			$is_json = TRUE;
		}
	}

	$ch = curl_init();

	curl_setopt($ch,CURLOPT_RETURNTRANSFER,TRUE);
	curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,FALSE);
	curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,FALSE);
	curl_setopt($ch,CURLOPT_URL,$url);
	curl_setopt($ch,CURLOPT_TIMEOUT,$timeout);

	if(!empty($header))
	{
		$http_header = array();

		foreach($header as $key => $value)
		{
			$http_header[] = strtoupper($key).': '.$value;
		}

		curl_setopt($ch,CURLOPT_HTTPHEADER,$http_header);
	}
	

	if(!empty($entity))
	{
		if(empty($method))
		{
			curl_setopt($ch,CURLOPT_POST,TRUE);
			curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($entity));
		}
		else
		{
			switch($method)
			{
				case 'get':
					curl_setopt($ch,CURLOPT_URL,$url.'?'.http_build_query($entity));
					break;
				
				case 'post':
					$method = '';

					curl_setopt($ch,CURLOPT_POST,TRUE);
					curl_setopt($ch,CURLOPT_POSTFIELDS,$entity);
					break;
				
				default:
					if($method == 'json')
					{
						$method = '';

						curl_setopt($ch,CURLOPT_POST,TRUE);
					}

					curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($entity));
					break;
			}
		}
	}

	if(!empty($method))
	{
		curl_setopt($ch,CURLOPT_CUSTOMREQUEST,strtoupper($method));
	}

	$result = curl_exec($ch);

	// var_dump(curl_getinfo($ch));
	
	curl_close($ch);

	if($is_json)
	{
		$result = json_decode($result,TRUE);
	}

	return $result;
}

function img_resize($src,$dst,$dst_w,$dst_h,$size = '')
{
	$type = exif_imagetype($src);

	switch($type)
	{
		case IMAGETYPE_JPEG : $src_img = imagecreatefromjpeg($src); break;
		case IMAGETYPE_PNG  : $src_img = imagecreatefrompng($src);  break;
		case IMAGETYPE_GIF  : $src_img = imagecreatefromgif($src);  break;
		
		default: return FALSE; break;
	}

	$src_w = imagesx($src_img);
	$src_h = imagesy($src_img);

	$src_ratio = $src_w / $src_h;

	if($dst_w > 0 && $dst_h > 0)
	{
		$dst_ratio = $dst_w / $dst_h;

		switch($size)
		{
			case 'cover':
				if($src_ratio > $dst_ratio)
				{
					$new_h = $dst_h;
					$new_w = (int)round($new_h * $src_ratio);
				}
				else
				{
					$new_w = $dst_w;
					$new_h = (int)round($new_w / $src_ratio);
				}

				$dst_img = imagecreatetruecolor($new_w,$new_h);

				imagecopyresampled($dst_img,$src_img,0,0,0,0,$new_w,$new_h,$src_w,$src_h);

				imagedestroy($src_img);
				break;
			
			case 'contain':
				if($src_ratio > $dst_ratio)
				{
					$new_w = $dst_w;
					$new_h = (int)round($new_w / $src_ratio);
				}
				else
				{
					$new_h = $dst_h;
					$new_w = (int)round($new_h * $src_ratio);
				}

				$dst_img = imagecreatetruecolor($new_w,$new_h);

				imagecopyresampled($dst_img,$src_img,0,0,0,0,$new_w,$new_h,$src_w,$src_h);

				imagedestroy($src_img);
				break;
			
			default:
				if($src_ratio > $dst_ratio)
				{
					$new_h = $dst_h;
					$new_w = (int)round($new_h * $src_ratio);

					$x = (int)round(($new_w - $dst_w) / 2);
					$y = 0;
				}
				else
				{
					$new_w = $dst_w;
					$new_h = (int)round($new_w / $src_ratio);

					$x = 0;
					$y = (int)round(($new_h - $dst_h) / 2);
				}

				$new_img = imagecreatetruecolor($new_w,$new_h);

				imagecopyresampled($new_img,$src_img,0,0,0,0,$new_w,$new_h,$src_w,$src_h);

				imagedestroy($src_img);

				$dst_img = imagecreatetruecolor($dst_w,$dst_h);

				imagecopy($dst_img,$new_img,0,0,$x,$y,$dst_w,$dst_h);

				imagedestroy($new_img);
				break;
		}
	}
	elseif($dst_w > 0 && $dst_h == 0)
	{
		$new_w = $dst_w;
		$new_h = (int)round($new_w / $src_ratio);

		$dst_img = imagecreatetruecolor($new_w,$new_h);

		imagecopyresampled($dst_img,$src_img,0,0,0,0,$new_w,$new_h,$src_w,$src_h);

		imagedestroy($src_img);
	}
	elseif($dst_w == 0 && $dst_h > 0)
	{
		$new_h = $dst_h;
		$new_w = (int)round($new_h * $src_ratio);

		$dst_img = imagecreatetruecolor($new_w,$new_h);

		imagecopyresampled($dst_img,$src_img,0,0,0,0,$new_w,$new_h,$src_w,$src_h);

		imagedestroy($src_img);
	}
	else
	{
		imagedestroy($src_img);

		return '';
	}

	switch($type)
	{
		case IMAGETYPE_JPEG : $dst .= strpos($dst,'.') === FALSE ? '.jpg' : ''; imagejpeg($dst_img,$dst,100); break;
		case IMAGETYPE_PNG  : $dst .= strpos($dst,'.') === FALSE ? '.png' : ''; imagepng($dst_img,$dst,9);  break;
		case IMAGETYPE_GIF  : $dst .= strpos($dst,'.') === FALSE ? '.gif' : ''; imagegif($dst_img,$dst);  break;
	}

	imagedestroy($dst_img);

	return $dst;
}

function sure_url($url,$base = '')
{
	if(empty($base))
	{
		if((substr($url,0,7) != 'http://') && (substr($url,0,8) != 'https://')) return FALSE;
	}
	else
	{
		if(strpos($base,',') === FALSE)
		{
			if(substr($url,0,strlen($base)) != $base) return FALSE;
		}
		else
		{
			$stop = TRUE;

			foreach(explode(',',$base) as $b)
			{
				if(substr($url,0,strlen($b)) == $b)
				{
					$stop = FALSE;

					break;
				}
			}

			if($stop) return FALSE;
		}
	}

	$ch = curl_init();

	curl_setopt($ch,CURLOPT_URL,$url);
	curl_setopt($ch,CURLOPT_NOBODY,1);
	curl_setopt($ch,CURLOPT_CONNECTTIMEOUT,3);
	curl_setopt($ch,CURLOPT_TIMEOUT,3);
	curl_setopt($ch,CURLOPT_FOLLOWLOCATION,1);
	curl_setopt($ch,CURLOPT_AUTOREFERER,1);

	curl_exec($ch);

	$cd = curl_getinfo($ch,CURLINFO_HTTP_CODE);

	curl_close($ch);

	return $cd == 200;
}

function size_img($url,$base = '')
{
	$step = 1024;
	$curn = 1;
	$maxn = 30;

	$re = '';

	if(empty($base))
	{
		if((substr($url,0,7) != 'http://') && (substr($url,0,8) != 'https://')) return FALSE;
	}
	else
	{
		if(strpos($base,',') === FALSE)
		{
			if(substr($url,0,strlen($base)) != $base) return FALSE;
		}
		else
		{
			$stop = TRUE;

			foreach(explode(',',$base) as $b)
			{
				if(substr($url,0,strlen($b)) == $b)
				{
					$stop = FALSE;

					break;
				}
			}

			if($stop) return FALSE;
		}
	}

	$ch = curl_init($url);

	curl_setopt($ch,CURLOPT_CONNECTTIMEOUT,3);
	curl_setopt($ch,CURLOPT_TIMEOUT,5);
	curl_setopt($ch,CURLOPT_FOLLOWLOCATION,1);
	curl_setopt($ch,CURLOPT_RETURNTRANSFER,TRUE);

	do
	{
		if($curn > $maxn) break;

		curl_setopt($ch,CURLOPT_RANGE,(($curn - 1) * $step).'-'.($curn * $step - 1));
		$re .= curl_exec($ch);

		$size = getimagesize('data://image/unknown;base64,'.base64_encode($re));

		$curn++;
	}while(empty($size));

	curl_close($ch);

	return !empty($size) ? array('w' => $size[0],'h' => $size[1],'t' => $size[2]) : FALSE;
}

function saltedhash($string,$salt = NULL,$saltLength = 8)
{
	if($salt == NULL)
	{
		$salt = substr(md5(time()),0,$saltLength);
	}
	else
	{
		$salt = substr($salt,0,$saltLength);
	}

	return $salt.sha1($salt.$string);
}

function &load_redis($conf)
{
	static $redis;

	$id = md5(json_encode($conf));

	if(!isset($redis[$id]))
	{
		$redis[$id] = new Redis();

		if($redis[$id]->connect($conf['host'],$conf['port'],3))
		{
			$redis[$id]->select($conf['dbno']);
		}
		else
		{
			$redis[$id] = NULL;
		}
	}

	return $redis[$id];
}

function &load_mysql($conf)
{
	static $mysql;

	$id = md5(json_encode($conf));

	if(!isset($mysql[$id]))
	{
		if(!empty($conf))
		{
			$mysql[$id] = @ new mysqli($conf['hostname'],$conf['username'],$conf['password'],$conf['database'],$conf['hostport']);

			if(!$mysql[$id]->connect_error)
			{
				if(version_compare($mysql[$id]->get_server_info(),'5.0.7','>='))
				{
					$mysql[$id]->set_charset($conf['char_set']);
				}
				else
				{
					$mysql[$id]->query('SET NAMES \''.$conf['char_set'].'\' COLLATE \''.$conf['dbcollat'].'\'');
				}
			}
			else
			{
				message('mysql: Connect Error ('.$mysql[$id]->connect_errno.') - '.$mysql[$id]->connect_error);
			}
		}
		else
		{
			message('mysql: no config data.');
		}
	}

	return $mysql[$id];
}

function &load_odbc($conf)
{
	static $odbc;

	$id = md5(json_encode($conf));

	if(!isset($odbc[$id]))
	{
		if(!empty($conf))
		{
			$odbc[$id] = new \sox\sdk\com\odbc($conf['hostname'],$conf['username'],$conf['password'],$conf['database']);

			if($odbc[$id]->connect_error)
			{
				message('odbc: Connect Error ('.$odbc[$id]->connect_errno.') - '.$odbc[$id]->connect_error);
			}
		}
		else
		{
			message('odbc: no config data.');
		}
	}

	return $odbc[$id];
}

function message($content)
{
	if(!defined('SOXMSG'))
	{
		echo 'System Message: '.$content."\r\n";
	}
	else
	{
		call_user_func(SOXMSG,$content);
	}

	exit;
}

function imagefilledroundrect($image,$radius,$x1,$y1,$x2,$y2,$color)
{
	imagefilledellipse($image,$x1+$radius,$y1+$radius,2*$radius,2*$radius,$color);
	imagefilledellipse($image,$x2-$radius,$y1+$radius,2*$radius,2*$radius,$color);
	imagefilledellipse($image,$x1+$radius,$y2-$radius,2*$radius,2*$radius,$color);
	imagefilledellipse($image,$x2-$radius,$y2-$radius,2*$radius,2*$radius,$color);

	imagefilledrectangle($image,$x1+$radius,$y1,$x2-$radius,$y2,$color);
	imagefilledrectangle($image,$x1,$y1+$radius,$x2,$y2-$radius,$color);
}

function str2bin($str)
{
	$arr = str_split((string)$str);

	foreach($arr as &$arv)
	{
		$tmp = unpack('H*',$arv);
		$arv = str_pad(base_convert($tmp[1],16,2),8,'0',STR_PAD_LEFT);
	}

	return implode('',$arr);
}

function bin2str($bin)
{
	$arr = str_split($bin,8);

	foreach($arr as &$arv)
	{
		$arv = pack('H*',base_convert($arv,2,16));
	}

	return join('',$arr);
}

function str4img($str,$img,$out_type = '')
{
	$stp = '11111111';

	$bin = str2bin($str).$stp;
	$len = strlen($bin);

	if($file = fopen($img,'rb'))
	{
		$fbin = fread($file,2);

		fclose($file);
	}
	else
	{
		return '';
	}

	$fstr = unpack('C2chars',$fbin);
	$fint = intval($fstr['chars1'].$fstr['chars2']);

	switch($fint)
	{
		case 255216: $type = 'jpg';$im = imagecreatefromjpeg($img);  break;
		case 13780:  $type = 'png';$im = imagecreatefrompng($img);  break;
		case 7173:   $type = 'gif';$im = imagecreatefromgif($img);  break;
		case 6677:   $type = 'bmp';$im = imagecreatefrombmp($img);  break;

		default: return ''; break;
	}

	if(empty($out_type)) $out_type = $type;

	$ix = imagesx($im);
	$iy = imagesy($im);

	$in = 0;

	for($x = 0;$x < $ix;$x++)
	{
		if($in == $len) break;

		for($y = 0;$y < $iy;$y++)
		{
			if($in == $len) break;

			$rgb = imagecolorat($im,$x,$y);

			$r = ($rgb >> 16) & 0xFF;
			$g = ($rgb >> 8) & 0xFF;
			$b = $rgb & 0xFF;

			$r = $r % 2 == $bin[$in] ? $r : $r - 1;//bindec(substr(decbin($r),0,-1).$bin[$in]);
			$g = $g % 2 == $bin[$in] ? $g : $g - 1;//bindec(substr(decbin($g),0,-1).$bin[$in]);
			$b = $b % 2 == $bin[$in] ? $b : $b - 1;//bindec(substr(decbin($b),0,-1).$bin[$in]);

			$rgb = imagecolorallocate($im,$r,$g,$b);

			imagesetpixel($im,$x,$y,$rgb);

			$in++;
		}
	}

	$pathinfo = pathinfo($img);

	$file = $pathinfo['dirname'].'/'.$pathinfo['filename'].'_msg.'.$out_type;

	switch($out_type)
	{
		case 'jpg': imagejpeg($im,$file,100);break;
		case 'png': imagepng($im,$file);break;
		case 'gif': imagegif($im,$file);break;
		case 'bmp': imagebmp($im,$file);break;
		
		default: imagedestroy($im);return ''; break;
	}

	imagedestroy($im);

	return $file;
}

function img4str($img)
{
	$bin = '';

	if($file = fopen($img,'rb'))
	{
		$fbin = fread($file,2);

		fclose($file);
	}
	else
	{
		return '';
	}

	$fstr = unpack('C2chars',$fbin);
	$fint = intval($fstr['chars1'].$fstr['chars2']);

	switch($fint)
	{
		case 255216: $im = imagecreatefromjpeg($img);  break;
		case 13780:  $im = imagecreatefrompng($img);  break;
		case 7173:   $im = imagecreatefromgif($img);  break;
		case 6677:   $im = imagecreatefrombmp($img);  break;

		default: return ''; break;
	}

	$ix = imagesx($im);
	$iy = imagesy($im);

	$in = 0;
	$ll = '';

	for($x = 0;$x < $ix;$x++)
	{
		if($ll == '11111111') break;

		for($y = 0;$y < $iy;$y++)
		{
			if($ll == '11111111') break;

			if($in == 8)
			{
				$bin .= $ll;

				$ll = '';

				$in = 0;
			}

			$rgb = imagecolorat($im,$x,$y);

			$r = ($rgb >> 16) & 0xFF;
			$g = ($rgb >> 8) & 0xFF;
			$b = $rgb & 0xFF;

			$l1 = $r % 2;
			$l2 = $g % 2;
			$l3 = $b % 2;

			$ll .= $l1 + $l2 + $l3 > 1 ? '1' : '0';

			$in++;
		}
	}

	imagedestroy($im);

	return bin2str($bin);
}

function remote_ip()
{
	if(empty($_SERVER["HTTP_X_FORWARDED_FOR"]))
	{
		$remote_ip = isset($_SERVER["REMOTE_ADDR"]) ? $_SERVER["REMOTE_ADDR"] : '';
	}
	else
	{
		$remote_ip = $_SERVER["HTTP_X_FORWARDED_FOR"];
	}

	return $remote_ip;
}

function valid_ipv4($ip)
{
	$ip_segments = explode('.', $ip);

	// Always 4 segments needed
	if(count($ip_segments) !== 4)
	{
		return FALSE;
	}
	// IP can not start with 0
	if($ip_segments[0][0] == '0')
	{
		return FALSE;
	}

	// Check each segment
	foreach($ip_segments as $segment)
	{
		// IP segments must be digits and can not be
		// longer than 3 digits or greater then 255
		if($segment == '' OR preg_match("/[^0-9]/", $segment) OR $segment > 255 OR strlen($segment) > 3)
		{
			return FALSE;
		}
	}

	return TRUE;
}

function valid_ipv6($str)
{
	// 8 groups, separated by :
	// 0-ffff per group
	// one set of consecutive 0 groups can be collapsed to ::

	$groups = 8;
	$collapsed = FALSE;

	$chunks = array_filter(
		preg_split('/(:{1,2})/', $str, NULL, PREG_SPLIT_DELIM_CAPTURE)
	);

	// Rule out easy nonsense
	if(current($chunks) == ':' OR end($chunks) == ':')
	{
		return FALSE;
	}

	// PHP supports IPv4-mapped IPv6 addresses, so we'll expect those as well
	if(strpos(end($chunks), '.') !== FALSE)
	{
		$ipv4 = array_pop($chunks);

		if(!valid_ipv4($ipv4))
		{
			return FALSE;
		}

		$groups--;
	}

	while($seg = array_pop($chunks))
	{
		if($seg[0] == ':')
		{
			if(--$groups == 0)
			{
				return FALSE;	// too many groups
			}

			if(strlen($seg) > 2)
			{
				return FALSE;	// long separator
			}

			if($seg == '::')
			{
				if($collapsed)
				{
					return FALSE;	// multiple collapsed
				}

				$collapsed = TRUE;
			}
		}
		elseif(preg_match("/[^0-9a-f]/i", $seg) OR strlen($seg) > 4)
		{
			return FALSE; // invalid segment
		}
	}

	return $collapsed OR $groups == 1;
}

function str_rand($length,$str_select = '')
{
	if(empty($str_select)) $str_select = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
	$str_length = strlen($str_select);

	$rand_str = '';
	for($i = 0;$i < $length;$i++)
	{
		$rand_str .= substr($str_select,mt_rand(0,$str_length-1),1);
	}

	return $rand_str;
}

function str_encrypt($txt,$key)
{
	$encrypt_key = md5(mt_rand(0,99999));

	$ctr = 0;
	$tmp = '';

	for($i = 0;$i < strlen($txt);$i++)
	{
		$ctr = $ctr == strlen($encrypt_key) ? 0 : $ctr;
		$tmp .= $encrypt_key[$ctr].($txt[$i] ^ $encrypt_key[$ctr++]);
	}

	return base64_encode(str_key($tmp,$key));
}

function str_decrypt($txt,$key)
{
	$txt = str_key(base64_decode($txt), $key);
	$len = strlen($txt);
	if($len%2 != 0) return '';

	$tmp = '';

	for($i = 0;$i < $len;$i++)
	{
		$tmp .= $txt[$i] ^ $txt[++$i];
	}

	return $tmp;
}

function str_key($txt,$encrypt_key)
{
	$encrypt_key = md5($encrypt_key);

	$ctr = 0;
	$tmp = '';

	for($i = 0; $i < strlen($txt); $i++)
	{
		$ctr = $ctr == strlen($encrypt_key) ? 0 : $ctr;
		$tmp .= $txt[$i] ^ $encrypt_key[$ctr++];
	}

	return $tmp;
}

function str_encode($str,$key)
{
	$string_rand = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
	$encrypt_len = 4;
	$encrypt_key = '';

	for($i = 0;$i < $encrypt_len;$i++)
	{
		$encrypt_key .= $string_rand[mt_rand(0,61)];
	}

	$ctr = 0;
	$tmp = '';

	for($i = 0;$i < strlen($str);$i++)
	{
		$ctr = $ctr == strlen($encrypt_key) ? 0 : $ctr;
		$tmp .= $str[$i] ^ $encrypt_key[$ctr++];
	}

	$tmp = $encrypt_key.$tmp;

	return str_replace(array('+','/'),array('-','_'),trim(base64_encode(str_key($tmp,$key)),'='));
}

function str_decode($str,$key)
{
	$str = str_key(base64_decode(str_replace(array('-','_'),array('+','/'),$str)),$key);

	$encrypt_len = 4;
	$encrypt_key = substr($str,0,$encrypt_len);

	$str = substr($str,$encrypt_len);

	$ctr = 0;
	$tmp = '';

	for($i = 0;$i < strlen($str);$i++)
	{
		$ctr = $ctr == strlen($encrypt_key) ? 0 : $ctr;
		$tmp .= $str[$i] ^ $encrypt_key[$ctr++];
	}

	return $tmp;
}

?>