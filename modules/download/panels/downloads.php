<?php if (! defined ( 'BASEPATH' )) exit ( 'No direct script access allowed' );

class downloads extends CI_Controller {
	
	function panel_params($params) {
	
		$this->load->model('cms/cms_page_panel_model');
		
		$params['downloads'] = array_values($this->cms_page_panel_model->get_list('download/download', ['group' => $params['group']]));
		
		if (!empty($params['arrange_images'])){
		
			$images = array_values($params['images']);
	
			$image = 0;
			$number_images = count($images);
			
			foreach($params['downloads'] as $key => $download){
				
				if ($key % 2 == 0 && $number_images){
					$params['downloads'][$key]['image'] = $images[$image % $number_images]['image'];
					$image ++;
				} else {
					$params['downloads'][$key]['image'] = '';
				}
	
			}
			
		}
		
		return $params;
		
	}
	
}
