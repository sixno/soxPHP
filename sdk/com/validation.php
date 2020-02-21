<?php namespace sox\sdk\com;

use \sox\sdk\com\db;
use \sox\sdk\com\ini;

class validation
{
	static $label = [];
	static $input = [];
	static $error = [];

	static $err_t = [
		'required'           => '%s为必填项',
		'regex_match'        => '%s必须是%s',
		'matches'            => '%s与%s不匹配',
		'greater_to'         => '%s必须大于等于%s',
		'less_to'            => '%s必须小于等于%s',
		'later_to'           => '%s必须不早于%s',
		'earlier_to'         => '%s必不晚于%s',
		'greater_than'       => '%s必须大于%s',
		'less_than'          => '%s必须小于%s',
		'later_than'         => '%s必须晚于%s',
		'earlier_than'       => '%s必须早于%s',
		'is_unique'          => '%s已存在',
		'min_length'         => '%s至少%s位',
		'max_length'         => '%s最多%s位',
		'exact_length'       => '%s必须%s位',
		'valid_email'        => '%s格式不正确',
		'valid_ip'           => '%s格式不正确',
		'alpha'              => '%s只能包含字母',
		'alpha_numeric'      => '%s只能包含字母和数字',
		'alpha_dash'         => '%s只能包含字母、数字、下划线和破折号',
		'numeric'            => '%s只能包含数字',
		'integer'            => '%s只能是整数',
		'decimal'            => '%s只能是浮点数',
		'is_natural'         => '%s必须是自然数',
		'is_natural_no_zero' => '%s必须是非0自然数',
		'valid_base64'       => '%s必须是base64编码字符',
		'valid_safe_base64'  => '%s必须是base64编码字符',
		'valid_url'          => '%s不是合法的URL地址',
		'is_available_url'   => '%s文件不存在或地址无效',
		'is_image_url'       => '%s图片文件不存在或地址无效',
	];

	static function required($field)
	{
		$result = isset(self::$input[$field]) ? (self::$input[$field] !== '') : FALSE;

		if(!$result)
		{
			self::$error[$field] = sprintf(self::$err_t['required'],self::$label[$field]);
		}

		return $result;
	}

	static function regex_match($field, $regex)
	{
		if(!isset(self::$input[$field]) || (string)self::$input[$field] === '') return TRUE;

		$result = (bool) preg_match($regex, self::$input[$field]);

		if(!$result)
		{
			self::$error[$field] = sprintf(self::$err_t['regex_match'],self::$label[$field]);
		}

		return  TRUE;
	}

	static function matches($field, $field2)
	{
		if(!isset(self::$input[$field]) || (string)self::$input[$field] === '') return TRUE;

		$result = (self::$input[$field] === self::$input[$field2]);

		if(!$result)
		{
			self::$error[$field] = sprintf(self::$err_t['matches'],self::$label[$field],self::$label[$field2]);
		}

		return ($str !== $fstr) ? FALSE : TRUE;
	}

	static function greater_to($field, $field2)
	{
		if(!isset(self::$input[$field]) || (string)self::$input[$field] === '') return TRUE;

		$result = (self::$input[$field] >= self::$input[$field2]);

		if(!$result)
		{
			self::$error[$field] = sprintf(self::$err_t['greater_to'],self::$label[$field],self::$label[$field2]);
		}

		return $result;
	}

	static function less_to($field, $field2)
	{
		if(!isset(self::$input[$field]) || (string)self::$input[$field] === '') return TRUE;

		$result = (self::$input[$field] <= self::$input[$field2]);

		if(!$result)
		{
			self::$error[$field] = sprintf(self::$err_t['less_to'],self::$label[$field],self::$label[$field2]);
		}

		return $result;
	}

	static function later_to($field, $field2)
	{
		if(!isset(self::$input[$field]) || (string)self::$input[$field] === '') return TRUE;

		$input1 = !is_numeric(self::$input[$field]) ? strtotime(self::$input[$field]) : self::$input[$field];
		$input2 = !is_numeric(self::$input[$field2]) ? strtotime(self::$input[$field2]) : self::$input[$field2];

		$result = ($input1 >= $input2);

		if(!$result)
		{
			self::$error[$field] = sprintf(self::$err_t['later_to'],self::$label[$field],self::$label[$field2]);
		}

		return $result;
	}

