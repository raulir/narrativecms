<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

if (!class_exists('CI_Log')){

class CI_Log {

	protected $_date_fmt	= 'Y-m-d H:i:s';
	protected $_enabled	= TRUE;
	protected $_levels	= array('ERROR' => '1', 'DEBUG' => '2',  'INFO' => '3', 'ALL' => '4');

	public function write_log($level = 'error', $msg = '', $php_error = FALSE)
	{
		if ($this->_enabled === FALSE)
		{
			return FALSE;
		}

		$level = strtoupper($level);

		if (empty($GLOBALS['config']['errors_log'])){
			$GLOBALS['config']['errors_log'] = 'cache/errors';
		}

		$filepath = $GLOBALS['config']['base_path'].$GLOBALS['config']['errors_log'].'.txt';
		$message  = '';

		if ( ! file_exists($filepath))
		{
			$message .= "<"."?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed'); ?".">\n\n";
		}

		if ( ! $fp = @fopen($filepath, 'w'))
		{
			return FALSE;
		}

		$message .= $level.' '.(($level == 'INFO') ? ' -' : '-').' '.date($this->_date_fmt). ' --> '.$msg."\n";

		flock($fp, LOCK_EX);
		fwrite($fp, $message);
		flock($fp, LOCK_UN);
		fclose($fp);

		return TRUE;
	}

}

}
