<?php namespace sox\sdk\com;

use \sox\sdk\com\db;

class ini {
	static $conf = [];

	static function get($key, $def = '', $mod = TRUE) {
		if (is_bool($def)) {
			$mod = $def;

			$def = '';
		}

		$key = explode('.', $key);

		$file_name = array_shift($key);

		if (!isset(self::$conf[$file_name])) {
			if ($mod) {
				$sub_dir = '';

				if (defined('SOXINI')) {
					$hostname = gethostname();

					$hostlist = explode(';', SOXINI);

					foreach ($hostlist as $host) {
						$host_arr = explode(':', $host);

						if ($host_arr[0] == $hostname) {
							$sub_dir = ($host_arr[1] ?? $host_arr[0]).'/';

							break;
						}
					}
				}

				if ($sub_dir && is_file(__DIR__.'/../../com/ini/'.$sub_dir.$file_name.'.ini')) {
					self::$conf[$file_name] = parse_ini_file(__DIR__.'/../../com/ini/'.$sub_dir.$file_name.'.ini', TRUE);
				} else {
					self::$conf[$file_name] = parse_ini_file(__DIR__.'/../../com/ini/'.$file_name.'.ini', TRUE);
				}
			} else {
				self::$conf[$file_name] = parse_ini_file(__DIR__.'/../../com/ini/'.$file_name.'.ini', TRUE);
			}
		}

		if (empty($key)) {
			return self::$conf[$file_name];
		} else {
			$cop = &self::$conf[$file_name];

			foreach ($key as $ckey) {
				if (isset($cop[$ckey])) {
					$cop = &$cop[$ckey];
				} else {
					return $def;
				}
			}

			return $cop;
		}
	}

	static function get_prikey($table, $up = TRUE) {
		$model = new db('prikey',self::get('db'));

		$prikey = (int)$model->find('auto_increment',['table_name' => $table]);

		if ($prikey == 0) {
			$prikey = 1;

			if ($up) $model->create(['table_name' => $table,'auto_increment' => 1]);
		} else {
			$prikey += 1;

			if ($up) $model->increase(['table_name' => $table],'auto_increment',1);
		}

		return $prikey;
	}

	static function state($class, &$item, $key, $def = '', $alias = '') {
		if (isset($item[$key])) {
			if (isset($class::${$key.'_state'})) {
				$item[$alias ?: $key.'_state'] = isset($class::${$key.'_state'}[$item[$key]]) ? $class::${$key.'_state'}[$item[$key]] : $def;
			} else if (method_exists($class,'_'.$key.'_state')) {
				$item[$alias ?: $key.'_state'] = call_user_func($class.'::_'.$key.'_state',$item[$key],$alias,$item);
			} else {
				$item[$alias ?: $key.'_state'] = $def;
			}
		} else {
			$item[$key] = '';
			$item[$alias ?: $key.'_state'] = $def;
		}

		return $item[$alias ?: $key.'_state'];
	}

	static function table_cols($file_name) {
		return self::get('ini_db/'.$file_name.'.field',FALSE);
	}
}