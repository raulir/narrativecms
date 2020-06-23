<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_search_model extends Model {
	
	/*
	 * 
	 * params['all'] means to ignore scores and show list pages
	 * 
	 */
	function get_search($term, $params = []){
		
		$this->load->model('cms/cms_page_panel_model');
		$this->load->model('cms/cms_slug_model');
		$this->load->model('cms/cms_module_model');
		
		if (!empty($params['all'])){
			$sql = "select * from cms_page_panel_param where value like ? ";
		} else {
			$sql = "select a.*, b.show from cms_page_panel_param a join cms_page_panel b on a.cms_page_panel_id = b.cms_page_panel_id where b.show = 1 and a.search > 0 and a.value like ? ";
		}
		
		$query = $this->db->query($sql, array('%'.$term.'%', ));
		$result = $query->result_array();

		// summarise block scores
		$block_scores = array();
		$cms_page_panels = array();
		
		foreach($result as $row){
			
			if (empty($block_scores[$row['cms_page_panel_id']])){
				$block_scores[$row['cms_page_panel_id']] = 0;
			}
			
			$block_scores[$row['cms_page_panel_id']] += !empty($params['all']) ? 1 : $row['search'];
			$cms_page_panels[$row['cms_page_panel_id']] = $row['cms_page_panel_id'];
			
		}
		
		// cms page panel data
		foreach($cms_page_panels as $cms_page_panel_id => $data){
			
			$sql = "select * from cms_page_panel where cms_page_panel_id = ? ";
			$query = $this->db->query($sql, array($cms_page_panel_id, ));
			$cms_page_panels[$cms_page_panel_id] = $query->row_array();
			
		}
		
		// add child panels score to main panel before adding them to page
		$block_scores_page = [];
		foreach($block_scores as $cms_page_panel_id => $block_score){
			
			if (empty($block_scores_page[$cms_page_panel_id])){
				$block_scores_page[$cms_page_panel_id] = 0;
			}
			
			// if has parent id -> means child block
			if (!empty($cms_page_panels[$cms_page_panel_id]['parent_id'])){
			
				if (empty($block_scores_page[$cms_page_panels[$cms_page_panel_id]['parent_id']])){
					$block_scores_page[$cms_page_panels[$cms_page_panel_id]['parent_id']] = 0;
				}
				
				$block_scores_page[$cms_page_panels[$cms_page_panel_id]['parent_id']] += $block_score;
			
			} else {
				
				$block_scores_page[$cms_page_panel_id] += $block_score;
				
			}
			
		}
		
		// get page scores
		$lists = $this->cms_page_panel_model->get_lists();
		$modules = $this->cms_module_model->get_modules();

		$page_scores = array();
		foreach($block_scores_page as $block_id => $block_score){
			
			// if child panel page, then not present in this array
			if (empty($cms_page_panels[$block_id])){
				$sql = "select * from cms_page_panel where cms_page_panel_id = ? ";
				$query = $this->db->query($sql, array($cms_page_panel_id, ));
				$cms_page_panels[$block_id] = $query->row_array();
			}
		
			$page_id = !in_array($cms_page_panels[$block_id]['cms_page_id'], [0, 999999]) ? $cms_page_panels[$block_id]['cms_page_id'] : $cms_page_panels[$block_id]['panel_name'].'='.$cms_page_panels[$block_id]['cms_page_panel_id'];
			
			// settings block is not page and should be excluded?
			if (stristr($page_id, '=') && !in_array($cms_page_panels[$block_id]['panel_name'], $lists)){
				continue;
			}
			
			// if do not show list main pages
			if (empty($params['all']) && $cms_page_panels[$block_id]['cms_page_id'] != 0 && in_array($cms_page_panels[$block_id]['panel_name'], $lists)){
				continue;
			}
			
			// don't show pages without slug
			if (empty($params['all']) && empty($this->cms_slug_model->get_cms_slug_by_target($page_id))){
				continue;
			}
			
			if (empty($page_scores[$page_id])){
				$page_scores[$page_id] = $block_score;
			} else {
				$page_scores[$page_id] += $block_score;
			}
			
		}
		
		asort($block_scores);
		
		asort($page_scores);
		$page_scores = array_reverse($page_scores, true);

		return [
				'cms_page_panels' => $block_scores,
				'cms_pages' => $page_scores,
				'panel_data' => $cms_page_panels,
		];
		 
		
	}
	
	function get_result($term){
		
		// TODO: support for parent/child

		$limit = 30;
		
		$sql = "select * from cms_page_panel_param where search > 0 and value like ? ";
    	$query = $this->db->query($sql, array('%'.$term.'%', ));
    	$result = $query->result_array();
    	
    	// summarise block scores
    	$block_scores = array();
    	$cms_page_panel_ids = array();
    	foreach($result as $row){
    		if (empty($block_scores[$row['cms_page_panel_id']])){
	    		$block_scores[$row['cms_page_panel_id']] = $row['search'];
    		} else {
    			$block_scores[$row['cms_page_panel_id']] += $row['search'];
    		}
    		$cms_page_panel_ids[] = $row['cms_page_panel_id'];
    	}
    	
    	// add time related scores, max 90 days
    	if (count($cms_page_panel_ids)){
	    	$sql = "select a.cms_page_panel_id, a.value as _search_time_timestamp_day, b.value as _search_time_extra from cms_page_panel_param a ".
	      			" join cms_page_panel_param b on a.cms_page_panel_id = b.cms_page_panel_id and b.name = '_search_time_extra' ".
	      	 		" where a.name = '_search_time_timestamp_day' and ".
	    			" cast(a.value AS UNSIGNED) > '".(time()/86400 - 90)."' and a.cms_page_panel_id in (".implode(',', $cms_page_panel_ids).") ";
	
	    	$query = $this->db->query($sql);
	    	$result = $query->result_array();
	    	$now_day = time()/86400;
	    	
	    	foreach($result as $row){
	    		$row_day = $row['_search_time_timestamp_day'];
	    		$extra_points = 0;
	    		foreach(unserialize($row['_search_time_extra']) as $days => $points){
	    			if(($now_day - $row_day) < $days){
	    				$extra_points = max($extra_points, $points);
	    			}
	    		}
	    		$block_scores[$row['cms_page_panel_id']] += $extra_points;
	    	}
    	}

    	// get page scores
    	$page_scores = array();
    	foreach($block_scores as $block_id => $block_score){
    		$sql = "select * from cms_page_panel where cms_page_panel_id = ? ";
     		$query = $this->db->query($sql, array($block_id, ));
    		$row = $query->row_array();
    		
    		$page_id = (!($row['cms_page_id'] == 999999 || $row['cms_page_id'] == 0)) ? $row['cms_page_id'] : $row['panel_name'].'='.$row['cms_page_panel_id']; 
   		
   			if (empty($page_scores[$page_id])){
	    		$page_scores[$page_id] = array(
    					'page_id' => $row['cms_page_id'],
    					'block_id' => $row['cms_page_panel_id'],
     					'panel_name' => $row['panel_name'],
    					'score' => $block_score,
    			);
   			} else {
   				$page_scores[$page_id]['score'] += $block_score;
   			}
    	}
    	
    	// get score counts
    	$score_counts_pages = array();
    	$score_counts_content = array();
    	$score_counts_news = array();
    	
    	foreach ($page_scores as $page_id => $data){
    		if (!($row['page_id'] == 999999 || $row['page_id'] == 0)){
    			if (empty($score_counts_pages[$data['score']])) $score_counts_pages[$data['score']] = array();
    			$score_counts_pages[$data['score']][] = $page_id;
    		} else if ($data['panel_name'] == 'team' || $data['panel_name'] == 'driver' || $data['panel_name'] == 'race'){
    			if (empty($score_counts_content[$data['score']])) $score_counts_content[$data['score']] = array();
    			$score_counts_content[$data['score']][] = $page_id;
    		} else if ($data['panel_name'] == 'article') {
    			if (empty($score_counts_news[$data['score']])) $score_counts_news[$data['score']] = array();
    			$score_counts_news[$data['score']][] = $page_id;
    		}
    	}

		$final_result = array();

    	$result_count = 0;
    	foreach($score_counts_pages as $page_ids){
    		foreach($page_ids as $page_id){
    			if ($result_count < $limit){
    				$final_result[] = array(
							'page_id' => $page_scores[$page_id]['page_id'], 
							'score' => $page_scores[$page_id]['score'],
							'type' => 'page',
					);
    			}
    		}
    	}
    	foreach($score_counts_content as $page_ids){
    		foreach($page_ids as $page_id){
    			if ($result_count < $limit){
    				$final_result[] = array(
							'page_id' => $page_id, 
							'score' => $page_scores[$page_id]['score'], 
							'block_id' => $page_scores[$page_id]['block_id'],
							'type' => $page_scores[$page_id]['panel_name'],
					);
    			}
    		}
    	}
    	foreach($score_counts_news as $page_ids){
    		foreach($page_ids as $page_id){
    			if ($result_count < $limit){
    				$final_result[] = array(
							'page_id' => $page_id, 
							'score' => $page_scores[$page_id]['score'], 
							'block_id' => $page_scores[$page_id]['block_id'],
							'type' => $page_scores[$page_id]['panel_name'],
					);
    			}
    		}
    	}
    	
    	// sort results
    	function scoresort($a, $b){
    		if ($a['score'] > $b['score']){
    			return -1;
    		} elseif ($a['score'] < $b['score']){
    			return 1;
    		} else {
    			return 0;
    		}
    	}
    	
    	usort($final_result, 'scoresort');
    	
// print_ r($final_result);

   		return $final_result;

	}
	
	function get_result_prepared($term, $params){
		
		if (empty($term)){
			$term = '';
		}
		
		$return = $this->get_cached_result($term, $params);
		
		if ($return === false){
		
			$result = $this->get_result($term);
			
			// get page data
			$this->load->model('cms/cms_slug_model');
			$this->load->model('cms/cms_page_model');
			$this->load->model('cms/cms_page_panel_model');
			
			// load header params
			$blocks = $this->cms_page_panel_model->get_cms_page_panels_by(array('panel_name' => 'header', ));
			$header_params = array_merge($blocks[0], $params);
			
			foreach($result as $key => $page){
				
				// reclassify type
				$type = '';
				if (!empty($page['cms_page_panel_id'])){
					if ($page['type'] == 'article'){
						$block = $this->cms_page_panel_model->get_cms_page_panel($page['cms_page_panel_id']);
						if(empty($block['show'])){
							$type = '';
						} else if ($block['type'] == 'plus'){
							$type = 'sportscarplus';
						} else if ($block['type'] == 'memory'){
							$type = 'memory';
						} else if ($block['type'] == 'feature'){
							$type = 'feature';
						} else if ($block['type'] == 'content'){
							$type = 'pages';
						} else if (!empty($block['video'])){
							$type = 'video';
						} else {
							$type = 'news';
						}
					} else if ($page['type'] == 'driver' || $page['type'] == 'team') {
						$type = 'teams';
					} else if ($page['type'] == 'race') {
						$type = 'races';
					} else {
						$type = $page['type'];
					}
				} else {
					$type = 'pages';
				}
				
				if (!empty($type)){
					$preresult[$type][$key]['slug'] = $this->cms_slug_model->get_cms_slug_by_target($page['page_id']);
					if (empty($page['block_id'])){
						$pagex = $this->cms_page_model->get_page($page['page_id']);
						$preresult[$type][$key]['title'] = $pagex['title'];
//						$preresult[$type][$key]['block_id'] = $page['page_id'];
					} else {
						$block = $this->cms_page_panel_model->get_cms_page_panel($page['cms_page_panel_id']);
						$preresult[$type][$key]['title'] = $block['heading'];
						$preresult[$type][$key]['block_id'] = $page['cms_page_panel_id'];
					}
				}

			}

			// rearrange and limit to 6 in type and max 12
			$number = 0;
			$return = array();
			
			if (!empty($preresult['pages'])){
				$return['pages'] = array_slice($preresult['pages'], 0, $header_params['search_results_per_section']);
				$number = $number + count($return['pages']);
			}
			
			if (!empty($preresult['sportscarplus']) && $number < $header_params['search_max_results']){
				$return['sportscarplus'] = array_slice($preresult['sportscarplus'], 0, 
						min($header_params['search_results_per_section'], $header_params['search_max_results'] - $number));
				$number = $number + count($return['sportscarplus']);
			}

			if (!empty($preresult['teams']) && $number < $header_params['search_max_results']){
				$return['teams'] = array_slice($preresult['teams'], 0, 
						min($header_params['search_results_per_section'], $header_params['search_max_results'] - $number));
				$number = $number + count($return['teams']);
			}

			if (!empty($preresult['races']) && $number < $header_params['search_max_results']){
				$return['races'] = array_slice($preresult['races'], 0, 
						min($header_params['search_results_per_section'], $header_params['search_max_results'] - $number));
				$number = $number + count($return['races']);
			}
			
			if (!empty($preresult['feature']) && $number < $header_params['search_max_results']){
				$return['feature'] = array_slice($preresult['feature'], 0, 
						min($header_params['search_results_per_section'], $header_params['search_max_results'] - $number));
				$number = $number + count($return['feature']);
			}
			
			if (!empty($preresult['video']) && $number < $header_params['search_max_results']){
				$return['video'] = array_slice($preresult['video'], 0, 
						min($header_params['search_results_per_section'], $header_params['search_max_results'] - $number));
				$number = $number + count($return['video']);
			}
			
			if (!empty($preresult['news']) && $number < $header_params['search_max_results']){
				$return['news'] = array_slice($preresult['news'], 0, 
						min($header_params['search_results_per_section'], $header_params['search_max_results'] - $number));
				$number = $number + count($return['news']);
			}
			
			if (!empty($preresult['memory']) && $number < $header_params['search_max_results']){
				$return['memory'] = array_slice($preresult['memory'], 0, 
						min($header_params['search_results_per_section'], $header_params['search_max_results'] - $number));
				$number = $number + count($return['memory']);
			}

			$this->save_cached_result($term, $return, $header_params['search_cache']);
			
		}
		
		return $return;
		
	}
	
	function get_cached_result($term){
		
		$sql = "select * from cms_search_cache where term = ? and cached_time > ? limit 1 ";
    	$query = $this->db->query($sql, array($term, time(), ));
    	if ($query->num_rows()){
	    	$row = $query->row_array();
	    	return json_decode($row['result'], true);
    	} else {
    		return false;
    	}
		
	}
	
	function save_cached_result($term, $result, $time){
		
		$sql = "delete from cms_search_cache where term = ? ";
		$this->db->query($sql, array($term, ));
		
		$sql = "insert into cms_search_cache set term = ? , cached_time = ? , result = ? ";
    	$this->db->query($sql, array($term, time() + $time, json_encode($result), ));
		
	}
	
}