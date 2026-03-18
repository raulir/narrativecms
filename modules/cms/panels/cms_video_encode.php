<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_video_encode extends CI_Controller {
	
	function panel_action($params){
		
		$queue_filename = $GLOBALS['config']['base_path'].'cache/video_queue.json';
		$queue_lockname = $GLOBALS['config']['base_path'].'cache/video_queue.lock';
		
		if (file_exists($queue_lockname)){
			return [];
		}
		
		if (file_exists($queue_filename)){
			$queue = json_decode(file_get_contents($queue_filename), true);
		} else {
			$queue = [];
		}
		
		if (empty($queue[0])){
			return [];
		}
		
		file_put_contents($queue_lockname, 'lock - video encoding in progress');
		
		set_time_limit(3600);
		
		$todo = $queue[0];
		
		$ffmpeg_name = str_replace('<filename>', 'ffmpeg', $GLOBALS['config']['ffmpeg']);
		$ffprobe_name = str_replace('<filename>', 'ffprobe', $GLOBALS['config']['ffmpeg']);
		
		if (!is_dir($todo['target_folder'])){
			mkdir($todo['target_folder'], 0755, true);
		}
		
		$cmd = $ffprobe_name." -v quiet -print_format json -show_format -show_streams " . escapeshellarg($todo['videofile']);
		$json = json_decode(shell_exec($cmd), true);
		$duration_sec = $json['format']['duration'] ?? 0;
		$has_audio = false;
		foreach ($json['streams'] as $stream) {
		    if (($stream['codec_type'] ?? '') === 'audio') {
		        $has_audio = true;
		        break;
		    }
		}

		$is_screen = false;
		
		if (strpos(basename($todo['videofile']), 'screenrecording') !== false) {
		    $is_screen = true;
		} else if (isset($json['format']['tags']['encoder'])) {
		    $encoder = strtolower($json['format']['tags']['encoder']);
		    if (strpos($encoder, 'screen') !== false || strpos($encoder, 'lavf') !== false || strpos($encoder, 'obs') !== false) {
		        $is_screen = true;
		    }
		} else if (isset($json['streams'][0]['avg_frame_rate'])) {
		    $fr = $json['streams'][0]['avg_frame_rate'];
		    if ($fr === '0/0' || strpos($fr, '1000') !== false) {   // vfr or weird numbers
		        $is_screen = true;
		    }
		}
		
		if ($is_screen) {
		    $new_videofile = $this->_normalise_video($todo['videofile'], $todo['target_folder']);
		    if ($new_videofile !== $todo['videofile']) {
		        // re-probe the normalised file (now it is clean/standard)
		        $cmd = $ffprobe_name." -v quiet -print_format json -show_format -show_streams " . escapeshellarg($new_videofile);
		        $json = json_decode(shell_exec($cmd), true);
		        $duration_sec = $json['format']['duration'] ?? 0;
		        $todo['videofile'] = $new_videofile;
		    }
		}
	
		// aspect ratio
		$dar = '16:9';
		foreach ($json['streams'] as $stream) {
			if (!empty($stream['codec_type']) && $stream['codec_type'] == 'video') {
				$width  = $stream['width']  ?? 0;
				$height = $stream['height'] ?? 0;
				$sar    = $stream['sample_aspect_ratio'] ?? '1:1';  // usually missing = 1:1
				
				if ($width > 0 && $height > 0) {
				    [$sar_w, $sar_h] = array_pad(array_map('floatval', explode(':', $sar)), 2, 1.0);
				    $dar_w = $width  * $sar_w;
				    $dar_h = $height * $sar_h;
				
				    // Reduce fraction
				    $gcd = function($a, $b) use (&$gcd) { return $b ? $gcd($b, $a % $b) : $a; };
				    $g = $gcd((int)round($dar_w), (int)round($dar_h));
				    $dar = (round($dar_w / $g)) . ':' . (round($dar_h / $g));
				} else {
				    $dar = '16:9';
				}
				break;
			}
		}
		
		// 265
		$filters     = [];
		$maps        = [];
		$varmap      = [];
		$audio_maps  = [];
		$i           = 0;
		
		foreach ($todo['ladder'] as $step) {
		    
			$filters[] = "[0:v]scale={$step['width']}:-2:force_original_aspect_ratio=decrease,pad=ceil(iw/2)*2:ceil(ih/2)*2,setsar=1[v{$i}out]";

    		$maps[] = "-map \"[v{$i}out]\" " .
            "-aspect:v:{$i} {$dar} " .
            "-c:v:{$i} libx265 " .
			"-b:v:{$i} {$step['br']} -maxrate:v:{$i} {$step['br']} " .
			"-bufsize:v:{$i} " . (2 * (int)str_replace('k', '', $step['br'])) . "k " .
			"-crf:v:{$i} {$step['crf']} -preset medium " .
			"-profile:v:{$i} {$step['profile']} " .
			"-g 150 -keyint_min 150";

    		if ($has_audio) {
				$audio_maps[] = "-map a:0 -c:a:{$i} aac -b:a:{$i} {$step['audio_br']}";
				$varmap[]     = "v:{$i},a:{$i}";
			} else {
				$varmap[] = "v:{$i}";
			}
			$i++;
		}
		
		$adaptation = "id=0,streams=v" . ($has_audio ? " id=1,streams=a" : "");
		
		$cmd = $ffmpeg_name . " -y -i " . escapeshellarg($todo['videofile']) .
	       " -filter_complex \"" . implode(';', $filters) . "\" " .
	       implode(' ', $maps) . " " . implode(' ', $audio_maps) .
	       " -var_stream_map \"" . implode(' ', $varmap) . "\" " .
	       " -f dash -seg_duration 5 " .   // reliable timing control
	       " -init_seg_name init-\$RepresentationID\$.m4s " .
	       " -media_seg_name chunk-\$RepresentationID\$-\$Number%05d\$.m4s " .
	       " -adaptation_sets \"{$adaptation}\" " .
	       " -brand dash -write_prft 1 " .
	       " -movflags +frag_keyframe+empty_moov " .
	       " -use_template 1 -use_timeline 1 " .
	       escapeshellarg("{$todo['target_folder']}/manifest.mpd");
	       
	    // 264
	    
       $avc_target_folder = $todo['target_folder'] . 'libx264/';
       if (!is_dir($avc_target_folder)) {
       		mkdir($avc_target_folder, 0755, true);
       }
       
       $avc_filters = [];
       $avc_maps = [];
       $avc_audio_maps = [];
       $avc_varmap = [];
       $j = 0;
       
       foreach ($todo['ladder'] as $step) {
       	$avc_filters[] = "[0:v]scale={$step['width']}:-2:force_original_aspect_ratio=decrease,pad=ceil(iw/2)*2:ceil(ih/2)*2,setsar=1[avc{$j}out]";
       
       	$avc_maps[] = "-map \"[avc{$j}out]\" " .
       	"-c:v:{$j} libx264 -tag:v:{$j} avc1 " .
       	"-b:v:{$j} " . (trim($step['br'], 'k') * 1.6) . "k " .           // AVC needs ~60% more bitrate
       	"-maxrate:v:{$j} " . (trim($step['br'], 'k') * 1.6) . "k " .
       	"-bufsize:v:{$j} " . (2 * (trim($step['br'], 'k') * 1.6)) . "k " .
       	"-crf:v:{$j} " . ($step['crf'] + 2) . " -preset medium " .
       	"-profile:v:{$j} main -level:v:{$j} 4.1 -pix_fmt yuv420p " .
       	"-g 150 -keyint_min 75 " .
       	"-aspect:v:{$j} {$dar}";
       
       	$avc_varmap[] = "v:{$j}";
       
       	$j++;
       }
       
       // audio (only once, not duplicated per rep)
       if ($has_audio) {
       	foreach ($todo['ladder'] as $k => $step) {
       		$avc_audio_maps[] = "-map a:0 -c:a:{$k} aac -b:a:{$k} {$step['audio_br']}";
       		$avc_varmap[] = "v:{$k},a:{$k}";
       	}
       } else {
       	foreach ($todo['ladder'] as $k => $step) {
       		$avc_varmap[] = "v:{$k}";
       	}
       }
       
       $avc_adaptation = $has_audio ? "id=0,streams=v id=1,streams=a" : "id=0,streams=v";
       
       $avc_cmd = $ffmpeg_name . " -y -i " . escapeshellarg($todo['videofile']) .
       " -filter_complex \"" . implode(';', $avc_filters) . "\" " .
       implode(' ', $avc_maps) . " " . implode(' ', $avc_audio_maps) .
       " -var_stream_map \"" . implode(' ', $avc_varmap) . "\" " .
       " -f dash -seg_duration 5 " .
       " -init_seg_name init-\$RepresentationID\$.m4s " .
       " -media_seg_name chunk-\$RepresentationID\$-\$Number%05d\$.m4s " .
       " -adaptation_sets \"{$avc_adaptation}\" " .
       " -brand dash -write_prft 1 " .
       " -movflags +frag_keyframe+empty_moov " .
       " -use_template 1 -use_timeline 1 " .
       escapeshellarg($avc_target_folder . 'manifest.mpd');

	       
	       
	       
		// fallback video 400kbps
		$fallback_cmd = $ffmpeg_name." -y -i ".escapeshellarg($todo['videofile']).
		" -vf \"scale=640:360:force_original_aspect_ratio=decrease,pad=640:360:(ow-iw)/2:(oh-ih)/2\"".
		" -c:v libx265 -b:v 400k -maxrate 400k -bufsize 1000k -preset medium -profile:v ".$step['profile']." ".
		($has_audio ? " -c:a aac -b:a 96k " : '').
		" -movflags +faststart".
		" ".escapeshellarg("{$todo['target_folder']}/fallback.mp4");
		
		// gif thumbnail
		function hmsToSeconds($hms) {
			$parts = explode(':', $hms);
			return ($parts[0]*3600) + ($parts[1]*60) + ($parts[2] ?? 0);
		}

		$positions = [0, 0.25, 0.5, 0.75];
		$gif_filters = [];
		foreach ($positions as $idx => $pct) {
			$start = $duration_sec * $pct;
			$gif_filters[] = "[0:v]trim=start={$start}:duration=0.5,scale=120:120:force_original_aspect_ratio=decrease,".
					"pad=120:120:(ow-iw)/2:(oh-ih)/2,setpts=PTS-STARTPTS[clip{$idx}]";
		}
		$concat = implode(';', $gif_filters).";".implode('', array_map(fn($i)=>"[clip{$i}]", range(0,3)))."concat=n=4:v=1:a=0[outv]";
		
		$gif_cmd = $ffmpeg_name." -y -i ".escapeshellarg($todo['videofile']).
		" -filter_complex \"{$concat}\" -map \"[outv]\" -loop 0 -final_delay 50".
		" ".escapeshellarg("{$todo['target_folder']}/thumb.gif");
		
		// jpg cover thumbnail for loading
		$jpg_cmd = $ffmpeg_name.'-i '.escapeshellarg($todo['videofile']).' -vf scale=300:-2 -frames:v 1 -q:v 5'.
				escapeshellarg("{$todo['target_folder']}/cover.jpg");

		// run jpg extract
		exec($jpg_cmd . ' 2>&1', $output, $ret);
		if ($ret !== 0) {
			$msg = "jpg extract failed (exit $ret):\n" . implode("\n", $output);
			unlink($queue_lockname);
			throw new Exception("jpg extract failed: " . implode("\n", $output));
		}
		
		// run main
		exec($cmd . ' 2>&1', $output, $ret);
		if ($ret !== 0) {
			$msg = "DASH encoding failed (exit $ret):\n" . implode("\n", $output);
			unlink($queue_lockname);
			throw new Exception("DASH encoding failed: " . implode("\n", $output));
		}

		// run fallback encode
		exec($fallback_cmd.' 2>&1', $out_fb, $ret_fb);
		if ($ret_fb !== 0) {
			unlink($queue_lockname);
			throw new Exception("Fallback encoding failed: " . implode("\n", $out_fb));
		}
		
		// run gif encode
		exec($gif_cmd.' 2>&1', $out_gif, $ret_gif);
		if ($ret_gif !== 0) {
			unlink($queue_lockname);
			throw new Exception("GIF encoding failed: " . implode("\n", $out_gif));
		}
		
		// run libx264/avc encode
		exec($avc_cmd . ' 2>&1', $avc_output, $avc_ret);
		if ($avc_ret !== 0) {
			$msg = "AVC encoding failed (exit $avc_ret):\n" . implode("\n", $avc_output);
			unlink($queue_lockname);
			throw new Exception("AVC encoding failed: " . implode("\n", $avc_output));
		}
		
		// codec name fix for x265
		$mpd_file = "{$todo['target_folder']}/manifest.mpd";
		$mpd = file_get_contents($mpd_file);
		$mpd = preg_replace('/ sar="[^"]*"/', ' sar="1:1"', $mpd);
		if ($step['profile'] == 'main10'){
			$mpd = preg_replace('/codecs=""/', 'codecs="hvc1.2.4.L63.B0"', $mpd);
		} else {
			$mpd = preg_replace('/codecs=""/', 'codecs="hvc1.1.6.L63.90"', $mpd);
		}
		
		file_put_contents($mpd_file, $mpd);

		array_shift($queue);
		$queue = array_values($queue);
		
		file_put_contents($queue_filename, json_encode($queue, JSON_PRETTY_PRINT));
		
		unlink($queue_lockname);
		
		return [];
				
	}
	
	function _normalise_video($input_file, $target_folder) {
		
		$normalised_file = $target_folder.str_replace('.mp4', '_nrm.mp4', basename($input_file));
		$ffmpeg_name = str_replace('<filename>', 'ffmpeg', $GLOBALS['config']['ffmpeg']);

		// reencode to standard AVC + constant frame rate, shrink to max 1080p if larger
		$normalise_cmd = $ffmpeg_name . " -y -i " . escapeshellarg($input_file) .
	        " -r 30 -vsync cfr " .   // force constant 30 fps
	        " -vf \"scale='min(1920,iw)':-2:force_original_aspect_ratio=decrease,pad=ceil(iw/2)*2:ceil(ih/2)*2,format=yuv420p\" " .
	        " -color_range pc " .
	        " -c:v libx264 -profile:v main -level 4.1 -crf 21 -preset medium " .
	        " -pix_fmt yuv420p -movflags +faststart " .
	        " -c:a aac -b:a 128k " .
	        escapeshellarg($normalised_file);
	
	    exec($normalise_cmd . ' 2>&1', $out_norm, $ret_norm);
	    if ($ret_norm !== 0) {
	        error_log("Normalisation failed for $input_file: " . implode("\n", $out_norm));
	        return $input_file; // fallback to original if fail
	    }
	
		return $normalised_file; // use this as input for main DASH encode
		
	}

}
