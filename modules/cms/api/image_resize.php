<?php

	// optimise images in non-session way
	
	$_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_STRING);

	if ($_POST['do'] == 'resize'){
		
		$name = $_POST['name'];
		
		$output = $_POST['output'];
		$width = $_POST['width'];
		
		$image_dir = pathinfo($name, PATHINFO_DIRNAME);
		$image_name = pathinfo($name, PATHINFO_FILENAME);
		
		$target_url = $GLOBALS['config']['upload_url'].$image_dir.'/_'.$image_name.'.'.$width.'.'.$output;
		
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
				
				print(json_encode(['result' => ['src' => $GLOBALS['config']['upload_url'].$name, ]], JSON_PRETTY_PRINT));
				die();
			
			}

		}
		
		// lock
		$time = time();
		$locked[$time.'|'.$target_url] = $target_url;
		file_put_contents($lockfile, json_encode($locked, JSON_PRETTY_PRINT));
		
		// load helper
		include($GLOBALS['config']['base_path'].'system/helpers/image_optimiser_helper.php');
		
		_iw($name, ['width' => $width, 'output' => $output, ]);

		print(json_encode(['result' => ['src' => $target_url, ]], JSON_PRETTY_PRINT));
		
		// unlock
		unset($locked[$time.'|'.$target_url]);
		file_put_contents($lockfile, json_encode($locked, JSON_PRETTY_PRINT));
		
		die();

	}
