<?php

namespace PhpOffice;

class Autoloader
{
	public static function loadByNamespace($name)
	{
		$class_path = str_replace('\\',DIRECTORY_SEPARATOR,$name);

		if(strpos($name,'PhpOffice\\') === 0)
		{
			$class_file = __DIR__.substr($class_path,strlen('PhpOffice')).'.php';
		}

		if(isset($class_file) && is_file($class_file))
		{
			require_once $class_file;

			return class_exists($name,FALSE);
		}

		return FALSE;
	}
}

spl_autoload_register('\PhpOffice\Autoloader::loadByNamespace');

?>