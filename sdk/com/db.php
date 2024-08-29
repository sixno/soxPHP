<?php namespace sox\sdk\com;

class db {
	public $conf  = NULL;

	public $table = '';
	public $drive = '';

	public $fixed = '';
	public $reset = FALSE;

	public $insert_id = 0;
	public $p_insert_id = 0;
	public $affected_rows = 0;
	public $last_query = '';

	public function __construct($table = NULL, $conf = NULL) {
		if (is_array($table)) {
			$conf = $table;

			$table = '';
		}

		$this->table = $table;
		$this->fixed = $table;

		$this->ini($conf);
	}

	public function ini($conf) {
		$this->conf = $conf;

		switch ($conf['dbdriver']) {
			case 'pdo':  $this->mysql = load_pdo($conf);  break;
			case 'odbc': $this->mysql = load_odbc($conf); break;

			default: $this->mysql = load_mysql($conf); break;
		}
	}

	public function set($table = '', $reset = TRUE) {
		$this->table = $table;

		if ($reset && $this->fixed) {
			$this->reset = $reset;
		}

		return $this;
	}

	public function reset() {
		if ($this->reset) {
			$this->table = $this->fixed;

			$this->reset = FALSE;
		}
	}

	public function escape($str) {
		// return $this->mysql->real_escape_string($str);

		return str_replace(['\\', '\'', '"', "\r", "\n"], ['\\\\', '\\\'', '\\"', '\\r', '\\n'], $str);
	}

	public function error() {
		return $this->mysql->error;
	}

	public function query($sql) {
		$this->insert_id = 0;
		$this->affected_rows = 0;
		$this->last_query = $sql;

		$result = $this->mysql->query($sql);

		$this->insert_id = $this->mysql->insert_id;
		$this->affected_rows = $this->mysql->affected_rows;

		// 多数情况下，以下重试代码只在后台长时运行任务中、且数据库是从内网穿透出的情况下才有机会被执行
		// 猜测可能的原因是内网出口网络环境发生变化，导致端口转发时重置连接
		// 并且有个非常蛋疼的情况，就是可能SQL语句已经被数据库受理并执行，但是网络握手中断造成返回连接掉线错误
		// 此时重新执行插入语句可能会报主键（或唯一键）重复，或无主键插入时导致数据重复插入
		// 为了避免上述可能出现的数据重复插入的情况，插入操作不应该重试，除非数据中包含主键

		if (!$result && $this->error() == 'MySQL server has gone away' && isset($this->conf['pconnect']) && $this->conf['pconnect'] > 0) {
			$this->conf['pconnect']++;

			$this->ini($this->conf);

			if (substr($sql, 0, 6) != 'INSERT' || $this->p_insert_id > 0) {
				$result = $this->mysql->query($sql);

				$this->insert_id = $this->mysql->insert_id;
				$this->affected_rows = $this->mysql->affected_rows;

				if (!$result && substr($this->error(), 0, 15) == 'Duplicate entry') {
					$result = TRUE;

					$this->insert_id = $this->p_insert_id;
				}
			}
		}

		switch (strtoupper(substr($sql, 0, 4))) {
			case 'SELE':
				if (substr($sql, 0, 12) != 'SELECT COUNT') {
					return $result ? $result->fetch_all(MYSQLI_ASSOC) : FALSE;
				} else {
					return $result ? $result->fetch_row()[0] : FALSE;
				}
				break;

			case 'SHOW':
			case 'DESC':
			case 'EXPL':
				return $result ? $result->fetch_all(MYSQLI_ASSOC) : FALSE;
				break;

			case 'INSE':
				return $result && $this->insert_id ? $this->insert_id : $result;
				break;

			default: return $result; break;
		}
	}

