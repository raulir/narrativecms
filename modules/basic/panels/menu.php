<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class menu extends CI_Controller {
	
	function __construct(){
		
        parent::__construct();        

		$this->js[] = [
				'script' => 'modules/cms/js/cms_change_hash.js',
				'sync' => 'defer', 
		];
		$this->js[] = [
				'script' => 'modules/cms/js/cms_scroll_to.js',
				'sync' => 'defer',
		];
		
	}
	
	function panel_params($params){

		if (!empty($params['links'])){
			
			foreach ($params['links'] as $key => $link){

				// link leads to the same page
				if ($link['link']['cms_page_id'] == $params['page_id'] || $link['link']['target'] == '_none') {
					
					if (empty($link['hash'])){
						$params['links'][$key]['hash'] = '_top';
					}
					
					$params['links'][$key]['cms_scroll_to'] = true;
					
				} else {
					
					$params['links'][$key]['href'] = $link['link']['url'].(!empty($link['hash']) ? '#'.$link['hash'] : '');
				
				}
				
			}
		
		} else {
			
			$params['links'] = [];
			
		}

		return $params;
	
	}
	
}
