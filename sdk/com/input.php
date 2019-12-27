<?php namespace sox\sdk\com;

use \sox\sdk\com\ini;
use \sox\sdk\com\rsa;
use \sox\sdk\com\validation;

class input
{
	static function extract(&$data,$key,$def = '')
	{
		if(isset($data[$key]))
		{
			$result = $data[$key];

			unset($data[$key]);

			return $result;
		}

		return $def;
	}

	static function map(&$map,$key,$input,$callback = NULL,$empty_value = '')
	{
		if($callback !== NULL && !is_callable($callback))
		{
			$empty_value = $callback;

			$callback = NULL;
		}

		if($input === $empty_value) return $empty_value;

		if(empty($callback))
		{
			$map[$key] = $input;
		}
		else
		{
			$map[$key] = $callback($input);
		}

		return $input;
	}

	static function set(&$data,$rule,$value = '')
	{
		if(is_string($rule))
		{
			if(strpos($rule,';') === FALSE)
			{
				list($field,$parse,$value) = (explode(':',$rule) + array('','',$value));
			}
			else
			{
				foreach(explode(';',$rule) as $_set)
				{
					self::set($data,$_set,$value);
				}
			}
		}
		else
		{
			$field = '';
			$parse = '';
			$value = '';

			foreach($rule as $key => $val)
			{
				$$key = $val;
			}
		}

		if(empty($field)) return $data;

		if(!isset($data[$field]) || $data[$field] === '')
		{
			$data[$field] = $value;
		}

		if($data[$field] !== NULL)
		{
			switch($parse)
			{
				case 'strval':
					$data[$field] = htmlentities($data[$field]);
					break;

				case 'txtval':
					$data[$field] = self::remove_xss($data[$field]);
					break;

				case 'intval':
					$data[$field] = strval($data[$field]);

					if($data[$field] !== '')
					{
						$data[$field] = strval(intval($data[$field]));
					}
					break;

				case 'number':
					$data[$field] = strval($data[$field]);

					if($data[$field] !== '')
					{
						$data[$field] = strval(floatval($data[$field]));
					}
					break;

				case 'putext':
					$data[$field] = self::remove_hms($data[$field]);
					break;

				case 'imtext':
					$data[$field] = self::remove_xss(self::remove_hms($data[$field],'img'));
					break;

				case 'ictext':
					$data[$field] = self::remove_xss(self::remove_hms($data[$field],'img,code'));
					break;
				
				case 'rsastr':
					$data[$field] = rsa::decrypt($data[$field]);
					break;
				
				case 'rsatss':
					$data[$field] = rsa::decrypt($data[$field]);

					$pos = strrpos($data[$field],'@');

					if(!empty($pos))
					{
						$time = (int)substr($data[$field],$pos+1);

						if(abs(time() - $time) < 300)
						{
							$data[$field] = substr($data[$field],0,$pos);
						}
						else
						{
							$data[$field] = '';
						}
					}
					else
					{
						$data[$field] = '';
					}
					break;
				
				case 'rsatsi':
					$storage_key = md5($data[$field]);

					$redis = load_redis(ini::get('redis'));

					if($redis !== NULL)
					{
						$data[$field] = rsa::decrypt($data[$field]);

						$pos = strrpos($data[$field],'@');

						if(!empty($pos))
						{
							$time = (int)substr($data[$field],$pos+1);

							if($redis->get('rsatss_'.$storage_key) != $time)
							{
								if(abs(time() - $time) < 300)
								{
									$data[$field] = substr($data[$field],0,$pos);

									$redis->setex('rsatss_'.$storage_key,300,$time);
								}
								else
								{
									$data[$field] = '';
								}
							}
							else
							{
								$data[$field] = '';
							}
						}
						else
						{
							$data[$field] = '';
						}
					}
					else
					{
						$data[$field] = '';
					}
					break;
				
				default:
					$data[$field] = strval($data[$field]);
					break;
			}
		}
		else
		{
			unset($data[$field]);
		}

		return $data[$field];
	}

