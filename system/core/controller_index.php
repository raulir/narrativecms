<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Index extends CI_Controller {
	
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
	    	// check for shorcut panels
    		if (is_numeric($block['panel_name']) && (int)$block['panel_name'] == $block['panel_name']){
    			// get real panel data
				$blocks[$key] = $this->cms_page_panel_model->get_cms_page_panel($block['panel_name']);
				$blocks[$key]['cms_page_id'] = $page_id;
    		}
		}
		
		return $blocks;
	
   	}

    function index($page_id = 1, $extra = ''){
    	
	    $this->load->model('cms/cms_page_panel_model');
    	$this->load->model('cms/cms_page_model');
    	$this->load->model('cms/cms_menu_model');
    	
    	$page_config = array();
    		
        // if module list item module/item=XX then / causes second parameter
    	if (!empty($extra)){
    		$page_id = $page_id . '/' . $extra;
    	}
    	
    	// set page static config
    	if (!empty($GLOBALS['config']['static_panels'])){
    		_html_error('Config contains static panels. This is not supported anymore.');
    	}

    	// get panels on page
    	$cms_page_id = 0;
    	if (!stristr($page_id, '=')){ // direct page id

			$page = $this->cms_page_model->get_page($page_id, 'auto');

			if (!empty($page['seo_title'])){
    			$GLOBALS['_panel_titles'][] = $page['seo_title'];
			} else if (!empty($page['title'])){
				$GLOBALS['_panel_titles'][] = $page['title'];
			}
			$GLOBALS['_panel_descriptions'][] = !empty($page['description']) ? $page['description'] : '';
			$GLOBALS['_panel_images'][] = !empty($page['image']) ? $page['image'] : '';
			
			$blocks = $this->_get_cms_page_panels($page_id);

			foreach($blocks as $block){

				$page_config[] = array(
						'position' => 'main',
						'panel' => $block['panel_name'],
						'params' => $block,
						'_cms_layout' => $page['layout'],
				);
				
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

					$page_config[] = array(
							'position' => 'main',
							'panel' => $block['panel_name'],
							'params' => array_merge($block, $extra_params_2, // keep submenu details from settings ->
									array('submenu_anchor' => $block['submenu_anchor'], 'submenu_title' =>  $block['submenu_title'], )),
							'_cms_layout' => $page['layout'],
					);
					
				}
				
				$cms_page_id = $page['cms_page_id'];

    		} else { // put a panel to main position
    			
	    		if ($list_item_data['show'] == 1){
					$page_config[] = array(
							'position' => 'main',
							'panel' => $panel_name,
							'params' => array_merge($list_item_data, $extra_params),
							'_cms_layout' => $page['layout'],
					);
	    		}
	    		
    		}
    		
    	}
    	
    	// add headers, footers, etc
    	if($cms_page_id && !empty($page['positions'])){

    		foreach($page['positions'] as $position){
    			
    			if (!empty($position['value'])){
    		
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

    	if (extension_loaded('newrelic') && !empty($page_config)) {

			newrelic_set_appname($GLOBALS['config']['title']);
			
    		$newrelic_name = '';
    		foreach($page_config as $config_item){
    			if ($config_item['position'] == 'main'){
    				$newrelic_name .= '/'.$config_item['panel'];
    			}
    		}
    		
    		if (!empty($newrelic_name)){
  				newrelic_name_transaction($newrelic_name);
    		}
    		
		}

		//
		$_ajax = $this->input->post('_ajax');
		if (empty($_ajax)){

			// render panels
			$panel_data = $this->render($page_config);

			//  output to the template, deprecated - layout without module name
			if (!stristr($page['layout'], '/')){
				$page['layout'] = 'cms/'.$page['layout'];
			}
			$this->output($page['layout'], $page_id, $panel_data);
		
		} else {
			
			$positions = $this->input->post('cms_positions');
			
			if (!empty($positions)){
				
				// new position_links functionality
				$return = [];
				
//				_print_r($positions);
				
//				_print_r($page_config);
				
				$positions_needed = array_keys($positions);

				foreach($page_config as $key => $panel_config){
					if (in_array($panel_config['position'], $positions_needed)
							&& ($panel_config['params']['cms_page_id'] != $positions[$panel_config['position']])) {
						
						$panel_data = $this->ajax_panel($panel_config['panel'], $panel_config['params']);
						
						if (empty($return[$panel_config['position']])){
							$return[$panel_config['position']]['html'] = '';
						}
						$return[$panel_config['position']]['html'] .= $panel_data['html'];
						$return[$panel_config['position']]['cms_page_id'] = $panel_config['params']['cms_page_id'];
					}
				}

				print(json_encode(array(
						'positions' => $return,
						'title' => $this->compile_page_title(),
				)));

			} else {
		
				$return = '';
	
				$_positions = $this->input->post('_positions');
				foreach($page_config as $key => $panel_config){
					if (in_array($panel_config['position'], $_positions)) {
						$panel_data = $this->ajax_panel($panel_config['panel'], $panel_config['params']);
						$return .= $panel_data['html'];
					}
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
						'html' => $return, 
						'menu_item_id' => $menu_item_id, 
						'title' => !empty($GLOBALS['_panel_titles']) ? trim(implode(' - ', $GLOBALS['_panel_titles']), ' -') : '',
				)));
			
			}
				
		}
		
    }
    
}
