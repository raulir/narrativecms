<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_file_model extends CI_Model {
	
	function get_cms_file_by_name($name){

		$sql = "select * from cms_file where name = ? ";
    	$query = $this->db->query($sql, array($name));
    	$result = $query->row_array();
    	
    	return $result;

	}
	
	function get_cms_file_by_filename($filename){

		$sql = "select * from cms_file where filename = ? ";
    	$query = $this->db->query($sql, array($filename));
    	$result = $query->row_array();
    	
    	return $result;

	}

	function get_cms_files(){
	
		$sql = "select * from cms_file order by sort desc";
		$query = $this->db->query($sql);
		$result = $query->result_array();
		
		return $result;
	
	}
	
	// without date folders in fron of the filename
	function sanitize_filename($name_data){
		
		$name_data = strtolower($name_data);
		
		$extension_a = explode('.', $name_data);
		$extension = array_pop($extension_a);
		
		$name_data = str_replace('.'.$extension, '', trim($name_data));
		
		$name_data = str_replace(array(' ','-','.'), '_', $name_data);
		
		$name_data = preg_replace('/[^\w\d]/ui', '', $name_data);
		
		$name_data = trim(preg_replace('/[_]+/ui', '_', $name_data), '_');
		
		$found = true;
		$str = $name_data.'.'.$extension;
		$i = 1;
		while($found){
			$sql = "select * from cms_file where name = ? ";
			$query = $this->db->query($sql, array($str));
			$result = $query->result_array();
			if (!empty($result[0]['name'])){
				$str = $name_data . '_' . $i . '.' . $extension;
				$i = $i + 1;
			} else {
				$found = false;
				$name_data = $str;
			}
		}
		
		return $name_data;
		
	}
	
	function create_cms_file($dir, $name_data, $overwrite = false){
		
		if (!$overwrite){
			$name_data = $this->sanitize_filename($name_data);
		}
		
		// make dir
		$year = date('Y');
		$month = date('m');
		
		if (!is_dir($GLOBALS['config']['upload_path'].$dir)){
			mkdir($GLOBALS['config']['upload_path'].$dir);
		}
		
		if (!is_dir($GLOBALS['config']['upload_path'].$dir.$year)){
			mkdir($GLOBALS['config']['upload_path'].$dir.$year);
		}
		
		if (!is_dir($GLOBALS['config']['upload_path'].$dir.$year.'/'.$month)){
			mkdir($GLOBALS['config']['upload_path'].$dir.$year.'/'.$month);
		}
		
		$filename = $dir.$year.'/'.$month.'/'.$name_data;
		
		$extension = pathinfo($filename, PATHINFO_EXTENSION);
		$filename_wo = substr($filename, 0, -(strlen($extension) + 1));
		
		$name_data_wo = substr($name_data, 0, -(strlen($extension) + 1));
		
		if ($overwrite){
			$this->delete_cms_file_by_filename($filename);
		}
		
		$i = 1;
		while (file_exists($GLOBALS['config']['upload_path'].$filename)){
			$filename = $filename_wo.'_'.$i.'.'.$extension;
			$i += 1;
			$name_data = $name_data_wo.'_'.$i.'.'.$extension;
		}
				
		$sql = "insert into cms_file set name = ? , filename = ? , date_posted = ? ";
		$this->db->query($sql, array($name_data, $filename, date('Y-m-d H:i:s')));
 
 		return array(
				'filename' => $filename,
				'name' => $name_data,
 				'name_original' => $name_data_wo.'.'.$extension,
				'cms_file_id' => $this->db->insert_id(),
				'date_posted' => date('Y-m-d H:i:s'),
		);
 
	}
	
	function delete_cms_file_by_filename($filename){
		$sql = "delete from cms_file where filename = ? ";
		$this->db->query($sql, array($filename, ));
	}
	
	function count_user_files($user_id){

		$sql = "select count(*) as number from `cms_file` where user_id = ? or user_id = 0 ";
    	$query = $this->db->query($sql, array($user_id, ));
 	   	$result = $query->row_array();

    	return $result['number'];
    
	}
	
	function get_user_files($user_id, $start = 0, $number = 10){
		
		$sql = "select cms_file_id, name, icon, title, date_posted from `cms_file` where user_id = ? or user_id = 0 order by sort limit ? , ?";
    	$query = $this->db->query($sql, array($user_id, $start, $number, ));
 	   	$result = $query->result_array();

    	return $result;
		
	}

	function get_file($cms_file_id){
		$sql = "select * from cms_file where cms_file_id = ? ";
    	$query = $this->db->query($sql, array($cms_file_id));
    	if ($query->num_rows()){
	    	$row = $query->row_array();
    	} else {
    		$row = array();
    	}
    	return $row;
	}
	
	function update_cms_file($filename, $data){

		// check if cms_file table has hash field - deprecated
		$sql = "SHOW COLUMNS FROM cms_file LIKE 'hash'";
		$query = $this->db->query($sql);
		$result = $query->result_array();
		
		if (empty($result) || !count($result)){
			$sql = "ALTER TABLE cms_file ADD hash VARCHAR( 40 ) after name";
			$query = $this->db->query($sql);
		}

		foreach($data as $field => $value){
			$sql = "update cms_file set ".$field." = ? where filename = ? ";
			$query = $this->db->query($sql, array($value, $filename, ));
		}
	
	}
	
	function rename_file($old_name, $new_filename, $new_short_name){
		
		$this->load->model('cms_page_panel_model');
		
		$this->update_cms_file($old_name, ['filename' => $new_filename]);
		$this->update_cms_file($new_filename, ['name' => $new_short_name]);
		
		$sql = "select * from cms_page_panel_param where value = ? ";
		$query = $this->db->query($sql, [$old_name]);
		
		$result = $query->result_array();
		
		$sql = "update cms_page_panel_param set value = ? where cms_page_panel_param_id = ? ";
		foreach($result as $row){
			
			$query = $this->db->query($sql, [$new_filename, $row['cms_page_panel_param_id']]);
			
			$this->cms_page_panel_model->_update_cached_params($row['cms_page_panel_id']);
			$this->cms_page_panel_model->invalidate_html_cache($row['cms_page_panel_id']);
			
		}
		
	}
	
	function scrape_file($source, $filename = ''){
	
		if (empty($filename)){
			if (stristr($source, '?')){
				list($fn, $pr) = explode('?', $source, 2);
				$ext = pathinfo($fn, PATHINFO_EXTENSION);
				$filename = pathinfo($fn, PATHINFO_BASENAME);
			} else {
				$ext = pathinfo($source, PATHINFO_EXTENSION);
				$filename = pathinfo($source, PATHINFO_BASENAME);
			}
		}
	
		$return = '';
	

		$image_content = file_get_contents($source);
		if (!empty($image_content)){

			// move it to year/month directory
			if (!file_exists($GLOBALS['config']['upload_path'].date('Y'))){
				mkdir($GLOBALS['config']['upload_path'].date('Y'));
			}

			if (!file_exists($GLOBALS['config']['upload_path'].date('Y').'/'.date('m'))){
				mkdir($GLOBALS['config']['upload_path'].date('Y').'/'.date('m'));
			}

			$return = $this->create_cms_file('/', $filename, true);

			file_put_contents($GLOBALS['config']['upload_path'].$return['filename'], $image_content);

		}
	
		return $return['filename'];
	
	}
	
}
