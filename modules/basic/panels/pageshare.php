<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class pageshare extends CI_Controller{
	
	function panel_action($params){
		
		$do = $this->input->post('do');
        
        if ($do == 'shorten'){
        	
        	$url_key = $this->input->post('url_key');
        	$url = $this->input->post('url');
        	$long_url = $this->input->post('long_url');
        	$title = $this->input->post('title');
        	 
        	$filename = $GLOBALS['config']['base_path'].'/cache/bitly_'.$url_key.'.json';

        	if (md5($url) == $url_key){
        		
        		if (file_exists($filename)){
        			$response = json_decode(file_get_contents($filename), true);
        		}
        		
        		if (empty($response['link'])){

        			$headers = 	[
        					'Authorization: Bearer '.$params['bitly_token'],
        					'Content-Type: application/json',
        			];
	        		
	        		$context = stream_context_create([
	        			'http' => [
	        				'header' => $headers,
	        				'method' => 'POST',
	        				'content'=> json_encode([
	        					'long_url' => $long_url,
								'title' => $title,
        					]),
	        			],
	        		]);

	        		$response = json_decode(file_get_contents('https://api-ssl.bitly.com/v4/bitlinks', 0, $context), true);
	        		
	        		file_put_contents($filename, json_encode($response, JSON_PRETTY_PRINT));
        		
        		}
        		
        	}
        	
        	print(json_encode(['link' => $response['link']], JSON_PRETTY_PRINT));
        	
        	exit();
        }
        
        return $params;
 	
	}
	
	function panel_params($params){

		$this->load->model('cms/cms_page_panel_model');

		if (!empty($params['content'])){
			$content = $params['content'];
		}
		
		$params = array_merge($this->cms_page_panel_model->get_cms_page_panel_settings('basic/pageshare'), $params);
		
		if (!empty($content)){
			$params['content'] = $content;
		}
		
		foreach($params['channels'] as $key => $channel){
			if (!empty($params['filter'])){
				if (!in_array($channel['type'], $params['filter'])){
					unset($params['channels'][$key]);
				}
			}
		}
		
		if (!empty($params['bitly_token'])){
			foreach($params['channels'] as $key => $channel){
				if ($channel['type'] == 'twitter'){

					$url_key = md5($params['url'] ?? '');
					
					$filename = $GLOBALS['config']['base_path'].'/cache/bitly_'.$url_key.'.json';
					
					if (file_exists($filename)){
						
						$bitly_data = json_decode(file_get_contents($filename), true);

						if (!empty($bitly_data['link'])){
						
							$params['channels'][$key]['url'] = $bitly_data['link'];
						
						}
						
					} 
					
					if (empty($params['channels'][$key]['url'])) {
						
						$params['channels'][$key]['url_key'] = $url_key;
						
					}
					
				}
			}
		}

		return $params;
		
	}

}
