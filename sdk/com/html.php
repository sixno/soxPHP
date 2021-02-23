<?php namespace sox\sdk\com;

class html
{
	static $a = [];
	static $d = [];

	static $uri = '';
	static $get = '';

	static $file = 'index.php';
	static $base = 'html';

	static $base_dir = '';
	static $view_dir = '';
	static $vars_dir = '';

	static $def_route = '';
	static $hide_file = TRUE;

	static function __workon($file = 'index.php',$base = 'html',$must = '',$def_route = 'index',$hide_file = TRUE)
	{
		self::$file = $file;
		self::$base = $base;

		self::$def_route = $def_route;
		self::$hide_file = $hide_file;

		self::$base_dir = getcwd().'/'.self::$base.'/';
		self::$view_dir = self::$base_dir.'view/';
		self::$vars_dir = self::$base_dir.'vars/';

		self::$uri = self::uri(1,$def_route).'/'.self::uri(2,$def_route);

		if($must) self::$d = require self::$vars_dir.$must.'.php';

		if(substr(self::$uri,0,1) == '_' || strpos(self::$uri,'/_')) self::__403();

		if(self::uri(1,$def_route) != '')
		{
			if(is_file(self::$view_dir.self::uri(1,$def_route).'.php'))
			{
				self::$uri = self::uri(1,$def_route);

				self::$a = array_slice(self::uri(),2);

				self::__output(self::uri(1,$def_route));
			}
			elseif(is_dir(self::$view_dir.self::uri(1,$def_route)))
			{
				if(is_file(self::$view_dir.self::uri(1,$def_route).'/'.self::uri(2,$def_route).'.php'))
				{
					self::uri(explode('/',self::$uri));

					self::$a = array_slice(self::uri(),3);

					self::__output(self::uri(1,$def_route).'/'.self::uri(2,$def_route));
				}
				else
				{
					self::__404();
				}
			}
			else
			{
				self::__404();
			}
		}
		else
		{
			self::__404();
		}
	}

	static function __403()
	{
		header('HTTP/1.1 403 Forbidden');

		echo '<!DOCTYPE html><html><head><title>404 Not Found</title></head><body bgcolor="white"><center><h1>403 Forbidden</h1></center><hr></body></html>';

		exit;
	}

	static function __404()
	{
		header('HTTP/1.1 404 Not Found');

		echo '<!DOCTYPE html><html><head><title>404 Not Found</title></head><body bgcolor="white"><center><h1>404 Not Found</h1></center><hr></body></html>';

		exit;
	}

	static function __output($path)
	{
		header('Cache-Control: no-cache');
		header('Pragma: no-cache');
		header('Expires: 0');
		header('Content-type: text/html; charset=utf-8');

		self::render($path);

		exit;
	}

	static function render($path,$vars = '')
	{
		$h = '\\'.self::class;

		$d = self::$d;

		if($vars !== FALSE)
		{
			if(is_array($vars))
			{
				$d = array_merge($d,$vars);
			}
			else
			{
				if(!$vars)
				{
					if(substr($path,0,1) != '/' && is_file(self::$vars_dir.'view/'.$path.'.php'))
					{
						$d = array_merge($d,include self::$vars_dir.'view/'.$path.'.php');
					}
				}
				else
				{
					$d = array_merge($d,include self::$vars_dir.$vars.'.php');
				}
			}
		}

		ob_start();

		if(substr($path,0,1) == '/')
		{
			include self::$base.$path.'.php';
		}
		else
		{
			include self::$view_dir.$path.'.php';
		}

		$view = ob_get_contents();

		ob_clean();

		echo $view;
	}

	static function import($path,$vars = FALSE)
	{
		self::render($path,$vars);
	}

	static function url($uri = '',$get = [],$hash = '')
	{
		if(is_string($get))
		{
			$hash = $get;

			$get = [];
		}

		if(self::$get)
		{
			foreach(explode(',',self::$get) as $g)
			{
				$get[$g] = http_get($g);
			}
		}

		$get = array_filter($get);

		if(!empty($get))
		{
			$uri .= '?'.http_build_query($get);
		}

		if($hash)
		{
			$uri .= '#'.$hash;
		}

		if(url() == '/')
		{
			return url(self::$hide_file ? $uri : self::$file.'/'.$uri);
		}
		else
		{
			return url(self::$file.'/'.$uri);
		}
	}

	static function uri($key = 0,$def = '')
	{
		return http_uri($key, $def, self::$file);
	}

	static function redirect($uri = '',$get = [],$hash = '',$wait = FALSE)
	{
		if(is_int($hash))
		{
			$wait = $hash;

			$hash = '';
		}

		if(is_int($get))
		{
			$wait = $get;

			$get = [];
		}

		if(is_string($get))
		{
			$hash = $get;

			$get = [];
		}

		$url = self::url($uri,$get,$hash);

		if($wait === FALSE)
		{
			header("Location:".$url);
		}
		else
		{
			header("Refresh:".$wait.";url=".$url);
		}

		exit;
	}
}

?>