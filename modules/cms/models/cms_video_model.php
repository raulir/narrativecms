<?php

namespace cms;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_video_model extends \Model {

	function video_add_queue($video_id){

		if (!$this->ffmpeg_is_available()){
			$this->_log_encode('ffmpeg not available, queue skipped for video_id '.$video_id);
			return;
		}

		$sql = "select * from cms_image where cms_image_id = ? ";
		$query = $this->db->query($sql, [$video_id, ]);
		$video = $query->result_array()[0];

		$videofile = $GLOBALS['config']['upload_path'].$video['filename'];

		$queue = $this->_read_queue();

		foreach ($queue as $item){
			if (!empty($item['videofile']) && $item['videofile'] === $videofile){
				return;
			}
		}

		try {
			$metadata = $this->get_video_metadata($videofile);
		} catch (\Exception $e) {
			$this->_log_encode('video_add_queue metadata failed for '.$videofile.': '.$e->getMessage());
			return;
		}

		$ladder = [
				['width' => 320, 'stdbr' => 150, 'crf' => 32, 'audio_br' => '32k',],
				['width' => 640, 'stdbr' => 500, 'crf' => 30, 'audio_br' => '64k',],
				['width' => 1280, 'stdbr' => 2000, 'crf' => 28, 'audio_br' => '96k',],
				['width' => 1920, 'stdbr' => 4000, 'crf' => 26, 'audio_br' => '128k',],
				['width' => 3840, 'stdbr' => 10000, 'crf' => 24, 'audio_br' => '192k',],
		];

		$video_todo = [
				'ladder' => [],
				'videofile' => $videofile,
				'target_folder' => $videofile.'.data/'
		];

		foreach ($ladder as $step){
			if ($step['width'] < $metadata['width']){
				$video_todo['ladder'][] = $step;
			} else {
				$video_todo['ladder'][] = $step;
				break;
			}
		}

		foreach($video_todo['ladder'] as $i => $step){
			$video_todo['ladder'][$i]['br'] = round($step['stdbr'] * 1.5 * $metadata['height'] / $metadata['width']).'k';
			$video_todo['ladder'][$i]['profile'] = $metadata['bit_depth'] == 10 ? 'main10' : 'main';
		}

		$queue[] = $video_todo;
		$this->_write_queue($queue);

	}

	function ffmpeg_is_available(){

		if (empty($GLOBALS['config']['ffmpeg'])){
			return false;
		}

		return is_file($this->_ffmpeg_path());

	}

	function get_video_metadata($videofile){

		$json = $this->_probe_video($videofile);

		$video_stream = null;
		foreach ($json['streams'] as $stream) {
			if ($stream['codec_type'] === 'video') {
				$video_stream = $stream;
				break;
			}
		}
		if (!$video_stream) {
			throw new \Exception('No video stream found.');
		}

		$width = $video_stream['width'];
		$height = $video_stream['height'];
		$bitrate = $video_stream['bit_rate'] ?? $json['format']['bit_rate'] ?? 0;

		$bit_depth = $video_stream['bits_per_raw_sample'] ?? $video_stream['pix_fmt'] ?? null;
		if (!$bit_depth && isset($video_stream['pix_fmt'])) {
			if (preg_match('/(\d+)le$/', $video_stream['pix_fmt'], $m)) {
				$bit_depth = (int)$m[1];
			} elseif (strpos($video_stream['pix_fmt'], '10') !== false) {
				$bit_depth = 10;
			} else {
				$bit_depth = 8;
			}
		}

		return [
				'width' => $width,
				'height' => $height,
				'bitrate_kbps' => round($bitrate / 1000),
				'bit_depth' => $bit_depth,
		];
	}

	function convert_gif_to_mp4($filename){

		$gif_path = $GLOBALS['config']['upload_path'].$filename;
		if (!file_exists($gif_path)){
			return false;
		}

		$name_a = pathinfo($filename);
		$new_filename = $name_a['dirname'].'/'.$name_a['filename'].'.mp4';
		$mp4_path = $GLOBALS['config']['upload_path'].$new_filename;

		$cmd = $this->_ffmpeg_path().' -y -i '.escapeshellarg($gif_path).' -movflags +faststart -pix_fmt yuv420p -an '.
				escapeshellarg($mp4_path).' 2>&1';

		exec($cmd, $out, $ret);

		if ($ret !== 0 || !file_exists($mp4_path)){
			return false;
		}

		$result = ['filename' => $new_filename, 'width' => 0, 'height' => 0, ];

		try {
			$metadata = $this->get_video_metadata($mp4_path);
			$result['width'] = $metadata['width'];
			$result['height'] = $metadata['height'];
		} catch (\Exception $e) {
		}

		return $result;

	}

