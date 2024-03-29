<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class feed_model extends CI_Model {
	
	function refresh_feeds(){

   		$this->load->model('cms/cms_page_panel_model');
   		$this->load->model('cms/cms_image_model');
		
   		$feed_settings_a = $this->cms_page_panel_model->get_cms_page_panels_by(['panel_name' => 'feed/feed_settings']);
   		
   		if (empty($feed_settings_a[0])){
   			return array();
   		}
   		
   		$feed_settings = $feed_settings_a[0];
   		
   		$stats = [];
   		
   		if (!empty($feed_settings['channels'])){
   		
	   		// check for panels
	   		foreach($feed_settings['channels'] as $feed_setting){
	   			
	   			/* TODO: change articles and projects etc to list */
	   			
	   			if (in_array($feed_setting['source'], array('project', 'article', ))){
	
					$stats['projects'] = 0;
					$projects = $this->cms_page_panel_model->get_cms_page_panels_by(
							array('_limit' => 20, 'panel_name' => $feed_setting['source'], 'page_id' => '0', 'show' => '1', ));
			   		
			   		foreach($projects as $project){
			   			
						$hash = md5($feed_setting['source'].'='.$project['block_id']);
						
						// check if this item is already there?
						$project_check_a = $this->cms_page_panel_model->get_cms_page_panels_by(
								array('_limit' => 1, 'panel_name' => ['feed','feed/feed'], 'page_id' => '0', 'hash' => $hash, ));
								
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
	   				
	   				$twitter_settings = $this->cms_page_panel_model->get_cms_page_panel_settings('feed/twitter_settings');

	   				$stats['tweets'] = 0;
	
					$tweets = $this->get_twitter_by_username(
							$feed_setting['filter'], 
							$twitter_settings['twitter_api_key'], 
							$twitter_settings['twitter_api_secret']
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
					
					if (stristr($feed_setting['filter'], '#')){
					
						$instagrams = $this->get_instagram_by_hashtag(str_replace('#', '', $feed_setting['filter']));
						
	   				} else {
	   					
	   					$instagrams = $this->get_instagram_by_username(str_replace('@', '', $feed_setting['filter']));
	   					
	   					if (!is_array($instagrams)){
	   						$GLOBALS['feed_error'] = $instagrams;
	   						continue;
	   					}

	   				}
	   				
	   				$instagrams = array_reverse($instagrams, true);
	
			   		foreach($instagrams as $instagram){
			   			
			   			$hash = md5('instagram='.$instagram['id']);
			   			
						// check if this item is already there?
						$item_check_a = $this->cms_page_panel_model->get_cms_page_panels_by(
								['_limit' => 1, 'panel_name' => 'feed/feed', 'cms_page_id' => '0', 'hash' => $hash]);
								
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
		
		$item['update_time'] = time();

		// data fields
		$item['image'] = $cms_list_item['image'];
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
		$item['update_time'] = time();

		// data fields
		$item['image'] = $this->cms_image_model->scrape_image($tweet['image'], 'twitter', 'feed');
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
		$item['update_time'] = time();

		// data fields
		$item['image'] = $this->cms_image_model->scrape_image($instagram['image_full'], 'instagram', 'feed');
		$item['heading'] = iconv('UTF-8', 'UTF-8//IGNORE', $instagram['title']);
		$item['text'] = iconv('UTF-8', 'UTF-8//IGNORE', $instagram['html']);
		$item['date'] = date('d/m/Y', $instagram['created_time']);
		$item['created_time'] = $instagram['created_time'];
		$item['link_url'] = ''; // $instagram['url'];
		$item['link_text'] = ''; // $article['link_text'];
		$item['username'] = iconv('UTF-8', 'UTF-8//IGNORE', $instagram['username']);

		// instagram specific
		$item['profile_image'] = $instagram['profile_picture'];
		$item['likes_count'] = $instagram['likes_count'];
		$item['comments_count'] = $instagram['comments_count'];
		
		if (!empty($instagram['images'])){
			
			$item['images'] = [];
			
			foreach($instagram['images'] as $i){
				$image_local = $this->cms_image_model->scrape_image($i, 'instagram', 'feed');
				$item['images'][] = [
					'image' => $image_local,
				];
			}
			
		}

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
	
	function get_instagram_by_hashtag($hashtag){
		
		$hashtag = trim($hashtag);
		
		if (empty($hashtag)){
			return [];
		}
		
		$filename = $GLOBALS['config']['base_path'] . 'cache/instagram_hashtag_' . $hashtag . '.json';
		
		// update when needed
		if (!file_exists($filename) || time()-filemtime($filename) > 900) {
				
			// to avoid multiple updates during update
			if (file_exists($filename)){
				touch($filename);
			}
		
			$url = 'https://www.instagram.com/explore/tags/'.$hashtag.'/?__a=1';
		
			$data = file_get_contents($url);
				
			file_put_contents($filename, json_encode(json_decode($data, true), JSON_PRETTY_PRINT));
				
		} else {
				
			$data = file_get_contents($filename);
				
		}
		
		$data = json_decode($data, true);
		
		$posts = $data['graphql']['hashtag']['edge_hashtag_to_media']['edges'];
		$return = array();
		if (!empty($posts)){
			foreach($posts as $image){
				
				// more information
				$details_filename = $GLOBALS['config']['base_path'] . 'cache/instagram_details_' . $image['node']['shortcode'] . '.json';
				if (!file_exists($details_filename) || time()-filemtime($details_filename) > 10000000) {
					// to avoid multiple updates during update
					if (file_exists($details_filename)){
						touch($details_filename);
					}
					$details_url = 'https://www.instagram.com/p/' . $image['node']['shortcode'] . '/?__a=1';
					$details_data = file_get_contents($details_url);
					file_put_contents($details_filename, json_encode(json_decode($details_data, true), JSON_PRETTY_PRINT));
				} else {
					$details_data = file_get_contents($details_filename);
				}
				$details_data = json_decode($details_data, true);

				$return_item = array(
						'image_full' => $image['node']['display_url'],
						'link' => '',
						'likes_count' => $image['node']['edge_liked_by']['count'],
						'comments_count' => $image['node']['edge_media_to_comment']['count'],
						'id' => $image['node']['id'],
						'created_time' => $image['node']['taken_at_timestamp'],
						'username' => $details_data['graphql']['shortcode_media']['owner']['username'],
						'profile_picture' => $details_data['graphql']['shortcode_media']['owner']['profile_pic_url'],
				);
					
				if (!empty($details_data['graphql']['shortcode_media']['edge_media_to_caption']['edges'][0]['node']['text'])){
					$return_item['text'] = $details_data['graphql']['shortcode_media']['edge_media_to_caption']['edges'][0]['node']['text'];
				} else {
					$return_item['text'] = '';
				}
					
				$return[] = $return_item;
					
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
		$users = $this->cms_page_panel_model->get_cms_page_panels_by(['panel_name' => 'feed/feed_instagram_user', 'username' => $username, ]);
		if (count($users)){
			$user = array_values($users)[0];
			$instagram_token = $user['access_token'];
		}

		if (empty($instagram_token)){
			return [];
		}

		$filename = $GLOBALS['config']['base_path'] . 'cache/instagram_' . $username . '.json';

		// update when needed
		if (!file_exists($filename) || time()-filemtime($filename) > $interval * 10) {
			
			// to avoid multiple updates during update
			if (file_exists($filename)){
				touch($filename);
			}

			$url = 'https://graph.instagram.com/'.$user['user_id'].'?fields=media&access_token='.$user['access_token'];
			
			$data = @file_get_contents($url, false, stream_context_create(['http' => ['ignore_errors' => true]]));
			
			$check_data = json_decode($data, true);

			if (!empty($check_data['error']['message'])){
				return ('Instagram error: <br><br>'.str_replace(['n:','.'], ['n:<br>', '<br>'], $check_data['error']['message']));
			}

			file_put_contents($filename, json_encode($data, JSON_PRETTY_PRINT));
			
		} else {

			$data = file_get_contents($filename);
					
		}

		$data = json_decode($data, true);
		
		$count = 0;
		$return = array();
		if (!empty($data['media']['data'])){
			foreach($data['media']['data'] as $item){

				if ($count < $limit){

					$filename = $GLOBALS['config']['base_path'] . 'cache/ig_id_' . $item['id'] . '.json';
					if (!file_exists($filename) || time()-filemtime($filename) > $interval * 60) {
					
						$url = 'https://graph.instagram.com/'.$item['id'].'?fields=caption,media_type,media_url,permalink,thumbnail_url,timestamp,children{media_url,thumbnail_url}&'.
								'access_token='.$user['access_token'];
						
						$image_data = file_get_contents($url);
						
						file_put_contents($filename, json_encode(json_decode($image_data, true), JSON_PRETTY_PRINT));
							
					} else {
								
						$image_data = file_get_contents($filename);
								
					}

					$image = json_decode($image_data, true);
					
					if($image['media_type'] == 'IMAGE'){

						$return_item = array(
							'image_full' => $image['media_url'],
							'link' => $image['permalink'],
							'likes_count' => 0, // $image['likes']['count'],
							'comments_count' => 0, // $image['comments']['count'],
							'id' => $image['id'],
							'created_time' => strtotime($image['timestamp']),
							'username' => $user['username'],
							'profile_picture' => $user['profile_picture'],
						);
						
						$return_item['text'] = !empty($image['caption']) ? $image['caption'] : '';
	
						$return[] = $return_item;
						
						$count = $count + 1;
					
					} else if ($image['media_type'] == 'CAROUSEL_ALBUM'){
						
						$return_item = array(
								'image_full' => $image['media_url'],
								'link' => $image['permalink'],
								'likes_count' => 0, // $image['likes']['count'],
								'comments_count' => 0, // $image['comments']['count'],
								'id' => $image['id'],
								'created_time' => strtotime($image['timestamp']),
								'username' => $user['username'],
								'profile_picture' => $user['profile_picture'],
						);
						
						$return_item['text'] = !empty($image['caption']) ? $image['caption'] : '';
						
						// add images
						if (!empty($image['children']['data'])){
							
							$return_item['images'] = [];
							
							foreach($image['children']['data'] as $child){
								
								$return_item['images'][] = (!empty($child['thumbnail_url']) ? $child['thumbnail_url'] : $child['media_url']);
								
							}
							
						}
						
						$return[] = $return_item;
						
						$count = $count + 1;

					}
					
				}
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
