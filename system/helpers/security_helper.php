<?php defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * XSS / filename sanitizers (from CI Security class, procedural).
 * CSRF lives in modules/basic/models/basic_csrf_model.php when needed.
 */

function xss_clean($str, $is_image = false){

	if (is_array($str)){
		foreach ($str as $key => $val){
			$str[$key] = xss_clean($val, $is_image);
		}
		return $str;
	}

	$str = remove_invisible_characters($str);
	$str = _xss_validate_entities($str);
	$str = rawurldecode($str);

	$str = preg_replace_callback("/[a-z]+=([\'\"]).*?\\1/si", '_xss_convert_attribute', $str);
	$str = preg_replace_callback("/<\w+.*?(?=>|<|$)/si", '_xss_decode_entity', $str);

	$str = remove_invisible_characters($str);

	if (strpos($str, "\t") !== false){
		$str = str_replace("\t", ' ', $str);
	}

	$converted_string = $str;
	$str = _xss_do_never_allowed($str);

	if ($is_image === true){
		$str = preg_replace('/<\?(php)/i', "&lt;?\\1", $str);
	} else {
		$str = str_replace(['<?', '?>'], ['&lt;?', '?&gt;'], $str);
	}

	$words = [
		'javascript', 'expression', 'vbscript', 'script', 'base64',
		'applet', 'alert', 'document', 'write', 'cookie', 'window',
	];

	foreach ($words as $word){
		$temp = '';
		$wordlen = strlen($word);
		for ($i = 0; $i < $wordlen; $i++){
			$temp .= substr($word, $i, 1)."\s*";
		}
		$str = preg_replace_callback('#('.substr($temp, 0, -3).')(\W)#is', '_xss_compact_exploded_words', $str);
	}

	do {
		$original = $str;

		if (preg_match("/<a/i", $str)){
			$str = preg_replace_callback("#<a\s+([^>]*?)(>|$)#si", '_xss_js_link_removal', $str);
		}

		if (preg_match("/<img/i", $str)){
			$str = preg_replace_callback("#<img\s+([^>]*?)(\s?/?>|$)#si", '_xss_js_img_removal', $str);
		}

		if (preg_match("/script/i", $str) || preg_match("/xss/i", $str)){
			$str = preg_replace("#<(/*)(script|xss)(.*?)\>#si", '[removed]', $str);
		}
	} while ($original != $str);

	$str = _xss_remove_evil_attributes($str, $is_image);

	$naughty = 'alert|applet|audio|basefont|base|behavior|bgsound|blink|body|embed|expression|form|frameset|frame|head|html|ilayer|iframe|input|isindex|layer|link|meta|object|plaintext|style|script|textarea|title|video|xml|xss';
	$str = preg_replace_callback('#<(/*\s*)('.$naughty.')([^><]*)([><]*)#is', '_xss_sanitize_naughty_html', $str);

	$str = preg_replace('#(alert|cmd|passthru|eval|exec|expression|system|fopen|fsockopen|file|file_get_contents|readfile|unlink)(\s*)\((.*?)\)#si',
		"\\1\\2&#40;\\3&#41;", $str);

	$str = _xss_do_never_allowed($str);

	if ($is_image === true){
		return ($str == $converted_string) ? true : false;
	}

	return $str;

}

function entity_decode($str, $charset = 'UTF-8'){

	if (stristr($str, '&') === false){
		return $str;
	}

	$str = html_entity_decode($str, ENT_COMPAT, $charset);
	$str = preg_replace_callback('~&#x(0*[0-9a-f]{2,5})~i', function($m){
		return chr(hexdec($m[1]));
	}, $str);
	return preg_replace_callback('~&#([0-9]{2,4})~', function($m){
		return chr((int)$m[1]);
	}, $str);

}

function sanitize_filename($str, $relative_path = false){

	$bad = [
		"../", "<!--", "-->", "<", ">", "'", '"', '&', '$', '#',
		'{', '}', '[', ']', '=', ';', '?',
		"%20", "%22", "%3c", "%253c", "%3e", "%0e",
		"%28", "%29", "%2528", "%26", "%24", "%3f", "%3b", "%3d",
	];

	if ( ! $relative_path){
		$bad[] = './';
		$bad[] = '/';
	}

	$str = remove_invisible_characters($str, false);
	return stripslashes(str_replace($bad, '', $str));

}

// --- internal XSS helpers ---

function _xss_hash(){

	static $hash = '';
	if ($hash === ''){
		$hash = md5(uniqid((string)mt_rand(), true));
	}
	return $hash;

}