	function process_encode_queue(){

		if (!$this->ffmpeg_is_available()){
			return ['message' => 'ffmpeg not available'];
		}

		if ($this->_is_queue_locked()){
			return ['message' => 'locked, encoding in progress'];
		}

		$queue = $this->_read_queue();

		if (empty($queue[0])){
			return ['message' => 'queue empty'];
		}

		$load = $this->_server_cpu_load_ratio();
		if ($load !== false && $load >= $this->_encode_load_limit()){
			return ['message' => 'server load too high ('.round($load * 100).'%)'];
		}

		$todo = $queue[0];

		if (!file_exists($todo['videofile'])){
			array_shift($queue);
			$this->_write_queue(array_values($queue));
			$msg = 'skipped missing file: '.$todo['videofile'];
			$this->_log_encode($msg);
			return ['message' => $msg];
		}

		$this->_acquire_queue_lock();

		set_time_limit(3600);

		try {

			$this->_encode_queue_item($todo);

			array_shift($queue);
			$this->_write_queue(array_values($queue));
			$this->_release_queue_lock();

			return ['message' => 'encoded: '.$todo['videofile']];

		} catch (\Exception $e) {

			array_shift($queue);
			$queue[] = $todo;
			$this->_write_queue(array_values($queue));

			$msg = 'encode failed: '.$e->getMessage();
			$this->_log_encode($msg);
			$this->_release_queue_lock();

			return ['message' => $msg];

		}

	}

	function _ffmpeg_path(){

		return str_replace('<filename>', 'ffmpeg', $GLOBALS['config']['ffmpeg']);

	}

	function _ffprobe_path(){

		return str_replace('<filename>', 'ffprobe', $GLOBALS['config']['ffmpeg']);

	}

	function _probe_video($videofile){

		$command = $this->_ffprobe_path().' -v quiet -print_format json -show_format -show_streams '.escapeshellarg($videofile);
		$output = shell_exec($command);
		$json = json_decode($output, true);

		return $json ?: [];

	}

	function _queue_filename(){

		return $GLOBALS['config']['base_path'].'cache/video_queue.json';

	}

	function _queue_lock_path(){

		return $GLOBALS['config']['base_path'].'cache/video_queue.lock';

	}

	function _read_queue(){

		if (file_exists($this->_queue_filename())){
			return json_decode(file_get_contents($this->_queue_filename()), true) ?: [];
		}

		return [];

	}

	function _write_queue($queue){

		file_put_contents($this->_queue_filename(), json_encode($queue, JSON_PRETTY_PRINT));

	}

	function _is_queue_locked(){

		if (!file_exists($this->_queue_lock_path())){
			return false;
		}

		$started = $this->_queue_lock_started();
		if ($started && (time() - $started) > 7200){
			$this->_release_queue_lock();
			return false;
		}

		return true;

	}

	function _queue_lock_started(){

		if (!file_exists($this->_queue_lock_path())){
			return false;
		}

		$content = file_get_contents($this->_queue_lock_path());
		$data = json_decode($content, true);
		if (!empty($data['started'])){
			return (int)$data['started'];
		}

		return filemtime($this->_queue_lock_path()) ?: time();

	}

	function _acquire_queue_lock(){

		file_put_contents($this->_queue_lock_path(), json_encode(['started' => time()], JSON_PRETTY_PRINT));

	}

	function _release_queue_lock(){

		if (file_exists($this->_queue_lock_path())){
			unlink($this->_queue_lock_path());
		}

	}

	function _encode_load_limit(){

		$limit = !empty($GLOBALS['config']['video_encode_max_load']) ? (float)$GLOBALS['config']['video_encode_max_load'] : 0.8;
		if ($limit < 0){
			$limit = 0;
		}
		if ($limit > 1){
			$limit = 1;
		}

		return $limit;

	}

	function _server_cpu_load_ratio(){

		if (function_exists('sys_getloadavg')){
			$load = sys_getloadavg();
			if (!empty($load[0])){
				$cpus = $this->_cpu_count();
				if ($cpus > 0){
					return min(1.0, $load[0] / $cpus);
				}
			}
		}

		if (stristr(PHP_OS, 'WIN') !== false){
			$output = shell_exec('wmic cpu get loadpercentage 2>nul');
			if (!empty($output) && preg_match_all('/\d+/', $output, $matches)){
				$vals = array_filter(array_map('intval', $matches[0]), function($v){ return $v <= 100; });
				if (!empty($vals)){
					return (array_sum($vals) / count($vals)) / 100;
				}
			}
		}

		return false;

	}

