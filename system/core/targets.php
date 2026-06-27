<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * 
 * Visitor target groups for AB testing, special blocks etc
 * 
 */

if (!function_exists('_targets_normalise_language_id')){

	function _targets_normalise_language_id($language_id){

		return strtolower(trim($language_id));

	}

	function _targets_language_key($language_id, $ug_matches){

		$normalised = _targets_normalise_language_id($language_id);

		foreach($ug_matches as $key => $match_id){
			if (_targets_normalise_language_id($match_id) === $normalised){
				return $key;
			}
		}

		return false;

	}

	function _targets_language_from_accept_header($accept_header, $ug_matches){

		$languages_accepted = explode(',', $accept_header);
		$language_a = [];

		foreach($languages_accepted as $language_accepted){

			$lang_e = explode(';', trim($language_accepted));
			$tag = trim($lang_e[0]);
			if ($tag === ''){
				continue;
			}

			$language_a[] = [
				'tag' => $tag,
				'q' => !empty($lang_e[1]) ? (float)str_replace('q=', '', $lang_e[1]) : 1.0,
			];

		}

		usort($language_a, function($a, $b){
			if ($a['q'] == $b['q']){
				return 0;
			}
			return ($a['q'] > $b['q']) ? -1 : 1;
		});

		foreach($language_a as $entry){

			$key = _targets_language_key($entry['tag'], $ug_matches);
			if ($key !== false){
				return $key;
			}

			$prefix = _targets_normalise_language_id(substr($entry['tag'], 0, 2));
			foreach($ug_matches as $match_key => $match_id){
				if (_targets_normalise_language_id($match_id) === $prefix){
					return $match_key;
				}
			}

		}

		return false;

	}

	function _targets_explode_pipe($value){

		return array_map('trim', explode('|', $value ?? ''));

	}

	function _targets_validate_two_labels($group){

		$labels = _targets_explode_pipe($group['labels'] ?? '');
		if (count($labels) < 2){
			return false;
		}
		if ($labels[0] === '' || $labels[1] === ''){
			return false;
		}

		return true;

	}

	function _targets_validate_random($group){

		$labels = _targets_explode_pipe($group['labels'] ?? '');
		$weights = _targets_explode_pipe($group['settings'] ?? '');
		if (count($labels) === 0 || count($labels) !== count($weights)){
			return false;
		}

		foreach($labels as $label){
			if ($label === ''){
				return false;
			}
		}

		foreach($weights as $weight){
			if (!is_numeric($weight) || (float)$weight <= 0){
				return false;
			}
		}

		return true;

	}

	function _targets_validate_language($group){

		if (($group['heading'] ?? '') !== 'language'){
			return false;
		}

		$labels = _targets_explode_pipe($group['labels'] ?? '');
		$settings = _targets_explode_pipe($group['settings'] ?? '');
		if (count($labels) === 0 || count($labels) !== count($settings)){
			return false;
		}

		foreach($settings as $language_id){
			if ($language_id === ''){
				return false;
			}
		}

		foreach($labels as $label){
			if ($label === ''){
				return false;
			}
		}

		return true;

	}

	function _targets_random_pick($group){

		$labels = _targets_explode_pipe($group['labels']);
		$weights = _targets_explode_pipe($group['settings']);

		$total = 0;
		foreach($weights as $weight){
			$total += (float)$weight;
		}

		$rand = mt_rand(1, 1000000) / 1000000.0 * $total;
		$cumulative = 0;

		foreach($weights as $key => $weight){
			$cumulative += (float)$weight;
			if ($rand <= $cumulative){
				return $labels[$key];
			}
		}

		return $labels[count($labels) - 1];

	}

	function _targets_random_sticky($group, $persisted_targets){

		$heading = $group['heading'];
		$labels = _targets_explode_pipe($group['labels']);

		if (!empty($persisted_targets[$heading]) && in_array($persisted_targets[$heading], $labels, true)){
			return $persisted_targets[$heading];
		}

		return _targets_random_pick($group);

	}

	function _targets_load_groups_from_params($db){

		$groups = [];
		$sql = "select b.name, b.value from cms_page_panel a join cms_page_panel_param b on a.cms_page_panel_id = b.cms_page_panel_id "
				." where a.panel_name = 'cms/cms_targets' and b.name like 'groups.%' order by b.name";
		$query = mysqli_query($db, $sql);

		if (!$query){
			return [];
		}

		while($result = mysqli_fetch_assoc($query)){
			if (!preg_match('/^groups\.(\d+)\.(\w+)$/', $result['name'], $matches)){
				continue;
			}
			$groups[(int)$matches[1]][$matches[2]] = $result['value'];
		}

		if (empty($groups)){
			return [];
		}

		ksort($groups);

		return array_values($groups);

	}

	function _targets_load_panel_config($db){

		$config = [];

		$sql = "select b.value from cms_page_panel a join cms_page_panel_param b on a.cms_page_panel_id = b.cms_page_panel_id "
				." where a.panel_name = 'cms/cms_targets' and b.name = '' limit 1";
		$query = mysqli_query($db, $sql);

		if ($query && ($result = mysqli_fetch_assoc($query))){
			$decoded = json_decode($result['value'], true);
			if (is_array($decoded) && !empty($decoded['groups'])){
				return $decoded;
			}
		}

		$groups = _targets_load_groups_from_params($db);
		if (!empty($groups)){
			$config['groups'] = $groups;
		}

		return $config;

	}

}

