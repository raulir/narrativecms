<?php

namespace cms;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_page_panel extends \Controller {

	function __construct(){

		parent::__construct();

		// check if user
		if(empty($_SESSION['cms_user']['cms_user_id'])){
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}

		// print_fields() in cms_page_panel.tpl.php — definition fields + stored block only.
		// Do not call the page panel's panel_params here — that is frontend-only
		// (redirects, score HTML, etc.). Custom field types prepare themselves via
		// their own panel_params when print_fields() renders them with _panel().
		// See modules/cms/docs/cms_panel_params.md
		$this->load->helper('cms/cms_fields_helper');

	}

	/**
	 * Admin ajax actions (save, delete, show, copy, caching, shortcut, title preview).
	 * Domain work lives on cms_page_panel_model; hooks stay here.
	 */
	function panel_action($params){

		$do = $this->input->post('do');
		if (empty($do)){
			return $params;
		}

		// Action-only: skip editor panel_params + template (get_ajax_panel callers)
		$params['no_html'] = 1;

		$this->load->model('cms/cms_page_panel_model');
		$this->load->model('cms/cms_panel_model');

		if ($do == 'cms_page_panel_shortcut'){

			$this->cms_page_panel_model->create_cms_page_panel([
					'sort' => 'last',
					'cms_page_id' => $this->input->post('cms_page_id'),
					'title' => '',
					'panel_name' => $this->input->post('cms_page_panel_id'),
			]);

		} elseif ($do == 'cms_page_panel_caching'){

			$target_id = $this->input->post('target_id');
			$lists = $this->input->post('lists');
			$caching = $this->input->post('caching');

			$params['_caching'] = 0;

			if (!empty($lists) && is_array($lists)){
				$this->cms_page_panel_model->update_cms_page_panel($target_id, ['_cache_lists' => implode(',', $lists), ]);
				$params['_caching'] = 1;
			} else {
				$this->cms_page_panel_model->update_cms_page_panel($target_id, ['_cache_lists' => '', ]);
			}

			$this->cms_page_panel_model->update_cms_page_panel($target_id, ['_cache_time' => $caching, ]);
			if (!empty($caching)){
				$params['_caching'] = 1;
			}

		} elseif ($do == 'cms_page_panel_show'){

			$cms_page_panel_id = $this->input->post('cms_page_panel_id');
			$block = $this->cms_page_panel_model->get_cms_page_panel($cms_page_panel_id);

			if (!empty($block['show'])){
				$result = $this->cms_page_panel_model->set_cms_page_panel_show($cms_page_panel_id, 0);
			} else {
				$params['notification'] = $this->run_panel_method($block['panel_name'], 'on_show', $block);
				$result = $this->cms_page_panel_model->set_cms_page_panel_show($cms_page_panel_id, 1);
			}

			$params['show'] = $result['show'];

		} elseif ($do == 'cms_page_panel_copy'){

			$this->cms_page_panel_model->copy_cms_page_panel($this->input->post('cms_page_panel_id'));

		} elseif ($do == 'cms_page_panel_preview_title'){

			$block_id = $this->input->post('cms_page_panel_id');
			$language = $this->_resolve_cms_language($this->input->post('language'));

			if (!$this->cms_page_panel_model->_is_default_language($language)){
				if (!empty($block_id)){
					$saved_panel = $this->cms_page_panel_model->get_cms_page_panel(
							$block_id, $this->cms_page_panel_model->default_language);
					if (is_array($saved_panel)){
						$params['_title'] = $this->cms_page_panel_model->get_panel_admin_title($saved_panel);
					}
				}
			} else {
				$built = $this->cms_page_panel_model->build_panel_data_for_save(
						$this->_post_panel_form_input($block_id), $language);
				$compiled_title = $this->cms_page_panel_model->compile_list_item_title(
						$built['data_merged'], $built['panel_config'], $block_id, $language);

				if ($compiled_title !== false){
					$params['_title'] = $this->cms_page_panel_model->_compute_cached_title(
							$compiled_title, $built['data_merged']['_targets'] ?? []);
				}
			}

		} elseif ($do == 'cms_page_panel_save'){

			$block_id = $this->input->post('cms_page_panel_id');
			$language = $this->_resolve_cms_language($this->input->post('language'));

			$old_data = $this->cms_page_panel_model->get_cms_page_panel($block_id, $language);

			$built = $this->cms_page_panel_model->build_panel_data_for_save(
					$this->_post_panel_form_input($block_id), $language);
			$data_merged = $built['data_merged'];
			$panel_config = $built['panel_config'];
			$panel_structure = $built['panel_structure'];

			if (!empty($old_data['_cache_lists'])){
				$data_merged['_cache_lists'] = $old_data['_cache_lists'];
			}
			if (!empty($old_data['_cache_time'])){
				$data_merged['_cache_time'] = $old_data['_cache_time'];
			}

			$compiled_title = $this->cms_page_panel_model->compile_list_item_title(
					$data_merged, $panel_config, $block_id, $language);

			if ($compiled_title !== false && $this->cms_page_panel_model->_is_default_language($language)){
				$data_merged['title'] = $compiled_title;
			}

			$data_merged = $this->run_panel_method($data_merged['panel_name'], 'on_update', $data_merged);

			$saved = $this->cms_page_panel_model->save_cms_page_panel_admin($block_id, $data_merged, [
					'panel_config' => $panel_config,
					'parent_name' => $this->input->post('parent_name'),
					'old_data' => is_array($old_data) ? $old_data : [],
			]);
			$block_id = $saved['cms_page_panel_id'];

			$this->cms_page_panel_model->delete_orphan_upload_files(
					$panel_structure, is_array($old_data) ? $old_data : [], $data_merged);

			$params['cms_page_panel_id'] = $block_id;

			$saved_panel = $this->cms_page_panel_model->get_cms_page_panel($block_id, $language);
			if (is_array($saved_panel)){
				$params['_title'] = $this->cms_page_panel_model->get_panel_admin_title($saved_panel);
			}

		} elseif ($do == 'cms_page_panel_delete'){

			$block_id = $this->input->post('cms_page_panel_id');

			$data = $this->cms_page_panel_model->get_cms_page_panel($block_id);
			$panel_config = $this->cms_panel_model->get_cms_panel_config($data['panel_name']);
			$panel_structure = $this->cms_panel_model->get_cms_panel_edit_structure(
					$panel_config, $data['cms_page_id'], $data['parent_id'], $data['sort']);

			$this->cms_page_panel_model->delete_cms_page_panel($block_id);
			$this->cms_page_panel_model->delete_orphan_upload_files($panel_structure, $data);

		}

		if (empty($params['cms_page_panel_id'])){
			$params['cms_page_panel_id'] = !empty($params['block_id']) ? $params['block_id'] : 0;
		}

		return $params;

	}

