<?php require 'sdk/core.php';

use \sox\sdk\com\html;

date_default_timezone_set("Asia/Shanghai");

set_env('err', function($content) {
	html::__503($content);
});

html::__workon('index.php', 'html', '', 'index', TRUE);