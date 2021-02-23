<?php namespace sox\sdk\com;

class api
{
	static $file = 'api.php';
	static $base = 'api';
	static $json = ['out' => '0','msg' => '','data' => '','code' => '','line' => ''];
	static $cors = FALSE;
	static $acch = 'x-token';

	static $base_dir = '';
	static $json_set = [];
	static $func_cur = '';

	static function __workon($file = 'api.php',$base = 'api',$cors = FALSE,$def_api = '',$def_act = '')
	{
		self::$file = $file;
		self::$base = $base;

		self::$base_dir = getcwd().'/'.self::$base.'/';

		$uri_1 = self::uri(1,$def_api);
		$uri_2 = self::uri(2,$def_act);

		if (!is_array($cors))
		{
			self::$cors = $cors;
		}
		else
		{
			if (isset($cors['acch']))
			{
				self::$acch = $cors['acch'];
			}

			if (isset($cors['cors']))
			{
				if (is_array($cors['cors']))
				{
					if ($cors['cors']['*'])
					{
						self::$cors = $cors['cors']['*'];
					}
					else
					{
						self::$cors = FALSE;
					}

					if ($uri_1 && isset($cors['cors'][$uri_1]))
					{
						self::$cors = $cors['cors'][$uri_1];
					}
				}
				else
				{
					self::$cors = $cors['cors'];
				}
			}
		}

		if(http_method() == 'options') self::__output();

		if($uri_1 != '')
		{
			if(is_file(self::$base_dir.$uri_1.'.php'))
			{
				$status = require self::$base_dir.$uri_1.'.php';

				if($status || $status === NULL)
				{
					if($uri_2 != '')
					{
						if(method_exists('\\sox\\'.str_replace('/','\\',self::$base).'\\'.$uri_1,$uri_2))
						{
							self::$func_cur = 'sox\\api\\'.$uri_1.'::'.$uri_2;

							$method = '\\'.self::$func_cur;
							$params = array_slice(self::uri(),3);

							$result = empty($params) ? $method() : call_user_func_array($method,$params);

							if(is_array($result))
							{
								self::$json['out'] = '1';
								self::$json['msg'] = '';

								self::$json['data'] = $result;
								self::$json['code'] = '';
								self::$json['line'] = '';
							}
							elseif(is_bool($result))
							{
								self::$json['out'] = $result ? '1' : '0';

								self::$json['line'] = '';
							}
							else
							{
								self::$json['data'] = '';
								self::$json['code'] = '';
								self::$json['line'] = '';

								if(strpos($result,'|') === FALSE)
								{
									self::$json['out'] = '1';
									self::$json['msg'] = $result;
								}
								else
								{
									$result = explode('|',$result);

									self::$json['out'] = $result[0];
									self::$json['msg'] = $result[1];

									if(isset($result[2])) self::$json['code'] = $result[2];
								}
							}

							foreach(self::$json_set as $key => $val)
							{
								self::$json[$key] = $val;
							}
						}
						else
						{
							self::$json['msg'] = '请求方法不存在';
						}
					}
					else
					{
						self::$json['msg'] = '未设置默认请求方法';
					}
				}
			}
			else
			{
				self::$json['msg'] = '请求文件不存在';
			}
		}
		else
		{
			self::$json['msg'] = '未设置默认请求文件';
		}

		if(self::$json['line'] === '0' && is_array(self::$json['data']))
		{
			$last = end(self::$json['data']);

			if(isset($last['_id']))
			{
				self::$json['line'] = $last['_id'];
			}
			else
			{
				self::$json['line'] = isset($last['id']) ? $last['id'] : 'end';
			}
		}

		if(self::$json['line'] === '-' && is_array(self::$json['data']))
		{
			$last = end(self::$json['data']);

			if(isset($last['_id']))
			{
				self::$json['line'] = '-'.$last['_id'];
			}
			else
			{
				self::$json['line'] = isset($last['id']) ? '-'.$last['id'] : 'end';
			}
		}

		if(in_array(self::$json['line'],['0','-'])) self::$json['line'] = 'end';

		self::__output();
	}