	static function earlier_to($field, $field2)
	{
		if(!isset(self::$input[$field]) || (string)self::$input[$field] === '') return TRUE;

		$input1 = !is_numeric(self::$input[$field]) ? strtotime(self::$input[$field]) : self::$input[$field];
		$input2 = !is_numeric(self::$input[$field2]) ? strtotime(self::$input[$field2]) : self::$input[$field2];

		$result = ($input1 <= $input2);

		if(!$result)
		{
			self::$error[$field] = sprintf(self::$err_t['earlier_to'],self::$label[$field],self::$label[$field2]);
		}

		return $result;
	}

	static function greater_than($field, $field2)
	{
		if(!isset(self::$input[$field]) || (string)self::$input[$field] === '') return TRUE;

		$result = (self::$input[$field] > self::$input[$field2]);

		if(!$result)
		{
			self::$error[$field] = sprintf(self::$err_t['greater_to'],self::$label[$field],self::$label[$field2]);
		}

		return $result;
	}

	static function less_than($field, $field2)
	{
		if(!isset(self::$input[$field]) || (string)self::$input[$field] === '') return TRUE;

		$result = (self::$input[$field] < self::$input[$field2]);

		if(!$result)
		{
			self::$error[$field] = sprintf(self::$err_t['less_to'],self::$label[$field],self::$label[$field2]);
		}

		return $result;
	}

	static function later_than($field, $field2)
	{
		if(!isset(self::$input[$field]) || (string)self::$input[$field] === '') return TRUE;

		$input1 = !is_numeric(self::$input[$field]) ? strtotime(self::$input[$field]) : self::$input[$field];
		$input2 = !is_numeric(self::$input[$field2]) ? strtotime(self::$input[$field2]) : self::$input[$field2];

		$result = ($input1 > $input2);

		if(!$result)
		{
			self::$error[$field] = sprintf(self::$err_t['later_to'],self::$label[$field],self::$label[$field2]);
		}

		return $result;
	}

	static function earlier_than($field, $field2)
	{
		if(!isset(self::$input[$field]) || (string)self::$input[$field] === '') return TRUE;

		$input1 = !is_numeric(self::$input[$field]) ? strtotime(self::$input[$field]) : self::$input[$field];
		$input2 = !is_numeric(self::$input[$field2]) ? strtotime(self::$input[$field2]) : self::$input[$field2];

		$result = ($input1 < $input2);

		if(!$result)
		{
			self::$error[$field] = sprintf(self::$err_t['earlier_to'],self::$label[$field],self::$label[$field2]);
		}

		return $result;
	}

	static function is_unique($field, $db_field, $db_ini = '')
	{
		if(!isset(self::$input[$field]) || (string)self::$input[$field] === '') return TRUE;

		list($table, $db_field) = explode('.', $db_field);

		if($pos = strpos($db_field,'#'))
		{
			if(self::$input[$field] == substr($db_field,$pos+1)) return TRUE;

			$db_field = substr($db_field,0,$pos);
		}

		if(!$db_ini) $db_ini = 'db';

		$db = new db($table,ini::get($db_ini));

		$result = ($db->find($db_field,[$db_field => self::$input[$field]]) === FALSE);

		if(!$result)
		{
			self::$error[$field] = sprintf(self::$err_t['is_unique'],self::$label[$field]);
		}

		return $result;
	}

	static function min_length($field, $len)
	{
		if(!isset(self::$input[$field]) || (string)self::$input[$field] === '') return TRUE;

		if (function_exists('mb_strlen'))
		{
			$result = (mb_strlen(self::$input[$field],'utf8') >= $len);
		}
		else
		{
			$result = (strlen(self::$input[$field]) >= $len);
		}

		if(!$result)
		{
			self::$error[$field] = sprintf(self::$err_t['min_length'],self::$label[$field],$len);
		}

		return $result;
	}