function _xss_compact_exploded_words($matches){

	return preg_replace('/\s+/s', '', $matches[1]).$matches[2];

}

function _xss_remove_evil_attributes($str, $is_image){

	$evil_attributes = ['(?<!\w)on\w*', 'style', 'xmlns', 'formaction'];

	if ($is_image === true){
		unset($evil_attributes[array_search('xmlns', $evil_attributes)]);
	}

	do {
		$count = 0;
		$attribs = [];

		preg_match_all('/('.implode('|', $evil_attributes).')\s*=\s*(\042|\047)([^\\2]*?)(\\2)/is', $str, $matches, PREG_SET_ORDER);
		foreach ($matches as $attr){
			$attribs[] = preg_quote($attr[0], '/');
		}

		preg_match_all('/('.implode('|', $evil_attributes).')\s*=\s*([^\s>]*)/is', $str, $matches, PREG_SET_ORDER);
		foreach ($matches as $attr){
			$attribs[] = preg_quote($attr[0], '/');
		}

		if (count($attribs) > 0){
			$str = preg_replace('/(<?)(\/?[^><]+?)([^A-Za-z<>\-])(.*?)('.implode('|', $attribs).')(.*?)([\s><]?)([><]*)/i',
				'$1$2 $4$6$7$8', $str, -1, $count);
		}
	} while ($count);

	return $str;

}

function _xss_sanitize_naughty_html($matches){

	$str = '&lt;'.$matches[1].$matches[2].$matches[3];
	$str .= str_replace(['>', '<'], ['&gt;', '&lt;'], $matches[4]);
	return $str;

}

function _xss_js_link_removal($match){

	return str_replace(
		$match[1],
		preg_replace(
			'#href=.*?(alert\(|alert&\#40;|javascript\:|livescript\:|mocha\:|charset\=|window\.|document\.|\.cookie|<script|<xss|data\s*:)#si',
			'',
			_xss_filter_attributes(str_replace(['<', '>'], '', $match[1]))
		),
		$match[0]
	);

}

function _xss_js_img_removal($match){

	return str_replace(
		$match[1],
		preg_replace(
			'#src=.*?(alert\(|alert&\#40;|javascript\:|livescript\:|mocha\:|charset\=|window\.|document\.|\.cookie|<script|<xss|base64\s*,)#si',
			'',
			_xss_filter_attributes(str_replace(['<', '>'], '', $match[1]))
		),
		$match[0]
	);

}

function _xss_convert_attribute($match){

	return str_replace(['>', '<', '\\'], ['&gt;', '&lt;', '\\\\'], $match[0]);

}

function _xss_filter_attributes($str){

	$out = '';
	if (preg_match_all('#\s*[a-z\-]+\s*=\s*(\042|\047)([^\\1]*?)\\1#is', $str, $matches)){
		foreach ($matches[0] as $match){
			$out .= preg_replace("#/\*.*?\*/#s", '', $match);
		}
	}
	return $out;

}

function _xss_decode_entity($match){

	$charset = $GLOBALS['config']['system']['charset'] ?? 'UTF-8';
	return entity_decode($match[0], strtoupper($charset));

}

function _xss_validate_entities($str){

	$str = preg_replace('|\&([a-z\_0-9\-]+)\=([a-z\_0-9\-]+)|i', _xss_hash()."\\1=\\2", $str);
	$str = preg_replace('#(&\#?[0-9a-z]{2,})([\x00-\x20])*;?#i', "\\1;\\2", $str);
	$str = preg_replace('#(&\#x?)([0-9A-F]+);?#i', "\\1\\2;", $str);
	return str_replace(_xss_hash(), '&', $str);

}

function _xss_do_never_allowed($str){

	$never_str = [
		'document.cookie' => '[removed]',
		'document.write' => '[removed]',
		'.parentNode' => '[removed]',
		'.innerHTML' => '[removed]',
		'window.location' => '[removed]',
		'-moz-binding' => '[removed]',
		'<!--' => '&lt;!--',
		'-->' => '--&gt;',
		'<![CDATA[' => '&lt;![CDATA[',
		'<comment>' => '&lt;comment&gt;',
	];

	$never_regex = [
		'javascript\s*:',
		'expression\s*(\(|&\#40;)',
		'vbscript\s*:',
		'Redirect\s+302',
		"([\"'])?data\s*:[^\\1]*?base64[^\\1]*?,[^\\1]*?\\1?",
	];

	$str = str_replace(array_keys($never_str), $never_str, $str);
	foreach ($never_regex as $regex){
		$str = preg_replace('#'.$regex.'#is', '[removed]', $str);
	}
	return $str;

}
