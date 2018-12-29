<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * 
 * Visitor target groups for AB testing, special blocks etc
 * 
 */

// load target groups configuration
$sql = "select b.name, b.value from block a join cms_page_panel_param b on a.block_id = b.cms_page_panel_id where a.panel_name = 'cms/cms_targets' and b.name = ''";
$query = mysqli_query($db, $sql);

while($result = mysqli_fetch_assoc($query)){
	$_SESSION['config']['targets'] = json_decode($result['value'], true); 
}

if(!empty($_SESSION['config']['targets']['groups'])){
	
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

			$useragent = $_SERVER['HTTP_USER_AGENT'];
			if (preg_match('/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od|ad)|iris'.
					'|kindle|lge |maemo|midp|mmp|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo'.
					'|up\.(browser|link)|vodafone|wap|windows (ce|phone)|xda|xiino/i',$useragent)||preg_match('/1207|6310|6590|3gso|4thp|50[1-6]i|770s'.
					'|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck'.
					'|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte'.
					'|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|'.
					'gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-('.
					'20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-'.
					'|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa'.
					'|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-'.
					'|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt'.
					'|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-'.
					'|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01'.
					'|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750'.
					'|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|'.
					'yas\-|your|zeto|zte\-/i',substr($useragent,0,4))){
	
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

				foreach($languages as $lang){

					if (empty($GLOBALS[$group['heading']]) && (false !== $key = array_search($lang, $ug_matches))){

						$GLOBALS[$group['heading']] = [
								'label' => $ug_labels[$key],
								'language_id' => $ug_matches[$key],
								'default' => $ug_matches[0],
						];
						
						setcookie('language', $GLOBALS[$group['heading']]['language_id'], time() + 10000000, '/');
						
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
			
			if (empty($_SESSION['cms_language'])){
				$_SESSION['cms_language'] = $GLOBALS[$group['heading']]['default'];
			}

		}

	}

}
