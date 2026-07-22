<?php

namespace cms;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

// optimise images in non-session way
// Do not use FILTER_SANITIZE_STRING (deprecated / can corrupt paths on PHP 8+)

	if (!is_array($_POST) || empty($_POST['do'])){
		$_POST = array_merge(is_array($_POST) ? $_POST : [], $_REQUEST);
	}

	if (!empty($_POST['do']) && $_POST['do'] == 'resize'){
		
		$name = isset($_POST['name']) ? (string)$_POST['name'] : '';

		if (!empty($name) && pathinfo($name, PATHINFO_EXTENSION) === 'gif'){

			include_once($GLOBALS['config']['base_path'].'system/core/Common.php');
			include($GLOBALS['config']['base_path'].'system/core/controller.php');
			$ci = new \Controller();
			$ci->load->model('cms/cms_image_model');
			$name = $ci->cms_image_model->normalise_gif_original($name);

		}
		
		$output = !empty($_POST['output']) ? (string)$_POST['output'] : '';
		$width = !empty($_POST['width']) ? $_POST['width'] : '';
		
		$image_dir = pathinfo($name, PATHINFO_DIRNAME);
		$image_name = pathinfo($name, PATHINFO_FILENAME);
		
		if (empty($output)){
			$output = pathinfo($name, PATHINFO_EXTENSION);
		}
		
		if (empty($width)){
			$width = round($GLOBALS['config']['rem_px'] * (!empty($GLOBALS['config']['images_textarea']) ? $GLOBALS['config']['images_textarea'] : 0.5));
			if (empty($width) || $width > 1000) {
				$width = 1000;
			}
		}
		$width = (int)$width;
		
		$target_name = $image_dir.'/_'.$image_name.'.'.$width.'.'.$output;
		$target_path = $GLOBALS['config']['upload_path'].$target_name;
		$target_url = $GLOBALS['config']['upload_url'].$target_name;

		include_once($GLOBALS['config']['base_path'].'system/helpers/image_helper.php');
		
		// Already optimised — return derivative (+ optional ?v= when force_image_download)
		if ($name !== '' && file_exists($target_path) && !is_dir($target_path)){
			$src = $target_url;
			if (function_exists('_image_url_with_version')){
				$src = _image_url_with_version($src, $name, $target_path);
			}
			print(json_encode(['result' => [
					'src' => $src,
					'filename' => $target_name,
			]], JSON_PRETTY_PRINT));
			die();
		}
		
		// lock the file
		$lockfile = $GLOBALS['config']['base_path'].'cache/image_resize_lock.json';
		if (file_exists($lockfile)){
			$locked = json_decode(file_get_contents($lockfile), true);
		} else {
			$locked = [];
		}
		if (!is_array($locked)){
			$locked = [];
		}
		
		if (in_array($target_url, $locked)){
			
			$time_locked = array_search($target_url, $locked);
			$parts = explode('|', (string)$time_locked);
			$time_was = (int)($parts[0] ?? 0);
			
			if ((time() - $time_was) > 300){
				
				unset($locked[$time_locked]);
				
			} else {

				// Still building — return busy so client keeps B/W original and re-queues
				// (do not return original as final optimised src)
				if (file_exists($target_path) && !is_dir($target_path)){
					$src = $target_url;
					if (function_exists('_image_url_with_version')){
						$src = _image_url_with_version($src, $name, $target_path);
					}
					print(json_encode(['result' => [
							'src' => $src,
							'filename' => $target_name,
					]], JSON_PRETTY_PRINT));
					die();
				}

				print(json_encode(['result' => [
						'busy' => 1,
						'src' => '',
						'filename' => $target_name,
				]], JSON_PRETTY_PRINT));
				die();
			
			}

		}
		
		// lock
		$time = time();
		$locked[$time.'|'.$target_url] = $target_url;
		file_put_contents($lockfile, json_encode($locked, JSON_PRETTY_PRINT));
		
		// load helper
		include($GLOBALS['config']['base_path'].'system/helpers/image_optimiser_helper.php');
		
		$image_data = _iw($name, ['width' => $width, 'output' => $output, ]);

		// unlock before response
		unset($locked[$time.'|'.$target_url]);
		file_put_contents($lockfile, json_encode($locked, JSON_PRETTY_PRINT));

		// Write/permission failure: derivative still missing — client must not re-queue this page load
		clearstatcache(true, $target_path);
		if (!file_exists($target_path) || is_dir($target_path)){
			print(json_encode(['result' => [
					'failed' => 1,
					'src' => '',
					'filename' => $target_name,
			]], JSON_PRETTY_PRINT));
			die();
		}

		$src = $GLOBALS['config']['upload_url'].$image_data['image'];
		$src_path = $GLOBALS['config']['upload_path'].$image_data['image'];
		// Version optimised path by parent cms_image hash when force_image_download is on
		if (function_exists('_image_url_with_version')){
			$src = _image_url_with_version($src, $name, $src_path);
		}
		
		print(json_encode(['result' => [
				'src' => $src,
				'filename' => $image_data['image'],
				'data' => $image_data,
		]], JSON_PRETTY_PRINT));
		
		die();

	}