	static function max_length($field, $len)
	{
		if(!isset(self::$input[$field]) || (string)self::$input[$field] === '') return TRUE;

		if (function_exists('mb_strlen'))
		{
			$result = (mb_strlen(self::$input[$field],'utf8') <= $len);
		}
		else
		{
			$result = (strlen(self::$input[$field]) <= $len);
		}

		if(!$result)
		{
			self::$error[$field] = sprintf(self::$err_t['max_length'],self::$label[$field],$len);
		}

		return $result;
	}

	static function exact_length($field, $len)
	{
		if(!isset(self::$input[$field]) || (string)self::$input[$field] === '') return TRUE;

		if (function_exists('mb_strlen'))
		{
			$result = (mb_strlen(self::$input[$field],'utf8') == $len);
		}
		else
		{
			$result = (strlen(self::$input[$field]) == $len);
		}

		if(!$result)
		{
			self::$error[$field] = sprintf(self::$err_t['exact_length'],self::$label[$field],$len);
		}

		return $result;
	}

	static function valid_email($field)
	{
		if(!isset(self::$input[$field]) || (string)self::$input[$field] === '') return TRUE;


		$result = (bool)preg_match("/^([a-z0-9\+_\-]+)(\.[a-z0-9\+_\-]+)*@([a-z0-9\-]+\.)+[a-z]{2,6}$/ix", self::$input[$field]);

		if(!$result)
		{
			self::$error[$field] = sprintf(self::$err_t['valid_email'],self::$label[$field]);
		}

		return $result;
	}

	static function valid_ip($field, $which = '')
	{
		if(!isset(self::$input[$field]) || (string)self::$input[$field] === '') return TRUE;

		$which = strtolower($which);

		if ($which !== 'ipv6' && $which !== 'ipv4')
		{
			if (strpos(self::$input[$field], ':') !== FALSE)
			{
				$which = 'ipv6';
			}
			elseif (strpos(self::$input[$field], '.') !== FALSE)
			{
				$which = 'ipv4';
			}
			else
			{
				self::$error[$field] = sprintf(self::$err_t['valid_ip'],self::$label[$field]);

				return FALSE;
			}
		}

		// First check if filter_var is available
		if (is_callable('filter_var'))
		{
			switch($which)
			{
				case 'ipv4': $flag = FILTER_FLAG_IPV4; break;
				case 'ipv6': $flag = FILTER_FLAG_IPV6; break;
				default: $flag = ''; break;
			}

			$result = (bool) filter_var(self::$input[$field], FILTER_VALIDATE_IP, $flag);
		}
		else
		{
			switch($which)
			{
				case 'ipv4': $result = valid_ipv4(self::$input[$field]); break;
				case 'ipv6': $result = valid_ipv6(self::$input[$field]); break;
				default: $result = FALSE; break;
			}
		}

		if(!$result)
		{
			self::$error[$field] = sprintf(self::$err_t['valid_ip'],self::$label[$field]);
		}

		return $result;
	}

	static function alpha($field)
	{
		if(!isset(self::$input[$field]) || (string)self::$input[$field] === '') return TRUE;

		$result = (bool)preg_match('/^([a-z])+$/i', self::$input[$field]);

		if(!$result)
		{
			self::$error[$field] = sprintf(self::$err_t['alpha'],self::$label[$field]);
		}

		return $result;
	}

	static function alpha_numeric($field)
	{
		if(!isset(self::$input[$field]) || (string)self::$input[$field] === '') return TRUE;

		$result = (bool)preg_match('/^([a-z0-9])+$/i', self::$input[$field]);

		if(!$result)
		{
			self::$error[$field] = sprintf(self::$err_t['alpha_numeric'],self::$label[$field]);
		}

		return $result;
	}

	static function alpha_dash($field)
	{
		if(!isset(self::$input[$field]) || (string)self::$input[$field] === '') return TRUE;

		$result = (bool)preg_match("/^([-a-z0-9_-])+$/i", self::$input[$field]);

		if(!$result)
		{
			self::$error[$field] = sprintf(self::$err_t['alpha_dash'],self::$label[$field]);
		}

		return $result;
	}

