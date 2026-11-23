<?php

namespace basic;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Browser console lines to log/console.log, for debugging a device. Definition access basic_base:
 * it runs only for a signed-in CMS user, elsewhere it is skipped quietly.
 */
class console_log extends \Controller {

	function panel_action($params){

		if (($params['cms_do'] ?? '') === 'console_log_write'){

			// One line per message, capped
			$message = preg_replace('/[\r\n]+/', ' ', (string)($params['message'] ?? ''));
			$message = mb_substr(cms_utf8_string($message), 0, 2000);

			file_put_contents(cms_path('log').'console.log', "\n".date('Y-m-d H:i:s').' '.$message, FILE_APPEND | LOCK_EX);

		}

		return $params;

	}

}
