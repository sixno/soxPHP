<?php namespace sox\sdk\com;

use \sox\com\model\task_m;

class cli {
	static $log_file = '';
	static $log_line = 0;
	static $log_size = 1000;

	static $task_id = '';
	static $pattern = '';

	static function color($code = '') {
		// 因为开发环境（WINNT）经常使用sublime 2控制台（不支持字符颜色控制）执行代码测试，故使用该判断屏蔽颜色输出控制
		if (PHP_OS != 'Linux') return '';

		// 若使用日志文件，屏蔽颜色输出控制
		if (!empty(self::$log_file)) return '';

		list($color, $depth) = str_split($code) + ['', 1]; // depth: 1(default) | 0

		switch ($color) {
			case 'd': $color = 0; break; // gray(dark)
			case 'r': $color = 1; break; // red
			case 'g': $color = 2; break; // green
			case 'y': $color = 3; break; // yellow
			case 'b': $color = 4; break; // blue
			case 'p': $color = 5; break; // purple
			case 'c': $color = 6; break; // cyan
			case 'l': $color = 7; break; // white(light)
			
			default: return '';
		}

		return "\033[".$depth.';3'.$color.'m';
	}

	static function output($msg = '', $br = 1, $color = '') {
		if (is_string($br)) {
			$color = $br;

			$br = 1;
		}

		if (!is_string($msg)) {
			$msg = var_export($msg, TRUE);
		}

		$color = self::color($color);

		if ($color) {
			$msg = $color.$msg."\033[0m";
		}

		for($i = 0;$i < $br;$i++) {
			$msg .= "\n";
		}

		if (empty(self::$log_file)) {
			echo $msg;
		} else {
			// getmypid() can be used to control process

			if (self::$log_line == 0) {
				file_put_contents(self::$log_file, '');
			}

			if (self::$log_line >= self::$log_size) {
				$file_lines = explode("\n", file_get_contents(self::$log_file));

				if (array_pop($file_lines) == '-- end --') {
					exit;
				}

				$splice_num = (int) ceil(self::$log_size / 2);

				array_splice($file_lines, 0, $splice_num);

				self::$log_line -= $splice_num;

				file_put_contents(self::$log_file, implode("\n", $file_lines));
			}

			file_put_contents(self::$log_file, $msg, FILE_APPEND);

			self::$log_line += count(explode("\n", trim($msg)));
		}
	}

	static function tip($tip, $br = 1, $color = '') {
		if (is_string($br)) {
			$color = $br;

			$br = 1;
		}

		$str = '';

		for($i = 0;$i < $br;$i++) {
			$str .= "\n";
		}

		$color = self::color($color);

		if (!$color) {
			$msg = '['.date('Y-m-d H:i:s').'] '.$tip.$str;
		} else {
			$msg = $color.'['.date('Y-m-d H:i:s')."]\033[0m: ".$tip.$str;
		}

		self::output($msg, 0);
	}

	static function end($err, $br = 1, $task_status = 0) {
		self::tip($err, $br, 'r');

		if (!empty(self::$task_id)) {
			task_m::set_status(cli::$task_id, $task_status);
		}

		exit;
	}

	static function msg($msg, $br = 1, $color = '') {
		if (self::$pattern == '1') return self::tip($msg, $br, $color);

		if (is_string($br)) {
			$color = $br;

			$br = 1;
		}

		$str = '';

		for($i = 0;$i < $br;$i++) {
			$str .= "\n";
		}

		$color = self::color($color);

		if (!$color) {
			$msg .= $str;
		} else {
			$msg = $color.$msg."\033[0m".$str;
		}

		self::output($msg, 0);
	}

	static function err($err, $br = 1) {
		if (self::$pattern == '1') return self::end($err, $br, 2);

		self::msg($err, $br, 'r');

		if (!empty(self::$task_id)) {
			task_m::set_status(cli::$task_id, 2);
		}

		exit;
	}

	static function set($str, $color) {
		$color = self::color($color);

		return $color ? $color.$str."\033[0m" : $str;
	}
}