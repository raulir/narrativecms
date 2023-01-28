<?php 

use Seld\JsonLint\JsonParser;

// don't do slow parse for error location when in live
if (!empty($GLOBALS['config']['environment'])){

	include_once('system/vendor/jsonlint/JsonParser.php');
	include_once('system/vendor/jsonlint/Lexer.php');
	include_once('system/vendor/jsonlint/Undefined.php');
	include_once('system/vendor/jsonlint/ParsingException.php');

}

function cms_json_decode($json, $filename = 'json'){

	$return = json_decode($json, true);
	
	if( json_last_error() ){
		if (empty($GLOBALS['config']['environment'])){
			
			_html_error('Problem loading json: '.json_last_error_msg().' in '.str_replace($GLOBALS['config']['base_path'], '', $filename));
		
		} else {
				
			$parser = new JsonParser();
			
			$result = $parser->lint($json, JsonParser::DETECT_KEY_CONFLICTS);
				
			if ($result !== null){
				
				$message_lines = explode("\n", $result->getMessage());
				
				_html_error('Problem loading json: '.json_last_error_msg().' in '.str_replace($GLOBALS['config']['base_path'], '', $filename).
						':'.$result->getDetails()['loc']['first_line'].':'.$result->getDetails()['loc']['first_column'].
						' near "<b>'.htmlspecialchars($message_lines[1], ENT_SUBSTITUTE).'</b>"');
				
			}

		}
	}
		
	return $return;

}