	static function validate(&$data,$rule,&$error,$extend_validation = NULL)
	{
		// rule => { field, alias, parse, value, rules }
		// $extend_validation must be an object, such as new class, no matter static or dynamic

		$label  = [];

		foreach($rule as $key => $val)
		{
			if(empty($val['alias']))
			{
				$label[$val['field']] = $val['field'];
			}
			else
			{
				$label[$val['field']] = $val['alias'];
			}

			self::set($data,$val);
		}

		if($extend_validation)
		{
			$extend_validation::$label = $label;
			$extend_validation::$input = $data;
		}

		validation::$label = &$label;
		validation::$input = &$data;

		foreach($rule as $key => $val)
		{
			if(empty($val['rules'])) continue;

			$val['rules'] = preg_replace_callback('/\[.*?\]/',function($match){
				return str_replace('|','(%)',$match[0]);
			},$val['rules']);

			$validate_func_array = explode('|',$val['rules']);

			foreach($validate_func_array as $func)
			{
				$func = str_replace('(%)','|',$func);

				$para_posi = strpos($func,'[');
				$func_para = [];

				if($para_posi > 0)
				{
					$real_func = substr($func,0,$para_posi);
					$func_para = explode('][',substr($func,$para_posi+1,-1));
				}
				else
				{
					$real_func = $func;
				}

				array_unshift($func_para,$val['field']);


				if($real_func == 'needed')
				{
					if(!isset($data[$val['field']])) break;

					if($data[$val['field']] !== NULL)
					{
						$real_func = 'required';
					}
					else
					{
						unset($data[$val['field']]);

						break;
					}
				}

				if($extend_validation && method_exists($extend_validation,$real_func))
				{
					$validation = $extend_validation;
				}
				elseif(method_exists(new validation,$real_func))
				{
					$validation = new validation;
				}
				else
				{
					$error[$val['field']] = 'The validation function ['.$real_func.'] does not exist';

					break;
				}

				if(!call_user_func_array([$validation,$real_func],$func_para))
				{
					$error[$val['field']] = $val['error'][$real_func] ?? ($validation::$error[$val['field']] ?? 'The validation function ['.$real_func.'] has no error description');

					break;
				}
			}
		}

		return empty($error);
	}

