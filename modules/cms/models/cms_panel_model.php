<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_panel_model extends CI_Model {
	
	/**
	 *  load block type structure from json file
	 */
	function get_cms_panel_definition($panel_definition_name){
		
		$return = $this->get_cms_panel_config($panel_definition_name);
		
		return !empty($return['item']) ? $return['item'] : array();

	}
	
	function get_cms_panel_config($cms_panel){
		
		$filename = '';
		if (!stristr($cms_panel, '/')){
			
			// figure out module name
			foreach($GLOBALS['config']['modules'] as $module){
				
				$hfilename = $GLOBALS['config']['base_path'].'modules/'.$module.'/definitions/'.$cms_panel.'.json';
				if (file_exists($hfilename)){
					$filename = $hfilename;
					$default_module = $module;
				}
			}
			
		} else {
			
			$hfilename = $GLOBALS['config']['base_path'].'modules/'.str_replace('/', '/definitions/',$cms_panel).'.json';
			if (file_exists($hfilename)){
				$filename = $hfilename;
				list($default_module, $_panel_name) = explode('/', $cms_panel);
			}

		}
		
		
		$return = array('item' => array(), 'version' => 1, );

		if ($filename){

			$json_data = file_get_contents($filename);
			$panel_params_structure = json_decode($json_data, true);

			if (empty($panel_params_structure['version']) || $panel_params_structure['version'] < 2){
				$return['item'] = $panel_params_structure;
				$return['version'] = 1;
			} else {
				$return = $panel_params_structure;
			}
			
			// if extends
			if(!empty($return['extends']['panel'])){
				
				// get panel name, if no module
				if (!stristr($return['extends']['panel'], '/')){
					$extends_cms_panel = $default_module.'/'.$return['extends']['panel'];
				} else {
					$extends_cms_panel = $return['extends']['panel'];
				}
				
				$extends_config = $this->get_cms_panel_config($extends_cms_panel);
				
				// join structures
				$return = array_merge_recursive($extends_config, $return);
				
			}
			
		}
		
		return $return;

	}
	
	function get_cms_panel_fk_data($cms_panel){
		
		$this->load->model('cms_page_panel_model');
		$this->load->model('cms_table_model');
		
		$return = [];
		
		$block_structure = $this->get_cms_panel_definition($cms_panel);
		
		// read needed fk data
		foreach ($block_structure as $struct){
			if ($struct['type'] == 'fk'){
				if (empty($return['fk_data'][$struct['name']])){
					$struct_table = str_replace('_id', '', (!empty($struct['field']) ? $struct['field'] : $struct['name']));
					$return[(!empty($struct['field']) ? $struct['field'] : $struct['name'])][0] = '-- not specified --';
					if ($struct['target'] == 'block'){
		
						if (empty($struct['filter'])){
							$struct['filter'] = array();
						}
		
						$return[(!empty($struct['field']) ? $struct['field'] : $struct['name'])] =
						$return[(!empty($struct['field']) ? $struct['field'] : $struct['name'])] +
						$this->cms_page_panel_model->get_fk_data($struct_table, $struct['filter'], (!empty($struct['label_field']) ? $struct['label_field'] : 'title'));
							
					} else {
							
						$return[(!empty($struct['field']) ? $struct['field'] : $struct['name'])] =
						$return[(!empty($struct['field']) ? $struct['field'] : $struct['name'])] +
						$this->cms_table_model->get_fk_data($struct_table);
							
					}
				}
			} elseif ($struct['type'] == 'repeater'){
				foreach ($struct['fields'] as $r_struct){
					if ($r_struct['type'] == 'fk'){
						if (empty($return[$r_struct['name']])){
							$struct_table = str_replace('_id', '', $r_struct['name']);
							$return[$r_struct['name']][0] = '-- not specified --';
							if ($r_struct['target'] == 'block'){
									
								if (empty($r_struct['filter'])){
									$r_struct['filter'] = array();
								}
		
								$return[$r_struct['name']] = $return[$r_struct['name']] +
								$this->cms_page_panel_model->get_fk_data($struct_table, $r_struct['filter'], (!empty($r_struct['label_field']) ? $r_struct['label_field'] : 'title'));
									
							} else {
									
								$return[$r_struct['name']] = $return[$r_struct['name']] +
								$this->cms_table_model->get_fk_data($struct_table);
									
							}
						}
					}
				}
			}
		}
		
		return $return;
		
	}

}