	public function where($map = FALSE) {
		if ($map !== FALSE) {
			if (is_array($map)) {
				$sql        = '';
				$need_logic = FALSE;

				foreach($map as $key => $val) {
					$front_char = substr($key,0,1);

					if ($front_char == '#') continue;

					if ($sql == '') $sql = 'WHERE ';

					if ($front_char == '^') {
						if (in_array($val,['and (','or ('])) {
							$val = $need_logic ? strtoupper($val) : '(';

							$need_logic = FALSE;
						}

						$sql .= $val.' ';

						continue;
					}

					if (FALSE === $pos = strpos($key,'#')) {
						$logic = 'and';
					} else {
						if ($pos == 0) continue;

						$logic = substr($key,0, $pos);
						$pos = strrpos($key,'#');
						$key = substr($key, $pos+1);
					}

					$key = str_replace(array(' ','\'','"'),'', $key);

					$chr = strpos($key,'`') === FALSE ? '`' : '';
					$chl = strlen($chr);

					$key = $chr.((strpos($key,'.') === FALSE) ? $key : ($chr != '' ? str_replace('.', $chr.'.'.$chr, $key) : $key)).$chr;

					switch (substr($key,-1-$chl,1)) {
						case '=':
							switch (substr($key,-2-$chl,2)) {
								case '<=':
									$key = substr($key,0,-2-$chl).$chr.' <= ';
									break;
								
								case '>=':
									$key = substr($key,0,-2-$chl).$chr.' >= ';
									break;
								
								case '!=':
									$key = substr($key,0,-2-$chl).$chr.' != ';
									break;
								
								default:
									$key = substr($key,0,-1-$chl).$chr.' = ';
									break;
							}
							break;
						
						case '<':
							$key = substr($key,0,-1-$chl).$chr.' < ';
							break;
						
						case '>':
							$key = substr($key,0,-1-$chl).$chr.' > ';
							break;
						
						default:
							$key = ($chl ? substr($key,0,-$chl) : $key).$chr.' = ';
							break;
					}

					if (strpos($logic,'in') === FALSE) {
						if ($val !== NULL) {
							if (strpos($logic,'like') !== FALSE) {
								$like = (substr($key,1,1) == '%' ? '%' : '_').(substr($key,-5,1) == '%' ? '%' : '_');

								switch ($like) {
									case '_%': $key = substr_replace($key,'',-5,1);$val = str_replace('%','\%', $val).'%'; break;
									case '%_': $key = substr_replace($key,'',1,1);$val = '%'.str_replace('%','\%', $val); break;

									default: $val = '%'.str_replace('%','\%', $val).'%'; break;
								}
							}

							$val = '\''.$this->escape($val).'\'';
						} else {
							if (strpos($key,'!=') === FALSE) {
								$key = str_replace(' = ',' IS ', $key);
							} else {
								$key = str_replace(' != ',' IS NOT ', $key);
							}

							$val = 'NULL';
						}
					} else {
						if (empty($val) && $val !== '0') continue;

						if (is_array($val)) {
							if (count($val) != count($val,1)) {
								$val_copy = $val;

								$val = [];

								preg_match('/:(.*?)` = /', $key, $matches);

								$key = str_replace(':'.$matches[1].'`', '`', $key);

								foreach ($val_copy as $vc) {
									$val[] = $vc[$matches[1]];
								}
							}

							foreach ($val as &$v) {
								$v = $this->escape($v);
							}

							$val = '(\''.implode('\',\'', $val).'\')';
						} else {
							$val = '(\''.str_replace(',','\',\'', $this->escape($val)).'\')';
						}
					}

					switch ($logic) {
						case 'and':
							$lgc = 'AND';
							break;

						case 'or':
							$lgc = 'OR';
							break;

						case 'like':
							$lgc = 'AND';

							$key = str_replace(' = ',' LIKE ',strpos($key,',') === FALSE ? $key : 'CONCAT_WS(\' \','.str_replace([',',' ='],['`,`',') ='], $key));
							break;

						case 'or_like':
							$lgc = 'OR';

							$key = str_replace(' = ',' LIKE ',strpos($key,',') === FALSE ? $key : 'CONCAT_WS(\' \','.str_replace([',',' ='],['`,`',') ='], $key));
							break;

						case 'not_like':
							$lgc = 'AND';

							$key = str_replace(' = ',' NOT LIKE ',strpos($key,',') === FALSE ? $key : 'CONCAT_WS(\' \','.str_replace([',',' ='],['`,`',') ='], $key));
							break;

						case 'or_not_like':
							$lgc = 'OR';

							$key = str_replace(' = ',' NOT LIKE ',strpos($key,',') === FALSE ? $key : 'CONCAT_WS(\' \','.str_replace([',',' ='],['`,`',') ='], $key));
							break;

						case 'in':
							$lgc = 'AND';

							if (strpos($val,',') !== FALSE) {
								$key = str_replace(' = ',' IN ', $key);
							} else {
								$val = substr($val,1,-1);
							}
							break;

						case 'or_in':
							$lgc = 'OR';

							if (strpos($val,',') !== FALSE) {
								$key = str_replace(' = ',' IN ', $key);
							} else {
								$val = substr($val,1,-1);
							}
							break;

						case 'not_in':
							$lgc = 'AND';

							if (strpos($val,',') !== FALSE) {
								$key = str_replace(' = ',' NOT IN ', $key);
							} else {
								$key = str_replace(' = ',' != ', $key);
								$val = substr($val,1,-1);
							}
							break;

						case 'or_not_in':
							$lgc = 'OR';

							if (strpos($val,',') !== FALSE) {
								$key = str_replace(' = ',' NOT IN ', $key);
							} else {
								$key = str_replace(' = ',' != ', $key);
								$val = substr($val,1,-1);
							}
							break;

						default:
							$lgc = 'AND';
							break;
					}

					if ($need_logic) {
						$sql .= $lgc.' '.$key.$val;
					} else {
						$need_logic = TRUE;

						$sql .= $key.$val;
					}

					$sql .= ' ';
				}

				return $sql;
			} else {
				return 'WHERE `'.$this->table.'`.`id` = \''.$this->escape($map).'\'';
			}
		} else {
			return '';
		}
	}

