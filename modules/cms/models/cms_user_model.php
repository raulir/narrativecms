<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_user_model extends CI_Model {

	function get_cms_users(){
		$sql = "select * from cms_user order by sort asc";
		$query = $this->db->query($sql);
		$result = $query->result_array();

		return $result;
	}

	function get_cms_user($cms_user_id){
		
		$return = [];
		
		if ($cms_user_id > 1){
		
			$sql = "select * from cms_user where cms_user_id = ? ";
			$query = $this->db->query($sql, array($cms_user_id));
			
			if ($query->num_rows()){
				$row = $query->row_array();
				$return = $row;
			}
		
		} else {

			$return['username'] = $GLOBALS['config']['admin_username'];
			
		}
		
		return $return;
		
	}

	function new_cms_user(){

		$sql = "select max(sort) as sort from cms_user";
		$query = $this->db->query($sql);
		$result = $query->row_array();

		return array(
				'cms_user_id' => 0,
				'sort' => $result['sort'] + 1,
				'username' => '',
				'password' => '',
				'email' => '',
				'name' => '',
				'access' => '',
		);
	}

	function update_cms_user($cms_user_id, $data){

		$sql = "update cms_user set ".implode(' = ? , ', array_keys($data))." = ? where cms_user_id = '".(int)$cms_user_id."' ";
		$this->db->query($sql, $data);

	}

	function create_cms_user($data){
		$sql = "insert into cms_user set ".implode(' = ? , ', array_keys($data))." = ? ";
		$this->db->query($sql, $data);
		return $this->db->insert_id();
	}

	function get_cms_users_by($filter){

		$fields = array_keys($filter);

		$sql = "select * from `cms_user` where " . preg_replace("/[^A-Za-z0-9_=? ]/", '', implode(' = ? and ', $fields)) . " = ? order by sort ";
		$query = $this->db->query($sql, $filter);
		if ($query->num_rows()){
			$return = $query->result_array();
		} else {
			$return = array();
		}

		return $return;

	}

	function delete_cms_user($cms_user_id){

		$sql = "delete from cms_user where cms_user_id = ? ";
		$this->db->query($sql, array($cms_user_id, ));

	}

	function get_cms_user_by_username($username){

		$sql = "select * from cms_user where username = ? ";

		$query = $this->db->query($sql, $username);
		if ($query->num_rows()){
			$return = $query->row_array();
		} else {
			$return = array();
		}

		return $return;
	}

	function get_cms_user_all_access_keys(){

		$this->load->model('cms_module_model');
		
		$return = [];

		foreach($GLOBALS['config']['modules'] as $module){
			$config = $this->cms_module_model->get_module_config($module);
			if (!empty($config['access']) && is_array($config['access'])){
				$return = array_merge($return, $config['access']);
			}
		}

		$access = [];

		foreach ($return as $right){
			$access[$right['access']] = $right['name'];
		}

		return $access;

	}

	function get_cms_user_login_data($username, $password){

		$return = [];

		if (empty($password)){

			// do nothing

		} elseif (!empty($GLOBALS['config']['admin_username']) && !empty($GLOBALS['config']['admin_password'])
				&& $username == $GLOBALS['config']['admin_username'] && $password == $GLOBALS['config']['admin_password']){

					// check if in config file
					$return['cms_user_id'] = 1;
					$return['access'] = ['*'];
					$return['name'] = $username;
					$return['username'] = $username;

		} else {

			// get user from database
			$this->load->model('cms/cms_user_model');
			$user = $this->cms_user_model->get_cms_user_by_username($username);

			if (!empty($user['username']) && $user['username'] == $username && $user['password'] == md5($username.$password)){
				$return['cms_user_id'] = $user['cms_user_id'];
				$return['access'] = !empty($user['access']) ? explode(',', $user['access']) : [];
				$return['name'] = $user['name'];
				$return['username'] = $user['username'];
			}

		}

		return $return;

	}

}
