<?php namespace sox\sdk\com;

use \sox\sdk\com\db;

class ini
{
	static $conf = [];

	static function get($file_name)
	{
		if(isset(self::$conf[$file_name]))
		{
			return self::$conf[$file_name];
		}
		else
		{
			self::$conf[$file_name] = parse_ini_file(__DIR__.'/../../'.(defined('SOXINI') ? SOXINI : 'com').'/ini/'.$file_name.'.ini',TRUE);

			return self::$conf[$file_name];
		}
	}

	static function get_prikey($table,$up = TRUE)
	{
		$model = new db('sys_prikey',self::get('db'));

		$prikey = (int)$model->find('auto_increment',['table_name' => $table]);

		if($prikey == 0)
		{
			$prikey = 1;

			if($up) $model->create(['table_name' => $table,'auto_increment' => 1]);
		}
		else
		{
			$prikey += 1;

			if($up) $model->increase(['table_name' => $table],'auto_increment',1);
		}

		return $prikey;
	}

	static function table_cols($file_name)
	{
		$table = self::get('db/'.$file_name);

		return $table['field'];
	}
}

?>