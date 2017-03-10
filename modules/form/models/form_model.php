<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class form_model extends CI_Model {
	
	function create_webform_data($cms_page_panel_id, $email, $data){

    	// check if table exists
		$this->create_table_webform_data();
		
		$data['time'] = time();
	
		$sql = "insert into webform_data set cms_page_panel_id = ? , email = ? , data = ? ";
		$this->db->query($sql, array($cms_page_panel_id, $email, json_encode($data), ));
		$return = $this->db->insert_id();
		
		return $return;
	
	}
	
    function send_contact_request($emails, $data, $title){

		foreach($emails as $email){    	

 			$content = 'New form "'.$title.'" submission on website "'.$GLOBALS['config']['title'].'":'."\n\n";

			foreach($data as $key => $value){
				$content .= $key . ': ' . $value . "\n";
 			}
 
	   		// send email
	    	@mail($email['email'], 'New form "'.$title.'" submission on website "'.$GLOBALS['config']['title'].'"', 
	    			$content, 'From: noreply@bytecrackers.com' . "\r\n");
    		
		}
    	
    }
    
    function create_table_webform_data(){
    	
    	$db_debug = $this->db->db_debug; //save setting
    	$this->db->db_debug = false; //disable debugging for queries
    	
		$sql = "select cms_page_panel_id from webform_data limit 1 ";
		$query = $this->db->query($sql);

		$this->db->db_debug = $db_debug; //restore setting
		
		if($this->db->_error_number() == 1146){
    	
    		$sql = "CREATE TABLE `webform_data` (
    					`webform_data_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    					`cms_page_panel_id` int(10) UNSIGNED NOT NULL,
    					`email` varchar(100) NOT NULL, `data` text NOT NULL,
    					PRIMARY KEY (`webform_data_id`)
    				) ENGINE=InnoDB DEFAULT CHARSET=utf8";
    		$this->db->query($sql);
    	
    		$sql = "ALTER TABLE `webform_data` ADD KEY `cms_page_panel_idx` (`cms_page_panel_id`)";
    		$this->db->query($sql);
    	
    	}

    }
    
    function get_webforms(){
    	
    	// check if table exists
		$this->create_table_webform_data();
    		
		$sql = "select a.cms_page_panel_id, b.title from webform_data a join block b on a.cms_page_panel_id = b.block_id group by a.cms_page_panel_id ";
		$query = $this->db->query($sql);
     		
     	$return = $query->result_array();
     	     	
 		return $return;
    	
    }
    
    function file_webform_data($cms_page_panel_id, $filename){
    	
    	$sql = "select * from webform_data where cms_page_panel_id = ? ";
     	$query = $this->db->query($sql, array($cms_page_panel_id, ));
    	$data = $query->result_array();
    	
    	// get possible fields
    	$fields = array('time');
    	$table = array();
    	foreach($data as $row){
    		$row_unpacked = !empty($row['data']) ? json_decode($row['data'], true) : array('email' => $row['email'], );
    		$fields = array_unique(array_merge($fields, array_keys($row_unpacked)));
    		$row_unpacked['time'] = date('Y-m-d H:i', !empty($row_unpacked['time']) ? $row_unpacked['time'] : 0);
    		$table[] = $row_unpacked;
    	}
    	
    	// create csv file
		header('Content-Type: application/CSV; charset=utf-16');
		header('Content-Disposition: attachment; filename="'.$filename.'.csv"');
	
		// start file with bom and use utf16le, which both mac and windows should be able to autodetect
    	print("\xFF\xFE");

		// heading
		print(mb_convert_encoding('"'.implode('"'."\t".'"', $fields).'"'."\n", 'UTF-16LE','UTF-8'));

		// use tabs
		foreach($table as $row){
			$row_print = array();
			foreach($fields as $field){
				$row_print[] = !empty($row[$field]) ? $row[$field] : '';
			}
			print(mb_convert_encoding('"'.implode('"'."\t".'"', $row_print).'"'."\n", 'UTF-16LE','UTF-8'));
		}
	
		die();
    	
    }
    
    function send_autoreply($data, $autoreply_text, $autoreply_email, $autoreply_name, $autoreply_subject){
    	
    	foreach($data as $key => $val){
    		$autoreply_text = str_replace('['.$key.']', $val, $autoreply_text);
    	}
    	
    	if (!empty($data['email'])){
	   		// send email
	    	@mail($data['email'], $autoreply_subject, $autoreply_text, 
					'From: '.$autoreply_name.'<'.$autoreply_email.'>'."\r\n".'Reply-to: '.$autoreply_name.'<'.$autoreply_email.'>'."\r\n");
    	}
    	
    }

}
