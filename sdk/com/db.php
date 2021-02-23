<?php namespace sox\sdk\com;

class db
{
	public $table = '';
	public $drive = '';

	public $fixed = '';
	public $reset = FALSE;

	public function __construct($table = NULL,$conf = NULL)
	{
		if(is_array($table))
		{
			$conf = $table;

			$table = '';
		}

		$this->table = $table;
		$this->fixed = $table;

		switch($conf['dbdriver'])
		{
			case 'pdo': $this->mysql = load_pdo($conf); break;
			case 'odbc': $this->mysql = load_odbc($conf); break;

			default: $this->mysql = load_mysql($conf); break;
		}
	}

	public function set($table = '',$reset = TRUE)
	{
		$this->table = $table;

		if($reset && $this->fixed)
		{
			$this->reset = $reset;
		}

		return $this;
	}

	public function reset()
	{
		if($this->reset)
		{
			$this->table = $this->fixed;

			$this->reset = FALSE;
		}
	}

	public function escape($str)
	{
		// return $this->mysql->real_escape_string($str);

		return str_replace(['\\','\'','"',"\r","\n"],['\\\\','\\\'','\\"','\\r','\\n'],$str);
	}

	public function error()
	{
		return $this->mysql->error;
	}

	public function query($sql)
	{
		$result = $this->mysql->query($sql);

		if(!$result) return FALSE;

		switch(strtoupper(substr($sql,0,4)))
		{
			case 'SELE':
			case 'SHOW':
			case 'DESC':
			case 'EXPL': return $result->fetch_all(MYSQLI_ASSOC); break;
			
			default: return $result; break;
		}
	}

