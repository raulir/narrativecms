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
		//list($source_ar_w, $source_ar_h) = explode(':', $dar);
		
		$filters     = [];
		$maps        = [];
		$varmap      = [];
		$audio_maps  = [];
		$i           = 0;
		
		foreach ($todo['ladder'] as $step) {
			// --- EXACT TARGET WIDTH & HEIGHT (both even) ---
		    //$target_w = $step['width'];
		    //$target_h = (int)floor($target_w * $source_ar_h / $source_ar_w);
		
		    // Force both even
		    //if ($target_w % 2 == 1) $target_w--;
		    //if ($target_h % 2 == 1) $target_h--;
		    
			$filters[] = "[0:v]scale={$step['width']}:-2:force_original_aspect_ratio=decrease,pad=ceil(iw/2)*2:ceil(ih/2)*2,setsar=1[v{$i}out]";
			// $filters[] = "[0:v]scale={$target_w}:{$target_h}:force_original_aspect_ratio=decrease,setsar=1[v{$i}out]";

    		$maps[] = "-map \"[v{$i}out]\" " .
            "-aspect:v:{$i} {$dar} " .
            "-c:v:{$i} libx265 " .
			"-b:v:{$i} {$step['br']} -maxrate:v:{$i} {$step['br']} " .
			"-bufsize:v:{$i} " . (2 * (int)str_replace('k', '', $step['br'])) . "k " .
			"-crf:v:{$i} {$step['crf']} -preset medium " .
			"-profile:v:{$i} {$step['profile']} " .
			"-g 150 -keyint_min 150";
		
			// ---- audio (only if source has it) ----------------------------------
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
			       " -f dash -seg_duration 5 " .
			       " -init_seg_name init-\$RepresentationID\$.m4s " .
			       " -media_seg_name chunk-\$RepresentationID\$-\$Number%05d\$.m4s " .
			       " -adaptation_sets \"{$adaptation}\" " .
			       " -brand dash -write_prft 1 " .
			       " -movflags +frag_keyframe+empty_moov " .
			       escapeshellarg("{$todo['target_folder']}/manifest.mpd");

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
		
		// execute these:

//		_print_r($pass1);
//		_print_r($cmd_base);

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
		
		/* TODO:
		
				$codec_map = [
		    'libx264' => 'avc1.64001F',   // H.264 High
		    'libx265' => 'hvc1.1.6.L63.90',
		    'libsvtav1' => 'av01.0.04M.08',
		];
		
		$codec = $codec_map[$step['codec'] ?? 'libx265'];
		$mpd = preg_replace('/codecs=""/', "codecs=\"{$codec}\"", $mpd);
		
		$level_map = [
		    240  => 'L30',  // 240p
		    360  => 'L40',
		    480  => 'L50',
		    720  => 'L60',
		    1080 => 'L63',
		    2160 => 'L81',
		];
		$width = $step['width'];
		$level = $level_map[$width] ?? 'L63';
		$codecs = "hvc1.1.6.{$level}.90";
		
		 */
		
		array_shift($queue);
		$queue = array_values($queue);
		
		file_put_contents($queue_filename, json_encode($queue, JSON_PRETTY_PRINT));
		
		unlink($queue_lockname);
		
		return [];
				
	}

}

/*
		$filters = [];
		$maps = [];
		$varmap = [];
		$audio_maps = [];
		$i = 0;
		foreach ($todo['ladder'] as $step) {
			// Scale to step width preserve AR
			$scale = "scale={$step['width']}:-2:force_original_aspect_ratio=decrease,pad=width=ceil(iw/2)*2:height=ceil(ih/2)*2";
			$filters[] = "[0:v]{$scale}[v{$i}out]";
			
			// Video map: 2-pass, slow preset, high compression
			$maps[] = "-map \"[v{$i}out]\" -c:v:{$i} libx265 -b:v:{$i} {$step['br']} -maxrate:v:{$i} {$step['br']} -bufsize:v:{$i} ". 
					(2 * (int)str_replace('k', '', $step['br'])) . "k -crf:v:{$i} {$step['crf']} -preset medium -profile:v:{$i} {$step['profile']} ".
					"-g 150 -keyint_min 150 -sc_threshold 0 -pass 1 -passlogfile passlog{$i}"; // Pass 1
					
			if($has_audio){
				// Audio per variant
				$audio_maps[] = "-map a:0 -c:a:{$i} aac -b:a:{$i} {$step['audio_br']}";
				$varmap[] = "v:{$i},a:{$i}";
			} else {
				$varmap[] = "v:{$i}";
			}
			
			$i++;
		}
		
		// For 2-pass: We need to run FFmpeg twice (first for analysis, second for encode)
		// But to simplify, this command is for pass 2; run pass 1 first by changing -pass 2 and removing DASH opts.
		
		$cmd_base = $ffmpeg_name." -y -i " . escapeshellarg($todo['videofile']) . " -filter_complex \"" . implode(';', $filters) . "\" " .
				implode(' ', $maps) . " " . implode(' ', $audio_maps) .
				" -var_stream_map \"" . implode(' ', $varmap) . "\" " .
				" -f dash -seg_duration 5 -init_seg_name init-\$RepresentationID\$.m4s -media_seg_name chunk-\$RepresentationID\$-\$Number%05d\$.m4s " .
				" -adaptation_sets \"id=0,streams=v " . ($has_audio ? " id=1,streams=a" : "") . escapeshellarg("{$todo['target_folder']}/manifest.mpd");
		
		// To enable 2-pass: First run with -pass 1 -an -f null /dev/null (analysis only)
		$pass1 = str_replace('-pass 2', '-pass 1', $cmd_base); // Adjust maps to -pass 1, remove audio/DASH, add -an -f null /dev/null
		$pass1 = preg_replace('/-pass 2/', '-pass 1', $pass1); // Simplified; full: remove DASH parts for pass 1
		if (!$has_audio) {
			$pass1 = preg_replace('/-map a:\d+[^ ]*/    //', '', $pass1); // remove any -map a:*
//		}
//		$pass1 .= " -an -f null /dev/null"; // No audio/output for pass 1

// Run Pass 1 (analysis, slow but necessary for compression)
//		exec($pass1 . ' 2>&1', $out1, $ret1);
//		if ($ret1 !== 0) throw new Exception("Pass 1 encoding failed: " . implode("\n", $out1));

// Run Pass 2 (encode)
//		exec($cmd_base . ' 2>&1', $out2, $ret2);
//		if ($ret2 !== 0) throw new Exception("Pass 2 encoding failed: " . implode("\n", $out2));




