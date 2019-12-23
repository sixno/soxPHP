<?php namespace sox\sdk\com;

class api
{
	static $file = 'api.php';
	static $base = 'api';
	static $json = ['out' => '0','msg' => '','data' => '','code' => '','line' => ''];
	static $cros = FALSE;

	static $base_dir = '';
	static $json_set = [];
	static $func_cur = '';

	static function __workon($file = 'api.php',$base = 'api',$cros = FALSE,$def_api = '',$def_act = '')
	{
		self::$file = $file;
		self::$base = $base;
		self::$cros = $cros;

		self::$base_dir = getcwd().'/'.self::$base.'/';

		$uri_1 = self::uri(1,$def_api);
		$uri_2 = self::uri(2,$def_act);

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
			if(self::$cros)
			{
				$allow = FALSE;

				if(self::$cros == '*')
				{
					$allow = TRUE;
				}
				else
				{
					$cros = explode(';',self::$cros);

					$pos = strpos($_SERVER['HTTP_ORIGIN'],'://');
					$cmp = substr($_SERVER['HTTP_ORIGIN'],$pos + 3);

					foreach($cros as $cro)
					{
						$cro = trim($cro);

						if(empty($cro)) continue;

						if(substr($cro,0,1) == '.')
						{
							$allow = (substr('.'.$cmp,-strlen($cro)) === $cro);
						}
						else
						{
							$allow = ($cmp === $cro);
						}

						if($allow) break;
					}
				}

				if($allow)
				{
					header('Access-Control-Allow-Origin: '.$_SERVER['HTTP_ORIGIN']);
					header('Access-Control-Allow-Methods: OPTIONS,GET,POST');
					header('Access-Control-Allow-Credentials: true');
					header('Access-Control-Allow-Headers:x-requested-with,content-type,if-modified-since,x-token');
					header('Access-Control-Expose-Headers: x-token');
				}
			}

			if(http_method() == 'options')
			{
				header('Access-Control-Max-Age: 86400');

				exit;
			}
		}

		array_walk_recursive(self::$json,function(&$v,$k){
			$v = (string)$v;
		});

		echo json_encode(self::$json,JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

		exit;
	}

	static function line(&$db,&$map,$length = 20,$method = FALSE)
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

		if(strpos(http_get('line'),',') !== FALSE || http_get('line') === '' || http_get('line' === '$'))
		{
			if(isset($map['#tline'])) unset($map['#tline']);

			if(isset($map['#lsort']))
			{
				$map['#order'] = empty($map['#order']) ? $map['#lsort'] : $map['lsort'].';'.$map['#order'];

				unset($map['lsort']);
			}

			list($page,$tote) = explode(',',self::get('line')) + [0,0];

			$tote = (int)$tote;

			if($tote == 0 || $page == '$')
			{
				$tote = $db->count($map);

				$tote = ceil($tote / $length);
			}

			if($page == '$')
			{
				$page = $tote;
			}
			else
			{
				$page = (int)$page;
			}

			if($page == 0) $page = 1;

			$offset = ($page - 1) * $length;

			$map['#limit'] = $length.','.$offset;

			self::set('line',$page < $tote ? ($page + 1).','.$tote.','.$page : 'end,'.$tote.','.$page,$method);
		}
		else
		{
			if(isset($map['#lsort'])) unset($map['#lsort']);

			if(isset($map['#tline']))
			{
				$table = $map['#tline'];

				unset($map['#tline']);
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