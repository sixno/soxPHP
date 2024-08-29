<?php namespace sox\sdk\com;

class str {
	static $str1;
	static $str2;
	static $c;

	static function getSimilar($str1, $str2) {
		$len1 = strlen($str1);
		$len2 = strlen($str2);var_dump(self::getLCS($str1, $str2, $len1, $len2));
		$len = strlen(self::getLCS($str1, $str2, $len1, $len2));
		return $len * 2 / ($len1 + $len2);
	}

	static function getLCS($str1, $str2, $len1 = 0, $len2 = 0) {
		self::$str1 = $str1;
		self::$str2 = $str2;
		if ($len1 == 0) $len1 = strlen($str1);
		if ($len2 == 0) $len2 = strlen($str2);
		self::initC($len1, $len2);
		return self::printLCS(self::$c, $len1 - 1, $len2 - 1);
	}

	static function initC($len1, $len2) {
		for ($i = 0; $i < $len1; $i++) self::$c[$i][0] = 0;
		for ($j = 0; $j < $len2; $j++) self::$c[0][$j] = 0;
		for ($i = 1; $i < $len1; $i++) {
			for ($j = 1; $j < $len2; $j++) {
				if (self::$str1[$i] == self::$str2[$j]) {
					self::$c[$i][$j] = self::$c[$i - 1][$j - 1] + 1;
				} else if (self::$c[$i - 1][$j] >= self::$c[$i][$j - 1]) {
					self::$c[$i][$j] = self::$c[$i - 1][$j];
				} else {
					self::$c[$i][$j] = self::$c[$i][$j - 1];
				}
			}
		}
	}

	static function printLCS($c, $i, $j) {
		if ($i == 0 || $j == 0) {
			if (self::$str1[$i] == self::$str2[$j]) return self::$str2[$j];

			return "";
		}

		if (self::$str1[$i] == self::$str2[$j]) {
			return self::printLCS(self::$c, $i - 1, $j - 1).self::$str2[$j];
		} else if (self::$c[$i - 1][$j] >= self::$c[$i][$j - 1]) {
			return self::printLCS(self::$c, $i - 1, $j);
		} else {
			return self::printLCS(self::$c, $i, $j - 1);
		}
	}
}