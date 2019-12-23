<?php namespace sox\sdk\com;

class check
{
	static function do(&$data,$rule)
	{
		//

		foreach($rule as $key => $val)
		{
			if(empty($val['alias']))
			{
				$label[$val['field']] = $val['field'];
			}
			else
			{
				$label[$val['field']] = $val['alias'];
			}

			$this->get_data($val);
		}

		foreach($rule as $key => $val)
		{
			if(empty($val['rules'])) continue;

			$validate_func_array = explode('|',$val['rules']);

			foreach($validate_func_array as $func)
			{
				$para_posi = strpos($func,'[');
				$func_para = array();
				if($para_posi > 0)
				{
					$real_func = substr($func,0,$para_posi);
					$func_para = explode('][',substr($func,$para_posi+1,-1));
				}
				else
				{
					$real_func = $func;
				}

				if(isset($this->enter_data[$val['field']]))
				{
					array_unshift($func_para,$this->enter_data[$val['field']]);
				}
				else
				{
					array_unshift($func_para,FALSE);
				}

				if($real_func == 'needed')
				{
					if(!isset($this->enter_data[$val['field']])) break;

					if($this->enter_data[$val['field']] !== FALSE)
					{
						$real_func = 'required';
					}
					else
					{
						unset($this->enter_data[$val['field']]);

						break;
					}
				}

				if(function_exists($real_func))
				{
					if(!call_user_func_array($real_func,$func_para))
					{
						if(!isset($input_error_tpl[$real_func])) $input_error_tpl[$real_func] = 'The validation function ['.$real_func.'] has no error description';

						$error_tpl = isset($val['error'][$real_func]) ? $val['error'][$real_func] : $input_error_tpl[$real_func];
						$sec_label = $sec_label = !isset($func_para[1]) ? NULL : vo($label[$func_para[1]],$func_para[1]);

						$this->input_errs[$val['field']] = $this->wrap_start.sprintf($error_tpl,$label[$val['field']],$sec_label).$this->wrap_close;
						break;
					}
				}
				else
				{
					$this->input_errs[$val['field']] = $this->wrap_start.'The validation function ['.$real_func.'] does not exist'.$this->wrap_close;

					break;
				}
			}
		}
	}
}

?>