	public function where($map = FALSE)
	{
		if($map !== FALSE)
		{
			if(is_array($map))
			{
				$sql        = '';
				$need_logic = FALSE;

				foreach($map as $key => $val)
				{
					$front_char = substr($key,0,1);

					if($front_char == '#') continue;

					if($sql == '') $sql = 'WHERE ';

					if($front_char == '^')
					{
						if(in_array($val,['and (','or (']))
						{
							$val = $need_logic ? strtoupper($val) : '(';

							$need_logic = FALSE;
						}

						$sql .= $val.' ';

						continue;
					}

					if(FALSE === $pos = strpos($key,'#'))
					{
						$logic = 'and';
					}
					else
					{
						if($pos == 0) continue;

						$logic = substr($key,0,$pos);
						$pos = strrpos($key,'#');
						$key = substr($key,$pos+1);
					}

					$key = str_replace(array(' ','\'','"'),'',$key);

					$chr = strpos($key,'`') === FALSE ? '`' : '';
					$chl = strlen($chr);

					$key = $chr.((strpos($key,'.') === FALSE) ? $key : ($chr != '' ? str_replace('.',$chr.'.'.$chr,$key) : $key)).$chr;

					switch(substr($key,-1-$chl,1))
					{
						case '=':
							switch (substr($key,-2-$chl,2))
							{
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

					if(strpos($logic,'in') === FALSE)
					{
						if($val !== NULL)
						{
							if(strpos($logic,'like') !== FALSE)
							{
								$like = (substr($key,1,1) == '%' ? '%' : '_').(substr($key,-5,1) == '%' ? '%' : '_');

								switch($like)
								{
									case '_%': $key = substr_replace($key,'',-5,1);$val = str_replace('%','\%',$val).'%'; break;
									case '%_': $key = substr_replace($key,'',1,1);$val = '%'.str_replace('%','\%',$val); break;

									default: $val = '%'.str_replace('%','\%',$val).'%'; break;
								}
							}

							$val = '\''.$this->escape($val).'\'';
						}
						else
						{
							if(strpos($key,'!=') === FALSE)
							{
								$key = str_replace(' = ',' IS ',$key);
							}
							else
							{
								$key = str_replace(' != ',' IS NOT ',$key);
							}

							$val = 'NULL';
						}
					}
					else
					{
						if(is_array($val))
						{
							if(count($val) != count($val,1)) continue;

							foreach($val as &$v)
							{
								$v = $this->escape($v);
							}

							$val = '(\''.implode('\',\'',$val).'\')';
						}
						else
						{
							$val = '(\''.str_replace(',','\',\'',$this->escape($val)).'\')';
						}
					}

					switch($logic)
					{
						case 'and':
							$lgc = 'AND';
							break;

						case 'or':
							$lgc = 'OR';
							break;

						case 'like':
							$lgc = 'AND';

							$key = str_replace(' = ',' LIKE ',strpos($key,',') === FALSE ? $key : 'CONCAT_WS(\' \','.str_replace([',',' ='],['`,`',') ='],$key));
							break;

						case 'or_like':
							$lgc = 'OR';

							$key = str_replace(' = ',' LIKE ',strpos($key,',') === FALSE ? $key : 'CONCAT_WS(\' \','.str_replace([',',' ='],['`,`',') ='],$key));
							break;

						case 'not_like':
							$lgc = 'AND';

							$key = str_replace(' = ',' NOT LIKE ',strpos($key,',') === FALSE ? $key : 'CONCAT_WS(\' \','.str_replace([',',' ='],['`,`',') ='],$key));
							break;

						case 'or_not_like':
							$lgc = 'OR';

							$key = str_replace(' = ',' NOT LIKE ',strpos($key,',') === FALSE ? $key : 'CONCAT_WS(\' \','.str_replace([',',' ='],['`,`',') ='],$key));
							break;

						case 'in':
							$lgc = 'AND';

							if(strpos($val,',') !== FALSE)
							{
								$key = str_replace(' = ',' IN ',$key);
							}
							else
							{
								$val = substr($val,1,-1);
							}
							break;

						case 'or_in':
							$lgc = 'OR';

							if(strpos($val,',') !== FALSE)
							{
								$key = str_replace(' = ',' IN ',$key);
							}
							else
							{
								$val = substr($val,1,-1);
							}
							break;

						case 'not_in':
							$lgc = 'AND';

							if(strpos($val,',') !== FALSE)
							{
								$key = str_replace(' = ',' NOT IN ',$key);
							}
							else
							{
								$key = str_replace(' = ',' != ',$key);
								$val = substr($val,1,-1);
							}
							break;

						case 'or_not_in':
							$lgc = 'OR';

							if(strpos($val,',') !== FALSE)
							{
								$key = str_replace(' = ',' NOT IN ',$key);
							}
							else
							{
								$key = str_replace(' = ',' != ',$key);
								$val = substr($val,1,-1);
							}
							break;

						default:
							$lgc = 'AND';
							break;
					}

					if($need_logic)
					{
						$sql .= $lgc.' '.$key.$val;
					}
					else
					{
						$need_logic = TRUE;

						$sql .= $key.$val;
					}

					$sql .= ' ';
				}

				return $sql;
			}
			else
			{
				return 'WHERE `'.$this->table.'`.`id` = \''.$this->escape($map).'\'';
			}
		}
		else
		{
			return '';
		}
	}

	public function count($map = array())
	{
		$sql = 'SELECT COUNT(*) FROM `'.$this->table.'` ';

		if(isset($map['#unite']))
		{
			foreach(explode(';',$map['#unite']) as $join_str)
			{
				$join_arr = explode(',',$join_str);

				if(isset($join_arr[2]))
				{
					$sql .= strtoupper($join_arr[2]).' JOIN `'.$join_arr[0].'` ON `'.str_replace([' ','.','='],['','`.`','` = `'],$join_arr[1]).'` ';
				}
				else
				{
					$sql .= 'JOIN `'.$join_arr[0].'` ON `'.str_replace([' ','.','='],['','`.`','` = `'],$join_arr[1]).'` ';
				}
			}
		}

		$sql .= $this->where($map);

		if(empty($map))
		{
			$sql .= ' FORCE INDEX(PRIMARY) ';
		}

		$result = $this->mysql->query($sql)->fetch_row();

		$this->reset();

		return $result[0];
	}

	public function create($data,$batch = FALSE)
	{
		$sql = '';

		if($batch === FALSE)
		{
			$sql = 'INSERT INTO `'.$this->table.'` ';

			$field = '';
			$value = '';

			foreach($data as $key => $val)
			{
				$field .= '`'.$key.'`,';
				$value .= '\''.$this->escape($val).'\',';
			}

			$sql .= '('.substr($field,0,-1).') VALUES ('.substr($value,0,-1).')';

			$result = $this->mysql->query($sql);

			$this->reset();

			return $this->mysql->insert_id ? $this->mysql->insert_id : $result;
		}
		else
		{
			$sql = 'INSERT IGNORE INTO `'.$this->table.'` ';

			$field = '';
			$value = '';

			foreach($data as $k => $v)
			{
				$tmp = '';

				foreach($v as $key => $val)
				{
					if($k == 0)
					{
						$field .= '`'.$key.'`,';
					}

					$tmp .= '\''.$this->escape($val).'\',';
				}

				$value .= '('.substr($tmp,0,-1).'), ';
			}

			$sql .= '('.substr($field,0,-1).') VALUES '.substr($value,0,-2);

			$result = $this->mysql->query($sql);

			$this->reset();

			return $this->mysql->affected_rows ? $this->mysql->affected_rows : $result;
		}
	}

	public function update($where = array(),$data = array())
	{
		$sql = 'UPDATE `'.$this->table.'` SET ';

		foreach($data as $key => $value)
		{
			$sql .= '`'.$key.'` = \''.$this->escape($value).'\',';
		}

		$sql = trim($sql,',').' ';

		$sql .= $this->where($where);

		$result = $this->mysql->query($sql);

		$this->reset();

		return $result;
	}

	public function delete($map = array())
	{
		$sql = 'DELETE FROM `'.$this->table.'` ';

		$sql .= $this->where($map);

		$result = $this->mysql->query($sql);

		$this->reset();

		return $result;
	}

	public function read($map = array(),$where = '',$limit = '',$order = '')
	{
		$sql = '';

		if(!is_array($map))
		{
			$field = $map;

			$map = array();

			if($field != '') $map['#field'] = $field;

			if(is_array($where))
			{
				$map = array_merge($map,$where);
			}
			else
			{
				$order = $limit;
				$limit = $where;
			}

			if($limit != '') $map['#limit'] = $limit;
			if($order != '') $map['#order'] = $order;
		}

		if(isset($map['#field']))
		{
			$map['#field'] = '`'.str_ireplace(array('.',' AS ',','),array('`.`','` AS `','`,`'),$map['#field']).'`';
			$map['#field'] = str_replace(['``','`*`'],['','*'],$map['#field']);
			$map['#field'] = str_ireplace('`distinct ','DISTINCT `',$map['#field']);

			$sql = 'SELECT '.$map['#field'].' FROM `'.$this->table.'` ';
		}
		else
		{
			$sql = 'SELECT * FROM `'.$this->table.'` ';
		}

		if(isset($map['#unite']))
		{
			foreach(explode(';',$map['#unite']) as $join_str)
			{
				$join_arr = explode(',',$join_str);

				if(isset($join_arr[2]))
				{
					$sql .= strtoupper($join_arr[2]).' JOIN `'.$join_arr[0].'` ON `'.str_replace([' ','.','='],['','`.`','` = `'],$join_arr[1]).'` ';
				}
				else
				{
					$sql .= 'JOIN `'.$join_arr[0].'` ON `'.str_replace([' ','.','='],['','`.`','` = `'],$join_arr[1]).'` ';
				}
			}
		}

		$sql .= $this->where($map);

		if(isset($map['#order']))
		{
			$sql .= 'ORDER BY `'.str_ireplace([' ','desc','asc','.',',',';'],['','DESC','ASC','`.`','` ',',`'],$map['#order']).' ';

			$sql = str_ireplace(['`rand()','rand()`'],['RAND()','RAND()'],$sql);
		}

		if(isset($map['#limit']))
		{
			list($limit,$ofset) = explode(',',$map['#limit']) + [0,0];

			$limit = (int)$limit;
			$ofset = (int)$ofset;

			if($limit > 0) $sql .= 'LIMIT '.$ofset.','.$limit.' ';
		}

		$result = $this->mysql->query($sql);

		$this->reset();

		if($result)
		{
			if(isset($map['#indby']))
			{
				$data = array();

				foreach($result->fetch_all(MYSQLI_ASSOC) as $key => $value)
				{
					$data[$value[$map['#indby']]] = $value;
				}

				return $data;
			}
			else
			{
				return $result->fetch_all(MYSQLI_ASSOC);
			}
		}
		else
		{
			return $result;
		}
	}

	public function find($map = array(),$where = array(),$order = '',$limit = '')
	{
		if(!is_array($map))
		{
			$field = $map;

			$map = array();

			if($field != '') $map['#field'] = $field;

			if(is_array($where))
			{
				if(!empty($where)) $map = array_merge($map,$where);
			}
			else
			{
				$map['id'] = $where;
			}

			if($order != '') $map['#order'] = $order;
			if($limit != '') $map['#limit'] = '1,0';
		}

		if(empty($map['#limit'])) $map['#limit'] = '1,0';

		$list = $this->read($map);

		if(!empty($list))
		{
			$data = array_shift($list);

			return (count($data) > 1) ? $data : array_pop($data);
		}
		else
		{
			return FALSE;
		}
	}

	public function increase($where = '',$item = '',$step = 0,$data = [])
	{
		if($step == 0 && is_numeric($item))
		{
			$step = $item;
			$item = $where;

			$where = '';
		}

		$sql = 'UPDATE `'.$this->table.'` SET ';

		if(is_string($item))
		{
			if($step == 0) return FALSE;

			foreach(explode(',',$item) as $key => $value)
			{
				$sql .= '`'.$value.'` = `'.$value.'`'.($step > 0 ? '+'.$step : (string)$step).', ';
			}
		}
		else
		{
			foreach($item as $key => $value)
			{
				$sql .= '`'.$key.'` = `'.$key.'`'.($value > 0 ? '+'.$value : (string)$value).', ';
			}

			$data = $step;
		}

		if(!empty($data))
		{
			foreach($data as $key => $value)
			{
				$sql .= '`'.$key.'` = \''.$this->escape($value).'\', ';
			}
		}

		$sql = substr($sql,0,-2).' '.$this->where($where);

		$result = $this->mysql->query($sql);

		$this->reset();

		return $result;
	}

	public function cols($only_field = FALSE)
	{
		$sql = 'SHOW COLUMNS FROM '.$this->table;

		$result = $this->mysql->query($sql);

		$this->reset();

		$column = $result->fetch_all(MYSQLI_ASSOC);

		foreach($column as $key => &$col)
		{
			$col = array_change_key_case($col);

			if($col['field'] == 'bucket__id') unset($column[$key]);
		}

		if($only_field)
		{

			$a = [];

			foreach($column as $col)
			{
				$a[] = $col['field'];
			}

			return $a;
		}

		return $column;
	}

	
}

?>