<?php

namespace cms;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_page_panel_export extends \Controller {

	function __construct(){

		parent::__construct();

		if(empty($_SESSION['cms_user']['cms_user_id'])){
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}

	}

	function panel_params($params){

		if (!empty($params['export_view']) && $params['export_view'] === 'settings'){
			$params['image_cutoff_px'] = !empty($params['image_cutoff_px']) ? (int)$params['image_cutoff_px'] : 1200;
			$params['video_quality'] = !empty($params['video_quality']) ? $params['video_quality'] : 'hd';
		}

		return $params;

	}

	function rrmdir($dir){

		if (!is_dir($dir)){
			return;
		}

		$objects = scandir($dir);
		foreach ($objects as $object){
			if ($object === '.' || $object === '..'){
				continue;
			}
			$path = $dir.'/'.$object;
			if (is_dir($path)){
				$this->rrmdir($path);
			} else {
				unlink($path);
			}
		}
		rmdir($dir);

	}

	function _parse_options(){

		$image_cutoff_px = (int)$this->input->post('image_cutoff_px');
		if ($image_cutoff_px < 200){
			$image_cutoff_px = 200;
		}
		if ($image_cutoff_px > 8000){
			$image_cutoff_px = 8000;
		}

		$video_quality = $this->input->post('video_quality');
		if (!in_array($video_quality, ['hd', 'ld'], true)){
			$video_quality = 'hd';
		}

		return [
			'include_database' => $this->input->post('include_database') !== '0',
			'include_files' => $this->input->post('include_files') !== '0',
			'optimised_images' => $this->input->post('optimised_images') !== '0',
			'image_cutoff_px' => $image_cutoff_px,
			'optimised_videos' => $this->input->post('optimised_videos') !== '0',
			'video_quality' => $video_quality,
			'include_panel_files' => $this->input->post('include_panel_files') === '1',
		];

	}

	function _format_size($size){

		if ($size < 512){
			return round($size).' B';
		}

		$size = $size / 1024;

		if ($size < 100){
			return round($size, 2).' kB';
		}
		if ($size < 512){
			return round($size, 2).' kB';
		}

		$size = $size / 1024;

		if ($size < 100){
			return round($size, 2).' MB';
		}
		if ($size < 512){
			return round($size, 2).' MB';
		}

		$size = $size / 1024;

		return round($size, 2).' GB';

	}

	function _get_panel_export_data($cms_page_panel_id){

		$sql = 'select * from cms_page_panel where cms_page_panel_id = ? ';
		$query = $this->db->query($sql, [$cms_page_panel_id]);
		$row = $query->row_array();

		if (empty($row['cms_page_panel_id'])){
			return false;
		}

		$params = $this->cms_page_panel_model->get_cms_page_panel_params($cms_page_panel_id, '');

		if (!is_array($params)){
			$params = [];
		}

		$data = array_merge($params, $row);
		$data['show'] = 0;

		if ($data['cms_page_id'] == 999999){
			$data['cms_page_id'] = 0;
		}

		return $data;

	}

	function _is_video_filename($filename){

		return strtolower(pathinfo($filename, PATHINFO_EXTENSION)) === 'mp4';

	}

	function _resolve_video_export_path($filename, $options){

		$upload = $GLOBALS['config']['upload_path'];
		$original = $upload.$filename;

		if (!file_exists($original) || is_dir($original)){
			return ['path' => '', 'size' => 0, 'optimised' => false];
		}

		$original_size = filesize($original);

		if (empty($options['optimised_videos'])){
			return ['path' => $original, 'size' => $original_size, 'optimised' => false];
		}

		$candidates = [];
		if ($options['video_quality'] === 'hd'){
			$candidates[] = $upload.$filename.'.data/fallback_hd.mp4';
		}
		$candidates[] = $upload.$filename.'.data/fallback.mp4';

		foreach ($candidates as $candidate){
			if (file_exists($candidate) && !is_dir($candidate)){
				$candidate_size = filesize($candidate);
				if ($candidate_size > 0 && $candidate_size < $original_size){
					return ['path' => $candidate, 'size' => $candidate_size, 'optimised' => true];
				}
			}
		}

		return ['path' => $original, 'size' => $original_size, 'optimised' => false];

	}