	static function numeric($field)
	{
		if(!isset(self::$input[$field]) || (string)self::$input[$field] === '') return TRUE;

		$result = (bool)preg_match( '/^[\-+]?[0-9]*\.?[0-9]+$/', self::$input[$field]);

		if(!$result)
		{
			self::$error[$field] = sprintf(self::$err_t['numeric'],self::$label[$field]);
		}

		return $result;
	}

	static function integer($field)
	{
		if(!isset(self::$input[$field]) || (string)self::$input[$field] === '') return TRUE;

		$result = (bool) preg_match('/^[\-+]?[0-9]+$/', self::$input[$field]);

		if(!$result)
		{
			self::$error[$field] = sprintf(self::$err_t['integer'],self::$label[$field]);
		}

		return $result;
	}

	static function decimal($field)
	{
		if(!isset(self::$input[$field]) || (string)self::$input[$field] === '') return TRUE;

		$result = (bool) preg_match('/^[\-+]?[0-9]+\.[0-9]+$/', self::$input[$field]);

		if(!$result)
		{
			self::$error[$field] = sprintf(self::$err_t['decimal'],self::$label[$field]);
		}

		return $result;
	}

	static function is_natural($field)
	{
		if(!isset(self::$input[$field]) || (string)self::$input[$field] === '') return TRUE;

		$result = (bool) preg_match( '/^[0-9]+$/', self::$input[$field]);

		if(!$result)
		{
			self::$error[$field] = sprintf(self::$err_t['is_natural'],self::$label[$field]);
		}

		return $result;
	}

	static function is_natural_no_zero($field)
	{
		if(!isset(self::$input[$field]) || (string)self::$input[$field] === '') return TRUE;

		$result = (bool)preg_match( '/^[0-9]+$/', self::$input[$field]);

		if(self::$input[$field] == 0) $result = FALSE;

		if(!$result)
		{
			self::$error[$field] = sprintf(self::$err_t['is_natural_no_zero'],self::$label[$field]);
		}

		return $result;
	}

	static function valid_base64($field)
	{
		if(!isset(self::$input[$field]) || (string)self::$input[$field] === '') return TRUE;

		$result = (bool) ! preg_match('/[^a-zA-Z0-9\/\+=]/', self::$input[$field]);

		if(!$result)
		{
			self::$error[$field] = sprintf(self::$err_t['valid_base64'],self::$label[$field]);
		}

		return $result;
	}

	static function valid_safe_base64($field)
	{
		if(!isset(self::$input[$field]) || (string)self::$input[$field] === '') return TRUE;

		$result = (bool) ! preg_match('/[^a-zA-Z0-9\-_=]/', self::$input[$field]);

		if(!$result)
		{
			self::$error[$field] = sprintf(self::$err_t['valid_safe_base64'],self::$label[$field]);
		}

		return $result;
	}

	static function valid_url($field)
	{
		if(!isset(self::$input[$field]) || (string)self::$input[$field] === '') return TRUE;

		$result = (bool) preg_match('/^http(s)?:\/\/[_a-zA-Z0-9-]+\.(.+?)$/', self::$input[$field]);

		if(!$result)
		{
			self::$error[$field] = sprintf(self::$err_t['valid_url'],self::$label[$field]);
		}

		return $result;
	}

	static function is_available_url($field,$base = '')
	{
		if(!isset(self::$input[$field]) || (string)self::$input[$field] === '') return TRUE;

		$result = sure_url(self::$input[$field],$base);

		if(!$result)
		{
			self::$error[$field] = sprintf(self::$err_t['is_available_url'],self::$label[$field]);
		}

		return $result;
	}

	static function is_image_url($field,$base = '')
	{
		if(!isset(self::$input[$field]) || (string)self::$input[$field] === '') return TRUE;

		$result = (bool)size_img(self::$input[$label],$base);

		if(!$result)
		{
			self::$error[$field] = sprintf(self::$err_t['is_image_url'],self::$label[$field]);
		}

		return $result;
	}
}

?>