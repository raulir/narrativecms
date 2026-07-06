<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

if ( ! function_exists('markdown_get_parsedown')){

	function markdown_get_parsedown(){

		static $parsedown = null;

		if ($parsedown === null){
			require_once $GLOBALS['config']['base_path'].'system/vendor/parsedown/Parsedown.php';
			$parsedown = new Parsedown();
			$parsedown->setSafeMode(true);
		}

		return $parsedown;

	}

}

if ( ! function_exists('markdown_image_field_filename')){

	function markdown_image_field_filename($image){

		if (is_string($image) || is_numeric($image)){
			$filename = trim((string)$image);
			if ($filename === '' || $filename === 'Array'){
				return '';
			}
			return $filename;
		}

		if (!is_array($image)){
			return '';
		}

		foreach (['filename', 'value', 'path'] as $key){

			if (!empty($image[$key]) && (is_string($image[$key]) || is_numeric($image[$key]))){
				$filename = trim((string)$image[$key]);
				if ($filename !== '' && $filename !== 'Array'){
					return $filename;
				}
			}

		}

		$parts = [];

		foreach ($image as $key => $part){

			if (!is_numeric($key) && $key !== (string)(int)$key){
				continue;
			}

			$part = trim((string)$part);

			if ($part !== '' && $part !== 'Array'){
				$parts[] = $part;
			}

		}

		if (!empty($parts)){
			return implode('/', $parts);
		}

		$last = end($image);

		if (is_string($last) || is_numeric($last)){
			$filename = trim((string)$last);
			if ($filename !== '' && $filename !== 'Array'){
				return $filename;
			}
		}

		return '';

	}

}

if ( ! function_exists('markdown_repeater_images_to_rows')){

	function markdown_repeater_images_to_rows($images){

		if (!is_array($images) || empty($images)){
			return [];
		}

		if (isset($images['id']) && is_array($images['id']) && isset($images['image']) && is_array($images['image'])){

			$rows = [];

			foreach ($images['id'] as $key => $id){

				$rows[] = [
					'id' => $id,
					'image' => $images['image'][$key] ?? '',
				];

			}

			return $rows;

		}

		return $images;

	}

}

if ( ! function_exists('markdown_lookup_image_filename')){

	function markdown_lookup_image_filename($image_id, $images_map){

		$image_id = trim((string)$image_id);

		if ($image_id === '' || empty($images_map) || !is_array($images_map)){
			return '';
		}

		if (!empty($images_map[$image_id])){
			return trim((string)$images_map[$image_id]);
		}

		return '';

	}

}

if ( ! function_exists('markdown_normalise_images')){

	function markdown_normalise_images($images){

		$map = [];

		if (!is_array($images)){
			return $map;
		}

		$images = markdown_repeater_images_to_rows($images);

		foreach ($images as $row){

			if (!is_array($row)){
				continue;
			}

			$id = trim((string)($row['id'] ?? ''));

			if ($id === ''){
				continue;
			}

			$filename = markdown_image_field_filename($row['image'] ?? '');

			if ($filename !== '' && $filename !== $id){
				$map[$id] = $filename;
			}

		}

		return $map;

	}

}

if ( ! function_exists('markdown_apply_md_filter')){

	function markdown_apply_md_filter($md_filter, $params){

		$md_filter = trim((string)$md_filter);

		if ($md_filter === '' || !is_array($params)){
			return $params;
		}

		$parts = explode('/', $md_filter, 3);

		if (count($parts) !== 3){
			return $params;
		}

		$panel = $parts[0].'/'.$parts[1];
		$method = $parts[2];
		$CI =& get_instance();
		$result = $CI->run_panel_method($panel, $method, $params);

		if (!is_array($result)){
			return $params;
		}

		return array_merge($params, $result);

	}

}

if ( ! function_exists('markdown_image_looks_like_path')){

	function markdown_image_looks_like_path($src){

		$src = trim((string)$src);

		if ($src === ''){
			return false;
		}

		if (substr($src, 0, 4) === 'http' || substr($src, 0, 1) === '/'){
			return true;
		}

		if (strpos($src, '/') !== false){
			return true;
		}

		return (bool)preg_match('/\.(png|jpe?g|gif|webp|svg|ico)$/i', $src);

	}

}

if ( ! function_exists('markdown_broken_image_url')){

	function markdown_broken_image_url(){

		return ($GLOBALS['config']['base_site'] ?? '').$GLOBALS['config']['base_url'].'modules/cms/img/cms_no_image.png';

	}

}

if ( ! function_exists('markdown_broken_image_html')){

	function markdown_broken_image_html($alt = ''){

		$src = markdown_broken_image_url();
		$alt = htmlspecialchars((string)$alt, ENT_QUOTES, 'UTF-8');

		return '<img class="markdown_image markdown_image_broken" src="'.$src.'" alt="'.$alt.'">';

	}

}

if ( ! function_exists('markdown_image_url')){

	function markdown_image_url($filename){

		$filename = trim((string)$filename);

		if ($filename === ''){
			return '';
		}

		$CI =& get_instance();
		$CI->load->helper('image_helper');

		$image_data = _i($filename, ['width' => 600, 'silent' => 1]);

		if (empty($image_data['image'])){
			return '';
		}

		if (substr($image_data['image'], 0, 4) === 'http'){
			return $image_data['image'];
		}

		return ($GLOBALS['config']['base_site'] ?? '').$GLOBALS['config']['upload_url'].$image_data['image'];

	}

}

