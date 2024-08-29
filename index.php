<?php

use \sox\sdk\com\html;

date_default_timezone_set("Asia/Shanghai");

require 'sdk/common.php';

html::__workon('index.php', 'html', '', 'index', TRUE);