	static function __output()
	{
		header('Cache-Control: no-cache');
		header('Pragma: no-cache');
		header('Expires: 0');
		header('Content-type: application/json; charset=utf-8');

		if(!empty($_SERVER['HTTP_ORIGIN']))
		{
			if(self::$cors)
			{
				$allow = FALSE;

				if(self::$cors == '*')
				{
					$allow = TRUE;
				}
				else
				{
					$cors = explode(';',self::$cors);

					$pos = strpos($_SERVER['HTTP_ORIGIN'],'://');
					$cmp = substr($_SERVER['HTTP_ORIGIN'],$pos + 3);

					foreach($cors as $cor)
					{
						$cor = trim($cor);

						if(empty($cor)) continue;

						if(substr($cor, 0, 1) == '.')
						{
							$allow = (substr('.'.$cmp,-strlen($cor)) === $cor);
						}
						else
						{
							$allow = ($cmp === $cor);
						}

						if($allow) break;
					}
				}

				if($allow)
				{
					header('Access-Control-Allow-Origin: '.$_SERVER['HTTP_ORIGIN']);
					header('Access-Control-Allow-Methods: OPTIONS,GET,POST');
					header('Access-Control-Allow-Credentials: true');
					header('Access-Control-Allow-Headers: x-requested-with,content-type,if-modified-since,'.self::$acch);
					header('Access-Control-Expose-Headers: '.self::$acch);
					header('Access-Control-Max-Age: 86400');
				}
			}
		}

		if (http_method() == 'options') exit;

		array_walk_recursive(self::$json,function(&$v,$k){
			if($v === NULL)
			{
				$v = (object)NULL;
			}
			else
			{
				$v = (string)$v;
			}
		});

		echo json_encode(self::$json,JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

		exit;
	}

	static function line(&$db, &$map, $length = 20, $method = FALSE)
	{
		if($method === FALSE)
		{
			$debug = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS,2);

			if(isset($debug[1]))
			{
				$method = $debug[1]['class'].$debug[1]['type'].$debug[1]['function'];
			}
			else
			{
				$method = '';
			}
		}

		if($method != self::$func_cur) return ;

		if(strpos(http_get('line'),',') !== FALSE || http_get('line') === '' || http_get('line') === '$')
		{
			if(isset($map['#sort1']))
			{
				$map['#order'] = empty($map['#order']) ? $map['#sort1'] : $map['#sort1'].';'.$map['#order'];
			}

			list($page, $size, $rows) = explode(',',self::get('line')) + [1, $length, 0];

			$rows = (int)$rows;

			if($rows == 0 || $page == '$') $rows = $db->count($map);

			$nums = ceil($rows / $size);

			if($page == '$')
			{
				$page = $nums;
			}
			else
			{
				$page = (int)$page;
			}

			if($page <= 0) $page = 1;

			$move = ($page - 1) * $size;

			$map['#limit'] = $size.','.$move;

			self::set('line', ($page < $nums ? ($page + 1) : 'end').','.$size.','.$rows, $method);
		}
		else
		{
			if(isset($map['#sort2']))
			{
				$table = $map['#sort2'];
			}
			else
			{
				$table = $db->table;
			}

			if(substr(http_get('line'),0,1) == '-')
			{
				if(http_get('line') != '-') $map[$table.'.id <'] = substr(http_get('line'),1);

				$map['#limit'] = $length.',0';
				$map['#order'] = empty($map['#order']) ? $table.'.id,desc' : $table.'.id,desc;'.$map['#order'];

				self::set('line','-',$method);
			}
			else
			{
				if(http_get('line') != '0') $map[$table.'.id >'] = http_get('line');

				$map['#limit'] = $length.',0';
				$map['#order'] = empty($map['#order']) ? $table.'.id,asc' : $table.'.id,asc;'.$map['#order'];

				self::set('line','0',$method);
			}
		}

		$db->reset();

		return self::$json['line'];
	}

	static function set($key,$val = '',$method = FALSE)
	{
		if($method === FALSE)
		{
			$debug = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS,2);

			if(isset($debug[1]))
			{
				$method = $debug[1]['class'].$debug[1]['type'].$debug[1]['function'];
			}
			else
			{
				$method = '';
			}
		}

		if($method != self::$func_cur) return ;

		if(!is_array($key))
		{
			self::$json_set[$key] = $val;
		}
		else
		{
			foreach($key as $k => $v)
			{
				self::$json_set[$k] = $v;
			}
		}
	}

	static function msg($msg,$code = '',$data = [])
	{
		if(!is_array($msg))
		{
			self::$json['msg'] = $msg;

			if(is_string($code) || is_int($code))
			{
				self::$json['code'] = $code;

				if($data)
				{
					self::$json['data'] = $data;
				}
				else
				{
					self::$json['data'] = '';
				}
			}
			elseif(is_array($code))
			{
				self::$json['data'] = $code;
				self::$json['code'] = '';
			}
		}
		else
		{
			self::$json['data'] = $msg;
			self::$json['code'] = $code;

			self::$json['msg'] = '';
		}

		self::$json['line'] = '';

		return TRUE;
	}

	static function err($msg,$code = '',$data = [])
	{
		if(!is_array($msg))
		{
			self::$json['msg'] = $msg;

			if(is_string($code) || is_int($code))
			{
				self::$json['code'] = $code;

				if($data)
				{
					self::$json['data'] = $data;
				}
				else
				{
					self::$json['data'] = '';
				}
			}
			elseif(is_array($code))
			{
				self::$json['data'] = $code;
				self::$json['code'] = '';
			}
		}
		else
		{
			self::$json['data'] = $msg;
			self::$json['code'] = $code;

			self::$json['msg'] = '';
		}

		self::$json['line'] = '';

		return FALSE;
	}

	static function uri($n = 0,$def = '')
	{
		return http_uri($n,$def,self::$file);
	}

	static function get($key = '',$def = '')
	{
		return http_get($key,$def);
	}

	static function post($key = '',$def = '')
	{
		return http_post($key,$def);
	}

	static function json($key = '',$def = '')
	{
		return http_json($key,$def);
	}
}

?>