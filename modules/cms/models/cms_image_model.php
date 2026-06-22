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
		
		foreach($return['result'] as $key => $image){
			foreach($pages as $page){
					
				if (!empty($page['image']) && $image['filename'] == $page['image']){
					$return['result'][$key]['number'] += 1;
				}
			
			}
		}
		
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
				if (!empty($existing) && !file_exists($existing['filename'])){
					file_put_contents($GLOBALS['config']['upload_path'].$existing['filename'], $image_content);
					$return = $existing['filename'];
				} else if (empty($existing)) {
					$return = $this->create_cms_image(date('Y').'/'.date('m').'/', $prefix.'_'.$filename, $category);
					file_put_contents($GLOBALS['config']['upload_path'].$return, $image_content);
					$this->update_cms_image($return, ['hash' => $hash, ]);
				}

			}

		}

		return $return;

	}

	function get_image_url($image, $params){
		
		include_once $GLOBALS['config']['base_path'].'application/helpers/image_optimiser_helper.php';

		return $GLOBALS['config']['upload_url']._iw($image, $params)['image'];

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

}