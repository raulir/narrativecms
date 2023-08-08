<?php 

use Seld\JsonLint\JsonParser;

// don't do slow parse for error location when in live
// if (!empty($GLOBALS['config']['environment'])){

	include_once('system/vendor/jsonlint/JsonParser.php');
	include_once('system/vendor/jsonlint/Lexer.php');
	include_once('system/vendor/jsonlint/Undefined.php');
	include_once('system/vendor/jsonlint/ParsingException.php');

// }

function set_status_header($code = 200, $text = '')	{
	$stati = array(
			200	=> 'OK',
			201	=> 'Created',
			202	=> 'Accepted',
			203	=> 'Non-Authoritative Information',
			204	=> 'No Content',
			205	=> 'Reset Content',
			206	=> 'Partial Content',

			300	=> 'Multiple Choices',
			301	=> 'Moved Permanently',
			302	=> 'Found',
			304	=> 'Not Modified',
			305	=> 'Use Proxy',
			307	=> 'Temporary Redirect',

			400	=> 'Bad Request',
			401	=> 'Unauthorized',
			403	=> 'Forbidden',
			404	=> 'Not Found',
			405	=> 'Method Not Allowed',
			406	=> 'Not Acceptable',
			407	=> 'Proxy Authentication Required',
			408	=> 'Request Timeout',
			409	=> 'Conflict',
			410	=> 'Gone',
			411	=> 'Length Required',
			412	=> 'Precondition Failed',
			413	=> 'Request Entity Too Large',
			414	=> 'Request-URI Too Long',
			415	=> 'Unsupported Media Type',
			416	=> 'Requested Range Not Satisfiable',
			417	=> 'Expectation Failed',

			500	=> 'Internal Server Error',
			501	=> 'Not Implemented',
			502	=> 'Bad Gateway',
			503	=> 'Service Unavailable',
			504	=> 'Gateway Timeout',
			505	=> 'HTTP Version Not Supported'
	);

	if ($code == '' OR ! is_numeric($code)){
		_html_error('Status codes must be numeric');
	}

	if (isset($stati[$code]) AND $text == ''){
		$text = $stati[$code];
	}

	if ($text == ''){
		_html_error('No status text available. Please check your status code number or supply your own message text.');
	}

	$server_protocol = (isset($_SERVER['SERVER_PROTOCOL'])) ? $_SERVER['SERVER_PROTOCOL'] : FALSE;

	if (substr(php_sapi_name(), 0, 3) == 'cgi')
	{
		header("Status: {$code} {$text}", TRUE);
	}
	elseif ($server_protocol == 'HTTP/1.1' OR $server_protocol == 'HTTP/1.0')
	{
		header($server_protocol." {$code} {$text}", TRUE, $code);
	}
	else
	{
		header("HTTP/1.1 {$code} {$text}", TRUE, $code);
	}
}
	
function cms_json_decode($json, $filename = 'json'){
	
	if(empty($GLOBALS['config']['base_path'])){
		$directory = $GLOBALS['working_directory'];
		$pre_config = 1;
	} else {
		$directory = $GLOBALS['config']['base_path'];
		$pre_config = 0;
	}

	$return = json_decode($json, true);
	if( json_last_error() ){
		if (empty($GLOBALS['config']['environment']) && !$pre_config){
				
			_html_error('Problem loading json: '.json_last_error_msg().' in '.str_replace($directory, '', $filename));
		
		} else {

			$parser = new JsonParser();
			
			$result = $parser->lint($json, JsonParser::DETECT_KEY_CONFLICTS);
				
			if ($result !== null){
				
				$message_lines = explode("\n", $result->getMessage());
				
				_html_error('Problem loading json: '.json_last_error_msg().' in '.str_replace($directory, '', $filename).
						':'.$result->getDetails()['loc']['first_line'].':'.$result->getDetails()['loc']['first_column'].
						' near "<b>'.htmlspecialchars($message_lines[1], ENT_SUBSTITUTE).'</b>"');

			}

		}
	}
		
	return $return;

}
