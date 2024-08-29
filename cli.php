<?php

use \sox\sdk\com\ini;
use \sox\sdk\com\db;

date_default_timezone_set("Asia/Shanghai");

if(php_sapi_name() != 'cli' && !defined('STDIN')) exit('it can not be run without cli');

// exec
$exec = '';

// global loader

require 'sdk/common.php';

// user function

// go
if (empty($exec)) {
	$para = array_slice($_SERVER['argv'], 1);
} else {
	$para = explode(' ', $exec);
}

$func = array_shift($para);

if (empty($func)) exit("cmd not found\r\n");

if (function_exists('\\sox\\'.$func)) {
	call_user_func_array('\\sox\\'.$func, $para);
} else {
	exit("function not found\r\n");
}