// load target groups configuration (always from DB — never keep stale session copy)
$_SESSION['config']['targets'] = _targets_load_panel_config($db);

if(!empty($_SESSION['config']['targets']['groups'])){
	
	$persisted_targets = !empty($_SESSION['targets']) ? $_SESSION['targets'] : [];
	$_SESSION['targets'] = [];
	
	foreach($_SESSION['config']['targets']['groups'] as $group){

		$heading = trim($group['heading'] ?? '');
		if ($heading === ''){
			continue;
		}

		$strategy = $group['strategy'] ?? '';

		if ($strategy == 'random'){

			if (!_targets_validate_random($group)){
				continue;
			}

			$_SESSION['targets'][$heading] = _targets_random_sticky($group, $persisted_targets);
			
		} else if ($strategy == 'mobile'){

			if (!_targets_validate_two_labels($group)){
				continue;
			}

			$ug_labels = _targets_explode_pipe($group['labels']);
			
			if (!empty($_SESSION['mobile'])){

				$_SESSION['targets'][$heading] = $ug_labels[1];
			
			} else {
			
				$_SESSION['targets'][$heading] = $ug_labels[0];
			
			}

		} else if ($strategy == 'admin'){

			if (!_targets_validate_two_labels($group)){
				continue;
			}

			$ug_labels = _targets_explode_pipe($group['labels']);

			if(!empty($_SESSION['cms_user']['cms_user_id'])){
					
				$_SESSION['targets'][$heading] = $ug_labels[1];
			
			} else {
			
				$_SESSION['targets'][$heading] = $ug_labels[0];
			
			}

		} else if ($strategy == 'loggedin'){

			if (!_targets_validate_two_labels($group)){
				continue;
			}

			$ug_labels = _targets_explode_pipe($group['labels']);

			if(!empty($_SESSION['user'])){
					
				$_SESSION['targets'][$heading] = $ug_labels[1];
			
			} else {
			
				$_SESSION['targets'][$heading] = $ug_labels[0];
			
			}

		} else if ($strategy == 'language'){

			if (!_targets_validate_language($group)){
				continue;
			}

			$GLOBALS['language'] = [];
			
			$ug_labels = _targets_explode_pipe($group['labels']);
			$ug_matches = _targets_explode_pipe($group['settings']);

			// 1. if cookie language set and in allowed languages
			if (!empty($_COOKIE['language'])){
				
				$key = _targets_language_key($_COOKIE['language'], $ug_matches);
				if ($key !== false){
					$GLOBALS['language'] = [
							'label' => $ug_labels[$key],
							'language_id' => $ug_matches[$key],
							'default' => $ug_matches[0],
					];
				}

			}
			
			// 2. if mandatory language is set
			if (empty($GLOBALS['language']) && !empty($GLOBALS['config']['language'])){
				
				$key = _targets_language_key($GLOBALS['config']['language'], $ug_matches);
				if ($key !== false){
					$GLOBALS['language'] = [
							'label' => $ug_labels[$key],
							'language_id' => $ug_matches[$key],
							'default' => $ug_matches[0],
					];
					
					include_once($GLOBALS['config']['base_path'].'system/helpers/cookie_helper.php');
					cms_cookie_create('language', $ug_matches[$key], 90);
				}

			}

			// 3. if not mandatory language, select suitable from Accept-Language header
			if (empty($GLOBALS['language']) && !empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])){
			
				$key = _targets_language_from_accept_header($_SERVER['HTTP_ACCEPT_LANGUAGE'], $ug_matches);
				
				if ($key !== false){

					$GLOBALS['language'] = [
							'label' => $ug_labels[$key],
							'language_id' => $ug_matches[$key],
							'default' => $ug_matches[0],
					];
					
					include_once($GLOBALS['config']['base_path'].'system/helpers/cookie_helper.php');
					cms_cookie_create('language', $ug_matches[$key], 90);

				}
			
			}

			if (empty($GLOBALS['language'])) {
				
				$GLOBALS['language'] = [
						'label' => $ug_labels[0],
						'language_id' => $ug_matches[0],
						'default' => $ug_matches[0],
				];
				
				include_once($GLOBALS['config']['base_path'].'system/helpers/cookie_helper.php');
				cms_cookie_create('language', $GLOBALS['language']['language_id'], 90);
				
			}

			$GLOBALS['language']['languages'] = [];
			foreach($ug_matches as $key => $language_id){
				
				$GLOBALS['language']['languages'][$language_id] = $ug_labels[$key];
				
			}
			
			$_SESSION['targets']['language'] = $GLOBALS['language']['language_id'];
			
			if (empty($_SESSION['cms_language'])){
				$_SESSION['cms_language'] = $GLOBALS['language']['default'];
			}

		}

	}
	
	$_SESSION['config']['targets']['hash'] = md5(serialize($_SESSION['targets']));

}
