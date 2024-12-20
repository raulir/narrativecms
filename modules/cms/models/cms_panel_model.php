<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

include_once('system/helpers/json_helper.php');

if (!function_exists('array_merge_recursive_ex')){

	function array_merge_recursive_ex(array $array1, array $array2){
			
		$merged = $array1;

		foreach ($array2 as $key => & $value) {
			if (is_array($value) && isset($merged[$key]) && is_array($merged[$key])) {
				$merged[$key] = array_merge_recursive_ex($merged[$key], $value);
			} else if (is_numeric($key)) {
				if (!in_array($value, $merged)) {
					$merged[] = $value;
				}
			} else {
				$merged[$key] = $value;
			}
		}

		return $merged;
			
	}

}

class cms_panel_model extends Model {
	
	/**
	 *  load block type structure from json file
	 */
	function get_cms_panel_definition($panel_definition_name){
		
		$return = $this->get_cms_panel_config($panel_definition_name);
		
		return !empty($return['item']) ? $return['item'] : array();

	}
	
	function get_cms_panel_config($cms_panel){

		$return = [
				'item' => [],
				'version' => 2,
		];
		
		if (!stristr($cms_panel, '/')){
			_html_error('Panel name has to include module: '.$cms_panel, 0, ['backtrace' => 1]);
			return $return;
		}
		
		$filename = '';

		$hfilename = $GLOBALS['config']['base_path'].'modules/'.str_replace('/', '/definitions/',$cms_panel).'.json';
		if (file_exists($hfilename)){
			$filename = $hfilename;
			list($default_module, $_panel_name) = explode('/', $cms_panel);
		}
				
		$return['module'] = !empty($default_module) ? $default_module : 'cms';

		if ($filename){

			$json_data = file_get_contents($filename);

			// images // replace
			$json_data = str_replace('"//', '"'.$return['module'].'/', $json_data);
			
			$return = cms_json_decode($json_data, $filename);

			// if extends
			if(!empty($return['extends']['panel'])){

				$extends_config = $this->get_cms_panel_config($return['extends']['panel']);
				
				// join structures, do not overwrite item elements
				$items = $return['item'] ?? [];
				
				if (empty($extends_config['item'])){
					$extends_config['item'] = [];
				}
				
				array_push($items, ...$extends_config['item']);

				$return = array_merge_recursive_ex($extends_config, $return);
				
				$return['item'] = $items;
				
			}
			
		}
		
		$return['filename'] = $filename;
		
		if (!empty($return['version']) && is_array($return['version'])){
			$return['version'] = end($return['version']);
		}
		
			if (!empty($return['label']) && is_array($return['label'])){
			$return['label'] = end($return['label']);
		}
		
		if (!empty($return['description']) && is_array($return['description'])){
			$return['description'] = end($return['description']);
		}
		
		if (!empty($return['image']) && is_array($return['image'])){
			$return['image'] = end($return['image']);
		}
		
		foreach($GLOBALS['config']['extends'] as $item){
			if ($item['target'] == $cms_panel){
				$return = $this->merge_structures($return, $this->get_cms_panel_config($item['source']));
			}
		}
		
		return $return;

	}
	
	function merge_structures($structure_into, $structure_from){
		
		if (empty($structure_from['item'])) $structure_from['item'] = [];
		if (empty($structure_into['item'])) $structure_into['item'] = [];
		foreach($structure_from['item'] as $item){
			
			$copied = 0;
			foreach($structure_into['item'] as $key => $into){
				if (!empty($item['name']) && !empty($into['name']) && $into['name'] == $item['name']){
					$structure_into['item'][$key] = $item;
					$copied = 1;
				}
			}
			if ($copied == 0){
				$structure_into['item'][] = $item;
			}
			
		}
		
		if (empty($structure_from['settings'])) $structure_from['settings'] = [];
		if (empty($structure_into['settings'])) $structure_into['settings'] = [];
		foreach($structure_from['settings'] as $item){
				
			$copied = 0;
			foreach($structure_into['settings'] as $key => $into){
				if (!empty($item['name']) && !empty($into['name']) && $into['name'] == $item['name']){
					$structure_into['settings'][$key] = $item;
					$copied = 1;
				}
			}
			if ($copied == 0){
				$structure_into['settings'][] = $item;
			}
				
		}
		
		return $structure_into;
		
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