<?php namespace sox;

date_default_timezone_set("Asia/Shanghai");

require 'sdk/common.php';

class autoloader
{
	static function load_by_namespace($name)
	{
		$class_path = str_replace('\\','/',$name);

		if(strpos($name,'sox\\') === 0)
		{
			$class_file = __DIR__.substr($class_path,3).'.php';
		}
		else
		{
			$class_file = __DIR__.'/sdk/lib/'.$class_path.'.php';
		}

		if(isset($class_file) && is_file($class_file)) require($class_file);
	}
}

spl_autoload_register('\sox\autoloader::load_by_namespace');

\sox\sdk\com\html::__workon('index.php','html','','index',TRUE);

?>