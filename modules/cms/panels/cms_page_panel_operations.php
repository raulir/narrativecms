<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_page_panel_operations extends MY_Controller{

	function __construct(){

		parent::__construct();

		// check if user
		if(empty($_SESSION['cms_user']['cms_user_id'])){
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}

	}
	
	function panel_action($params){

		$this->load->model('cms_page_panel_model');
		
		$do = $this->input->post('do');

		if ($do == 'cms_page_panel_shortcut'){
			 
			$cms_page_id = $this->input->post('cms_page_id');
			$cms_page_panel_id = $this->input->post('cms_page_panel_id'); // to where the shortcut goes
			 
			// save data
			$this->cms_page_panel_model->create_cms_page_panel(array(
					'sort' => 'last',
					'page_id' => $cms_page_id,
					'title' => '',
					'panel_name' => $cms_page_panel_id,
			));
			 
		} elseif ($do == 'cms_page_panel_caching'){
			 
			$target_id = $this->input->post('target_id');
			$lists = $this->input->post('lists');
			$caching = $this->input->post('caching');

			$params['_caching'] = 0;
			 
			if (!empty($lists) && is_array($lists)){
				$this->cms_page_panel_model->update_cms_page_panel($target_id, array('_cache_lists' => implode(',', $lists), ), true);
				$params['_caching'] = 1;
			} else {
				$this->cms_page_panel_model->update_cms_page_panel($target_id, array('_cache_lists' => '', ), true);
			}

			$this->cms_page_panel_model->update_cms_page_panel($target_id, array('_cache_time' => $caching, ), true);
			if (!empty($caching)){
				$params['_caching'] = 1;
			}

		} elseif ($do == 'cms_page_panel_show'){
			 
			$cms_page_panel_id = $this->input->post('cms_page_panel_id');
			 
			// get current state
			$block = $this->cms_page_panel_model->get_cms_page_panel($cms_page_panel_id);
			 
			// save data
			if (!empty($block['show'])){
				$this->cms_page_panel_model->update_cms_page_panel($cms_page_panel_id, array('show' => 0, ));
				$params['show'] = 0;
			} else {
				$this->cms_page_panel_model->update_cms_page_panel($cms_page_panel_id, array('show' => 1, ));
				$params['show'] = 1;
			}
			 
		} elseif ($do == 'cms_page_panel_copy'){
			 
			$cms_page_panel_id = $this->input->post('cms_page_panel_id');
			 
			$this->load->model('cms_panel_model');
			 
			// get original data
			$data = $this->cms_page_panel_model->get_cms_page_panel($cms_page_panel_id);
			$panel_structure = $this->cms_panel_model->get_cms_panel_definition($data['panel_name']);
			 
			// set changes
			$data['show'] = 0;
			$data['title'] = 'Copy of '.$data['title'];
			if (!empty($data['heading'])){
				$data['heading'] = 'Copy of '.$data['heading'];
			}
			 
			// get children - if it contains cms_page_panels (panel_in_panel) fields
			$all_children = array();
			foreach($panel_structure as $struct){
				if ($struct['type'] == 'cms_page_panels' && !empty($data[$struct['name']])){

					$children = explode(',', $data[$struct['name']]);
					$new_children = array();
	     
					// copy children
					foreach($children as $child_id){
						$child_data = $this->cms_page_panel_model->get_cms_page_panel($child_id);
						unset($child_data['block_id']);
						$new_children[] = $this->cms_page_panel_model->create_cms_page_panel($child_data); // returns id
					}
	     
					// update data with new children data
					$data[$struct['name']] = implode(',', $new_children);
	     
					$all_children = $all_children + $new_children;

				}
			}
			 
			// set new block sort to old + 1 and move other blocks out of the way
			if ($data['page_id'] == 999999 || $data['page_id'] == 0){
				$data['sort'] = $data['sort'] + 1;
				$this->cms_page_panel_model->shift_sort($data['panel_name'], $data['sort'], 1);
			}
			 
			// insert new block
			unset($data['block_id']);
			$new_block_id = $this->cms_page_panel_model->create_cms_page_panel($data);
			 
			// update children with parent id
			foreach($all_children as $new_child_id){
				$this->cms_page_panel_model->update_cms_page_panel($new_child_id, array('parent_id' => $new_block_id, ), true);
			}
			 
		} elseif ($do == 'cms_page_panel_save'){
			 
			$this->load->model('cms_panel_model');

			// collect data
			$block_id = $this->input->post('block_id');
			$data['page_id'] = $this->input->post('page_id');
			$data['parent_id'] = $this->input->post('parent_id');
			$data['sort'] = $this->input->post('sort');
			$data['title'] = $this->input->post('title');
			$data['submenu_anchor'] = $this->input->post('submenu_anchor');
			$data['submenu_title'] = $this->input->post('submenu_title');
			$data['panel_name'] = $this->input->post('panel_name');
			$data['panel_params'] = $this->input->post('panel_params');
			 
			// load existing data and save some of it
			$old_data = $this->cms_page_panel_model->get_cms_page_panel($block_id);
			if (!empty($old_data['_cache_lists'])){
				$data['_cache_lists'] = $old_data['_cache_lists'];
			}
			if (!empty($old_data['_cache_time'])){
				$data['_cache_time'] = $old_data['_cache_time'];
			}

			// transpose panel params arrays
			if (!is_array($data['panel_params'])){
				$data['panel_params'] = array();
			}
			 
			// put together search values against definition
			$panel_config = $this->cms_panel_model->get_cms_panel_config($data['panel_name']);
			 
			// if panel extends, save the fact as well
			if (!empty($panel_config['extends'])){
				$data['panel_params']['_extends'] = $panel_config['extends'];
			}
			 
			$panel_structure = !empty($panel_config['item']) ? $panel_config['item'] : array();
			 
			// search time bonus points
			if (!empty($panel_config['list']['search_time_extra']) && is_array($panel_config['list']['search_time_extra']) && !empty($data['panel_params']['date'])){
				$data['panel_params']['_search_time_extra'] = serialize($panel_config['list']['search_time_extra']);
				$data['panel_params']['_search_time_timestamp_day'] = strtotime($data['panel_params']['date'])/86400;
			}
			 
			$data['search_params'] = array();
			foreach($panel_structure as $struct){
				if (!empty($struct['search'])){
					$data['search_params'][$struct['name']] = $struct['search'];
				}
				if ($struct['type'] == 'repeater'){
					foreach ($struct['fields'] as $r_struct){
						if (!empty($r_struct['search'])){
							$data['search_params'][$struct['name']][$r_struct['name']] = $r_struct['search'];
						}
					}
				}
			}
			 
			// if it contains cms_page_panels (panel_in_panel) fields
			foreach($panel_structure as $struct){
				if ($struct['type'] == 'cms_page_panels' && !empty($data['panel_params'][$struct['name']]) &&
						is_array($data['panel_params'][$struct['name']])){
							 
							$data['panel_params'][$struct['name']] = implode(',', $data['panel_params'][$struct['name']]);

				}
			}

			foreach ($data['panel_params'] as $key => $value){

				// if repeater with something in it
				if (is_array($value) && is_array(reset($value))){
					$temp_result = array();
					foreach($value as $skey => $kvalues){

						foreach ($kvalues as $nkey => $nvalue){
							 
							if (!is_array($nvalue)){
								if (empty($temp_result[$nkey])){
									$temp_result[$nkey] = array();
								}
								$temp_result[$nkey][$skey] = $nvalue;
							} else {
								foreach($nvalue as $nnkey => $nnvalue){
									if (empty($temp_result[$nnkey][$skey])){
										$temp_result[$nnkey][$skey] = array();
									}
									$temp_result[$nnkey][$skey][$nkey] = $nnvalue;
								}
							}

						}

					}
					$data['panel_params'][$key] = $temp_result;
				}

			}

			if (($data['page_id'] == 999999 || $data['page_id'] == 0) && !empty($data['panel_params']['heading'])){
				$data['title'] = $data['panel_params']['heading'];
			}

			// save data
			if($block_id){

				$this->cms_page_panel_model->update_cms_page_panel($block_id, $data);
				 
			} else {

				$block_id = $this->cms_page_panel_model->create_block($data);

				// if list and add to top, move to top
				if (!empty($panel_config['list']['new_first'])){
					$this->cms_page_panel_model->move_first($block_id);
				}

			}

			// delete files
			$old_filenames = $this->cms_page_panel_model->get_page_panel_data_filenames($panel_structure, $old_data);
			$new_filenames = $this->cms_page_panel_model->get_page_panel_data_filenames($panel_structure, $data['panel_params']);
				
			$filenames_diff = array_diff($old_filenames, $new_filenames);
			foreach($filenames_diff as $filename){
				unlink($GLOBALS['config']['upload_path'].$filename);
			}

			// if link target, update slug
			if (!empty($panel_config['list']['link_target'])){
				$this->load->model('cms_slug_model');
				$this->cms_slug_model->request_slug($data['panel_name'].'='.$block_id);
			}

			// save to parents children list
			if (!empty($data['parent_id']) && !empty($params['parent_name'])){

				$parent = $this->cms_page_panel_model->get_cms_page_panel($data['parent_id']);

				if (empty($parent[$params['parent_name']])){
					$field_data = array();
				} else {
					if (!is_array($parent[$params['parent_name']])){
						$field_data = explode(',', $parent[$params['parent_name']]);
					} else {
						$field_data = $parent[$params['parent_name']];
					}
				}

				if (!in_array($block_id, $field_data)){
					$field_data[] = $block_id;
					$field_data = array_values($field_data); // renum array
					$this->cms_page_panel_model->update_cms_page_panel($data['parent_id'], [$params['parent_name'] => $field_data, ], true);
				}

			}
			 
			if (!empty($params['on_save'])){
				$this->load->model($params['on_save']['model']);
				// should support more params, ok for now
				$params['on_save']['params'][0] = str_replace('_block_id', $block_id, $params['on_save']['params'][0]);
				$params['on_save']['params'][1] = str_replace('_heading', $data['panel_params'][$params['title_field']], $params['on_save']['params'][1]);
				if (count($params['on_save']['params']) == 1){
					$this->$params['on_save']['model']->$params['on_save']['function']($params['on_save']['params'][0]);
				} else {
					$this->$params['on_save']['model']->$params['on_save']['function']($params['on_save']['params'][0], $params['on_save']['params'][1]);
				}
			}
			 
			$params = array_merge($params, array('block_id' => $block_id, 'filter' => array('block_id' => $block_id, )));

		} elseif ($do == 'cms_page_panel_delete'){
			 
			$block_id = $this->input->post('block_id');
			
			$this->load->model('cms_panel_model');
			
			// data for filenames
			$data = $this->cms_page_panel_model->get_cms_page_panel($block_id);
			$panel_config = $this->cms_panel_model->get_cms_panel_config($data['panel_name']);
			$panel_structure = !empty($panel_config['item']) ? $panel_config['item'] : array();
				
			$this->cms_page_panel_model->delete_cms_page_panel($block_id);

			// delete files
			$filenames = $this->cms_page_panel_model->get_page_panel_data_filenames($panel_structure, $data);
			foreach($filenames as $filename){
				unlink($GLOBALS['config']['upload_path'].$filename);
			}

		}
		
		if (empty($params['cms_page_panel_id'])){
			$params['cms_page_panel_id'] = !empty($params['block_id']) ? $params['block_id'] : 0;
		}
		
		return $params;

	}

}
