<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_image_model extends CI_Model {

	function get_cms_image_by_filename($filename){

		// check if cached image data exists
		if (empty($GLOBALS['cache']['images_by_filename'])){
			
			$GLOBALS['cache']['images_by_filename'] = [];
			
			// first 50
			$sql = "select * from cms_image order by cms_image_id limit 50";
			$query = $this->db->query($sql);
			$result = $query->result_array();
			
			foreach($result as $row){
				$GLOBALS['cache']['images_by_filename'][$row['filename']] = $row;
			}

			// last 50, only when at least 50 was found
			if (count($GLOBALS['cache']['images_by_filename']) > 49){
			
				$sql = "select * from cms_image order by cms_image_id desc limit 50";
				$query = $this->db->query($sql);
				$result = $query->result_array();
					
				foreach($result as $row){
					$GLOBALS['cache']['images_by_filename'][$row['filename']] = $row;
				}
			
			}
				
			foreach($GLOBALS['cache']['images_by_filename'] as $fkey => $data){
			
				if (!empty($data['meta'])){
					$GLOBALS['cache']['images_by_filename'][$fkey] = array_merge($data, json_decode($data['meta'], true));
				}
			
			}

		}

		// check from cache
		if (!empty($GLOBALS['cache']['images_by_filename'][$filename])){
			
			$result = $GLOBALS['cache']['images_by_filename'][$filename];
			
		} else {

			$sql = "select * from cms_image where filename = ? ";
			$query = $this->db->query($sql, array($filename));
			$result = $query->row_array();
	
			if (!empty($result['meta'])){
				$result = array_merge($result, json_decode($result['meta'], true));
			}
		
		}

		// if no hash
		if (empty($result['hash'])){
			
			$result['hash'] = $this->refresh_cms_image_hash($filename);
			$GLOBALS['cache']['images_by_filename'][$filename] = $result;
		
		}
		
		// if no height and width information
		if (empty($result['original_width']) && file_exists($GLOBALS['config']['upload_path'].$filename) && !is_dir($GLOBALS['config']['upload_path'].$filename)){
			
			if (!empty($result['meta'])){
				$meta = json_decode($result['meta'], true);
			} else {
				$meta = [];
			}
			
			list($meta['original_width'], $meta['original_height']) = getimagesize($GLOBALS['config']['upload_path'].$filename);
			
			$this->update_cms_image($filename,['meta' => json_encode($meta, JSON_PRETTY_PRINT) , ]);
			
			$result = array_merge($result, $meta);
			$GLOBALS['cache']['images_by_filename'][$filename] = $result;
			
		}

		return $result;

	}

	function get_cms_images($page, $limit, $category, $search){
		
		$this->load->model('cms/cms_page_model');

		$page = (int)$page;
		$limit = (int)$limit;
		$search = str_replace(array("'", '"', ), '', $search);
		$category = trim(preg_replace("/[^[:alnum:]_]/ui", '', $category));

		$where = " 1=1 ";
		$params = array();

		if ($category !== ''){
			$where .= " and category = ? ";
			$params[] = ($category === 0 || $category === '0' ? '' : $category);
		}

		if ($search !== ''){
			$where .= " and ( filename like '%".$search."%' or keyword like '%".$search."%' ) ";
		}

		$sql = "select a.*, count(b.cms_page_panel_param_id) as number from cms_image a left join cms_page_panel_param b on a.filename = b.value " .
				" where ".$where." group by a.cms_image_id order by cms_image_id desc limit ".(int)$limit." offset ".(int)($page * $limit)." ";

		$query = $this->db->query($sql, $params);

		$return['result'] = $query->result_array();
		
		$pages = $this->cms_page_model->get_cms_pages();

		$page_image_counts = [];
		foreach ($pages as $cms_page){
			if (!empty($cms_page['image'])){
				$page_image_counts[$cms_page['image']] = ($page_image_counts[$cms_page['image']] ?? 0) + 1;
			}
		}
		
		foreach($return['result'] as $key => $image){
			if (!empty($page_image_counts[$image['filename']])){
				$return['result'][$key]['number'] += $page_image_counts[$image['filename']];
			}
		}

		$this->_enrich_cms_images_usage($return['result'], $page_image_counts);
		
		$sql_count = "select count(*) as number from cms_image a where ".$where." ";
		$query = $this->db->query($sql_count, $params);
		$number_a = $query->row_array();
		$return['count'] = !empty($number_a['number']) ? $number_a['number'] : 0;

		return $return;

	}

	function create_cms_image($dir, $name_data, $category){

		if ($category === 0){
			$category = '';
		}

		$name_data = strtolower($name_data);

		if (stristr($name_data, '?')){
			list($name_tmp, $par) = explode('?', $name_data, 2);
			$name_data = $name_tmp;
		}

		$temp = explode('.', $name_data);
		$extension = array_pop($temp);
		if ($extension == 'jpeg'){
			$extension = 'jpg';
		}

		$name_data = str_replace(['.jpg','.jpeg','.png','.gif','.svg','.mp4'], '', trim($name_data));

		$name_data = str_replace(array(' ','-','.'), '_', $name_data);

		$name_data = preg_replace('/[^\w\d]/ui', '', $name_data);

		$name_data = trim(preg_replace('/[_]+/ui', '_', $name_data), '_');

		$name_data = substr($name_data, 0, 30);

		$found = true;
		$str = $name_data;
		$i = 1;
		while($found){
			
			$sql = "select * from cms_image where name = ? ";
			$query = $this->db->query($sql, array($str));
			$result = $query->result_array();
			
			if (!empty($result[0]['name']) && substr_count($name_data, '/') == 1){
				
				return $dir . $name_data . '.' . $extension;
				
			} else if (!empty($result[0]['name'])){
				
				$str = $name_data . '_' . $i;
				$i = $i + 1;
				
			} else {
				
				$found = false;
				$name_data = $str;
				
			}
			
		}

		$filename = $dir . $name_data . '.' . $extension;
		
		$type = 'image';
		if ($extension == 'mp4'){
			$type = 'video';
		}

		$sql = "insert into cms_image set type = ? , name = ? , filename = ? , category = ? , hash = '', meta = '', keyword = '' ";
		$this->db->query($sql, [$type, $name_data, $filename, $category, ]);
		$insert_id = $this->db->insert_id();
		
		$hash = $this->refresh_cms_image_hash($filename);

		return ['filename' => $filename, 'type' => $type, 'cms_image_id' => $insert_id, 'hash' => $hash, ];

	}

	function delete_cms_image_by_filename($filename, $delete = true){

		if ($delete){
			$image = $this->get_cms_image_by_filename($filename);
			if (!empty($image['cms_image_id'])){
				$this->_remove_child_id_from_parent($image);
			}
		}

		if (file_exists($GLOBALS['config']['upload_path'].$filename)){
			unlink($GLOBALS['config']['upload_path'].$filename);
		}

		$name_a = pathinfo($filename);

		$image_names = $GLOBALS['config']['upload_path'].$name_a['dirname'].'/_'.$name_a['filename'].'.*.*';
		foreach(glob($image_names) as $_filename) {
			unlink($_filename);
		}
		
		$directory = is_dir($GLOBALS['config']['upload_path'].$filename.'.data');
		if (is_dir($directory)){
			_delete_directory($directory);
		}

		if ($delete){
			$sql = "delete from cms_image where filename = ? ";
			$this->db->query($sql, [$filename]);
		} else {
			$sql = "update cms_image where filename = ? set meta = '' limit 1";
			$this->db->query($sql, [$filename]);
		}
		
	}

	function update_cms_image($filename, $data){

		foreach($data as $field => $value){
			$sql = "update cms_image set ".$field." = ? where filename = ? ";
			$query = $this->db->query($sql, array($value, $filename, ));
		}

	}

	function get_cms_image_by_hash($hash){

		$sql = "select * from cms_image where hash = ? ";
		$query = $this->db->query($sql, array($hash));
		if ($query->num_rows()){
			$result = $query->row_array();
		} else {
			$result = array();
		}
		 
		return $result;

	}

	function refresh_cms_image_hash($filename){
		
		if (empty($filename) || !file_exists($GLOBALS['config']['upload_path'].$filename)){
			return '';
		}
		
		$hash = sha1_file($GLOBALS['config']['upload_path'].$filename);
		$this->update_cms_image($filename, array('hash' => $hash, ));
		
		return $hash;
		
	}

	function get_cms_image_categories(){

		$sql = "select category from cms_image group by category ";
		$query = $this->db->query($sql);
		$result = $query->result_array();

		$return = array();
		foreach($result as $row){
			if ($row['category'] == ''){
				$return[0] = 'No category';
			} else {
				$return[$row['category']] = ucfirst($row['category']);
			}
		}

		return $return;

	}

	function scrape_image($source, $prefix = 'scraped', $category = 'scraped', $fill_ext = 'jpg'){

		if (stristr($source, '?')){
			list($fn, $pr) = explode('?', $source, 2);
			$ext = pathinfo($fn, PATHINFO_EXTENSION);
			$filename = pathinfo($fn, PATHINFO_BASENAME);
		} else {
			$ext = pathinfo($source, PATHINFO_EXTENSION);
			$filename = pathinfo($source, PATHINFO_BASENAME);
		}
		
		if (empty($ext)){
			$ext = $fill_ext;
			$filename .= '.'.$fill_ext;
		}

		$return = '';

		if ($ext == 'jpg' || $ext == 'png'){
				
			$image_content = file_get_contents($source);
			if (!empty($image_content)){

				if (!file_exists($GLOBALS['config']['upload_path'].date('Y'))){
					mkdir($GLOBALS['config']['upload_path'].date('Y'));
				}

				if (!file_exists($GLOBALS['config']['upload_path'].date('Y').'/'.date('m'))){
					mkdir($GLOBALS['config']['upload_path'].date('Y').'/'.date('m'));
				}

				$hash = sha1($image_content);

				$existing = $this->get_cms_image_by_hash($hash);
				if (!empty($existing['filename'])) {
					// Same content already stored (e.g. product main image scraped before gallery) — reuse path
					if (!file_exists($GLOBALS['config']['upload_path'].$existing['filename'])) {
						file_put_contents($GLOBALS['config']['upload_path'].$existing['filename'], $image_content);
					}
					$return = $existing['filename'];
				} else {
					// create_cms_image returns array ['filename' => …] (upload callers expect that)
					$create = $this->create_cms_image(date('Y').'/'.date('m').'/', $prefix.'_'.$filename, $category);
					$new_filename = is_array($create) ? ($create['filename'] ?? '') : (string)$create;
					if ($new_filename !== '') {
						file_put_contents($GLOBALS['config']['upload_path'].$new_filename, $image_content);
						$this->update_cms_image($new_filename, ['hash' => $hash, ]);
						$return = $new_filename;
					}
				}

			}

		}

		return $return;

	}

	function gif_is_animated($filepath){

		$content = @file_get_contents($filepath);
		if ($content === false || strlen($content) < 10){
			return false;
		}

		return preg_match_all('/\x00\x2C/', $content, $matches) > 1;

	}

	function normalise_gif_original($filename){

		if (empty($filename)){
			return $filename;
		}

		$name_a = pathinfo($filename);
		if (($name_a['extension'] ?? '') !== 'gif'){
			return $filename;
		}

		$filepath = $GLOBALS['config']['upload_path'].$filename;
		if (!file_exists($filepath) || is_dir($filepath)){
			return $filename;
		}

		$image = $this->get_cms_image_by_filename($filename);
		$meta = $this->_get_image_meta_array($image);
		if (!empty($meta['converted_from'])){
			return $filename;
		}

		$lockfile = $GLOBALS['config']['base_path'].'cache/gif_normalise_lock.json';
		if (file_exists($lockfile)){
			$locked = json_decode(file_get_contents($lockfile), true);
		} else {
			$locked = [];
		}

		if (in_array($filename, $locked)){

			$time_locked = array_search($filename, $locked);
			list($time_was, $file) = explode('|', $time_locked);

			if ((time() - $time_was) <= 300){
				return $filename;
			}

			unset($locked[$time_locked]);

		}

		$time = time();
		$lock_key = $time.'|'.$filename;
		$locked[$lock_key] = $filename;
		file_put_contents($lockfile, json_encode($locked, JSON_PRETTY_PRINT));

		if ($this->gif_is_animated($filepath)){

			$this->load->model('cms/cms_video_model');
			if ($this->cms_video_model->ffmpeg_is_available()){
				$filename = $this->_convert_animated_gif($filename);
			}

		} else {

			$filename = $this->_convert_static_gif_to_png($filename);

		}

		unset($locked[$lock_key]);
		file_put_contents($lockfile, json_encode($locked, JSON_PRETTY_PRINT));

		return $filename;

	}

	function _convert_static_gif_to_png($filename){

		$gif_path = $GLOBALS['config']['upload_path'].$filename;
		$name_a = pathinfo($filename);
		$new_filename = $name_a['dirname'].'/'.$name_a['filename'].'.png';
		$png_path = $GLOBALS['config']['upload_path'].$new_filename;

		$src = @imagecreatefromgif($gif_path);
		if (empty($src)){
			return $filename;
		}

		imagealphablending($src, false);
		imagesavealpha($src, true);

		if (!imagepng($src, $png_path)){
			imagedestroy($src);
			return $filename;
		}

		imagedestroy($src);
		unlink($gif_path);

		$meta_extra = ['converted_from' => 'gif'];
		list($meta_extra['original_width'], $meta_extra['original_height']) = getimagesize($png_path);

		return $this->_update_image_filename($filename, $new_filename, $meta_extra);

	}

	function _convert_animated_gif($filename){

		$gif_path = $GLOBALS['config']['upload_path'].$filename;

		$image = $this->get_cms_image_by_filename($filename);
		$cms_image_id = !empty($image['cms_image_id']) ? $image['cms_image_id'] : 0;

		$this->load->model('cms/cms_video_model');
		$result = $this->cms_video_model->convert_gif_to_mp4($filename);

		if (empty($result)){
			return $filename;
		}

		$meta_extra = ['converted_from' => 'gif'];
		if (!empty($result['width'])){
			$meta_extra['original_width'] = $result['width'];
			$meta_extra['original_height'] = $result['height'];
		}

		unlink($gif_path);

		$new_filename = $this->_update_image_filename($filename, $result['filename'], $meta_extra);

		if ($cms_image_id){
			$this->cms_video_model->video_add_queue($cms_image_id);
		}

		return $new_filename;

	}

	function _get_image_meta_array($image){

		if (!empty($image['meta']) && is_string($image['meta'])){
			return json_decode($image['meta'], true) ?: [];
		}

		$meta = [];
		foreach (['author', 'copyright', 'description', 'original_width', 'original_height', 'converted_from'] as $key){
			if (isset($image[$key])){
				$meta[$key] = $image[$key];
			}
		}

		return $meta;

	}

	function _update_image_filename($old_filename, $new_filename, $meta_merge = []){

		$image = $this->get_cms_image_by_filename($old_filename);
		if (empty($image['cms_image_id'])){
			return $new_filename;
		}

		$meta = array_merge($this->_get_image_meta_array($image), $meta_merge);

		if (empty($meta['original_width']) && file_exists($GLOBALS['config']['upload_path'].$new_filename)){
			$size = @getimagesize($GLOBALS['config']['upload_path'].$new_filename);
			if (!empty($size)){
				list($meta['original_width'], $meta['original_height']) = $size;
			}
		}

		$type = pathinfo($new_filename, PATHINFO_EXTENSION) == 'mp4' ? 'video' : 'image';

		$this->update_cms_image($old_filename, [
				'filename' => $new_filename,
				'type' => $type,
				'meta' => json_encode($meta, JSON_PRETTY_PRINT),
		]);

		$this->load->model('cms/cms_page_panel_model');
		$this->cms_page_panel_model->swap_param_value($old_filename, $new_filename);

		$this->load->model('cms/cms_page_model');
		$pages = $this->cms_page_model->get_cms_pages();
		foreach ($pages as $page){
			if (!empty($page['image']) && $page['image'] == $old_filename){
				$this->cms_page_model->update_page($page['cms_page_id'], ['image' => $new_filename]);
			}
		}

		if (!empty($GLOBALS['cache']['images_by_filename'][$old_filename])){
			unset($GLOBALS['cache']['images_by_filename'][$old_filename]);
		}

		$this->refresh_cms_image_hash($new_filename);

		return $new_filename;

	}

	function get_cms_image_by_id($cms_image_id){

		$sql = 'select * from cms_image where cms_image_id = ? ';
		$query = $this->db->query($sql, [(int)$cms_image_id]);

		if ($query->num_rows()){
			$result = $query->row_array();
			if (!empty($result['meta'])){
				$result = array_merge($result, json_decode($result['meta'], true) ?: []);
			}
		} else {
			$result = [];
		}

		return $result;

	}

	function get_video_view_meta($filename){

		$image = $this->get_cms_image_by_filename($filename);
		if (empty($image['filename']) || !$this->is_source_video($image)){
			return null;
		}

		$meta = $this->_get_image_meta_array($image);
		if (empty($meta['parent_cms_image_id']) || empty($meta['parent_filename'])){
			return null;
		}

		if (empty($meta['crop']) || !is_array($meta['crop'])){
			return null;
		}

		$default_crop = [
			'x1' => '0.0',
			'y1' => '0.0',
			'x2' => '100.0',
			'y2' => '100.0',
		];

		$dims = $this->get_video_source_dimensions($meta['parent_filename']);

		return [
			'source_filename' => $meta['parent_filename'],
			'crop' => [
				'x1' => isset($meta['crop']['x1']) ? $meta['crop']['x1'] : $default_crop['x1'],
				'y1' => isset($meta['crop']['y1']) ? $meta['crop']['y1'] : $default_crop['y1'],
				'x2' => isset($meta['crop']['x2']) ? $meta['crop']['x2'] : $default_crop['x2'],
				'y2' => isset($meta['crop']['y2']) ? $meta['crop']['y2'] : $default_crop['y2'],
			],
			'brightness' => isset($meta['brightness']) ? $this->_format_level_value($meta['brightness']) : '0.50',
			'contrast' => isset($meta['contrast']) ? $this->_format_level_value($meta['contrast']) : '0.50',
			'overlay_colour' => !empty($meta['overlay_colour']) ? $this->_normalise_overlay_colour($meta['overlay_colour']) : '#000000',
			'overlay_opacity' => isset($meta['overlay_opacity']) ? $this->_format_level_value($meta['overlay_opacity']) : '0.00',
			'rotation' => isset($meta['rotation']) ? (string)(int)$this->_format_rotation_value($meta['rotation']) : '0',
			'source_width' => $dims['width'],
			'source_height' => $dims['height'],
		];

	}

	function get_video_source_dimensions($filename){

		$default = ['width' => 1920, 'height' => 1080, ];

		if (empty($filename)){
			return $default;
		}

		$this->load->model('cms/cms_video_model');

		$video_paths = [
			$GLOBALS['config']['upload_path'].$filename,
			$GLOBALS['config']['upload_path'].$filename.'.data/fallback.mp4',
		];

		foreach ($video_paths as $video_path){
			if (file_exists($video_path) && !is_dir($video_path) && $this->cms_video_model->ffmpeg_is_available()){
				try {
					$metadata = $this->cms_video_model->get_video_metadata($video_path);
					if (!empty($metadata['width']) && !empty($metadata['height'])){
						return [
							'width' => (int)$metadata['width'],
							'height' => (int)$metadata['height'],
						];
					}
				} catch (Exception $e){
				}
			}
		}

		$cover_path = $GLOBALS['config']['upload_path'].$filename.'.data/cover.jpg';
		if (file_exists($cover_path)){
			$size = getimagesize($cover_path);
			if (!empty($size[0]) && !empty($size[1])){
				$ref_width = 1920;
				return [
					'width' => $ref_width,
					'height' => (int)round($ref_width * $size[1] / $size[0]),
				];
			}
		}

		return $default;

	}

	function is_source_video($image){

		if (!empty($image['type']) && $image['type'] === 'video'){
			return true;
		}

		if (!empty($image['filename'])){
			return strtolower(pathinfo($image['filename'], PATHINFO_EXTENSION)) === 'mp4';
		}

		return false;

	}

	function _save_cms_image_video_child($source, $crop, $opened_meta, $view_meta, $opened_db_meta, $is_child_edit, $opened_filename = ''){

		if ($is_child_edit){

			$child_filename = $opened_filename;
			$child_cover_path = $GLOBALS['config']['upload_path'].$child_filename.'.data/cover.jpg';
			$child_cover_exists = file_exists($child_cover_path);
			$crop_unchanged = !empty($opened_db_meta['crop']) && $this->_crop_values_match($crop, $opened_db_meta['crop']);
			$adjust_unchanged = $this->_view_adjust_unchanged($opened_db_meta, $view_meta);

			if ($crop_unchanged && $adjust_unchanged && $child_cover_exists){

				$this->_update_child_meta($child_filename, $source, $crop, $opened_db_meta, $opened_meta, $view_meta);
				$this->_clear_image_cache($child_filename);

				return '';

			}

			if (!$this->_export_cms_image_video_child_cover($source['filename'], $child_filename, $crop, $view_meta)){
				return '';
			}

			$this->_update_child_meta($child_filename, $source, $crop, $opened_db_meta, $opened_meta, $view_meta);
			$this->_clear_image_cache($child_filename);

			return '';

		}

		$name_a = pathinfo($source['filename']);
		$dir = $name_a['dirname'].'/';
		$version = $this->_get_next_child_version($source['filename']);
		$create = $this->create_cms_image($dir, $name_a['filename'].'_v'.$version.'.'.$name_a['extension'], $source['category']);

		if (empty($create['filename'])){
			return '';
		}

		$child_filename = $create['filename'];

		if (!$this->_export_cms_image_video_child_cover($source['filename'], $child_filename, $crop, $view_meta)){
			if (!empty($create['cms_image_id'])){
				$sql = 'delete from cms_image where cms_image_id = ? ';
				$this->db->query($sql, [(int)$create['cms_image_id']]);
			}
			return '';
		}

		$this->_update_child_meta($child_filename, $source, $crop, [], $opened_meta, $view_meta);

		if (!empty($create['cms_image_id'])){
			$this->_add_child_id_to_parent((int)$source['cms_image_id'], (int)$create['cms_image_id']);
		}

		$this->_clear_image_cache($child_filename);

		return $child_filename;

	}

	function save_cms_image_child($source_cms_image_id, $crop, $opened_filename = '', $opened_meta = [], $view_meta = []){

		$source = $this->get_cms_image_by_id($source_cms_image_id);
		if (empty($source['cms_image_id']) || empty($source['filename'])){
			return '';
		}

		$crop = [
			'x1' => $this->_format_crop_value($crop['x1']),
			'y1' => $this->_format_crop_value($crop['y1']),
			'x2' => $this->_format_crop_value($crop['x2']),
			'y2' => $this->_format_crop_value($crop['y2']),
		];

		if ($this->_is_full_crop($crop)){
			return '';
		}

		$opened = [];
		$opened_db_meta = [];
		if (!empty($opened_filename)){
			$opened = $this->get_cms_image_by_filename($opened_filename);
			$opened_db_meta = $this->_get_image_meta_array($opened);
		}

		$is_child_edit = !empty($opened_db_meta['parent_cms_image_id'])
			&& (int)$opened_db_meta['parent_cms_image_id'] === (int)$source['cms_image_id']
			&& $opened_filename !== $source['filename'];

		if ($this->is_source_video($source)){
			return $this->_save_cms_image_video_child($source, $crop, $opened_meta, $view_meta, $opened_db_meta, $is_child_edit, $opened_filename);
		}

		$adjust = $this->_normalise_view_adjust($view_meta);

		if ($is_child_edit){

			$child_filename = $opened_filename;
			$child_path = $GLOBALS['config']['upload_path'].$child_filename;
			$child_file_exists = file_exists($child_path) && !is_dir($child_path);
			$crop_unchanged = !empty($opened_db_meta['crop']) && $this->_crop_values_match($crop, $opened_db_meta['crop']);
			$adjust_unchanged = $this->_view_adjust_unchanged($opened_db_meta, $view_meta);

			if ($crop_unchanged && $adjust_unchanged && $child_file_exists){

				$this->_update_child_meta($child_filename, $source, $crop, $opened_db_meta, $opened_meta, $view_meta);
				$this->_clear_image_cache($child_filename);

				return '';

			}

			$this->_delete_cms_image_derivatives($child_filename);
			if (file_exists($GLOBALS['config']['upload_path'].$child_filename)){
				unlink($GLOBALS['config']['upload_path'].$child_filename);
			}

			if (!$this->_export_cms_image_child_file($source['filename'], $child_filename, $crop, $adjust)){
				return '';
			}

			$this->_update_child_meta($child_filename, $source, $crop, $opened_db_meta, $opened_meta, $view_meta);
			$this->_clear_image_cache($child_filename);

			return '';

		}

		$name_a = pathinfo($source['filename']);
		$dir = $name_a['dirname'].'/';
		$version = $this->_get_next_child_version($source['filename']);
		$create = $this->create_cms_image($dir, $name_a['filename'].'_v'.$version.'.'.$name_a['extension'], $source['category']);
		$child_filename = $create['filename'];

		if (!$this->_export_cms_image_child_file($source['filename'], $child_filename, $crop, $adjust)){
			if (!empty($create['cms_image_id'])){
				$sql = 'delete from cms_image where cms_image_id = ? ';
				$this->db->query($sql, [(int)$create['cms_image_id']]);
			}
			return '';
		}

		$this->_update_child_meta($child_filename, $source, $crop, [], $opened_meta, $view_meta);
		if (!empty($create['cms_image_id'])){
			$this->_add_child_id_to_parent((int)$source['cms_image_id'], (int)$create['cms_image_id']);
		}
		$this->_clear_image_cache($child_filename);

		return $child_filename;

	}

	function _get_next_child_version($source_filename){

		$name_a = pathinfo($source_filename);
		$base = $name_a['filename'];
		$dir = $name_a['dirname'];
		$ext = $name_a['extension'];

		$sql = 'select filename from cms_image where filename like ? ';
		$query = $this->db->query($sql, [$dir.'/'.$base.'_v%.'.$ext]);

		$max = 0;
		foreach ($query->result_array() as $row){
			if (preg_match('/_v(\d+)\.'.preg_quote($ext, '/').'$/', $row['filename'], $match)){
				$max = max($max, (int)$match[1]);
			}
		}

		return $max + 1;

	}

	function _update_child_meta($child_filename, $source, $crop, $existing_meta, $opened_meta, $view_meta = []){

		$source_meta = $this->_get_image_meta_array($source);
		$child_meta = array_merge($existing_meta, [
			'parent_cms_image_id' => (int)$source['cms_image_id'],
			'parent_filename' => $source['filename'],
			'crop' => $crop,
		]);

		foreach (['author', 'copyright', 'description'] as $key){
			if (isset($opened_meta[$key]) && $opened_meta[$key] !== ''){
				$child_meta[$key] = $opened_meta[$key];
			} else if (!empty($source_meta[$key])){
				$child_meta[$key] = $source_meta[$key];
			}
		}

		if (isset($view_meta['zoom'])){
			$child_meta['zoom'] = $this->_format_zoom_value($view_meta['zoom']);
		}
		if (isset($view_meta['pan_x'])){
			$child_meta['pan_x'] = (float)$view_meta['pan_x'];
		}
		if (isset($view_meta['pan_y'])){
			$child_meta['pan_y'] = (float)$view_meta['pan_y'];
		}
		if (isset($view_meta['brightness'])){
			$child_meta['brightness'] = $this->_format_level_value($view_meta['brightness']);
		}
		if (isset($view_meta['contrast'])){
			$child_meta['contrast'] = $this->_format_level_value($view_meta['contrast']);
		}
		if (isset($view_meta['overlay_colour'])){
			$child_meta['overlay_colour'] = $this->_normalise_overlay_colour($view_meta['overlay_colour']);
		}
		if (isset($view_meta['overlay_opacity'])){
			$child_meta['overlay_opacity'] = $this->_format_level_value($view_meta['overlay_opacity']);
		}
		if (isset($view_meta['rotation'])){
			$child_meta['rotation'] = $this->_format_rotation_value($view_meta['rotation']);
		}
		if (isset($view_meta['rotation_fixed'])){
			$child_meta['rotation_fixed'] = $view_meta['rotation_fixed'] === '0' ? '0' : '1';
		}

		$child_path = $GLOBALS['config']['upload_path'].$child_filename;
		if (file_exists($child_path) && !is_dir($child_path)){
			$size = getimagesize($child_path);
			if (!empty($size)){
				$child_meta['original_width'] = $size[0];
				$child_meta['original_height'] = $size[1];
			}
		} else if ($this->is_source_video($source)){
			$dims = $this->get_video_source_dimensions($source['filename']);
			$child_meta['original_width'] = $dims['width'];
			$child_meta['original_height'] = $dims['height'];
		}

		$this->update_cms_image($child_filename, [
			'meta' => json_encode($child_meta),
			'category' => $source['category'],
		]);
		$this->refresh_cms_image_hash($child_filename);

	}

	function _crop_values_match($crop_a, $crop_b){

		foreach (['x1', 'y1', 'x2', 'y2'] as $key){
			if (abs((float)$crop_a[$key] - (float)$crop_b[$key]) > 0.05){
				return false;
			}
		}

		return true;

	}

	function _format_crop_value($value){

		return number_format((float)$value, 1, '.', '');

	}

	function _format_zoom_value($value){

		return number_format((float)$value, 1, '.', '');

	}

	function _format_level_value($value){

		return number_format((float)$value, 2, '.', '');

	}

	function _format_rotation_value($value){

		return (string)(int)round(max(-180, min(180, (float)$value)));

	}

	function _normalise_rotation($value){

		return (int)round(max(-180, min(180, (float)$value)));

	}

	function _normalise_view_adjust($view_meta){

		return [
			'brightness' => isset($view_meta['brightness']) ? (float)$view_meta['brightness'] : 0.5,
			'contrast' => isset($view_meta['contrast']) ? (float)$view_meta['contrast'] : 0.5,
			'overlay_colour' => $this->_normalise_overlay_colour(isset($view_meta['overlay_colour']) ? $view_meta['overlay_colour'] : '#000000'),
			'overlay_opacity' => isset($view_meta['overlay_opacity']) ? (float)$view_meta['overlay_opacity'] : 0.0,
			'rotation' => $this->_normalise_rotation(isset($view_meta['rotation']) ? $view_meta['rotation'] : 0),
		];

	}

	function _normalise_overlay_colour($colour){

		$colour = trim((string)$colour);
		if (preg_match('/^#([0-9A-Fa-f]{6})$/', $colour, $match)){
			return '#'.strtoupper($match[1]);
		}

		return '#000000';

	}

	function _level_to_filter_amount($value){

		$ui = max(0, min(1, (float)$value));
		if ($ui <= 0.5){
			return $ui / 0.5;
		}
		if ($ui <= 0.7){
			return 1 + (($ui - 0.5) / 0.2) * (0.76 / 3);
		}
		if ($ui <= 0.9){
			$n_07 = 1 + (0.76 / 3);
			$n_09 = 1.76 + (0.01 / 0.2) * 18.24;
			return $n_07 + (($ui - 0.7) / 0.2) * ($n_09 - $n_07);
		}

		$n_09 = 1.76 + (0.01 / 0.2) * 18.24;
		return $n_09 + (($ui - 0.9) / 0.1) * (20 - $n_09);

	}

	function _contrast_to_filter_amount($value){

		$ui = max(0, min(1, (float)$value));
		if ($ui <= 0.5){
			return $ui / 0.5;
		}
		if ($ui <= 0.8){
			return 1 + (($ui - 0.5) / 0.3) * 0.6;
		}

		return 1.6 + (($ui - 0.8) / 0.2) * 1.4;

	}

	function _level_values_match($a, $b){

		return abs((float)$a - (float)$b) <= 0.005;

	}

	function _view_adjust_unchanged($stored_meta, $view_meta){

		$stored = $this->_normalise_view_adjust($stored_meta);
		$new = $this->_normalise_view_adjust($view_meta);

		return $this->_level_values_match($stored['brightness'], $new['brightness'])
			&& $this->_level_values_match($stored['contrast'], $new['contrast'])
			&& $stored['overlay_colour'] === $new['overlay_colour']
			&& $this->_level_values_match($stored['overlay_opacity'], $new['overlay_opacity'])
			&& $this->_rotation_values_match($stored['rotation'], $new['rotation']);

	}

	function _rotation_values_match($a, $b){

		return abs((int)$a - (int)$b) <= 0;

	}

	function _parse_overlay_hex($hex){

		$hex = $this->_normalise_overlay_colour($hex);
		if (!preg_match('/^#([0-9A-F]{2})([0-9A-F]{2})([0-9A-F]{2})$/', $hex, $match)){
			return false;
		}

		return [
			(int)hexdec($match[1]),
			(int)hexdec($match[2]),
			(int)hexdec($match[3]),
		];

	}

	function _apply_image_overlay($image, $colour, $opacity){

		if ($this->_level_values_match($opacity, 0)){
			return;
		}

		$rgb = $this->_parse_overlay_hex($colour);
		if (empty($rgb)){
			return;
		}

		$op = max(0, min(1, (float)$opacity));
		$or = $rgb[0];
		$og = $rgb[1];
		$ob = $rgb[2];
		$width = imagesx($image);
		$height = imagesy($image);

		for ($y = 0; $y < $height; $y++){
			for ($x = 0; $x < $width; $x++){
				$rgba = imagecolorat($image, $x, $y);
				$a = ($rgba >> 24) & 0x7F;
				$r = ($rgba >> 16) & 0xFF;
				$g = ($rgba >> 8) & 0xFF;
				$b = $rgba & 0xFF;

				if ($a >= 127){
					$r = 255;
					$g = 255;
					$b = 255;
				}

				$r = max(0, min(255, (int)round($r * (1 - $op) + $or * $op)));
				$g = max(0, min(255, (int)round($g * (1 - $op) + $og * $op)));
				$b = max(0, min(255, (int)round($b * (1 - $op) + $ob * $op)));
				$new_a = $a >= 127 ? 0 : $a;

				$color = imagecolorallocatealpha($image, $r, $g, $b, $new_a);
				imagesetpixel($image, $x, $y, $color);
			}
		}

	}

	function _apply_image_brightness_contrast($image, $brightness, $contrast){

		if ($this->_level_values_match($brightness, 0.5) && $this->_level_values_match($contrast, 0.5)){
			return;
		}

		$b_n = $this->_level_to_filter_amount($brightness);
		$c_n = $this->_contrast_to_filter_amount($contrast);
		$width = imagesx($image);
		$height = imagesy($image);

		for ($y = 0; $y < $height; $y++){
			for ($x = 0; $x < $width; $x++){
				$rgba = imagecolorat($image, $x, $y);
				$a = ($rgba >> 24) & 0x7F;
				$r = ($rgba >> 16) & 0xFF;
				$g = ($rgba >> 8) & 0xFF;
				$b = $rgba & 0xFF;

				$r = max(0, min(255, (int)round($r * $b_n)));
				$g = max(0, min(255, (int)round($g * $b_n)));
				$b = max(0, min(255, (int)round($b * $b_n)));

				$r = max(0, min(255, (int)round(($r - 128) * $c_n + 128)));
				$g = max(0, min(255, (int)round(($g - 128) * $c_n + 128)));
				$b = max(0, min(255, (int)round(($b - 128) * $c_n + 128)));

				$color = imagecolorallocatealpha($image, $r, $g, $b, $a);
				imagesetpixel($image, $x, $y, $color);
			}
		}

	}

	function _is_full_crop($crop){

		return abs((float)$crop['x1']) < 0.05
			&& abs((float)$crop['y1']) < 0.05
			&& abs((float)$crop['x2'] - 100) < 0.05
			&& abs((float)$crop['y2'] - 100) < 0.05;

	}

	function _delete_cms_image_derivatives($filename){

		$name_a = pathinfo($filename);
		$image_names = $GLOBALS['config']['upload_path'].$name_a['dirname'].'/_'.$name_a['filename'].'.*.*';

		foreach (glob($image_names) as $_filename){
			unlink($_filename);
		}

	}

	function _sample_source_bilinear($src, $sx, $sy, $imagetype, $ow, $oh){

		if ($sx < 0 || $sy < 0 || $sx >= $ow || $sy >= $oh){
			if ($imagetype == IMAGETYPE_PNG){
				return [0, 0, 0, 127];
			}

			return [255, 255, 255, 0];
		}

		$x0 = (int)floor($sx);
		$y0 = (int)floor($sy);
		$x1 = min($ow - 1, $x0 + 1);
		$y1 = min($oh - 1, $y0 + 1);
		$fx = $sx - $x0;
		$fy = $sy - $y0;

		$decode = function($rgba){
			return [
				($rgba >> 16) & 0xFF,
				($rgba >> 8) & 0xFF,
				$rgba & 0xFF,
				($rgba >> 24) & 0x7F,
			];
		};

		$c00 = $decode(imagecolorat($src, $x0, $y0));
		$c10 = $decode(imagecolorat($src, $x1, $y0));
		$c01 = $decode(imagecolorat($src, $x0, $y1));
		$c11 = $decode(imagecolorat($src, $x1, $y1));

		$channels = [];
		for ($i = 0; $i < 4; $i++){
			$channels[$i] = (1 - $fx) * (1 - $fy) * $c00[$i]
				+ $fx * (1 - $fy) * $c10[$i]
				+ (1 - $fx) * $fy * $c01[$i]
				+ $fx * $fy * $c11[$i];
		}

		return [
			max(0, min(255, (int)round($channels[0]))),
			max(0, min(255, (int)round($channels[1]))),
			max(0, min(255, (int)round($channels[2]))),
			max(0, min(127, (int)round($channels[3]))),
		];

	}

	function _crop_rotation_centre($crop, $ow, $oh){

		$left_pct = min((float)$crop['x1'], (float)$crop['x2']);
		$right_pct = max((float)$crop['x1'], (float)$crop['x2']);
		$top_pct = min((float)$crop['y1'], (float)$crop['y2']);
		$bottom_pct = max((float)$crop['y1'], (float)$crop['y2']);

		return [
			'ccx' => (($left_pct + $right_pct) / 200) * $ow,
			'ccy' => (($top_pct + $bottom_pct) / 200) * $oh,
		];

	}

	function _fill_crop_with_rotation($dst, $src, $crop, $rotation_deg, $imagetype, $ow, $oh){

		if ($this->_fill_crop_with_rotation_affine($dst, $src, $crop, $rotation_deg, $imagetype, $ow, $oh)){
			return;
		}

		if ($this->_fill_crop_with_rotation_gd_rotate($dst, $src, $crop, $rotation_deg, $imagetype, $ow, $oh)){
			return;
		}

		$this->_fill_crop_with_rotation_pixel($dst, $src, $crop, $rotation_deg, $imagetype, $ow, $oh);

	}

	function _fill_crop_with_rotation_affine($dst, $src, $crop, $rotation_deg, $imagetype, $ow, $oh){

		if (!function_exists('imageaffinecopy')){
			return false;
		}

		$cw = imagesx($dst);
		$ch = imagesy($dst);
		$centre = $this->_crop_rotation_centre($crop, $ow, $oh);
		$ccx = $centre['ccx'];
		$ccy = $centre['ccy'];

		$theta = deg2rad((float)$rotation_deg);
		$cos_t = cos($theta);
		$sin_t = sin($theta);

		$matrix = [
			$cos_t,
			-$sin_t,
			$sin_t,
			$cos_t,
			$ccx + (0.5 * $cos_t) - (($cw / 2) * $cos_t) - (0.5 * $sin_t) + (($ch / 2) * $sin_t),
			$ccy + (0.5 * $sin_t) - (($cw / 2) * $sin_t) + (0.5 * $cos_t) - (($ch / 2) * $cos_t),
		];

		if (function_exists('imagesetinterpolation')){
			imagesetinterpolation($dst, IMG_BILINEAR_FIXED);
		}

		return imageaffinecopy($dst, $src, 0, 0, 0, 0, $ow, $oh, $matrix);

	}

	function _fill_crop_with_rotation_gd_rotate($dst, $src, $crop, $rotation_deg, $imagetype, $ow, $oh){

		if (!function_exists('imagerotate')){
			return false;
		}

		$cw = imagesx($dst);
		$ch = imagesy($dst);
		$centre = $this->_crop_rotation_centre($crop, $ow, $oh);
		$ccx = $centre['ccx'];
		$ccy = $centre['ccy'];

		$pad_w = max(1, (int)ceil(2 * max($ccx, $ow - $ccx)));
		$pad_h = max(1, (int)ceil(2 * max($ccy, $oh - $ccy)));

		$padded = imagecreatetruecolor($pad_w, $pad_h);
		if (empty($padded)){
			return false;
		}

		if ($imagetype == IMAGETYPE_PNG){
			imagealphablending($padded, false);
			imagesavealpha($padded, true);
			$bg = imagecolorallocatealpha($padded, 0, 0, 0, 127);
			imagefill($padded, 0, 0, $bg);
		} else {
			$bg = imagecolorallocate($padded, 255, 255, 255);
			imagefill($padded, 0, 0, $bg);
		}

		$off_x = (int)round(($pad_w / 2) - $ccx);
		$off_y = (int)round(($pad_h / 2) - $ccy);

		imagecopy($padded, $src, $off_x, $off_y, 0, 0, $ow, $oh);

		$rotated = imagerotate($padded, -(float)$rotation_deg, $bg);
		imagedestroy($padded);

		if (empty($rotated)){
			return false;
		}

		$rot_w = imagesx($rotated);
		$rot_h = imagesy($rotated);
		$crop_x = (int)round(($rot_w / 2) - ($cw / 2));
		$crop_y = (int)round(($rot_h / 2) - ($ch / 2));

		imagealphablending($dst, true);
		imagecopy($dst, $rotated, 0, 0, $crop_x, $crop_y, $cw, $ch);
		imagedestroy($rotated);

		return true;

	}

	function _fill_crop_with_rotation_pixel($dst, $src, $crop, $rotation_deg, $imagetype, $ow, $oh){

		$cw = imagesx($dst);
		$ch = imagesy($dst);
		$centre = $this->_crop_rotation_centre($crop, $ow, $oh);
		$ccx = $centre['ccx'];
		$ccy = $centre['ccy'];

		$theta = deg2rad((float)$rotation_deg);
		$cos_t = cos($theta);
		$sin_t = sin($theta);

		for ($dy = 0; $dy < $ch; $dy++){
			for ($dx = 0; $dx < $cw; $dx++){
				$rcx = $dx + 0.5 - ($cw / 2);
				$rcy = $dy + 0.5 - ($ch / 2);

				$sx = $ccx + ($rcx * $cos_t) - ($rcy * $sin_t);
				$sy = $ccy + ($rcx * $sin_t) + ($rcy * $cos_t);

				$sample = $this->_sample_source_bilinear($src, $sx, $sy, $imagetype, $ow, $oh);
				$color = imagecolorallocatealpha($dst, $sample[0], $sample[1], $sample[2], $sample[3]);
				imagesetpixel($dst, $dx, $dy, $color);
			}
		}

	}

	function _resolve_parent_video_path($parent_filename){

		$source_path = $GLOBALS['config']['upload_path'].$parent_filename;
		if (file_exists($source_path) && !is_dir($source_path)){
			return $source_path;
		}

		$fallback_path = $GLOBALS['config']['upload_path'].$parent_filename.'.data/fallback.mp4';
		if (file_exists($fallback_path) && !is_dir($fallback_path)){
			return $fallback_path;
		}

		return '';

	}

	function _export_cms_image_video_child_cover($parent_filename, $child_filename, $crop, $view_meta){

		$this->load->model('cms/cms_video_model');

		if (!$this->cms_video_model->ffmpeg_is_available()){
			return true;
		}

		$video_path = $this->_resolve_parent_video_path($parent_filename);
		if (empty($video_path)){
			return false;
		}

		$child_data_dir = $GLOBALS['config']['upload_path'].$child_filename.'.data';
		if (!is_dir($child_data_dir)){
			mkdir($child_data_dir, 0777, true);
		}

		$frame_path = $child_data_dir.'/_export_frame.jpg';
		$cover_path = $child_data_dir.'/cover.jpg';

		if (!$this->cms_video_model->extract_cover_frame($video_path, $frame_path, 0.1)){
			return false;
		}

		$adjust = $this->_normalise_view_adjust($view_meta);
		$result = $this->_export_cms_image_child_cover_from_paths($frame_path, $cover_path, $crop, $adjust);

		if (file_exists($frame_path)){
			unlink($frame_path);
		}

		return $result;

	}

	function _export_cms_image_child_file($source_filename, $target_filename, $crop, $adjust = []){

		$source_path = $GLOBALS['config']['upload_path'].$source_filename;
		if (!file_exists($source_path)){
			return false;
		}

		$target_path = $GLOBALS['config']['upload_path'].$target_filename;

		return $this->_export_cms_image_child_cover_from_paths($source_path, $target_path, $crop, $adjust);

	}

	function _export_cms_image_child_cover_from_paths($source_path, $target_path, $crop, $adjust = []){

		if (!file_exists($source_path)){
			return false;
		}

		$size = getimagesize($source_path);
		if (empty($size[0]) || empty($size[1])){
			return false;
		}

		$ow = $size[0];
		$oh = $size[1];

		$left_pct = min((float)$crop['x1'], (float)$crop['x2']);
		$right_pct = max((float)$crop['x1'], (float)$crop['x2']);
		$top_pct = min((float)$crop['y1'], (float)$crop['y2']);
		$bottom_pct = max((float)$crop['y1'], (float)$crop['y2']);

		$cw = max(1, (int)round(($right_pct - $left_pct) / 100 * $ow));
		$ch = max(1, (int)round(($bottom_pct - $top_pct) / 100 * $oh));
		$src_dest_x = (int)round((0 - $left_pct) / 100 * $ow);
		$src_dest_y = (int)round((0 - $top_pct) / 100 * $oh);

		if (!function_exists('imagecreatetruecolor')){
			return false;
		}

		$imagetype = exif_imagetype($source_path);
		if ($imagetype == IMAGETYPE_JPEG){
			$src = imagecreatefromjpeg($source_path);
		} else if ($imagetype == IMAGETYPE_PNG){
			ob_start();
			$src = imagecreatefrompng($source_path);
			ob_end_clean();
		} else {
			return false;
		}

		if (empty($src)){
			return false;
		}

		$dst = imagecreatetruecolor($cw, $ch);

		if ($imagetype == IMAGETYPE_PNG){
			imagealphablending($dst, false);
			imagesavealpha($dst, true);
			$transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
			imagefill($dst, 0, 0, $transparent);
		} else {
			$white = imagecolorallocate($dst, 255, 255, 255);
			imagefill($dst, 0, 0, $white);
		}

		$adjust = $this->_normalise_view_adjust($adjust);

		if ($adjust['rotation'] === 0){
			imagecopy($dst, $src, $src_dest_x, $src_dest_y, 0, 0, $ow, $oh);
		} else {
			$this->_fill_crop_with_rotation($dst, $src, $crop, $adjust['rotation'], $imagetype, $ow, $oh);
		}
		$this->_apply_image_brightness_contrast($dst, $adjust['brightness'], $adjust['contrast']);
		$this->_apply_image_overlay($dst, $adjust['overlay_colour'], $adjust['overlay_opacity']);

		$target_dir = pathinfo($target_path, PATHINFO_DIRNAME);
		if (!is_dir($target_dir)){
			mkdir($target_dir, 0777, true);
		}

		$target_ext = strtolower(pathinfo($target_path, PATHINFO_EXTENSION));
		if ($target_ext === 'png'){
			imagesavealpha($dst, true);
			imagepng($dst, $target_path);
		} else {
			imagejpeg($dst, $target_path, !empty($GLOBALS['config']['images_quality']) ? $GLOBALS['config']['images_quality'] : 85);
		}

		imagedestroy($src);
		imagedestroy($dst);

		return true;

	}

	function _clear_image_cache($filename){

		if (!empty($GLOBALS['cache']['images_by_filename'][$filename])){
			unset($GLOBALS['cache']['images_by_filename'][$filename]);
		}

	}

	function _enrich_cms_images_usage(&$images, $page_image_counts){

		$parent_child_ids = [];
		$all_child_ids = [];

		foreach ($images as $key => $image){

			$meta = [];
			if (!empty($image['meta']) && is_string($image['meta'])){
				$meta = json_decode($image['meta'], true) ?: [];
			}

			$is_child = !empty($meta['parent_cms_image_id']);
			$images[$key]['is_child'] = $is_child;
			$images[$key]['children_number'] = 0;
			$images[$key]['number'] = (int)$images[$key]['number'];

			if ($is_child){
				$images[$key]['usage_label'] = '('.$images[$key]['number'].')';
				continue;
			}

			$child_ids = [];
			if (!empty($meta['child_ids']) && is_array($meta['child_ids'])){
				$child_ids = array_map('intval', $meta['child_ids']);
			} else {
				$child_ids = $this->_rebuild_child_ids_if_needed((int)$image['cms_image_id'], $image['filename']);
			}

			if (!empty($child_ids)){
				$parent_child_ids[(int)$image['cms_image_id']] = $child_ids;
				$all_child_ids = array_merge($all_child_ids, $child_ids);
			}

			$images[$key]['usage_label'] = (string)$images[$key]['number'];

		}

		if (empty($all_child_ids)){
			return;
		}

		$all_child_ids = array_values(array_unique($all_child_ids));
		$placeholders = implode(',', array_fill(0, count($all_child_ids), '?'));
		$sql = 'select cms_image_id, filename from cms_image where cms_image_id in ('.$placeholders.') ';
		$query = $this->db->query($sql, $all_child_ids);

		$child_id_to_filename = [];
		$child_filenames = [];
		foreach ($query->result_array() as $row){
			$child_id_to_filename[(int)$row['cms_image_id']] = $row['filename'];
			$child_filenames[] = $row['filename'];
		}

		$usage_map = $this->_get_filenames_usage_map($child_filenames, $page_image_counts);

		foreach ($images as $key => $image){

			if (!empty($image['is_child'])){
				continue;
			}

			if (empty($parent_child_ids[(int)$image['cms_image_id']])){
				continue;
			}

			$children_sum = 0;
			foreach ($parent_child_ids[(int)$image['cms_image_id']] as $child_id){
				if (!empty($child_id_to_filename[$child_id])){
					$children_sum += $usage_map[$child_id_to_filename[$child_id]] ?? 0;
				}
			}

			$images[$key]['children_number'] = $children_sum;
			$images[$key]['usage_label'] = $images[$key]['number'].' ('.$children_sum.')';

		}

	}

	function _get_filenames_usage_map($filenames, $page_image_counts){

		$map = [];
		if (empty($filenames)){
			return $map;
		}

		foreach ($filenames as $filename){
			$map[$filename] = $page_image_counts[$filename] ?? 0;
		}

		$placeholders = implode(',', array_fill(0, count($filenames), '?'));
		$sql = 'select value as filename, count(*) as cnt from cms_page_panel_param where value in ('.$placeholders.') group by value ';
		$query = $this->db->query($sql, $filenames);

		foreach ($query->result_array() as $row){
			$map[$row['filename']] = ($map[$row['filename']] ?? 0) + (int)$row['cnt'];
		}

		return $map;

	}

	function _rebuild_child_ids_if_needed($parent_cms_image_id, $parent_filename){

		$name_a = pathinfo($parent_filename);
		$sql = 'select cms_image_id from cms_image where filename like ? ';
		$query = $this->db->query($sql, [$name_a['dirname'].'/'.$name_a['filename'].'_v%.'.$name_a['extension']]);

		$child_ids = [];
		foreach ($query->result_array() as $row){
			$child_ids[] = (int)$row['cms_image_id'];
		}

		if (empty($child_ids)){
			return [];
		}

		return $this->_rebuild_child_ids($parent_cms_image_id);

	}

	function _rebuild_child_ids($parent_cms_image_id){

		$parent = $this->get_cms_image_by_id($parent_cms_image_id);
		if (empty($parent['filename'])){
			return [];
		}

		$name_a = pathinfo($parent['filename']);
		$sql = 'select cms_image_id from cms_image where filename like ? ';
		$query = $this->db->query($sql, [$name_a['dirname'].'/'.$name_a['filename'].'_v%.'.$name_a['extension']]);

		$child_ids = [];
		foreach ($query->result_array() as $row){
			$child_ids[] = (int)$row['cms_image_id'];
		}

		if (!empty($child_ids)){
			$meta = $this->_get_image_meta_array($parent);
			$meta['child_ids'] = $child_ids;
			$this->update_cms_image($parent['filename'], ['meta' => json_encode($meta, JSON_PRETTY_PRINT)]);
			$this->_clear_image_cache($parent['filename']);
		}

		return $child_ids;

	}

	function _add_child_id_to_parent($parent_cms_image_id, $child_cms_image_id){

		$parent = $this->get_cms_image_by_id($parent_cms_image_id);
		if (empty($parent['cms_image_id'])){
			return;
		}

		$meta = $this->_get_image_meta_array($parent);
		if (empty($meta['child_ids']) || !is_array($meta['child_ids'])){
			$meta['child_ids'] = [];
		}

		$child_cms_image_id = (int)$child_cms_image_id;
		if (!in_array($child_cms_image_id, $meta['child_ids'], true)){
			$meta['child_ids'][] = $child_cms_image_id;
			$this->update_cms_image($parent['filename'], ['meta' => json_encode($meta, JSON_PRETTY_PRINT)]);
			$this->_clear_image_cache($parent['filename']);
		}

	}

	function _remove_child_id_from_parent($child_image){

		$child_meta = $this->_get_image_meta_array($child_image);
		if (empty($child_meta['parent_cms_image_id'])){
			return;
		}

		$parent = $this->get_cms_image_by_id($child_meta['parent_cms_image_id']);
		if (empty($parent['cms_image_id'])){
			return;
		}

		$meta = $this->_get_image_meta_array($parent);
		if (empty($meta['child_ids']) || !is_array($meta['child_ids'])){
			return;
		}

		$child_id = (int)$child_image['cms_image_id'];
		$meta['child_ids'] = array_values(array_filter($meta['child_ids'], function($id) use ($child_id){
			return (int)$id !== $child_id;
		}));

		$this->update_cms_image($parent['filename'], ['meta' => json_encode($meta, JSON_PRETTY_PRINT)]);
		$this->_clear_image_cache($parent['filename']);

	}

}