if ( ! function_exists('markdown_resolve_img_token_url')){

	function markdown_resolve_img_token_url($image_id, $images_map){

		$image_id = trim((string)$image_id);

		if ($image_id === ''){
			return markdown_broken_image_url();
		}

		$filename = markdown_lookup_image_filename($image_id, $images_map);

		if ($filename === '' && markdown_image_looks_like_path($image_id)){
			$filename = $image_id;
		}

		if ($filename !== ''){
			$url = markdown_image_url($filename);
			if ($url !== ''){
				return $url;
			}
		}

		return markdown_broken_image_url();

	}

}

if ( ! function_exists('markdown_resolve_img_tokens')){

	function markdown_resolve_img_tokens($text, $images_map){

		$text = (string)$text;

		if ($text === '' || strpos($text, '{{img=') === false){
			return $text;
		}

		if (!is_array($images_map)){
			$images_map = [];
		}

		return preg_replace_callback('/\{\{img=([^}]+)\}\}/', function($match) use ($images_map){

			return markdown_resolve_img_token_url($match[1] ?? '', $images_map);

		}, $text);

	}

}

if ( ! function_exists('markdown_sanitise_img_tag')){

	function markdown_sanitise_img_tag($tag){

		$tag = (string)$tag;

		if (!preg_match('/\bsrc="([^"]*)"/i', $tag, $src_match)){
			return '';
		}

		$src = trim((string)$src_match[1]);

		if ($src === '' || preg_match('/^\s*javascript:/i', $src)){
			return '';
		}

		$attrs = [
			'src' => htmlspecialchars($src, ENT_QUOTES, 'UTF-8'),
		];

		foreach (['alt', 'class', 'style', 'width', 'height'] as $name){

			if (preg_match('/\b'.preg_quote($name, '/').'="([^"]*)"/i', $tag, $attr_match)){
				$attrs[$name] = htmlspecialchars($attr_match[1], ENT_QUOTES, 'UTF-8');
			}

		}

		if (empty($attrs['class'])){
			$attrs['class'] = 'markdown_image';
		} elseif (strpos($attrs['class'], 'markdown_image') === false){
			$attrs['class'] .= ' markdown_image';
		}

		$html = '<img';

		foreach ($attrs as $name => $value){
			$html .= ' '.$name.'="'.$value.'"';
		}

		return $html.'>';

	}

}

if ( ! function_exists('markdown_preserve_html_img_tags')){

	function markdown_preserve_html_img_tags($text){

		$text = (string)$text;
		$stored = [];

		if ($text === '' || stripos($text, '<img') === false){
			return [$text, $stored];
		}

		$text = preg_replace_callback('/<img\b[^>]*>/i', function($match) use (&$stored){

			$tag = markdown_sanitise_img_tag($match[0]);

			if ($tag === ''){
				return $match[0];
			}

			$index = count($stored);
			$stored[$index] = $tag;

			return '%%MDIMG'.$index.'%%';

		}, $text);

		return [$text, $stored];

	}

}

if ( ! function_exists('markdown_restore_html_img_tags')){

	function markdown_restore_html_img_tags($html, $stored){

		$html = (string)$html;

		if ($html === '' || empty($stored) || !is_array($stored)){
			return $html;
		}

		foreach ($stored as $index => $tag){
			$html = str_replace('%%MDIMG'.$index.'%%', $tag, $html);
			$html = str_replace(htmlspecialchars('%%MDIMG'.$index.'%%', ENT_QUOTES, 'UTF-8'), $tag, $html);
		}

		return $html;

	}

}

if ( ! function_exists('markdown_resolve_image_tags')){

	function markdown_resolve_image_tags($html, $images_map){

		$html = (string)$html;

		if ($html === ''){
			return $html;
		}

		if (!is_array($images_map)){
			$images_map = [];
		}

		return preg_replace_callback('/<img\b[^>]*>/i', function($match) use ($images_map){

			$tag = $match[0];

			if (!preg_match('/\bsrc="([^"]*)"/i', $tag, $src_match)){
				return $tag;
			}

			$src = trim((string)$src_match[1]);

			if ($src === ''){
				return $tag;
			}

			$alt = '';

			if (preg_match('/\balt="([^"]*)"/i', $tag, $alt_match)){
				$alt = $alt_match[1];
			}

			$filename = markdown_lookup_image_filename($src, $images_map);

			if ($filename === '' && markdown_image_looks_like_path($src)){
				$filename = $src;
			}

			if ($filename !== ''){
				$resolved = markdown_image_html($filename, $alt);
				return $resolved !== '' ? $resolved : $tag;
			}

			return markdown_broken_image_html($alt);

		}, $html);

	}

}

if ( ! function_exists('markdown_image_html')){

	function markdown_image_html($filename, $alt = ''){

		$src = markdown_image_url($filename);

		if ($src === ''){
			return '';
		}

		$alt = htmlspecialchars((string)$alt, ENT_QUOTES, 'UTF-8');

		return '<img class="markdown_image" src="'.$src.'" alt="'.$alt.'">';

	}

}

if ( ! function_exists('markdown_render_body')){

	function markdown_render_body($text, $images = []){

		$text = trim((string)$text);

		if ($text === ''){
			return '';
		}

		$images_map = markdown_normalise_images($images);
		$text = markdown_resolve_img_tokens($text, $images_map);

		list($text, $preserved_img_tags) = markdown_preserve_html_img_tags($text);

		$html = markdown_get_parsedown()->text($text);
		$html = markdown_resolve_image_tags($html, $images_map);
		$html = markdown_restore_html_img_tags($html, $preserved_img_tags);

		return $html;

	}

}