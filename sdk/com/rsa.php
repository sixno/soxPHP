<?php namespace sox\sdk\com;

use \sox\sdk\com\ini;

class rsa
{
	static $conf_file = __DIR__.'/../../com/ini/rsa.ini';

	static $key = [];

	static function set($pair)
	{
		if(empty($pair)) return FALSE;

		$conf = ini::get('rsa');

		if(empty($conf[$pair])) return FALSE;

		self::$key = $conf[$pair];

		return TRUE;
	}

	static function encrypt($dec,$type = 'public',$safe = FALSE)
	{
		if($dec == '') return '';

		if(is_bool($type))
		{
			$safe = $type;
			$type = 'public';
		}

		if(strpos($type,':'))
		{
			list($pair,$type) = explode(':',$type);
		}

		if(isset($pair))
		{
			self::set($pair);
		}

		if(empty(self::$key[$type.'_key']))
		{
			self::set('primary');
		}

		if(empty(self::$key[$type.'_key'])) return '';

		$enc = '';

		switch ($type)
		{
			case 'public':
				$public_key = openssl_pkey_get_public(self::$key['public_key']);

				openssl_public_encrypt($dec,$enc,$public_key);
				break;
			
			case 'private':
				$private_key = openssl_pkey_get_private(self::$key['private_key']);

				openssl_private_encrypt($dec,$enc,$private_key);
				break;
			
			default:
				return '';
				break;
		}

		if($enc != '')
		{
			$enc = base64_encode($enc);

			if($safe) $enc = str_replace(['+','/'],['-','_'],$enc);
		}

		return $enc;
	}

	static function decrypt($enc,$type = 'private',$safe = FALSE)
	{
		if($enc == '') return '';

		if(is_bool($type))
		{
			$safe = $type;
			$type = 'private';
		}

		if(strpos($enc,':'))
		{
			list($pair,$enc) = explode(':',$enc);
		}

		if(isset($pair))
		{
			self::set($pair);
		}

		if(empty(self::$key[$type.'_key']))
		{
			self::set('primary');
		}

		if(empty(self::$key[$type.'_key'])) return '';

		if($safe) $enc = str_replace(['-','_'],['+','/'],$enc);

		$enc = base64_decode($enc);
		$dec = '';

		switch($type)
		{
			case 'private':
				$private_key = openssl_pkey_get_private(self::$key['private_key']);

				openssl_private_decrypt($enc,$dec,$private_key);
				break;
			
			case 'public':
				$public_key = openssl_pkey_get_public(self::$key['public_key']);

				openssl_public_decrypt($enc,$dec,$public_key);
				break;
			
			default:
				return '';
				break;
		}

		return $dec;
	}
}

?>