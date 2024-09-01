<?php require 'sdk/core.php';

use \sox\sdk\com\api;

date_default_timezone_set("Asia/Shanghai");

set_env('err', function($content) {
	api::err($content);

	api::__output();
});

api::__workon('api.php', 'api', '*');