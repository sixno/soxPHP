<?php

use \sox\sdk\com\api;

date_default_timezone_set("Asia/Shanghai");

define('SOXMSG', 'api_message');

require 'sdk/common.php';

function api_message($msg) {
	api::err($msg);

	api::__output();
}

api::__workon('api.php', 'api', '*');