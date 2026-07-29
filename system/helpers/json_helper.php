<?php

/**
 * JSON decode with optional lint detail (and early-boot host config load).
 * HTTP status → error_helper; UTF-8 sanitise → string_helper.
 */

use Seld\JsonLint\JsonParser;

// don't do slow parse for error location when in live
// if (!empty($GLOBALS['config']['environment'])){

	include_once('system/vendor/jsonlint/JsonParser.php');
	include_once('system/vendor/jsonlint/Lexer.php');
	include_once('system/vendor/jsonlint/Undefined.php');
	include_once('system/vendor/jsonlint/ParsingException.php');

// }

function cms_json_decode($json, $filename = 'json'){

	if (empty($GLOBALS['config']['base_path'])){
		$directory = $GLOBALS['working_directory'] ?? '';
		$pre_config = 1;
	} else {
		$directory = $GLOBALS['config']['base_path'];
		$pre_config = 0;
	}

	$return = json_decode($json, true);
	if (json_last_error()){
		if (empty($GLOBALS['config']['environment']) && !$pre_config){

			if (function_exists('_html_error')){
				_html_error('Problem loading json: '.json_last_error_msg().' in '.str_replace($directory, '', $filename));
			}

		} else {

			$parser = new JsonParser();

			$result = $parser->lint($json, JsonParser::DETECT_KEY_CONFLICTS);

			if ($result !== null && function_exists('_html_error')){

				$message_lines = explode("\n", $result->getMessage());

				_html_error('Problem loading json: '.json_last_error_msg().' in '.str_replace($directory, '', $filename).
						':'.$result->getDetails()['loc']['first_line'].':'.$result->getDetails()['loc']['first_column'].
						' near "<b>'.htmlspecialchars($message_lines[1] ?? '', ENT_SUBSTITUTE).'</b>"');

			}

		}
	}

	return $return;

}
