<?php namespace sox;

use \sox\sdk\com\ini;
use \sox\sdk\com\db;

date_default_timezone_set("Asia/Shanghai");

if(php_sapi_name() != 'cli' && !defined('STDIN')) exit('it can not be run without cli');

// exec
$exec = '';

// global loader

require 'sdk/common.php';

class autoloader
{
	static function load_by_namespace($name)
	{
		$class_path = str_replace('\\',DIRECTORY_SEPARATOR,$name);

		if(strpos($name,'sox\\') === 0)
		{
			$class_file = __DIR__.substr($class_path,3).'.php';
		}
		else
		{
			$class_file = __DIR__.DIRECTORY_SEPARATOR.'sdk'.DIRECTORY_SEPARATOR.'lib'.DIRECTORY_SEPARATOR.$class_path.'.php';
		}

		if(isset($class_file) && is_file($class_file)) require($class_file);
	}
}

spl_autoload_register('\sox\autoloader::load_by_namespace');

// user function

// go
if(empty($exec))
{
	$para = array_slice($_SERVER['argv'],1);
}
else
{
	$para = explode(' ',$exec);
}

$func = array_shift($para);

if(empty($func)) exit("cmd not found\r\n");

if(function_exists('\\sox\\'.$func))
{
	call_user_func_array('\\sox\\'.$func,$para);
}
else
{
	exit("function not found\r\n");
}

?>