	function _resolve_cms_language($language){

		$this->load->model('cms/cms_language_model');
		$resolved_language = $this->cms_language_model->resolve_language_id(
				$language, $GLOBALS['language']['languages'] ?? []);
		return $resolved_language !== false ? $resolved_language : $language;

	}

	function _post_panel_form_input($block_id){

		return [
			'cms_page_panel_id' => $block_id,
			'cms_page_id' => $this->input->post('cms_page_id'),
			'parent_id' => $this->input->post('parent_id'),
			'sort' => $this->input->post('sort'),
			'title' => $this->input->post('title'),
			'submenu_anchor' => $this->input->post('submenu_anchor'),
			'panel_name' => $this->input->post('panel_name'),
			'panel_params' => $this->input->post('panel_params'),
			'_template_page_id' => $this->input->post('_template_page_id'),
		];

	}

	function panel_params($params){
		
		$this->load->model('cms/cms_page_panel_model');
		$this->load->model('cms/cms_page_model');
		$this->load->model('cms/cms_panel_model');
		$this->load->model('cms/cms_module_model');
		$this->load->model('cms/cms_user_model');
		$this->load->model('cms/cms_language_model');
		
		$return = [];

		// set up new page panel
		$params['target_type'] = $this->input->post('target_type');
		$params['panel_name'] = $this->input->post('panel_name');
		if (is_string($params['panel_name'])){
			$params['panel_name'] = str_replace('__', '/', trim($params['panel_name']));
		}

		// if page
		if (empty($params['cms_page_panel_id']) && empty($params['cms_page_id']) && $params['target_type'] == 'page'){
			
			$params['cms_page_id'] = $this->input->post('target_id');
			$params['cms_page_panel_id'] = 0;

			if (!empty($params['panel_name']) && stristr($params['panel_name'], '/')){
				list($params['module'], $params['module_panel_name']) = explode('/', $params['panel_name'], 2);
			}
			
		} else if (empty($params['cms_page_panel_id']) && empty($params['cms_page_id']) && $params['target_type'] == 'panel'){

			$params['parent_id'] = $this->input->post('target_id');
			$params['parent_input_name'] = $this->input->post('target_input_name');
			
			$return['parent_name'] = $params['parent_input_name'];
			
			$params['cms_page_panel_id'] = 0;
			$params['cms_page_id'] = 0;

		}

		// URL segment can be a panel type with __ (e.g. admin/cms_page_panel/timmy__menu/)
		// Never treat a pure numeric id as a panel type name
		if (!empty($params['cms_page_panel_id']) && !is_numeric($params['cms_page_panel_id'])){
			$params['panel_name'] = str_replace('__', '/', trim($params['cms_page_panel_id']));
			$params['cms_page_panel_id'] = 0;
			$params['cms_page_id'] = 0;
		} else if (!empty($params['cms_page_panel_id']) && is_numeric($params['cms_page_panel_id'])){
			$return['block'] = $this->cms_page_panel_model->get_cms_page_panel($params['cms_page_panel_id'], $this->cms_language_model->get_cms_language());
		}

		// New panel from selector must have module/panel type
		if (empty($return['block']['cms_page_panel_id']) && !empty($params['target_type'])
				&& (empty($params['panel_name']) || !stristr($params['panel_name'], '/'))){
			_html_error('Panel name has to include module (got: '.var_export($params['panel_name'], true).')', 0, ['backtrace' => 1]);
			return $return;
		}

		if (!empty($params['base_url'])){
			$return['base_url'] = $params['base_url'];
		}

		if (!empty($params['base_title'])){
			$return['base_title'] = $params['base_title'];
		}

		if (!empty($params['_mode'])){
			$return['_mode'] = $params['_mode'];
		}

		// if no filtered block returned but has panel name
		if (empty($return['block']['cms_page_panel_id']) && !empty($params['panel_name']) && stristr($params['panel_name'], '/')){
			
			$return['block'] = $this->cms_page_panel_model->new_cms_page_panel();
			
			// new panel name
			$title = ucfirst(trim(!empty($params['module_panel_name']) ? $params['module_panel_name'] : $params['panel_name']));
			
			$title_needed = true;
			$i = 0;
				
			while ($title_needed){
			
				if ($i == 0){
					$new_title = $title;
				} else {
					$new_title = $title . ' (' . $i . ')';
				}
			
				$i++;
			
				$page_panels = $this->cms_page_panel_model->get_cms_page_panels_by(['title' => $new_title, ]);
			
				if (!count($page_panels)){
					$title_needed = false;
				}
			
			}
				
			$return['block']['title'] = $new_title;
			
			// new block of type
			$return['block']['panel_params'] = array();
			$return['block']['cms_page_id'] = 0; // $params['cms_page_id'];
			
			if ($params['target_type'] == 'page'){
				$return['block']['cms_page_id'] = $params['cms_page_id'];
			}
			
			$return['block']['panel_name'] = $params['panel_name'];
			
			// get new sort too as this is should be real block
			$return['block']['sort'] = $this->cms_page_panel_model->get_max_cms_page_panel_id($params['panel_name']) + 1;

		}

		// no page page_id -> 0
		if ($return['block']['cms_page_id'] == 999999) $return['block']['cms_page_id'] = 0;

		if (!empty($return['block']['cms_page_id'])){
			$return['cms_page'] = $this->cms_page_model->get_page($return['block']['cms_page_id']);
			$return['cms_page_id'] = $return['cms_page']['cms_page_id'];
		} else {
			$return['_admin_title'] = $return['block']['title'];
			$return['independent_block'] = 1;
		}

		// this is where panel definition comes from
		$return['block']['panel_definition'] = $return['block']['panel_name'];
		
		// check if panel is list item on the same named page
		$panel_definition = $this->cms_panel_model->get_cms_panel_config($return['block']['panel_definition']);

//		if (!empty($panel_definition['list']) && !empty($return['cms_page_id']) && $return['block']['panel_name'] == $panel_definition['module'].'/'.$return['cms_page']['slug']){
		if (!empty($panel_definition['list']) && !empty($return['cms_page_id'])){
			if (empty($panel_definition['settings'])){
				$panel_structure = [];
			} else {
				$panel_structure = $panel_definition['settings'];
			}
		} else {

			if (!empty($return['cms_page_id']) || !empty($return['block']['cms_page_id']) || !empty($return['block']['sort']) || !empty($return['block']['parent_id'])){

				$panel_structure = !empty($panel_definition['item']) ? $panel_definition['item'] : [];
			
			} else {
				if (!empty($panel_definition['settings'])){
					$panel_structure = $panel_definition['settings'];
				} else {
					$panel_structure = [
							[
									'type' => 'subtitle',
									'label' => 'This panel doesn\'t have settings fields'
							]
					];
				}
			}
		}
		
		// list items with template selector
		if (!empty($panel_definition['list']['templates'])){
			$return['list_templates'] = [];
			foreach($panel_definition['list']['templates'] as $page_slug => $list_template_name){
				$list_template_page = $this->cms_page_model->get_page_by_slug($page_slug); 
				if (!empty($list_template_page['cms_page_id'])){
					$return['list_templates'][$list_template_page['cms_page_id']] = $list_template_name;
				}
			}
		}
		
		$return['panel_params_structure'] = $panel_structure; // $this->cms_panel_model->get_cms_panel_definition($return['block']['panel_definition']);

		if (empty($return['block']['parent_id'])){
			$return['block']['parent_id'] = !empty($params['parent_id']) ? $params['parent_id'] : 0;
		}
		
		// creation and update
		if (!empty($return['block']['create_cms_user_id'])){
			$return['block']['create_user'] = $this->cms_user_model->get_cms_user($return['block']['create_cms_user_id']);
		}
		if (empty($return['block']['create_user'])){
			$return['block']['create_user'] = [];
		}
		if (!empty($return['block']['update_cms_user_id'])){
			$return['block']['update_user'] = $this->cms_user_model->get_cms_user($return['block']['update_cms_user_id']);
		}
		if (empty($return['block']['update_user'])){
			$return['block']['update_user'] = [];
		}
		
		$return['extra_buttons'] = $panel_definition['extra_buttons'] ?? [];
		
		return $return;

	}

}