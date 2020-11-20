<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * 
 * Visitor target groups for AB testing, special blocks etc
 * 
 */

// load target groups configuration
$sql = "select b.name, b.value from cms_page_panel a join cms_page_panel_param b on a.cms_page_panel_id = b.cms_page_panel_id where a.panel_name = 'cms/cms_targets' and b.name = ''";
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
					
				$_SESSION['targets'][$group['heading']] = $ug_labels[1];
			
			} else {
			
				$_SESSION['targets'][$group['heading']] = $ug_labels[0];
			
			}

		} else if ($group['strategy'] == 'language' && $group['heading'] == 'language'){
			
			$GLOBALS[$group['heading']] = [];
			
			$ug_labels = explode('|',$group['labels']);
			$ug_matches = explode('|',$group['settings']);

			if (!empty($_COOKIE['language']) && (false !== $key = array_search($_COOKIE['language'], $ug_matches))){
				
				$GLOBALS[$group['heading']] = [
						'label' => $ug_labels[$key],
						'language_id' => $ug_matches[$key],
						'default' => $ug_matches[0],
				];
				
			} else if (!empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])){
				
				$languages = explode(',', str_replace(';', ',', $_SERVER['HTTP_ACCEPT_LANGUAGE']));
				
				foreach($ug_matches as $key => $lang){
					
					if (in_array($lang, $languages)){
						
						$GLOBALS[$group['heading']] = [
								'label' => $ug_labels[$key],
								'language_id' => $ug_matches[$key],
								'default' => $ug_matches[0],
						];
						
						setcookie('language', $GLOBALS[$group['heading']]['language_id'], time() + 10000000, '/');
						
						break;
						
					}

				}

			} else {
				
				$GLOBALS[$group['heading']] = [
						'label' => $ug_labels[0],
						'language_id' => $ug_matches[0],
						'default' => $ug_matches[0],
				];
				
				setcookie('language', $GLOBALS[$group['heading']]['language_id'], time() + 10000000, '/');
				
			}

			$GLOBALS[$group['heading']]['languages'] = [];
			foreach($ug_matches as $key => $language_id){
				
				$GLOBALS[$group['heading']]['languages'][$language_id] = $ug_labels[$key];
				
			}
			
			$_SESSION['targets']['language'] = $GLOBALS[$group['heading']]['language_id'];
			
			if (empty($_SESSION['cms_language'])){
				$_SESSION['cms_language'] = $GLOBALS[$group['heading']]['default'];
			}

		}

	}
	
	$_SESSION['config']['targets']['hash'] = md5(serialize($_SESSION['targets']));

}
