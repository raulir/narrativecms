<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * 
 * Visitor target groups for AB testing, special blocks etc
 * 
 */

// load target groups configuration
$sql = "select b.name, b.value from cms_page_panel a join cms_page_panel_param b on a.cms_page_panel_id = b.cms_page_panel_id "
		." where a.panel_name = 'cms/cms_targets' and b.name = ''";
$query = mysqli_query($db, $sql);

while($result = mysqli_fetch_assoc($query)){
	$_SESSION['config']['targets'] = json_decode($result['value'], true); 
}

if(!empty($_SESSION['config']['targets']['groups'])){
	
	$_SESSION['targets'] = [];
	
	foreach($_SESSION['config']['targets']['groups'] as $group){

		if ($group['strategy'] == 'random' && !isset($_SESSION['targets'][$group['heading']])){
				
			$random = rand(0, 99);
			
			$ug_labels = explode('|',$group['labels']);
			$ug_weights = explode('|',$group['settings']);
				
			$tweight = 0;
			foreach ($ug_weights as $key => $weight){
				if ($random >= $tweight){
					$_SESSION['targets'][$group['heading']] = $ug_labels[$key];
				}
				$tweight += $weight;
			}
			
		} else if ($group['strategy'] == 'mobile' && !isset($_SESSION['targets'][$group['heading']])){
			
			$ug_labels = explode('|',$group['labels']);
			
			if ($_SESSION['mobile']){

				$_SESSION['targets'][$group['heading']] = $ug_labels[1];
			
			} else {
			
				$_SESSION['targets'][$group['heading']] = $ug_labels[0];
			
			}

		} else if ($group['strategy'] == 'admin'){
			
			$ug_labels = explode('|',$group['labels']);

			if(!empty($_SESSION['cms_user']['cms_user_id'])){
					
				$_SESSION['targets'][$group['heading']] = $ug_labels[1];
			
			} else {
			
				$_SESSION['targets'][$group['heading']] = $ug_labels[0];
			
			}

		} else if ($group['strategy'] == 'loggedin'){
			
			$ug_labels = explode('|',$group['labels']);

			if(!empty($_SESSION['user_id'])){
					
				$_SESSION['targets'][$group['heading']] = $ug_labels[1] ?? 'logged out';
			
			} else {
			
				$_SESSION['targets'][$group['heading']] = $ug_labels[0] ?? 'logged in';
			
			}

		} else if ($group['strategy'] == 'language' && $group['heading'] == 'language'){

			$GLOBALS['language'] = [];
			
			$ug_labels = explode('|',$group['labels']);
			$ug_matches = explode('|',$group['settings']);

			// 1. if cookie language set and in allowed languages
			if (!empty($_COOKIE['language']) && in_array($_COOKIE['language'], $ug_matches)){
				
				$key = array_search($_COOKIE['language'], $ug_matches);
				$GLOBALS['language'] = [
						'label' => $ug_labels[$key],
						'language_id' => $ug_matches[$key],
						'default' => $ug_matches[0],
				];

			}
			
			// 2. if mandatory language is set
			if (empty($GLOBALS['language']) && !empty($GLOBALS['config']['language']) && in_array($GLOBALS['config']['language'], $ug_matches)){
				
				$key = array_search($GLOBALS['language'], $ug_matches);
				$GLOBALS['language'] = [
						'label' => $ug_labels[$key],
						'language_id' => $ug_matches[$key],
						'default' => $ug_matches[0],
				];
				
				include_once($GLOBALS['config']['base_path'].'system/helpers/cookie_helper.php');
				cms_cookie_create('language', $ug_matches[$key], 90);

			}

			// 3. if not mandatory language, select suitable from settings
			if (empty($GLOBALS['language'])){
				
				// polyfill
				if (!function_exists('array_key_first')) {
					function array_key_first(array $arr) {
						foreach($arr as $key => $unused) {
							return $key;
						}
						return NULL;
					}
				}
				
				// en-GB,en;q=0.9,et;q=0.8,es;q=0.7,la;q=0.6
				
				if(!empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])){
			
					$languages_accepted = explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']);
					$language_a = [];
					
					foreach($languages_accepted as $language_accepted){
						
						$lang_e = explode(';', $language_accepted);
						if (empty($lang_e['1'])){
							$lang_e['1'] = 'q=1.0';
						}
						
						$lang_id = substr($lang_e['0'], 0, 2);
						$lang_val = str_replace('q=', '', $lang_e['1']);
	
						if (empty($language_a[$lang_id]) || $language_a[$lang_id] < $lang_val){
							$language_a[$lang_id] = $lang_val;
						}
						
					}
					
					arsort($language_a);
					
					$language_id = array_key_first($language_a);
	
					$key = array_search($language_id, $ug_matches);
					
					if ($key !== false){

						$GLOBALS['language'] = [
								'label' => $ug_labels[$key],
								'language_id' => $ug_matches[$key],
								'default' => $ug_matches[0],
						];
						
						include_once('../helpers/cookie_helper.php');
						cms_cookie_create('language', $ug_matches[$key], 90);

					}
					
				}
			
			}

			if (empty($GLOBALS['language'])) {
				
				$GLOBALS[$group['heading']] = [
						'label' => $ug_labels[0],
						'language_id' => $ug_matches[0],
						'default' => $ug_matches[0],
				];
				
				include_once('../helpers/cookie_helper.php');
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
