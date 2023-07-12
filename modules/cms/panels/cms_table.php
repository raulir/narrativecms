<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_table extends CI_Controller {

	function __construct(){

		parent::__construct();

		// check if user
		if(empty($_SESSION['cms_user']['cms_user_id'])){
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}

	}

	function panel_action($params){

		$do = $this->input->post('do');

		if ($do == 'cms_table_save'){
			 
			// collect data
			$table = $this->input->post('table');
			$data = $this->input->post('data');
			 
			// transpose panel params arrays
			if (!is_array($data)){
				$data = array();
			}
			foreach ($data as $key => $value){
				if (is_array($value)){
					$temp_result = array();
					foreach($value as $skey => $kvalues){
						foreach ($kvalues as $nkey => $nvalue){
							if (empty($temp_result[$nkey])){
								$temp_result[$nkey] = array();
							}
							$temp_result[$nkey][$skey] = $nvalue;
							$temp_result[$nkey]['sort'] = $nkey;
						}
					}
					$data[$key] = $temp_result;
				}
			}

			/*
			 print('<pre>');
			 print_r($data);
			 print('</pre>');
			 */

			// save data
			$this->load->model('cms/cms_table_model');
			$ids = array();
			if (!empty($data[$table])){
				foreach($data[$table] as $row){
					if(!empty($row[$table.'_id'])){
						$this->cms_table_model->update_row($table, $row);
						$ids[] = $row[$table.'_id'];
					} else {
						$ids[] = $this->cms_table_model->insert_row($table, $row);
					}
				}
			}
			 
			// get file field name load block type structure from definition file
			$this->load->model('cms/cms_panel_model');
			$structure = $this->cms_panel_model->get_cms_panel_definition('cms_table_'.$params['table']);

			foreach ($structure as $struct){
				if ($struct['type'] == 'repeater'){
					foreach ($struct['fields'] as $r_struct){
						if ($r_struct['type'] == 'file'){
							$file_fields[] = $r_struct['name'];
						}
					}
				}
			}

			$this->cms_table_model->delete_rows($table, $ids, $file_fields);

			return array('table' => $table, );

		}

	}

	function panel_params($params){

		$this->load->model('cms/cms_table_model');

		$return['data'][$params['table']] = $this->cms_table_model->get_data($params['table']);
		$return['table_title'] = ucfirst(str_replace('_', ' ', $params['table']).'s');

		// get file field name load block type structure from definition file
		$this->load->model('cms/cms_panel_model');
		$return['table_structure'] = $this->cms_panel_model->get_cms_panel_definition('cms_table_'.$params['table']);

		// collect needed fk data
		$return['fk_data'] = array();
		foreach ($return['table_structure'] as $struct){
			if ($struct['type'] == 'fk'){
				if (empty($return['fk_data'][$struct['name']])){
					$struct_table = str_replace('_id', '', $struct['name']);
					$return['fk_data'][$struct['name']][0] = '-- not specified --';
					$return['fk_data'][$struct['name']] = $return['fk_data'][$struct['name']] + $this->cms_table_model->get_fk_data($struct_table);
				}
			} elseif ($struct['type'] == 'repeater'){
				foreach ($struct['fields'] as $r_struct){
					if ($r_struct['type'] == 'fk'){
						if (empty($return['fk_data'][$r_struct['name']])){
							$struct_table = str_replace('_id', '', $r_struct['name']);
							$return['fk_data'][$r_struct['name']][0] = '-- not specified --';
							$return['fk_data'][$r_struct['name']] =
							$return['fk_data'][$r_struct['name']] + $this->cms_table_model->get_fk_data($struct_table);
						}
					}
				}
			}
		}

		return array_merge($return, $params);

	}

}
