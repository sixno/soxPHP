<?php if(php_sapi_name() != 'cli' && !defined('STDIN')) exit('it can not be run without cli');

require 'sdk/core.php';

use \sox\sdk\com\ini;
use \sox\sdk\com\db;

date_default_timezone_set("Asia/Shanghai");

// exec
$exec = '';

// ------

if (empty($exec)) {
	$para = array_slice($_SERVER['argv'], 1);
} else {
	$para = explode(' ', $exec);
}

$func = array_shift($para);

if (empty($func)) exit("cmd not set\r\n");

if (method_exists('\\sox\\cli\\'.$cli_name, $cli_func)) {
		call_user_func_array('\\sox\\cli\\'.$cli_name.'::'.$cli_func, $para);
} else {
	exit("cmd not found\r\n");
}