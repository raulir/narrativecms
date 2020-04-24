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
		
		// if array, get first with /
		if (is_array($cms_panel)){
			foreach($cms_panel as $_cms_panel){
				if (stristr($_cms_panel, '/')){
					$cms_panel = $_cms_panel;
					break;
				}
			}
		}
		
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
			
			if( json_last_error() ){
				
				_html_error('Problem loading json: '.json_last_error_msg().' in '.str_replace($GLOBALS['config']['base_path'], '', $filename));
				
			}

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
		
		$return['module'] = !empty($default_module) ? $default_module : '';
		$return['filename'] = $filename;
		
		if (!empty($return['version']) && is_array($return['version'])){
			$return['version'] = end($return['version']);
		}
		
		if (!empty($return['label']) && is_array($return['label'])){
			$return['label'] = end($return['label']);
		}
		
		return $return;

	}
	
	function get_cms_panel_fk_data($block_structure){
		
		$this->load->model('cms/cms_page_panel_model');
		$this->load->model('cms/cms_table_model');
		
		$return = [];
		
		if (!empty($block_structure) && is_array($block_structure)){
			
			// read needed fk data
			foreach ($block_structure as $struct){
				if ($struct['type'] == 'fk'){
					if (empty($return['fk_data'][$struct['name']])){
						
						$struct_table = str_replace('_id', '', (!empty($struct['field']) ? $struct['field'] : $struct['name']));
						$return[(!empty($struct['field']) ? $struct['field'] : $struct['name'])][0] = '-- not specified --';
						
						if (empty($struct['filter'])){
							$struct['filter'] = array();
						}
						
						if ($struct['target'] == 'block'){
						
							$return[(!empty($struct['field']) ? $struct['field'] : $struct['name'])] =
									$return[(!empty($struct['field']) ? $struct['field'] : $struct['name'])] +
									$this->cms_page_panel_model->get_fk_data($struct_table, $struct['filter'], (!empty($struct['label_field']) ? $struct['label_field'] : 'title'));
								
						} else if ($struct['target'] == 'table') {
								
							$return[(!empty($struct['field']) ? $struct['field'] : $struct['name'])] =
									$return[(!empty($struct['field']) ? $struct['field'] : $struct['name'])] +
									$this->cms_table_model->get_fk_data($struct_table);
								
						} else {
							
							$return[$struct['target']] = $this->cms_page_panel_model->get_fk_data($struct['list'], $struct['filter'], (!empty($struct['label_field']) ? $struct['label_field'] : 'heading'));

						}
					
					}
				} elseif ($struct['type'] == 'repeater'){
					
					foreach ($struct['fields'] as $r_struct){
						if ($r_struct['type'] == 'fk'){
							if (empty($return[$r_struct['name']])){
									
								$struct_table = str_replace('_id', '', (!empty($r_struct['field']) ? $r_struct['field'] : $r_struct['name']));
								$return[(!empty($r_struct['field']) ? $r_struct['field'] : $r_struct['name'])][0] = '-- not specified --';
								
								if (empty($r_struct['filter'])){
									$r_struct['filter'] = array();
								}
								
								if (!empty($r_struct['target']) && $r_struct['target'] == 'block'){
								
									$return[(!empty($r_struct['field']) ? $r_struct['field'] : $r_struct['name'])] =
									$return[(!empty($r_struct['field']) ? $r_struct['field'] : $r_struct['name'])] +
									$this->cms_page_panel_model->get_fk_data($struct_table, $r_struct['filter'], (!empty($r_struct['label_field']) ? $r_struct['label_field'] : 'title'));
										
								} else {
										
									$return[(!empty($r_struct['field']) ? $r_struct['field'] : $r_struct['name'])] = 
									$this->cms_page_panel_model->get_fk_data($r_struct['list'], $r_struct['filter'], (!empty($r_struct['label_field']) ? $r_struct['label_field'] : 'heading'));
								
								}
							}
						}
					}
				}
			}
		
		}
		
		return $return;
		
	}
	
	/**
	 * 
	 * get panels defined over all active modules
	 * 
	 * filter by flag
	 * 
	 * @param string $flag
	 * if set, returns all panels with this flag
	 * if empty, returns all non-hidden panels 
	 * 
	 * @return unknown[]
	 * 
	 */
	function get_cms_panels($flag = ''){
		
		// this is already present in config
		
		$return = [];
		
		foreach($GLOBALS['config']['module'] as $module => $data){
			
			foreach($data['panels'] as $panel){
				
				if (empty($flag)){
					
					if (empty($panel['flags']) || !in_array('hidden', $panel['flags'])){
						$return[$module.'/'.$panel['id']] = $panel['name'];
					}
					
				} elseif (!empty($panel['flags']) && in_array($flag, $panel['flags'])) {
					
					$return[$module.'/'.$panel['id']] = $panel['name'];
					
				}
				
			}
			
		}
		
		return $return;
		
	}

}