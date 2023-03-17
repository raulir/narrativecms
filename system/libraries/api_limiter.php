<?php
$GLOBALS['ajax_lib_version'][basename(__FILE__, '.php')] = 1;

/**
 * 
 * returns number of visits from this ip in period
 * 
 * @param integer $periods Number of periods
 * @param string $id Limiter id
 * @param integer $granularity Length of period in minutes, default 1 minute
 * 
 */
function get_limiter_use($periods, $id = '', $granularity = 0){
	
	if ($granularity == 0){
		if ($periods > 10){
			$granularity = round(0.5 + ($periods / 5));
			$periods = round(0.5 + ($periods / $granularity));
		} else {
			$granularity = 1;
		}
	}

	// remote user, if you have signing in system, use real username
	$remote_user = $_SERVER['REMOTE_ADDR'];
	
	// update stats
	$current_time = date('Y-m-d-H-i', round(time()/($granularity * 60)) * ($granularity * 60));
	$current_hash = substr(md5($id.$current_time), 0, 10);
	$lock_hash = substr(md5($id), 0, 10);
	
	$data = [];
	
	// try locking
	$locked = false;
	$locked_count = 0;
	while (!$locked) {
	    
	    $lock_data = get_data($lock_hash, 'lock');

	    if ($lock_data && empty($lock_data['time'])){
			put_data($lock_hash, 'lock', ['time' => time()]);
	        $lock_data['time'] = time();
	    }

	    if($lock_data && $lock_data['time'] + 5 < time()){
			$locked_count += 1;
			if ($locked_count > 250){ // 5s
				print('Ajax limiter lock error!');
				die();
			}
			usleep(20000);
	    } else {
	        put_data($lock_hash, 'lock', ['time' => time()]);
	        $locked = true; // successfully locked
	    }

	}
	
	// load and increment stats for current user
	$data[$current_time] = get_data($current_hash, 'limiter');

	if (empty($data[$current_time])) {
		$data[$current_time] = [];
	}
	
	$data[$current_time][$remote_user] = (!empty($data[$current_time][$remote_user]) ? $data[$current_time][$remote_user] + 1 : 1);

	put_data($current_hash, 'limiter',  $data[$current_time]);
	
	// remove lock
	delete_data($lock_hash);

	// get more minutes, up to period
	for ($i = 1; $i <= $periods; $i++){
		
		$new_time = round(strtotime('-'.($i * $granularity).' minutes')/($granularity * 60)) * ($granularity * 60);
		$time = date('Y-m-d-H-i', $new_time);
		
        $hash = substr(md5($id.$time), 0, 10);
		
        $timedata = get_data($hash, 'limiter');

        if (!empty($timedata)){
			$data[$time] = $timedata;
		}
		
	}

	$total = 0;
	foreach($data as $minute){
		$total += $minute[$remote_user];
	}
	
	return $total;
	
}

function check_limiter($limiter){
	
	if ($limiter['use'] > $limiter['limit']){
		
		$return = [
				'data' => [ ],
				'error' => [
						'code' => '1000',
						'text' => 'Ajax api overuse'
				],
				'info' => [
						'limiter' => $limiter
				],
				'query' => [ ]
		];
		
		if (! empty ( $_POST ['debug'] )) {
			print ('<br><pre>') ;
		}
		
		$return ['info'] ['timer'] = round ( microtime ( true ) * 1000 ) - $GLOBALS ['timer'] ['start'];
		
		// precision fix
		if (version_compare ( phpversion (), '7.1', '>=' )) {
			ini_set ( 'serialize_precision', - 1 );
		}
		
		print_r ( json_encode ( $return, JSON_PRETTY_PRINT ) );
		
		if (! empty ( $_POST ['debug'] )) {
			print ('</pre>') ;
		}
		
		die();
		
	}
	
	return $limiter;
	
}