	public function count($map = array()) {
		$sql = 'SELECT COUNT(*) FROM `'.$this->table.'` ';

		if (isset($map['#unite'])) {
			foreach(explode(';', $map['#unite']) as $join_str) {
				$join_arr = explode(',', $join_str);

				if (isset($join_arr[2])) {
					$sql .= strtoupper($join_arr[2]).' JOIN `'.$join_arr[0].'` ON `'.str_replace([' ','.','='],['','`.`','` = `'], $join_arr[1]).'` ';
				} else {
					$sql .= 'JOIN `'.$join_arr[0].'` ON `'.str_replace([' ','.','='],['','`.`','` = `'], $join_arr[1]).'` ';
				}
			}
		}

		$where = $this->where($map);

		if (empty($where)) {
			$sql .= 'FORCE INDEX(PRIMARY) ';
		} else {
			$sql .= $where;
		}

		$result = $this->query($sql);

		$this->reset();

		return $result;
	}

	public function create($data, $batch = FALSE) {
		$this->p_insert_id = 0;

		$sql = '';

		if ($batch === FALSE) {
			$sql = 'INSERT INTO `'.$this->table.'` ';

			$field = '';
			$value = '';

			foreach($data as $key => $val) {
				$field .= '`'.$key.'`,';
				$value .= '\''.$this->escape($val).'\',';
			}

			if (isset($data['id'])) $this->p_insert_id = (int)$data['id'];

			$sql .= '('.substr($field,0,-1).') VALUES ('.substr($value,0,-1).')';
		} else {
			$sql = 'INSERT IGNORE INTO `'.$this->table.'` ';

			$field = '';
			$value = '';

			foreach($data as $k => $v) {
				$tmp = '';

				foreach($v as $key => $val) {
					if ($k == 0) {
						$field .= '`'.$key.'`,';
					}

					$tmp .= '\''.$this->escape($val).'\',';
				}

				if (isset($v['id'])) $this->p_insert_id = (int)$v['id'];

				$value .= '('.substr($tmp,0,-1).'), ';
			}

			$sql .= '('.substr($field,0,-1).') VALUES '.substr($value, 0, -2);
		}

		$result = $this->query($sql);

		$this->reset();

		return $result;
	}

	public function update($where = array(), $data = array()) {
		$sql = 'UPDATE `'.$this->table.'` SET ';

		foreach($data as $key => $value) {
			$sql .= '`'.$key.'` = \''.$this->escape($value).'\',';
		}

		$sql = trim($sql,',').' ';

		$sql .= $this->where($where);

		$result = $this->query($sql);

		$this->reset();

		return $result;
	}

	public function delete($map = array()) {
		$sql = 'DELETE FROM `'.$this->table.'` ';

		$sql .= $this->where($map);

		$result = $this->query($sql);

		$this->reset();

		return $result;
	}