	static function remove_xss($str,$reserve = '')
	{
		// if(strpos($str,'&') !== FALSE) $str = str_replace('&','&amp;',$str);
		if(strpos($str,'<') === FALSE && strpos($str,'>') === FALSE) return $str;

		$never_allowed_tags = array('form','javascript','vbscript','expression','applet','meta','xml','blink','link','style','script','embed','object','iframe','frame','frameset','ilayer','layer','bgsound','title','base');
		$never_allowed_acts = array('onabort','onactivate','onafterprint','onafterupdate','onbeforeactivate','onbeforecopy','onbeforecut','onbeforedeactivate','onbeforeeditfocus','onbeforepaste','onbeforeprint','onbeforeunload','onbeforeupdate','onblur','onbounce','oncellchange','onchange','onclick','oncontextmenu','oncontrolselect','oncopy','oncut','ondataavailable','ondatasetchanged','ondatasetcomplete','ondblclick','ondeactivate','ondrag','ondragend','ondragenter','ondragleave','ondragover','ondragstart','ondrop','onerror','onerrorupdate','onfilterchange','onfinish','onfocus','onfocusin','onfocusout','onhelp','onkeydown','onkeypress','onkeyup','onlayoutcomplete','onload','onlosecapture','onmousedown','onmouseenter','onmouseleave','onmousemove','onmouseout','onmouseover','onmouseup','onmousewheel','onmove','onmoveend','onmovestart','onpaste','onpropertychange','onreadystatechange','onreset','onresize','onresizeend','onresizestart','onrowenter','onrowexit','onrowsdelete','onrowsinserted','onscroll','onselect','onselectionchange','onselectstart','onstart','onstop','onsubmit','onunload');

		if(!empty($reserve))
		{
			if(!is_array($reserve)) $reserve = explode(',',$reserve);

			foreach($reserve as $rsv)
			{
				if(stripos($str,$rsv) === FALSE) continue;

				$str = preg_replace_callback('/(\<'.preg_quote($rsv).'[\s\S]*?\>)([\s\S]*?)(\<\/'.preg_quote($rsv).'\>)/i',create_function('$matches','return $matches[1].str_replace(array("<",">"),array("&lt;","&gt;"),$matches[2]).$matches[3];'),$str);
			}
		}

		if(strpos($str,'?')) $str = preg_replace('/\<\?([\s\S]*?)\>/','',$str);

		foreach($never_allowed_tags as $tag)
		{
			if(stripos($str,$tag) === FALSE) continue;

			$str = preg_replace('/\<([\s]*?)'.preg_quote($tag).'([\s\S]*?)\<\/([\s]*?)'.preg_quote($tag).'([\s]*?)\>/i','',$str);
			$str = preg_replace('/\<([\s]*?)'.preg_quote($tag).'([\s\S]*?)\>/i','',$str);
			$str = preg_replace('/\<\/([\s]*?)'.preg_quote($tag).'([\s]*?)\>/i','',$str);
		}

		foreach($never_allowed_acts as $act)
		{
			if(stripos($str,$act) === FALSE) continue;

			$str = preg_replace('/'.$act.'([\s]*?)=["\']([\s\S]*?)["\']/','',$str);
		}

		if(strpos($str,'<') !== FALSE)
		{
			$escape = array(array('<' => '*$','>' => '$*'),array('<' => '*(','>' => ')*'),array('<' => '%*','>' => '*%'),array('<' => '^&','>' => '&^'),array('<' => '|\\','>' => '/|'),array('<' => '|/','>' => '\\|'));

			if(strpos($str,'>') !== FALSE)
			{
				foreach($escape as $key => $val)
				{
					if((strpos($str,$val['<']) !== FALSE) || (strpos($str,$val['>']) !== FALSE)) continue;

					$esc = $val;

					break;
				}

				if(!empty($esc))
				{
					$str = preg_replace('/<([a-zA-Z]+[\s\S]*?)>/',$esc['<'].'$1'.$esc['>'],$str);
					$str = preg_replace('/<(\/[\s\S]*?)>/',$esc['<'].'$1'.$esc['>'],$str);
				}
			}
		}

		$str = str_replace(array('<','>'),array('&lt;','&gt;'),$str);

		if(!empty($esc))
		{
			$str = str_replace(array($esc['<'],$esc['>']),array('<','>'),$str);
		}

		return $str;
	}

	static function remove_hms($str,$allow_tag = '')
	{
		if(strpos($str,'&') !== FALSE) $str = str_replace('&','&amp;',$str);
		if(strpos($str,'<') === FALSE && strpos($str,'>') === FALSE) return $str;

		$has_allow_tag = FALSE;

		if(!empty($allow_tag))
		{
			$escape = array(array('<' => '*$','>' => '$*'),array('<' => '*(','>' => ')*'),array('<' => '%*','>' => '*%'),array('<' => '^&','>' => '&^'),array('<' => '|\\','>' => '/|'),array('<' => '|/','>' => '\\|'));

			foreach($escape as $key => $val)
			{
				if((strpos($str,$val['<']) !== FALSE) || (strpos($str,$val['>']) !== FALSE)) continue;

				$esc = $val;

				break;
			}

			if(!empty($esc))
			{
				foreach(explode(',',$allow_tag) as $tag)
				{
					if(strpos($str,'<'.$tag) === FALSE) continue;
					if(!$has_allow_tag) $has_allow_tag = TRUE;

					$str = preg_replace('/<('.$tag.' .*?)>/i',$esc['<'].'$1'.$esc['>'],$str);
					$str = str_ireplace('</'.$tag.'>',$esc['<'].'/'.$tag.$esc['>'],$str);
				}
			}
		}

		if(strpos($str,'<') !== FALSE)
		{
			if(strpos($str,'?')) $str = preg_replace('/\<\?([\s\S]*?)\>/','',$str);

			$str = preg_replace('/<[a-zA-Z]+[\s\S]*?>/','',$str);
			$str = preg_replace('/<\/[\s\S]*?>/','',$str);
		}

		$str = str_replace(array('<','>'),array('&lt;','&gt;'),$str);

		if($has_allow_tag)
		{
			$str = str_replace(array($esc['<'],$esc['>']),array('<','>'),$str);
		}

		return $str;
	}
}

?>