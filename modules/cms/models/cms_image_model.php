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
		$search = str_replace(array("'", '"', ), '', $search); // preg_replace("/[^[:alnum:][:space:]_-]/ui", '', $search));
		$category = trim(preg_replace("/[^[:alnum:]_]/ui", '', $category));

		// build where
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
		
		// add page images to count
		$pages = $this->cms_page_model->get_cms_pages();
		
		foreach($return['result'] as $key => $image){
			foreach($pages as $page){
					
				if (!empty($page['image']) && $image['filename'] == $page['image']){
					$return['result'][$key]['number'] += 1;
				}
			
			}
		}
		
		// get total number of images in filter
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

		$name_data = str_replace(array('.jpg','.jpeg','.png','.gif','.svg'), '', trim($name_data));

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
			
			if (!empty($result[0]['name']) && substr_count($name_data, '/') == 1){ // module image and exists in db
				
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

		$sql = "insert into cms_image set name = ? , filename = ? , category = ? , hash = '', meta = '', keyword = '' ";
		$this->db->query($sql, array($name_data, $filename, $category, ));

		return $filename;

	}

	function delete_cms_image_by_filename($filename, $delete = true){

		// TODO: if not unlinking bad file
		if (file_exists($GLOBALS['config']['upload_path'].$filename)){
			unlink($GLOBALS['config']['upload_path'].$filename);
		}

		$name_a = pathinfo($filename);

		$image_names = $GLOBALS['config']['upload_path'].$name_a['dirname'].'/_'.$name_a['filename'].'.*.*';
		foreach(glob($image_names) as $_filename) {
			unlink($_filename);
		}

		if ($delete){ // TODO: here may be error!
			$sql = "delete from cms_image where filename = ? ";
			$this->db->query($sql, [$filename]);
		} else {
			$sql = "update cms_image where filename = ? set meta = '' limit 1";
			$this->db->query($sql, [$filename]);
		}
		
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

				// move it to year/month directory
				if (!file_exists($GLOBALS['config']['upload_path'].date('Y'))){
					mkdir($GLOBALS['config']['upload_path'].date('Y'));
				}

				if (!file_exists($GLOBALS['config']['upload_path'].date('Y').'/'.date('m'))){
					mkdir($GLOBALS['config']['upload_path'].date('Y').'/'.date('m'));
				}

				$return = $this->create_cms_image(date('Y').'/'.date('m').'/', $prefix.'_'.$filename, $category);

				file_put_contents($GLOBALS['config']['upload_path'].$return, $image_content);

				// check if not duplicate
				$hash = sha1_file($GLOBALS['config']['upload_path'].$return);
				$existing = $this->get_cms_image_by_hash($hash);

				if (empty($existing)){
					$this->update_cms_image($return, array('hash' => $hash, ));
				} else {
					$this->delete_cms_image_by_filename($return);
					$return = $existing['filename'];
				}

			}

		}

		return $return;

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
	
	function get_image_url($image, $params){
		
		include_once $GLOBALS['config']['base_path'].'application/helpers/image_optimiser_helper.php';

		return $GLOBALS['config']['upload_url']._iw($image, $params)['image'];

	}
	
}
