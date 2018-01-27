<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Index extends MY_Controller {
	
    public function __construct() {
    	
        parent::__construct();
             
        $this->params = $this->input->post();
                 
   	}
   	
   	/**
   	 *  get cms_page_panels data and check for shortcuts 
   	 */
   	function _get_cms_page_panels($page_id){
   		
	    $blocks = $this->cms_page_panel_model->get_cms_page_panels_by(array('page_id' => $page_id, 'show' => 1, ));
	    
		foreach($blocks as $key => $block){
	    	// check for shorcut panels
    		if (is_numeric($block['panel_name']) && (int)$block['panel_name'] == $block['panel_name']){
    			// get real panel data
				$blocks[$key] = $this->cms_page_panel_model->get_cms_page_panel($block['panel_name']);
				$blocks[$key]['page_id'] = $page_id;
    		}
		}
		
		return $blocks;
	
   	}

    function index($page_id = 1, $extra = ''){
    	
	    $this->load->model('cms_page_panel_model');
    	$this->load->model('cms_page_model');
    	$this->load->model('cms_menu_model');
    	 
    	$page_config = array();
    		
    	// set page static config
    	if (!empty($GLOBALS['config']['static_panels'])){
    		foreach($GLOBALS['config']['static_panels'] as $position => $panel_name ){
    			if (!is_array($panel_name)){
    				$panel_name = array($panel_name);
    			}
    			foreach($panel_name as $pn){
	    			// get data
	    			$panel_a = $this->cms_page_panel_model->get_cms_page_panels_by(array('panel_name' => $pn, 'page_id' => [999999,0], ));
	    			if (!empty($panel_a[0])){
	    				$params = array_merge($panel_a[0], array('page_id' => $page_id, ));
	    			} else {
	    				$params = array('page_id' => $page_id, );
	    			}
	    			$page_config[] = array('position' => $position, 'panel' => $pn, 'params' => $params, );
    			}
    		}
    	}
    	
    	// if module list item module/item=XX then / causes second parameter
    	if (!empty($extra)){
    		$page_id = $page_id . '/' . $extra;
    	}

    	// get panels on page
    	if (!stristr($page_id, '=')){ // direct page id

			$page = $this->cms_page_model->get_page($page_id);

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
				);
				
			}
			
    	} else { // put a panel to main position

    		list($panel_name, $cms_page_panel_id) = explode('=', $page_id);
 			$extra_params = array($panel_name => $cms_page_panel_id, '_panel_name' => $panel_name, '_cms_page_panel_id' => $cms_page_panel_id, '_page_id' => $page_id, );
 			$GLOBALS['_page_params'] = $extra_params;
    	
    		// this is when there is page parameter
    		// check if template page exists
    		$page = $this->cms_page_model->get_page_by_slug(str_replace('_', '-', $panel_name));
    		
    		// try without model
    		if (empty($page['page_id']) && stristr($panel_name, '/')){
    			list($m_module, $m_panel_name) = explode('/', $panel_name);
    			$page = $this->cms_page_model->get_page_by_slug(str_replace('_', '-', $m_panel_name));
    		}

    		if (!empty($page['page_id'])){
    			
	    		// if page exists, overload this
	    		$blocks = $this->_get_cms_page_panels($page['page_id']);
	    		 
				foreach($blocks as $block){
					
					// if the same block, load extra variable params, eg panel_name comes from url: "article=42" and block['panel_name'] is "article" or "news/article"
					if(stristr($block['panel_name'], '/')){
						list($block_module, $block_panel_name) = explode('/', $block['panel_name']);
					} else {
						$block_panel_name = $block['panel_name'];
					}

					if ($panel_name === $block_panel_name){
						$extra_params_2 = array_merge($this->cms_page_panel_model->get_cms_page_panel($cms_page_panel_id), $extra_params);
					} else {
						$extra_params_2 = $extra_params;
					}

					$page_config[] = array(
							'position' => 'main',
							'panel' => $block['panel_name'],
							'params' => array_merge($block, $extra_params_2, // keep submenu details from settings ->
									array('submenu_anchor' => $block['submenu_anchor'], 'submenu_title' =>  $block['submenu_title'], )),
					);
					
				}

    		} else {
	    		// else create new with one block
	    		$block = $this->cms_page_panel_model->get_cms_page_panel($cms_page_panel_id);
	    		if ($block['show'] == 1){
					$page_config[] = array(
							'position' => 'main',
							'panel' => $panel_name,
							'params' => array_merge($block, $extra_params),
					);
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
			//  output to the template
			$this->output((!empty($page['layout']) ? $page['layout'] : 'default'), $panel_data);
		
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