	function _get_optimised_image_dimensions($width, $height, $cutoff_px){

		if ($width < 1 || $height < 1){
			return ['needs_optimise' => false, 'new_width' => 0, 'new_height' => 0];
		}

		$max_dim = max($width, $height);
		if ($max_dim <= $cutoff_px){
			return ['needs_optimise' => false, 'new_width' => $width, 'new_height' => $height];
		}

		if ($width >= $height){
			$new_width = $cutoff_px;
			$new_height = (int)max(1, round($height * $cutoff_px / $width));
		} else {
			$new_height = $cutoff_px;
			$new_width = (int)max(1, round($width * $cutoff_px / $height));
		}

		return ['needs_optimise' => true, 'new_width' => $new_width, 'new_height' => $new_height];

	}

	function _estimate_optimised_image_size($original_size, $width, $height, $cutoff_px){

		$dims = $this->_get_optimised_image_dimensions($width, $height, $cutoff_px);

		if (empty($dims['needs_optimise'])){
			return $original_size;
		}

		$area_ratio = ($dims['new_width'] * $dims['new_height']) / ($width * $height);

		return (int)max(1, round($original_size * $area_ratio));

	}

	function _create_optimised_image_copy($filename, $folder, $cutoff_px){

		$source_path = $GLOBALS['config']['upload_path'].$filename;
		if (!file_exists($source_path) || is_dir($source_path)){
			return '';
		}

		$image_data = $this->cms_image_model->get_cms_image_by_filename($filename);
		$width = (int)($image_data['original_width'] ?? 0);
		$height = (int)($image_data['original_height'] ?? 0);

		if ($width < 1 || $height < 1){
			$size = @getimagesize($source_path);
			if (!empty($size[0]) && !empty($size[1])){
				$width = (int)$size[0];
				$height = (int)$size[1];
			}
		}

		$dims = $this->_get_optimised_image_dimensions($width, $height, $cutoff_px);
		if (empty($dims['needs_optimise'])){
			return '';
		}

		$new_width = $dims['new_width'];
		$new_height = $dims['new_height'];

		$imagetype = @exif_imagetype($source_path);
		if (!in_array($imagetype, [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)){
			return '';
		}

		if ($imagetype === IMAGETYPE_JPEG){
			$src = @imagecreatefromjpeg($source_path);
			$ext = 'jpg';
		} else {
			$src = @imagecreatefrompng($source_path);
			$ext = 'png';
		}

		if (empty($src)){
			return '';
		}

		$tmp = imagecreatetruecolor($new_width, $new_height);
		if ($imagetype === IMAGETYPE_PNG){
			imagealphablending($tmp, false);
			imagesavealpha($tmp, true);
		}

		imagecopyresampled($tmp, $src, 0, 0, 0, 0, $new_width, $new_height, $width, $height);

		$target = $folder.'/_optim_'.md5($filename.$cutoff_px).'.'.$ext;
		if ($imagetype === IMAGETYPE_JPEG){
			imagejpeg($tmp, $target, 85);
		} else {
			imagepng($tmp, $target, 6);
		}

		imagedestroy($src);
		imagedestroy($tmp);

		return $target;

	}

	function _register_file_resource($source_key, $meta, $size, $preview = false){

		if (empty($this->data['_files'][$source_key])){
			$this->data['_files'][$source_key] = $meta;
			$this->stats['files']['count'] += 1;
			$this->stats['files']['size'] += $size;
			if (!empty($meta['resource_type']) && $meta['resource_type'] === 'image'){
				$this->stats['images']['count'] += 1;
				$this->stats['images']['size'] += $size;
			}
		}

	}

	function _add_image_resource($filename, $folder, $options, $preview = false){

		$image_data = $this->cms_image_model->get_cms_image_by_filename($filename);
		if (empty($image_data['filename'])){
			return 0;
		}

		if ($this->_is_video_filename($filename)){
			return $this->_add_video_resource($filename, $folder, $options, $preview);
		}

		if (!empty($this->data['_files'][$image_data['filename']])){
			return 0;
		}

		$source_path = $GLOBALS['config']['upload_path'].$filename;
		if (!file_exists($source_path) || is_dir($source_path)){
			return 0;
		}

		$optimised = false;
		$export_path = $source_path;
		$filesize = filesize($source_path);

		$width = (int)($image_data['original_width'] ?? 0);
		$height = (int)($image_data['original_height'] ?? 0);
		if ($width < 1 || $height < 1){
			$size = @getimagesize($source_path);
			if (!empty($size[0]) && !empty($size[1])){
				$width = (int)$size[0];
				$height = (int)$size[1];
			}
		}

		$oversized = max($width, $height) > $options['image_cutoff_px'];

		if ($preview){
			if (!empty($options['optimised_images']) && $oversized){
				$filesize = $this->_estimate_optimised_image_size($filesize, $width, $height, $options['image_cutoff_px']);
				$optimised = true;
			}
		} else if (!empty($options['optimised_images']) && $oversized){
			$optim_copy = $this->_create_optimised_image_copy($filename, $folder, $options['image_cutoff_px']);
			if (!empty($optim_copy) && file_exists($optim_copy)){
				$export_path = $optim_copy;
				$filesize = filesize($optim_copy);
				$optimised = true;
			}
		}

		if (empty($image_data['hash']) && !$preview){
			$hash = sha1_file($export_path);
			$this->cms_image_model->update_cms_image($image_data['filename'], ['hash' => $hash]);
		} else {
			$hash = !empty($image_data['hash']) ? $image_data['hash'] : sha1($filename);
		}

		$export_filename = substr($hash, 0, 8).'_'.$image_data['name'].'.'.pathinfo($filename, PATHINFO_EXTENSION);

		$this->_register_file_resource($image_data['filename'], [
			'hash' => $hash,
			'export_filename' => $export_filename,
			'category' => $image_data['category'] ?? '',
			'name' => $image_data['name'],
			'resource_type' => 'image',
			'optimised' => $optimised ? 1 : 0,
		], $filesize, $preview);

		if (!$preview){
			copy($export_path, $folder.'/'.$export_filename);
		}

		return $filesize;

	}

	function _add_video_resource($filename, $folder, $options, $preview = false){

		if (!empty($this->data['_files'][$filename])){
			return 0;
		}

		$resolved = $this->_resolve_video_export_path($filename, $options);
		if (empty($resolved['path'])){
			return 0;
		}

		$image_data = $this->cms_image_model->get_cms_image_by_filename($filename);
		$name = !empty($image_data['name']) ? $image_data['name'] : pathinfo($filename, PATHINFO_FILENAME);

		if (!$preview && empty($image_data['hash'])){
			$hash = sha1_file($resolved['path']);
			if (!empty($image_data['filename'])){
				$this->cms_image_model->update_cms_image($image_data['filename'], ['hash' => $hash]);
			}
		} else {
			$hash = !empty($image_data['hash']) ? $image_data['hash'] : sha1($filename);
		}

		$export_filename = substr($hash, 0, 8).'_'.$name.'.mp4';

		$this->_register_file_resource($filename, [
			'hash' => $hash,
			'export_filename' => $export_filename,
			'name' => $name,
			'resource_type' => 'video',
			'optimised' => !empty($resolved['optimised']) ? 1 : 0,
		], $resolved['size'], $preview);

		if (!$preview){
			copy($resolved['path'], $folder.'/'.$export_filename);
		}

		return $resolved['size'];

	}

	function _add_upload_file_resource($filename, $folder, $options, $preview = false){

		$file_data = $this->cms_file_model->get_cms_file_by_filename($filename);
		if (empty($file_data['filename'])){
			return 0;
		}

		if (!empty($this->data['_files'][$file_data['filename']])){
			return 0;
		}

		$source_path = $GLOBALS['config']['upload_path'].$filename;
		if (!file_exists($source_path) || is_dir($source_path)){
			return 0;
		}

		$filesize = filesize($source_path);

		if (empty($file_data['hash']) && !$preview){
			$hash = sha1_file($source_path);
			$this->cms_file_model->update_cms_file($file_data['filename'], ['hash' => $hash]);
		} else {
			$hash = !empty($file_data['hash']) ? $file_data['hash'] : sha1($filename);
		}

		$export_filename = substr($hash, 0, 8).'_'.$file_data['name'].'.'.pathinfo($filename, PATHINFO_EXTENSION);

		$this->_register_file_resource($file_data['filename'], [
			'hash' => $hash,
			'export_filename' => $export_filename,
			'name' => $file_data['name'],
			'resource_type' => 'file',
			'optimised' => 0,
		], $filesize, $preview);

		if (!$preview){
			copy($source_path, $folder.'/'.$export_filename);
		}

		return $filesize;

	}

	function _traverse_structure_fields($data, $panel_structure, $folder, $options, $preview = false){

		foreach ($panel_structure as $struct){

			if ($struct['type'] === 'image' && !empty($data[$struct['name']])){
				if (!empty($options['include_files'])){
					$this->_add_image_resource($data[$struct['name']], $folder, $options, $preview);
				}
			} else if ($struct['type'] === 'file' && !empty($data[$struct['name']])){
				if (!empty($options['include_files'])){
					$this->_add_upload_file_resource($data[$struct['name']], $folder, $options, $preview);
				}
			} else if ($struct['type'] === 'repeater' && !empty($data[$struct['name']])){
				foreach ($data[$struct['name']] as $rdata){
					foreach ($struct['fields'] as $rstruct){
						if ($rstruct['type'] === 'image' && !empty($rdata[$rstruct['name']])){
							if (!empty($options['include_files'])){
								$this->_add_image_resource($rdata[$rstruct['name']], $folder, $options, $preview);
							}
						} else if ($rstruct['type'] === 'file' && !empty($rdata[$rstruct['name']])){
							if (!empty($options['include_files'])){
								$this->_add_upload_file_resource($rdata[$rstruct['name']], $folder, $options, $preview);
							}
						}
					}
				}
			} else if (($struct['type'] === 'panels' || $struct['type'] === 'cms_page_panels') && !empty($data[$struct['name']])){
				foreach ($data[$struct['name']] as $pp_id){
					$this->traverse_page_panel($pp_id, $options, $preview);
				}
			}

		}

	}

	function traverse_page_panel($cms_page_panel_id, $options, $preview = false){

		if (empty($options['include_database']) && empty($options['include_files']) && empty($options['include_panel_files'])){
			return;
		}

		if (!empty($this->data['_panels'][$cms_page_panel_id])){
			return;
		}

		$data = $this->_get_panel_export_data($cms_page_panel_id);
		if (empty($data)){
			return;
		}

		if (!empty($options['include_database'])){
			$this->data['_panels'][$cms_page_panel_id] = $data;
			$this->stats['panels']['count'] += 1;
			$this->stats['database']['size'] += strlen(json_encode($data, JSON_UNESCAPED_UNICODE));
		}

		$panel_structure = $this->cms_panel_model->get_cms_panel_definition($data['panel_name']);

		if (!empty($options['include_files'])){
			$this->_traverse_structure_fields($data, $panel_structure, $this->folder, $options, $preview);
		} else {
			foreach ($panel_structure as $struct){
				if (($struct['type'] === 'panels' || $struct['type'] === 'cms_page_panels') && !empty($data[$struct['name']])){
					foreach ($data[$struct['name']] as $pp_id){
						$this->traverse_page_panel($pp_id, $options, $preview);
					}
				}
			}
		}

		if (!empty($options['include_panel_files']) && !empty($data['panel_name']) && empty($this->panel_files_collected[$data['panel_name']])){
			$this->_collect_panel_source_files($data['panel_name'], $preview);
		}

	}

	function _collect_panel_source_files($panel_name, $preview = false){

		$this->panel_files_collected[$panel_name] = true;

		$ci =& get_instance();
		$files = $ci->get_panel_filenames($panel_name);
		$paths = [];

		if (!empty($files['controller']) && file_exists($files['controller'])){
			$paths[] = str_replace($GLOBALS['config']['base_path'], '', $files['controller']);
		}
		if (!empty($files['extend_controllers'])){
			foreach ($files['extend_controllers'] as $ext){
				if (!empty($ext['controller']) && file_exists($ext['controller'])){
					$paths[] = str_replace($GLOBALS['config']['base_path'], '', $ext['controller']);
				}
			}
		}
		if (!empty($files['template']) && file_exists($files['template']) && empty($files['template_extends'])){
			$paths[] = str_replace($GLOBALS['config']['base_path'], '', $files['template']);
		}

		$definition = $GLOBALS['config']['base_path'].'modules/'.$files['module'].'/definitions/'.$files['name'].'.json';
		if (file_exists($definition)){
			$paths[] = 'modules/'.$files['module'].'/definitions/'.$files['name'].'.json';
		}

		if (!empty($files['js'])){
			foreach ($files['js'] as $js){
				if (file_exists($GLOBALS['config']['base_path'].$js)){
					$paths[] = $js;
				}
			}
		}

		if (!empty($files['scss'])){
			foreach ($files['scss'] as $scss){
				if (!empty($scss['script']) && file_exists($GLOBALS['config']['base_path'].$scss['script'])){
					$paths[] = $scss['script'];
				}
			}
		}

		$paths = array_values(array_unique($paths));
		$this->data['_panel_files'][$panel_name] = $paths;

		foreach ($paths as $rel_path){
			$abs = $GLOBALS['config']['base_path'].$rel_path;
			if (file_exists($abs) && is_file($abs)){
				$size = filesize($abs);
				$this->stats['panel_files']['size'] += $size;
				if (!$preview){
					$target = $this->folder.'/_panel_files/'.$rel_path;
					$target_dir = dirname($target);
					if (!is_dir($target_dir)){
						mkdir($target_dir, 0777, true);
					}
					copy($abs, $target);
				}
			}
		}

		$this->stats['panel_files']['count'] = count($paths);

	}

	function _count_oversized_image($filename, $cutoff_px){

		$image_data = $this->cms_image_model->get_cms_image_by_filename($filename);
		$width = (int)($image_data['original_width'] ?? 0);
		$height = (int)($image_data['original_height'] ?? 0);

		if ($width < 1 || $height < 1){
			$path = $GLOBALS['config']['upload_path'].$filename;
			if (file_exists($path) && !is_dir($path)){
				$size = @getimagesize($path);
				if (!empty($size[0]) && !empty($size[1])){
					$width = (int)$size[0];
					$height = (int)$size[1];
				}
			}
		}

		return max($width, $height) > $cutoff_px;

	}

	function _scan_affected_counts($cms_page_panel_id, $options){

		$this->affected_counts = [
			'oversized_images' => [],
			'optimised_videos' => [],
		];

		$video_options = array_merge($options, ['optimised_videos' => true]);

		$check_image_field = function($fn) use ($options, $video_options){

			if ($this->_is_video_filename($fn)){
				$resolved = $this->_resolve_video_export_path($fn, $video_options);
				if (!empty($resolved['optimised'])){
					$this->affected_counts['optimised_videos'][$fn] = true;
				}
				return;
			}

			if ($this->_count_oversized_image($fn, $options['image_cutoff_px'])){
				$this->affected_counts['oversized_images'][$fn] = true;
			}

		};

		$walker = function($panel_id) use (&$walker, $check_image_field){

			$data = $this->_get_panel_export_data($panel_id);
			if (empty($data)){
				return;
			}

			$panel_structure = $this->cms_panel_model->get_cms_panel_definition($data['panel_name']);

			foreach ($panel_structure as $struct){
				if ($struct['type'] === 'image' && !empty($data[$struct['name']])){
					$check_image_field($data[$struct['name']]);
				} else if ($struct['type'] === 'repeater' && !empty($data[$struct['name']])){
					foreach ($data[$struct['name']] as $rdata){
						foreach ($struct['fields'] as $rstruct){
							if ($rstruct['type'] === 'image' && !empty($rdata[$rstruct['name']])){
								$check_image_field($rdata[$rstruct['name']]);
							}
						}
					}
				} else if (($struct['type'] === 'panels' || $struct['type'] === 'cms_page_panels') && !empty($data[$struct['name']])){
					foreach ($data[$struct['name']] as $pp_id){
						$walker($pp_id);
					}
				}
			}

		};

		$walker($cms_page_panel_id);

	}

	function _init_run_state(){

		$this->data = ['_panels' => [], '_files' => [], '_panel_files' => []];
		$this->panel_files_collected = [];
		$this->stats = [
			'images' => ['count' => 0, 'size' => 0],
			'files' => ['count' => 0, 'size' => 0],
			'panels' => ['count' => 0, 'size' => 0],
			'database' => ['size' => 0],
			'panel_files' => ['count' => 0, 'size' => 0],
		];

	}

	function _zip_entry_name($base_folder, $file_path){

		$base_folder = rtrim(str_replace('\\', '/', $base_folder), '/');
		$file_path = str_replace('\\', '/', $file_path);

		if (strpos($file_path, $base_folder.'/') === 0){
			return substr($file_path, strlen($base_folder) + 1);
		}

		return basename($file_path);

	}

	function _get_latest_panel_export($cms_page_panel_id){

		$cms_page_panel_id = (int)$cms_page_panel_id;
		if ($cms_page_panel_id < 1){
			return false;
		}

		$pattern = $GLOBALS['config']['base_path'].'cache/*__'.$cms_page_panel_id.'__*.zip';
		$files = glob($pattern);

		if (empty($files)){
			return false;
		}

		$latest_path = '';
		$latest_mtime = 0;

		foreach ($files as $path){
			if (!is_file($path)){
				continue;
			}
			$mtime = filemtime($path);
			if ($mtime > $latest_mtime){
				$latest_mtime = $mtime;
				$latest_path = $path;
			}
		}

		if (empty($latest_path)){
			return false;
		}

		return [
			'filename' => pathinfo($latest_path, PATHINFO_FILENAME),
			'exported_at' => date('j M Y H:i', $latest_mtime),
		];

	}

	function _build_export_folder_name($cms_page_panel_id){

		$meta = $this->cms_page_panel_model->get_cms_page_panel($cms_page_panel_id, '', false);
		if (empty($meta['panel_name'])){
			$sql = 'select panel_name, title from cms_page_panel where cms_page_panel_id = ? limit 1';
			$query = $this->db->query($sql, [$cms_page_panel_id]);
			$meta = $query->row_array();
		}

		return $GLOBALS['config']['base_path'].'cache/'.date('ymd').'__'.str_replace('/', '_', $meta['panel_name']).'__'.$cms_page_panel_id.'__'.
			trim(substr(preg_replace('/[ _]+/', '_', preg_replace('/[^0-9a-zA-Z ]/', '', $meta['title'])), 0, 24), '_');

	}

	function panel_action($params){

		$this->load->model('cms/cms_page_panel_model');
		$this->load->model('cms/cms_panel_model');
		$this->load->model('cms/cms_image_model');
		$this->load->model('cms/cms_file_model');

		$do = $this->input->post('do');
		$cms_page_panel_id = (int)$this->input->post('export_id');

		if ($do === 'cms_page_panel_export_settings'){

			$params['export_view'] = 'settings';
			$params['export_id'] = $cms_page_panel_id;
			$params['image_cutoff_px'] = 1200;
			$params['video_quality'] = 'hd';
			$params['last_export'] = $this->_get_latest_panel_export($cms_page_panel_id);

			return $params;

		}

		if ($do === 'cms_page_panel_export_preview'){

			$options = $this->_parse_options();
			$this->_init_run_state();
			$this->folder = '';

			$this->traverse_page_panel($cms_page_panel_id, $options, true);
			$this->_scan_affected_counts($cms_page_panel_id, $options);

			$files_bytes = $this->stats['files']['size'];
			$total_bytes = $this->stats['database']['size'] + $files_bytes;
			if (!empty($options['include_panel_files'])){
				$total_bytes += $this->stats['panel_files']['size'];
			}

			$params['database_size'] = $this->_format_size($this->stats['database']['size']);
			$params['files_size'] = $this->_format_size($files_bytes);
			$params['files_count'] = $this->stats['files']['count'];
			$params['panel_files_size'] = $this->_format_size($this->stats['panel_files']['size']);
			$params['panel_files_count'] = $this->stats['panel_files']['count'];
			$params['total_size'] = $this->_format_size($total_bytes);
			$params['oversized_images_count'] = count($this->affected_counts['oversized_images']);
			$params['optimised_videos_count'] = count($this->affected_counts['optimised_videos']);
			$params['result'] = 'ok';

			return $params;

		}

		if ($do === 'cms_page_panel_export'){

			$options = $this->_parse_options();
			$this->_init_run_state();

			$start_time = microtime(true);

			$this->data['_main'] = $cms_page_panel_id;
			$this->data['_export_options'] = $options;

			$this->folder = $this->_build_export_folder_name($cms_page_panel_id);
			$this->rrmdir($this->folder);
			mkdir($this->folder, 0777, true);

			$this->traverse_page_panel($cms_page_panel_id, $options, false);

			$data_json = json_encode($this->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
			file_put_contents($this->folder.'/data.json', $data_json);

			$this->stats['time']['data'] = microtime(true) - $start_time;

			if (file_exists($this->folder.'.zip')){
				unlink($this->folder.'.zip');
			}

			$zip = new \ZipArchive();
			if ($zip->open($this->folder.'.zip', ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true){
				die('An error occurred creating zip');
			}

			$iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->folder));
			foreach ($iterator as $fileinfo){
				if ($fileinfo->isFile()){
					$path = $fileinfo->getPathname();
					$relative = $this->_zip_entry_name($this->folder, $path);
					$zip->addFile($path, $relative);
				}
			}

			$zip->close();

			$this->stats['time']['compress'] = round((microtime(true) - $this->stats['time']['data'] - $start_time) * 1000);
			$this->stats['time']['data'] = round($this->stats['time']['data'] * 1000);

			$this->stats['panels']['size'] = filesize($this->folder.'/data.json');
			$raw_total = $this->stats['panels']['size'] + $this->stats['files']['size'] + $this->stats['panel_files']['size'];

			$this->stats['total']['size_raw'] = $raw_total;
			$this->stats['total']['size'] = $this->_format_size($raw_total);
			$this->stats['total']['compressed'] = $this->_format_size(filesize($this->folder.'.zip'));

			$this->stats['panels']['size'] = $this->_format_size($this->stats['panels']['size']);
			$this->stats['images']['size'] = $this->_format_size($this->stats['images']['size']);
			$this->stats['files']['size'] = $this->_format_size($this->stats['files']['size']);
			$this->stats['panel_files']['size'] = $this->_format_size($this->stats['panel_files']['size']);

			$this->rrmdir($this->folder);

			$params['filename'] = pathinfo($this->folder.'.zip', PATHINFO_FILENAME);
			$params['stats'] = $this->stats;
			$params['export_view'] = 'result';

			return $params;

		}

		return $params;

	}

}