	function _cpu_count(){

		$cpus = (int)trim((string)@shell_exec('nproc 2>/dev/null'));
		if ($cpus > 0){
			return $cpus;
		}

		if (!empty($_SERVER['NUMBER_OF_PROCESSORS'])){
			return (int)$_SERVER['NUMBER_OF_PROCESSORS'];
		}

		return 1;

	}

	function _log_encode($message){

		$line = date('Y-m-d H:i:s').' '.$message."\n";
		file_put_contents($GLOBALS['config']['base_path'].'cache/video_encode.log', $line, FILE_APPEND);

	}

	function _detect_screen_recording($json, $videofile){

		if (strpos(basename($videofile), 'screenrecording') !== false) {
			return true;
		}

		if (isset($json['format']['tags']['encoder'])) {
			$encoder = strtolower($json['format']['tags']['encoder']);
			if (strpos($encoder, 'screen') !== false || strpos($encoder, 'lavf') !== false || strpos($encoder, 'obs') !== false) {
				return true;
			}
		}

		if (isset($json['streams'][0]['avg_frame_rate'])) {
			$fr = $json['streams'][0]['avg_frame_rate'];
			if ($fr === '0/0' || strpos($fr, '1000') !== false) {
				return true;
			}
		}

		return false;

	}

	function _get_display_aspect_ratio($json){

		$dar = '16:9';

		foreach ($json['streams'] as $stream) {
			if (!empty($stream['codec_type']) && $stream['codec_type'] == 'video') {
				$width  = $stream['width']  ?? 0;
				$height = $stream['height'] ?? 0;
				$sar    = $stream['sample_aspect_ratio'] ?? '1:1';

				if ($width > 0 && $height > 0) {
					[$sar_w, $sar_h] = array_pad(array_map('floatval', explode(':', $sar)), 2, 1.0);
					$dar_w = $width  * $sar_w;
					$dar_h = $height * $sar_h;

					$gcd = function($a, $b) use (&$gcd) { return $b ? $gcd($b, $a % $b) : $a; };
					$g = $gcd((int)round($dar_w), (int)round($dar_h));
					$dar = (round($dar_w / $g)) . ':' . (round($dar_h / $g));
				}

				break;
			}
		}

		return $dar;

	}

	function _normalise_video($input_file, $target_folder){

		$normalised_file = $target_folder.str_replace('.mp4', '_nrm.mp4', basename($input_file));

		$normalise_cmd = $this->_ffmpeg_path() . " -y -i " . escapeshellarg($input_file) .
			" -r 30 -vsync cfr " .
			" -vf \"scale='min(1920,iw)':-2:force_original_aspect_ratio=decrease,pad=ceil(iw/2)*2:ceil(ih/2)*2,format=yuv420p\" " .
			" -color_range pc " .
			" -c:v libx264 -profile:v main -level 4.1 -crf 21 -preset medium " .
			" -pix_fmt yuv420p -movflags +faststart " .
			" -c:a aac -b:a 128k " .
			escapeshellarg($normalised_file);

		exec($normalise_cmd . ' 2>&1', $out_norm, $ret_norm);
		if ($ret_norm !== 0) {
			error_log("Normalisation failed for $input_file: " . implode("\n", $out_norm));
			return $input_file;
		}

		return $normalised_file;

	}

	function _encode_queue_item($todo){

		$ffmpeg_name = $this->_ffmpeg_path();

		if (!is_dir($todo['target_folder'])){
			mkdir($todo['target_folder'], 0755, true);
		}

		$json = $this->_probe_video($todo['videofile']);
		$duration_sec = $json['format']['duration'] ?? 0;
		$has_audio = false;
		foreach ($json['streams'] as $stream) {
			if (($stream['codec_type'] ?? '') === 'audio') {
				$has_audio = true;
				break;
			}
		}

		if ($this->_detect_screen_recording($json, $todo['videofile'])) {
			$new_videofile = $this->_normalise_video($todo['videofile'], $todo['target_folder']);
			if ($new_videofile !== $todo['videofile']) {
				$json = $this->_probe_video($new_videofile);
				$duration_sec = $json['format']['duration'] ?? 0;
				$todo['videofile'] = $new_videofile;
			}
		}

		$dar = $this->_get_display_aspect_ratio($json);

		$filters     = [];
		$maps        = [];
		$varmap      = [];
		$audio_maps  = [];
		$i           = 0;
		$step        = null;

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
			" -f dash -seg_duration 5 " .
			" -init_seg_name init-\$RepresentationID\$.m4s " .
			" -media_seg_name chunk-\$RepresentationID\$-\$Number%05d\$.m4s " .
			" -adaptation_sets \"{$adaptation}\" " .
			" -brand dash -write_prft 1 " .
			" -movflags +frag_keyframe+empty_moov " .
			" -use_template 1 -use_timeline 1 " .
			escapeshellarg("{$todo['target_folder']}/manifest.mpd");

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
				"-b:v:{$j} " . (trim($step['br'], 'k') * 1.6) . "k " .
				"-maxrate:v:{$j} " . (trim($step['br'], 'k') * 1.6) . "k " .
				"-bufsize:v:{$j} " . (2 * (trim($step['br'], 'k') * 1.6)) . "k " .
				"-crf:v:{$j} " . ($step['crf'] + 2) . " -preset medium " .
				"-profile:v:{$j} main -level:v:{$j} 4.1 -pix_fmt yuv420p " .
				"-g 150 -keyint_min 75 " .
				"-aspect:v:{$j} {$dar}";

			$avc_varmap[] = "v:{$j}";

			$j++;
		}

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

