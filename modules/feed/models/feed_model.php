<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class feed_model extends CI_Model {
	
	function refresh_feeds(){

   		$this->load->model('cms/cms_page_panel_model');
   		$this->load->model('cms/cms_image_model');
		
   		$feed_settings_a = $this->cms_page_panel_model->get_cms_page_panels_by(array('panel_name' => ['feed/feed_settings', 'feed_settings'], ));
   		
   		if (empty($feed_settings_a[0])){
   			return array();
   		}
   		
   		$feed_settings = $feed_settings_a[0];
   		
   		$stats = array();
   		
   		if (!empty($feed_settings['channels'])){
   		
	   		// check for panels
	   		foreach($feed_settings['channels'] as $feed_setting){
	   			
	   			/* TODO: change articles and projects etc to list */
	   			
	   			if (in_array($feed_setting['source'], array('project', 'article', ))){
	
					$stats['projects'] = 0;
					$projects = $this->cms_page_panel_model->get_cms_page_panels_by(
							array('_limit' => 20, 'panel_name' => $feed_setting['source'], 'page_id' => ['999999','0'], 'show' => '1', ));
			   		
			   		foreach($projects as $project){
			   			
						$hash = md5($feed_setting['source'].'='.$project['block_id']);
						
						// check if this item is already there?
						$project_check_a = $this->cms_page_panel_model->get_cms_page_panels_by(
								array('_limit' => 1, 'panel_name' => ['feed','feed/feed'], 'page_id' => ['999999','0'], 'hash' => $hash, ));
								
						if(!count($project_check_a)){
				   			$item = $this->parse_cms_list_item($project, $feed_setting);
							$item_id = $this->cms_page_panel_model->create_cms_page_panel($item);
							$stats['projects']++;
						}
						
			   		}
		   		}
	   		}
	   		
	   		// check for twitter
	   		foreach($feed_settings['channels'] as $feed_setting){
	   			if ($feed_setting['source'] == 'twitter'){
	
					$stats['tweets'] = 0;
	
					$tweets = $this->get_twitter_by_username(
							$feed_setting['username'], 
							$feed_settings['twitter_api_key'], 
							$feed_settings['twitter_api_secret']
					);
	
			   		foreach($tweets as $tweet){
			   			
			   			$hash = md5('twitter='.$tweet['twitter_id']);
			   			
						// check if this item is already there?
						$project_check_a = $this->cms_page_panel_model->get_cms_page_panels_by(
								array('_limit' => 1, 'panel_name' => ['feed', 'feed/feed'], 'page_id' => ['999999','0'], 'hash' => $hash, ));
								
						if(!count($project_check_a)){
				   			$item = $this->parse_tweet($tweet, $feed_setting);
							$item_id = $this->cms_page_panel_model->create_cms_page_panel($item);
							$stats['tweets']++;
						}
	
			   		}
	   			}
	   		}
	   		
	   		// check for instagram
	   		foreach($feed_settings['channels'] as $feed_setting){
	   			if ($feed_setting['source'] == 'instagram'){
	
					$stats['instagrams'] = 0;
					
					$instagrams = $this->get_instagram_by_username($feed_setting['username']);
	
			   		foreach($instagrams as $instagram){
			   			
			   			$hash = md5('instagram='.$instagram['id']);
			   			
						// check if this item is already there?
						$item_check_a = $this->cms_page_panel_model->get_cms_page_panels_by(
								array('_limit' => 1, 'panel_name' => ['feed', 'feed/feed'], 'page_id' => ['999999','0'], 'hash' => $hash, ));
								
						if(!count($item_check_a)){
				   			$item = $this->parse_instagram($instagram, $feed_setting);
							$item_id = $this->cms_page_panel_model->create_cms_page_panel($item);
							$stats['instagrams']++;
						}
	
			   		}
					
	   			}
	   		}
	   		
   		}

   		return $stats;

	}
	
	function parse_cms_list_item($cms_list_item, $feed_setting){
		
		$item = array();
		$item['hash'] = md5($feed_setting['source'].'='.$cms_list_item['block_id']);

		// panel fields
		$item['page_id'] = '0';
		$item['sort'] = 'first';
		$item['title'] = $cms_list_item['heading'];
		$item['panel_name'] = 'feed/feed';

		// feed fields
		$item['url'] = $feed_setting['source'].'='.$cms_list_item['block_id'];
		$item['show'] = $feed_setting['feed_show'];
		$item['moderated'] = 0;
		$item['source'] = $feed_setting['source'];
		$item['source_id'] = $cms_list_item['block_id'];
		
		if (!empty($cms_list_item['article_category_id'])){
			// get source label from other place
			$this->load->model('cms/cms_page_panel_model');
			$article_category = $this->cms_page_panel_model->get_cms_page_panel($cms_list_item['article_category_id']);
			$item['source_label'] = $article_category['heading'];
		} else {
			$item['source_label'] = $feed_setting['heading'];
		}
		
		$item['update_time'] = time();

		// data fields
		$item['image'] = $cms_list_item['image'];
		$item['icon'] = $feed_setting['icon'];
		$item['heading'] = $cms_list_item['heading'];
		$item['text'] = !empty($cms_list_item['lead']) ? $cms_list_item['lead'] : '';
		if (empty($item['text'])){
			$item['text'] = !empty($cms_list_item['text']) ? $cms_list_item['text'] : '';
		}
		$item['date'] = !empty($cms_list_item['date']) ? $cms_list_item['date'] : date('d/m/Y');
		$item['link_url'] = '';
		$item['link_text'] = '';
		$item['username'] = !empty($cms_list_item['author']) ? $cms_list_item['author'] : '';

		return $item;
		
	}
	
	function parse_tweet($tweet, $feed_setting){
		
		$this->load->model('cms/cms_image_model');
		
		$item = array();
		$item['hash'] = md5('twitter='.$tweet['twitter_id']);

		// panel fields
		$item['page_id'] = '0';
		$item['sort'] = 'first';
		$item['title'] = $tweet['title'];
		$item['panel_name'] = 'feed/feed';

		// feed fields
		$item['url'] = $tweet['url'];
		$item['show'] = $feed_setting['feed_show'];
		$item['moderated'] = 0;
		$item['source'] = 'twitter';
		$item['source_id'] = $tweet['twitter_id'];
		$item['source_label'] = $feed_setting['heading'];
		$item['update_time'] = time();

		// data fields
		$item['image'] = $this->cms_image_model->scrape_image($tweet['image'], 'twitter', 'feed');
		$item['icon'] = $feed_setting['icon'];
		$item['heading'] = $tweet['title'];
		$item['text'] = $tweet['html'];
		$item['date'] = date('d/m/Y', strtotime($tweet['date']));
		$item['link_url'] = $tweet['url'];
		$item['link_text'] = ''; // $article['link_text'];
		$item['username'] = $tweet['username'];
		
		$item['created_time'] = $tweet['created_time'];

		return $item;
		
	}
	
	function parse_instagram($instagram, $feed_setting){
		
		$this->load->model('cms/cms_image_model');
		
		$item = array();
		$item['hash'] = md5('instagram='.$instagram['id']);

		// panel fields
		$item['page_id'] = '0';
		$item['sort'] = 'first';
		$item['title'] = iconv('UTF-8', 'UTF-8//IGNORE', $instagram['title']);
		$item['panel_name'] = 'feed/feed';

		// feed fields
		$item['url'] = iconv('UTF-8', 'UTF-8//IGNORE', $instagram['link']);
		$item['show'] = $feed_setting['feed_show'];
		$item['moderated'] = 0;
		$item['source'] = 'instagram';
		$item['source_id'] = $instagram['id'];
		$item['source_label'] = $feed_setting['heading'];
		$item['update_time'] = time();

		// data fields
		$item['image'] = $this->cms_image_model->scrape_image($instagram['image_full'], 'instagram', 'feed');
		$item['icon'] = $feed_setting['icon'];
		$item['heading'] = iconv('UTF-8', 'UTF-8//IGNORE', $instagram['title']);
		$item['text'] = iconv('UTF-8', 'UTF-8//IGNORE', $instagram['html']);
		$item['date'] = date('d/m/Y', $instagram['created_time']);
		$item['created_time'] = $instagram['created_time'];
		$item['link_url'] = ''; // $instagram['url'];
		$item['link_text'] = ''; // $article['link_text'];
		$item['username'] = iconv('UTF-8', 'UTF-8//IGNORE', $instagram['username']);

		// instagram specific
		$item['profile_image'] = $this->cms_image_model->scrape_image($instagram['profile_picture'], 'instagram', 'feed');
		$item['likes_count'] = $instagram['likes_count'];
		$item['comments_count'] = $instagram['comments_count'];

		return $item;
		
	}

	function refresh_item($cms_page_panel_id){ // original panel
	
   		$this->load->model('cms/cms_page_panel_model');
		$original_a = $this->cms_page_panel_model->get_cms_page_panels_by(array('block_id' => $cms_page_panel_id, ));
   		$original = $original_a[0];
   		
   		$old_a = $this->cms_page_panel_model->get_cms_page_panels_by(array(
				'panel_name' => ['feed', 'feed/feed'], 
				'source' => $original['panel_name'],
				'source_id' => $cms_page_panel_id,
		));
   		$old = $old_a[0];
   		
   		$item = $this->parse_cms_list_item($original, array(
				'source' => $old['source'],
				'feed_show' => $old['show'],
				'heading' => $old['source_label'],
				'icon' => $old['icon'],
		));
		
		$item['sort'] = $old['sort'];
   		
		$this->cms_page_panel_model->update_block($old['block_id'], $item);
		
	}
	
	function get_twitter_by_username($data_username, $api_key, $api_secret, $twitter_interval = 300, $limit = 20){
		
		if (empty($data_username) || empty($api_key) || empty($api_secret)){
			return array();
		}
		
		if ($twitter_interval < 300){
			$twitter_interval = 300;
		}
		
		$filename = $GLOBALS['config']['base_path'] . 'cache/twitter_' . trim($data_username) . '_' . $limit . '.json';

		// update when needed
		if (!file_exists($filename) || time()-filemtime($filename) > $twitter_interval) {
			
			// to avoid multiple updates during update
			if (file_exists($filename)){
				touch($filename);
			}

			// twitter stuff starts

			// auth parameters
			$auth_url = 'https://api.twitter.com/oauth2/token';	
			
			// what we want?
			$data_url = 'https://api.twitter.com/1.1/statuses/user_timeline.json?tweet_mode=extended';
			
			// get api access token
			$api_credentials = base64_encode($api_key.':'.$api_secret);
					
			$auth_headers = 'Authorization: Basic '.$api_credentials."\r\n".
							'Content-Type: application/x-www-form-urlencoded;charset=UTF-8'."\r\n";
	
	  		$auth_context = stream_context_create(
	    		array(
	      			'http' => array(
	        			'header' => $auth_headers,
	        			'method' => 'POST',
	        			'content'=> http_build_query(array('grant_type' => 'client_credentials', )),
	      			)
	    		)
	  		);
	  		
	  		@$auth_response = json_decode(file_get_contents($auth_url, 0, $auth_context), true);
	  		if (!empty($auth_response)){
				$auth_token = $auth_response['access_token'];
				// get tweets
		  		$data_context = stream_context_create( array( 'http' => array( 'header' => 'Authorization: Bearer '.$auth_token."\r\n", ) ) );
				$data = file_get_contents($data_url.'&count='.$limit.'&screen_name='.urlencode($data_username), 0, $data_context);
				file_put_contents($filename, $data);
	  		} else {
				$data = file_get_contents($filename);
	  		}

		} else {
			
			$data = file_get_contents($filename);
			
		}
		
		mb_internal_encoding('UTF-8');
		$data = json_decode($data, true);

		// put together important data
		$return = array();
		if (!empty($data)){

			foreach ($data as $tweet){
				$return[] = array(
						
						'text' => ( empty($tweet['retweeted_status']['full_text']) ? $tweet['full_text'] : $tweet['retweeted_status']['full_text'] ),
						
						'username' => ( empty($tweet['retweeted_status']['user']['screen_name']) ? $tweet['user']['screen_name'] : 
								$tweet['retweeted_status']['user']['screen_name'] ) ,
						
						'date' => date('M d, Y', strtotime(empty($tweet['retweeted_status']['created_at']) ? $tweet['created_at'] : 
								$tweet['retweeted_status']['created_at'])),
						
						'url' => !empty($tweet['entities']['urls'][0]['expanded_url']) ? $tweet['entities']['urls'][0]['expanded_url'] :
								(!empty($tweet['extended_entities']['media'][0]['expanded_url']) ? $tweet['extended_entities']['media'][0]['expanded_url'] : false),
					
						'image' => !empty($tweet['entities']['media'][0]) && $tweet['entities']['media'][0]['type'] == 'photo' ? 
								$tweet['entities']['media'][0]['media_url'] : '',
						
						'twitter_id' => $tweet['id_str'],
						
						'urls' => $tweet['entities']['urls'],
						
						'created_time' => strtotime($tweet['created_at']),
						
				);
			}
		}

		// cleanup
		foreach($return as $key => $tweet){
			
			$urls = [];
			$original_urls = [];
			foreach($tweet['urls'] as $url){
				if (!stristr($url['display_url'], 'twitter.com')){
					
					if (mb_strlen($url['display_url']) > 22){
						$url['display_url'] = mb_substr($url['display_url'], 0, 20) . '..';
					}
					
					$urls[] = $url['url'];
					$original_urls[] = $url['display_url'];
				}
			}
			
			$text = str_replace($urls, $original_urls, $tweet['text']);

			$text = trim(preg_replace('~http[A-Za-z0-9/.:]+~', '', $text));
			$return[$key]['title'] = trim(str_replace(':', ' ', $text), ' .,');
			if (strlen($return[$key]['title']) > 40 ){
				$return[$key]['title'] = mb_substr($return[$key]['title'], 0, 36).' ...';
			}
			$text = trim(preg_replace('~([@#][A-Za-z0-9_]+)~', ' <b>$1</b> ', $text));
			
			$return[$key]['html'] = trim(str_replace(':', ' ', $text), ' .,');
			
			unset($return[$key]['urls']);

		}

		return $return;

	}
	
	function get_instagram_by_username($username, $interval = 5, $limit = 20){
		
		$username = trim($username);
		
		if (empty($username)){
			return array();
		}

		$this->load->model('cms/cms_page_panel_model');
			
		// instagram stuff
		if ($interval < 5){
			$interval = 5;
		}

		// get user instagram token
		$users = $this->cms_page_panel_model->get_cms_page_panels_by(['panel_name' => ['feed_instagram_user','feed/feed_instagram_user'], 'username' => $username, ]);
		$instagram_token = !empty($users[0]['access_token']) ? $users[0]['access_token'] : false;

		if (empty($instagram_token)){
			return array();
		}

		$filename = $GLOBALS['config']['base_path'] . 'cache/instagram_' . $username . '.json';

		// update when needed
		if (!file_exists($filename) || time()-filemtime($filename) > $interval * 60) {
			
			// to avoid multiple updates during update
			if (file_exists($filename)){
				touch($filename);
			}

			$url = 'https://api.instagram.com/v1/users/self/media/recent/?access_token='.$instagram_token;

			$data = file_get_contents($url);
			
			file_put_contents($filename, json_encode($data, JSON_PRETTY_PRINT));
			
		} else {
			
			$data = file_get_contents($filename);
					
		}

		$data = json_decode($data, true);

		$count = 0;
		$return = array();
		if (!empty($data['data'])){
			foreach($data['data'] as $image){
				if ($count < $limit){
					
					$return_item = array(
						'image_full' => $image['images']['standard_resolution']['url'],
						'link' => $image['link'],
						'likes_count' => $image['likes']['count'],
						'comments_count' => $image['comments']['count'],
						'id' => $image['id'],
						'created_time' => $image['created_time'],
						'username' => $image['user']['username'],
						'profile_picture' => $image['user']['profile_picture'],
					);
					
					if (!empty($image['caption']) && is_array($image['caption'])){
						$return_item['text'] = !empty($image['caption']['text']) ? $image['caption']['text'] : '';
					} else {
						$return_item['text'] = '';
					}
					
					$return[] = $return_item;
					
				}
				$count = $count + 1;
			}
		}
		
		// cleanup
		foreach($return as $key => $tweet){
			$text = trim(preg_replace('~http[A-Za-z0-9/.:]+~', '', $tweet['text']));
			$return[$key]['title'] = trim(str_replace(':', ' ', $text), ' .,');
			if (strlen($return[$key]['title']) > 40 ){
				$return[$key]['title'] = substr($return[$key]['title'], 0, 36).' ...';
			}
			$text = str_replace(array('@','#'), array(' @', ' #'), $text);
			$text = trim(preg_replace('~([@#][A-Za-z0-9_]+)~', '<b>$1</b>', $text));
			$return[$key]['html'] = trim(str_replace(':', ' ', $text), ' .,');
		}

		return $return;
		
	}
	
	function clean_feeds(){

   		$this->load->model('cms/cms_page_panel_model');
   		
   		$feed = $this->cms_page_panel_model->get_cms_page_panels_by(array('panel_name' => ['feed', 'feed/feed'], 'page_id' => ['999999','0'], '_limit' => 50, ));
   		
   		foreach($feed as $item){
   			if (empty($item['source'])){
   				print('x');
   				$this->cms_page_panel_model->delete_cms_page_panel($item['block_id']);
   			}
   		}
   		
   		print('<pre>');
   		print_r($feed);
 
 		die();
 
	}
	
}
