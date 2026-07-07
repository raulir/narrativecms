<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Index extends CI_Controller {
	
	var $params;
	var $cms_language_model;
	var $cms_page_panel_model;
	var $cms_page_model;
	var $cms_menu_model;
	var $cms_panel_model;
	var $cms_image_model;
	
    public function __construct() {
    	
        parent::__construct();
             
        $this->params = $this->input->post();
                 
   	}
   	
   	/**
   	 *  get cms_page_panels data and check for shortcuts 
   	 */
   	function _get_cms_page_panels($page_id){

   		$blocks = $this->cms_page_panel_model->get_cms_page_panels_by(['cms_page_id' => $page_id, 'show' => 1, ]);
	    
		foreach($blocks as $key => $block){

			if (!$this->cms_page_panel_model->panel_matches_visitor_targets($block)){
				unset($blocks[$key]);
				continue;
			}

	    	// check for shorcut panels
    		if (is_numeric($block['panel_name']) && (int)$block['panel_name'] == $block['panel_name']){
    			// get real panel data
				$blocks[$key] = $this->cms_page_panel_model->get_cms_page_panel($block['panel_name']);
				$blocks[$key]['cms_page_id'] = $page_id;
    		}
		}
		
		return $blocks;
	
   	}
   	
   	function _auth_redirect_if_needed($page, $panels_page_id = 0){
   		
   		if (empty($page['cms_page_id'])){
   			return;
   		}
   		
   		if (empty($panels_page_id)){
   			$panels_page_id = $page['cms_page_id'];
   		}
   		
   		$this->load->model('cms/cms_access_model');
   		$blocks = $this->_get_cms_page_panels($panels_page_id);
   		$redirect_url = $this->cms_access_model->resolve_auth_redirect_url($page, $blocks);
   		
   		if ($redirect_url){
   			_position_link_redirect($redirect_url);
   		}
   		
   	}
   	
   	function _enforce_main_page_access($page){
   		
   		if (empty($page['cms_page_id'])){
   			return;
   		}
   		
   		$this->load->model('cms/cms_access_model');
   		$is_ajax = !empty($this->input->post('_ajax'));
   		$this->cms_access_model->enforce_page_access($page, ['no_html' => $is_ajax ? 1 : 0]);
   		
   	}

    function index($page_id = 0, $extra = ''){
// _print_r($page_id);
    	if (empty($page_id)){
    		$page_id = $GLOBALS['config']['landing_page']['_value'];
    	}

    	$this->load->model('cms/cms_page_panel_model');
    	$this->load->model('cms/cms_page_model');
    	$this->load->model('cms/cms_menu_model');
    	
    	$page_config = [];
    	
    	$get_params = $this->input->get();
    	$bad_params = ['module', 'cms_page_panel_id', 'show', 'panel_name', ];
    	foreach($bad_params as $bad_param){
	    	if (isset($get_params[$bad_param])){
	    		unset($get_params[$bad_param]);
	    	}
    	}
    	
    	if (!is_array($get_params)){
    		$get_params = [];
    	}
    		
        // if module list item module/item=XX then / causes second parameter
    	if (!empty($extra)){
    		$page_id = $page_id . '/' . $extra;
    	}

    	// get panels on page
    	$cms_page_id = 0;
    	if (!stristr($page_id, '=')){ // direct page id

    		$page = $this->cms_page_model->get_page($page_id, 'auto');
    		
    		$this->_auth_redirect_if_needed($page, $page_id);
    		$this->_enforce_main_page_access($page);

			if (!empty($page['seo_title'])){
    			$GLOBALS['_panel_titles'][] = $page['seo_title'];
			} else if (!empty($page['title'])){
				$GLOBALS['_panel_titles'][] = $page['title'];
			}
			$GLOBALS['_panel_descriptions'][] = !empty($page['description']) ? $page['description'] : '';
			
    		if (!empty($page['image'])){
				$GLOBALS['_panel_images'][] = $page['image'];
			}
			
    				if (!empty($page['video'])){
				$GLOBALS['_panel_videos'][] = $page['video'];
			}
			
			if (!empty($page['video_id'])){
				$GLOBALS['_panel_video_id'] = $page['video_id'];
			}
			
			$blocks = $this->_get_cms_page_panels($page_id);

			foreach($blocks as $block){

				$page_config[] = [
						'position' => 'main',
						'panel' => $block['panel_name'],
						'params' => array_merge($get_params, $block),
						'_cms_layout' => $page['layout'],
				];
				
			}

			$cms_page_id = $page['cms_page_id'];

    	} else { // list item page

    		list($panel_name, $cms_page_panel_id) = explode('=', $page_id);
 			$extra_params = array($panel_name => $cms_page_panel_id, '_panel_name' => $panel_name, '_cms_page_panel_id' => $cms_page_panel_id, '_page_id' => $page_id, );
 			$GLOBALS['_page_params'] = $extra_params;

 			// list item panel data, for example article
    		$list_item_data = $this->cms_page_panel_model->get_cms_page_panel($cms_page_panel_id);

    		// if can't find list item - error 404
    		if (!is_array($list_item_data)){
    			show_404($page_id);
    		}
 			
    		if (empty($list_item_data['_template_page_id'])){
 			
	 			// this is when there is page parameter
	    		// check if template page exists
	    		$page = $this->cms_page_model->get_page_by_slug(str_replace('_', '-', $panel_name));
	    		
	    		// try without model
	    		if (empty($page['page_id']) && stristr($panel_name, '/')){
	    			list($m_module, $m_panel_name) = explode('/', $panel_name);
	    			$page = $this->cms_page_model->get_page_by_slug(str_replace('_', '-', $m_panel_name));
	    		}
    		
    		} else {
    			
    			// special template
    			$page = $this->cms_page_model->get_page($list_item_data['_template_page_id'], 'auto');
    			
    		}

    		if (!empty($page['page_id'])){
    			
    			$this->_auth_redirect_if_needed($page, $page['page_id']);
    			$this->_enforce_main_page_access($page);

    			// if page exists, overload this
	    		$blocks = $this->_get_cms_page_panels($page['page_id']);
	    		 
				foreach($blocks as $block){
					
					// if the same block, load extra variable params, eg panel_name comes from url: "article=42" 
					// and block['panel_name'] is "article" or "news/article"
					if(stristr($block['panel_name'], '/')){
						list($block_module, $block_panel_name) = explode('/', $block['panel_name']);
					} else {
						$block_panel_name = $block['panel_name'];
					}

					if ($panel_name === $block_panel_name || stristr($panel_name.'|', '/'.$block_panel_name.'|')){
						$extra_params_2 = array_merge($list_item_data, $extra_params);
					} else {
						$extra_params_2 = $extra_params;
					}

					$page_config[] = [
							'position' => 'main',
							'panel' => $block['panel_name'],
							'params' => array_merge($get_params, $block, $extra_params_2, // keep submenu details from settings ->
									[
											'submenu_anchor' => $block['submenu_anchor'], 
											'submenu_title' =>  $block['submenu_title'], 
											'cms_page_id' => $page['cms_page_id'],
									]),
							'_cms_layout' => $page['layout'],
					];
					
				}
				
				$cms_page_id = $page['cms_page_id'];

    		} else { // put a panel to main position
    			
    			if (empty($page)){
    				_html_error('No panel template or page defined. Panel name: '.$panel_name, 500);
    			}
    			
	    		if ($list_item_data['show'] == 1){
	    			$page_config[] = [
							'position' => 'main',
							'panel' => $panel_name,
							'params' => array_merge($get_params, $list_item_data, $extra_params, ['cms_page_id' => $page['cms_page_id'], ]),
							'_cms_layout' => $page['layout'],
					];
	    		}
	    		
    		}
    		
    	}

    	$position_pages = [];

    	// add headers, footers, etc
    	if($cms_page_id && !empty($page['positions'])){

    		$this->load->model('cms/cms_access_model');

    		foreach($page['positions'] as $position){
    			
    			if (!empty($position['value'])){
    				
    				$position_page = $this->cms_page_model->get_page($position['value']);
    				if (!empty($position['name'])) {
    					$position_pages[$position['name']] = $position_page;
    				}
    				
    				if (!$this->cms_access_model->user_has_page_access($position_page['access'] ?? '')){
    					
    					$page_config[] = [
    							'position' => $position['name'],
    							'_inline_access_denied' => 1,
    							'params' => ['cms_page_id' => $position['value']],
    							'_cms_page_id' => $cms_page_id,
    					];
    					
    					continue;
    					
    				}

    				$blocks = $this->_get_cms_page_panels($position['value']);
		    		
		    		foreach($blocks as $block){
		    		
		    			$page_config[] = array(
		    					'position' => $position['name'],
		    					'panel' => $block['panel_name'],
		    					'params' => $block,
		    					'_cms_page_id' => $cms_page_id,
								'_cms_layout' => $page['layout'],
		    			);
		    		
		    		}
	    		
    			}

    		}
    		
    	}

		if (!empty($_COOKIE['cms_preview_highlight'])) {
			$page_config[] = [
				'position' => 'footer',
				'panel' => 'cms/cms_preview_site',
				'params' => ['cms_page_panel_id' => (int)$_COOKIE['cms_preview_highlight']],
			];
		}

		$_ajax = $this->input->post('_ajax');
		if (empty($_ajax)){

			$page_cache_ttl = 0;
			$main_cache = trim((string)($page['cache'] ?? ''));
			if ($main_cache !== '' && ctype_digit($main_cache) && (int)$main_cache > 0) {
				$this->load->library('cache');
				$page_cache_ttl = $this->cache->should_write($page, $position_pages);
			}
			if ($page_cache_ttl > 0) {
				$GLOBALS['cms_page_cache_write'] = true;
			}

			// render panels
			$panel_data = $this->render($page_config);

			if (!empty($GLOBALS['page_cache_deferred_meta']['panels'])) {
				$this->panel('cms/cms_cache', ['cms_page_panel_id' => 0]);
			}

			if ($page_cache_ttl > 0) {
				$this->cache->write_partial_caches($page, $position_pages, $panel_data, $page_cache_ttl);
			}

			//  output to the template, deprecated - layout without module name
			if (!stristr($page['layout'], '/')){
				$page['layout'] = 'cms/'.$page['layout'];
			}
			if ($page['layout'] === 'cms/default'){
				$page['layout'] = 'cms/fixed';
			}

			$page_cache_context = null;
			if ($page_cache_ttl > 0) {
				$page_cache_context = [
					'ttl' => $page_cache_ttl,
					'page' => $page,
					'position_pages' => $position_pages,
					'route_target' => $page_id,
					'request_uri' => $this->cache->request_uri(),
				];
			}

			$this->output($page['layout'], $page_id, $panel_data, $page_cache_context);
		
		} else {
			
//			header('Access-Control-Allow-Origin: *');

			// existing positions
			$positions = $this->input->post('cms_positions');
			if (!empty($positions)){
				
				// new position_links functionality
				$return = [];
				$response_css = [];
				$response_css_force = 0;

				$positions_needed = array_keys($positions);
				$this->load->model('cms/cms_access_model');
				if (_position_links_active()) {
					$this->load->library('cache');
				}
				$position_page_ids = [];
				$served_from_cache = [];

				foreach ($page_config as $panel_config) {
					if (!in_array($panel_config['position'], $positions_needed)) {
						continue;
					}
					if (!empty($panel_config['params']['cms_page_id'])) {
						$position_page_ids[$panel_config['position']] = (int)$panel_config['params']['cms_page_id'];
					}
				}

				if (_position_links_active()) {
					foreach ($positions_needed as $position_name) {
						if (empty($position_page_ids[$position_name])) {
							continue;
						}
						if ((int)$position_page_ids[$position_name] === (int)($positions[$position_name] ?? 0)) {
							continue;
						}

						$cached_position = $this->cache->try_serve_position($position_page_ids[$position_name]);
						if ($cached_position !== false) {
							$served_from_cache[$position_name] = true;
							_merge_panel_css_urls($response_css, $response_css_force, $cached_position['meta']);
							$return[$position_name] = [
								'_html' => $cached_position['html'],
								'cms_page_id' => (int)$position_page_ids[$position_name],
								'has_deferred' => (int)($cached_position['meta']['has_deferred'] ?? 0),
							];
						}
					}
				}

				foreach($page_config as $key => $panel_config){
					
					if (!in_array($panel_config['position'], $positions_needed)){
						continue;
					}

					if (!empty($served_from_cache[$panel_config['position']])) {
						continue;
					}
					
					if (!empty($panel_config['_inline_access_denied'])){
						
						$return[$panel_config['position']]['_html'] = $this->cms_access_model->get_access_denied_inline_html();
						$return[$panel_config['position']]['cms_page_id'] = (int)$panel_config['params']['cms_page_id'];
						$return[$panel_config['position']]['has_deferred'] = 0;
						continue;
						
					}
					
					if ($panel_config['params']['cms_page_id'] != $positions[$panel_config['position']]) {
						
						$panel_data = $this->ajax_panel($panel_config['panel'], $panel_config['params']);
						_merge_panel_css_urls($response_css, $response_css_force, $panel_data);
						if (empty($return[$panel_config['position']])){
							$return[$panel_config['position']]['_html'] = '';
							$return[$panel_config['position']]['has_deferred'] = 0;
						}
						$return[$panel_config['position']]['_html'] .= $panel_data['_html'];
						$return[$panel_config['position']]['cms_page_id'] = $panel_config['params']['cms_page_id'];
						
					}
				}

				print(json_encode(array(
						'positions' => $return,
						'title' => $this->compile_page_title(),
						'_panel_css' => array_values($response_css),
						'_panel_css_force' => $response_css_force,
				)));

			} else {
		
				$return = '';
	
				$_positions = $this->input->post('_positions');
				$this->load->model('cms/cms_access_model');
				
				foreach($page_config as $key => $panel_config){
					
					if (!in_array($panel_config['position'], $_positions)){
						continue;
					}
					
					if (!empty($panel_config['_inline_access_denied'])){
						$return .= $this->cms_access_model->get_access_denied_inline_html();
						continue;
					}
					
					$panel_data = $this->ajax_panel($panel_config['panel'], $panel_config['params']);
					$return .= $panel_data['_html'];
				}
				
				// top level menu item id if exists
				$menu_item = $this->cms_menu_model->get_menu_items_by(array('link' => $page['slug'].'/', ));
				if (!empty($menu_item[0]['menu_id'])){
					$menu_item = $this->cms_menu_model->get_menu_items_by(array('menu_item_id' => $menu_item[0]['menu_id'], ));
				}
				
				if (empty($menu_item[0]['menu_item_id'])){
					$menu_item_id = 0;
				} else {
					$menu_item_id = $menu_item[0]['menu_item_id'];
				}
					
				print(json_encode(array(
						'_html' => $return, 
						'menu_item_id' => $menu_item_id, 
						'title' => !empty($GLOBALS['_panel_titles']) ? trim(implode(' - ', $GLOBALS['_panel_titles']), ' -') : '',
				)));
			
			}
				
		}
		
    }
    
}