		$fallback_cmd = $ffmpeg_name.' -y -i '.escapeshellarg($todo['videofile']).
			' -vf "scale=640:-2"'.
			' -c:v libx264 -b:v 400k -maxrate 400k -bufsize 1000k -preset medium'.
			' -profile:v main -level 3.1 -pix_fmt yuv420p '.
			($has_audio ? ' -c:a aac -b:a 96k ' : '').
			' -movflags +faststart '.
			escapeshellarg($todo['target_folder'].'/fallback.mp4');

		$fallback_hd_cmd = $ffmpeg_name.' -y -i '.escapeshellarg($todo['videofile']).
			' -vf "scale=854:-2"'.
			' -c:v libx264 -b:v 1000k -maxrate 1250k -bufsize 2500k -preset medium'.
			' -profile:v main -level 4.0 -pix_fmt yuv420p '.
			($has_audio ? ' -c:a aac -b:a 128k ' : '').
			' -movflags +faststart '.
			escapeshellarg($todo['target_folder'].'/fallback_hd.mp4');

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

		$jpg_cmd = $ffmpeg_name.' -i '.escapeshellarg($todo['videofile']).' -vf scale=300:-2 -frames:v 1 -q:v 5 '.
				escapeshellarg("{$todo['target_folder']}/cover.jpg");

		exec($jpg_cmd . ' 2>&1', $output, $ret);
		if ($ret !== 0) {
			throw new \Exception("jpg extract failed: " . implode("\n", $output));
		}

		exec($cmd . ' 2>&1', $output, $ret);
		if ($ret !== 0) {
			throw new \Exception("DASH encoding failed: " . implode("\n", $output));
		}

		exec($fallback_cmd.' 2>&1', $out_fb, $ret_fb);
		if ($ret_fb !== 0) {
			throw new \Exception("Fallback encoding failed: " . implode("\n", $out_fb));
		}

		exec($fallback_hd_cmd.' 2>&1', $out_fb, $ret_fb);
		if ($ret_fb !== 0) {
			throw new \Exception("Fallback encoding failed: " . implode("\n", $out_fb));
		}

		exec($gif_cmd.' 2>&1', $out_gif, $ret_gif);
		if ($ret_gif !== 0) {
			throw new \Exception("GIF encoding failed: " . implode("\n", $out_gif));
		}

		exec($avc_cmd . ' 2>&1', $avc_output, $avc_ret);
		if ($avc_ret !== 0) {
			throw new \Exception("AVC encoding failed: " . implode("\n", $avc_output));
		}

		$mpd_file = "{$todo['target_folder']}/manifest.mpd";
		$mpd = file_get_contents($mpd_file);
		$mpd = preg_replace('/ sar="[^"]*"/', ' sar="1:1"', $mpd);
		if ($step['profile'] == 'main10'){
			$mpd = preg_replace('/codecs=""/', 'codecs="hvc1.2.4.L63.B0"', $mpd);
		} else {
			$mpd = preg_replace('/codecs=""/', 'codecs="hvc1.1.6.L63.90"', $mpd);
		}

		file_put_contents($mpd_file, $mpd);

	}

	function extract_cover_frame($video_path, $output_jpg_path, $seek_sec = 0.1){

		if (!$this->ffmpeg_is_available()){
			return false;
		}

		if (!file_exists($video_path) || is_dir($video_path)){
			return false;
		}

		$output_dir = pathinfo($output_jpg_path, PATHINFO_DIRNAME);
		if (!is_dir($output_dir)){
			mkdir($output_dir, 0777, true);
		}

		$cmd = $this->_ffmpeg_path().' -y -ss '.escapeshellarg((string)$seek_sec).
			' -i '.escapeshellarg($video_path).
			' -frames:v 1 -q:v 2 '.
			escapeshellarg($output_jpg_path);

		exec($cmd.' 2>&1', $output, $ret);

		return $ret === 0 && file_exists($output_jpg_path);

	}

}