	public function read($map = array(), $where = '', $limit = '', $order = '') {
		$sql = '';

		if (!is_array($map)) {
			$field = $map;

			$map = array();

			if ($field != '') $map['#field'] = $field;

			if (is_array($where)) {
				$map = array_merge($map, $where);
			} else {
				$order = $limit;
				$limit = $where;
			}

			if ($limit != '') $map['#limit'] = $limit;
			if ($order != '') $map['#order'] = $order;
		}

		if (isset($map['#field'])) {
			$map['#field'] = '`'.str_ireplace(array('.',' AS ',','),array('`.`','` AS `','`,`'), $map['#field']).'`';
			$map['#field'] = str_replace(['``','`*`'],['','*'], $map['#field']);
			$map['#field'] = str_ireplace('`distinct ','DISTINCT `', $map['#field']);

			$sql = 'SELECT '.$map['#field'].' FROM `'.$this->table.'` ';
		} else {
			$sql = 'SELECT * FROM `'.$this->table.'` ';
		}

		if (isset($map['#unite'])) {
			foreach(explode(';', $map['#unite']) as $join_str) {
				$join_arr = explode(',', $join_str);

				if (isset($join_arr[2])) {
					$sql .= strtoupper($join_arr[2]).' JOIN `'.$join_arr[0].'` ON `'.str_replace([' ','.','='],['','`.`','` = `'], $join_arr[1]).'` ';
				} else {
					$sql .= 'JOIN `'.$join_arr[0].'` ON `'.str_replace([' ','.','='],['','`.`','` = `'], $join_arr[1]).'` ';
				}
			}
		}

		$sql .= $this->where($map);

		if (isset($map['#order'])) {
			$sql .= 'ORDER BY `'.str_ireplace([' ','desc','asc','.',',',';'],['','DESC','ASC','`.`','` ',',`'], $map['#order']).' ';

			$sql = str_ireplace(['`rand()','rand()`'],['RAND()','RAND()'], $sql);
		}

		if (isset($map['#limit'])) {
			list($limit, $ofset) = explode(',', $map['#limit']) + [0,0];

			$limit = (int)$limit;
			$ofset = (int)$ofset;

			if ($limit > 0) $sql .= 'LIMIT '.$ofset.','.$limit.' ';
		}

		$result = $this->query($sql);

		$this->reset();

		if ($result && isset($map['#indby'])) {
			$data = array();

			foreach($result as $key => $value) {
				$data[$value[$map['#indby']]] = $value;
			}

			return $data;
		} else {
			return $result;
		}
	}

	public function find($map = array(), $where = array(), $order = '', $limit = '') {
		if (!is_array($map)) {
			$field = $map;

			$map = array();

			if ($field != '') $map['#field'] = $field;

			if (is_array($where)) {
				if (!empty($where)) $map = array_merge($map, $where);
			} else {
				$map['id'] = $where;
			}

			if ($order != '') $map['#order'] = $order;
			if ($limit != '') $map['#limit'] = '1,0';
		}

		if (empty($map['#limit'])) $map['#limit'] = '1,0';

		$list = $this->read($map);

		if (!empty($list)) {
			$data = array_shift($list);

			return (count($data) > 1) ? $data : array_pop($data);
		} else {
			return FALSE;
		}
	}

	public function increase($where = '', $item = '', $step = 0, $data = []) {
		if ($step == 0 && is_numeric($item)) {
			$step = $item;
			$item = $where;

			$where = '';
		}

		$sql = 'UPDATE `'.$this->table.'` SET ';

		if (is_string($item)) {
			if ($step == 0) return FALSE;

			foreach(explode(',', $item) as $key => $value) {
				$sql .= '`'.$value.'` = `'.$value.'`'.($step > 0 ? '+'.$step : (string)$step).', ';
			}
		} else {
			foreach($item as $key => $value) {
				$sql .= '`'.$key.'` = `'.$key.'`'.($value > 0 ? '+'.$value : (string)$value).', ';
			}

			$data = $step;
		}

		if (!empty($data)) {
			foreach ($data as $key => $value) {
				$sql .= '`'.$key.'` = \''.$this->escape($value).'\', ';
			}
		}

		$sql = substr($sql,0,-2).' '.$this->where($where);

		$result = $this->query($sql);

		$this->reset();

		return $result;
	}

	public function cols($only_field = FALSE) {
		$sql = 'SHOW FULL COLUMNS FROM '.$this->table;

		$column = $this->query($sql);

		$this->reset();

		foreach ($column as &$col) {
			$col = array_change_key_case($col);
		}

		if ($only_field) {

			$a = [];

			foreach ($column as $col) {
				$a[] = $col['field'];
			}

			return $a;
		}

		return $column;
	}

	public function tbls($only_table = FALSE) {
		$sql = 'SELECT table_name,table_comment FROM information_schema.TABLES WHERE table_schema = \''.$this->conf['database'].'\' ORDER BY table_name';

		$table = $this->query($sql);

		if ($only_table) {
			$a = [];

			foreach ($table as $tbl) {
				$a[] = $tbl['table_name'];
			}

			return $a;
		}

		return $table;
	}

	public function pkey($only_key = FALSE) {
		$sql = 'SHOW keys FROM `'.$this->table.'` where key_name=\'PRIMARY\'';

		$key = $this->query($sql);

		$this->reset();

		foreach ($key as &$p) {
			$p = array_change_key_case($p);
		}

		if ($only_key) {
			$a = [];

			foreach ($key as $p) {
				$a[] = $p['column_name'];
			}

			return $a;
		}

		return $key;
	}
}