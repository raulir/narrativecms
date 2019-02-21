<?php

	// optimise images in non-session way
	
	$_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_STRING);

	if ($_POST['do'] == 'resize'){
		
		$name = $_POST['name'];
		
		$output = !empty($_POST['output']) ? $_POST['output'] : '';
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
		
		$target_name = $image_dir.'/_'.$image_name.'.'.$width.'.'.$output;
		$target_url = $GLOBALS['config']['upload_url'].$target_name;
		
		// lock the file
		$lockfile = $GLOBALS['config']['base_path'].'cache/image_resize_lock.json';
		if (file_exists($lockfile)){
			$locked = json_decode(file_get_contents($lockfile), true);
		} else {
			$locked = [];
		}
		
		if (in_array($target_url, $locked)){
			
			$time_locked = array_search($target_url, $locked);
			list($time_was, $file) = explode('|', $time_locked);
			
			if ((time() - $time_was) > 300){
				
				// was locked long time ago
				unset($locked[$time_locked]);
				
			} else {
				
				print(json_encode(['result' => [
						'src' => $GLOBALS['config']['upload_url'].$name, 
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
		
		print(json_encode(['result' => [
				'src' => $GLOBALS['config']['upload_url'] . $image_data['image'], 
				'filename' => $image_data['image'],
				'data' => $image_data,
		]], JSON_PRETTY_PRINT));
		
		// unlock
		unset($locked[$time.'|'.$target_url]);
		file_put_contents($lockfile, json_encode($locked, JSON_PRETTY_PRINT));
		
		die();

	}
