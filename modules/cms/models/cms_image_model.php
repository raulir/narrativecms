<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_image_model extends CI_Model {

	function get_cms_image_by_filename($filename){

		if (function_exists('mysql_set_charset')){
			@mysql_set_charset('utf8mb4');
		}

		$sql = "select * from cms_image where filename = ? ";
		$query = $this->db->query($sql, array($filename));
		$result = $query->row_array();

		if (!empty($result['meta'])){
			$meta = json_decode($result['meta'], true);
			foreach($meta as $key => $value){
				if (empty($result[$key])){
					$result[$key] = $value;
				}
			}
		}

		// if no height and width information
		if (empty($result['original_width']) && file_exists($GLOBALS['config']['upload_path'].$filename) && !is_dir($GLOBALS['config']['upload_path'].$filename)){
			list($result['original_width'], $result['original_height']) = getimagesize($GLOBALS['config']['upload_path'].$filename);
		}

		return $result;

	}

	function get_cms_images($page, $limit, $category, $search){
		
		$this->load->model('cms_page_model');

		if (function_exists('mysql_set_charset')){
			@mysql_set_charset('utf8mb4');
		}

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
					
				if ($image['filename'] == $page['image']){
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
			if (!empty($result[0]['name'])){
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

		$image_names = $GLOBALS['config']['upload_path'].$name_a['dirname'].'/_'.$name_a['filename'].'.*.'.$name_a['extension'];
		foreach(glob($image_names) as $_filename) {
			unlink($_filename);
		}

		if ($delete){
			$sql = "delete from cms_image where filename = ? ";
			$this->db->query($sql, array($filename, ));
		}

	}

	function scrape_image($source, $prefix = 'scraped', $category = 'scraped'){

		if (stristr($source, '?')){
			list($fn, $pr) = explode('?', $source, 2);
			$ext = pathinfo($fn, PATHINFO_EXTENSION);
			$filename = pathinfo($fn, PATHINFO_BASENAME);
		} else {
			$ext = pathinfo($source, PATHINFO_EXTENSION);
			$filename = pathinfo($source, PATHINFO_BASENAME);
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

				$this->load->model('cms_image_model');
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

	function purge_keyword($keyword){
		$sql = "select cms_image_id, keyword from cms_image where find_in_set( ? , keyword)";
		$query = $this->db->query($sql, array($keyword, ));
		$result = $query->result_array();
		foreach ($result as $row){
			$keywords = explode(',', $row['keyword']);
			if(($key = array_search($keyword, $keywords)) !== false) {
				 
				unset($keywords[$key]);
				 
				$sql = "update cms_image set keyword = ? where cms_image_id = ? ";
				$this->db->query($sql, array(implode(',', $keywords), $row['cms_image_id'], ));
				 
			}
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

}
