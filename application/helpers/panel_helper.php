<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

if ( !function_exists('get_position')) {
	
	/**
	 * checks if in template variable "data" is key starting with position name and outputs all such
	 */
    function get_position($name, $data) {
    	
    	$return = '';
    	
    	foreach($data as $key => $pdata){
    		if (!strncmp($key, $name, strlen($name))){
    			$return .= $pdata;
    		}
    	}
    	
		return "\n\n".'<!-- layout position '.$name.' start -->'."\n\n".$return."\n\n".'<!-- layout position '.$name.' end -->'."\n\n";
    	
    }
    
    function html_error($error){
    	
    	return '<div style="clear: both; border: 1px solid red; background-color: white; color: red; padding: 10px; box-sizing: border-box; font-size: 1rem; line-height: 1rem; ">'.
    			str_replace('#br#', '<br>', htmlentities(str_replace('<br>', '#br#', $error))).'</div>';
    	
    }
    
    function _html_error($error){
    	 
    	print(html_error($error));
    	 
    }
    
    /**
     * prints out cms_page_panel by id
     * 
     * @param int $block_id
     * @param array $_params optional extra params
     */
    function _panel_id($block_id, $_params = array()){
    	
    	if (empty($block_id)){
    		
    		_html_error('Embed panel by id called with empty id.');
    		return;
    	
    	}

    	$ci =& get_instance();
    	$ci->load->model('cms_page_panel_model');
    	
    	$params = $ci->cms_page_panel_model->get_cms_page_panel($block_id);
    	
    	if (empty($params['show'])){
    		if (empty($params['_return'])){
    			print('<!-- embed '.$params['panel_name'].' #'.$block_id.' is hidden -->');
    			return;
    		} else {
    			return('<!-- embed '.$params['panel_name'].' #'.$block_id.' is hidden -->');
    		}
    	}
    	
    	_panel($params['panel_name'], array_merge( !empty($GLOBALS['_page_params']) ? $GLOBALS['_page_params'] : array() , $params, $_params));
    	
    }
    
    function _panel($name, $params = array()){

    	if (!isset($params['_return'])){
    		$params['_return'] = false;
    	}
    	
    	$params['embed'] = 1;
    	$ci =& get_instance();
    	
    	$data = $ci->ajax_panel($name, $params);

		$GLOBALS['_panel_js'] = array_merge($GLOBALS['_panel_js'], $data['_panel_js']);
		$GLOBALS['_panel_css'] = array_merge($GLOBALS['_panel_css'], $data['_panel_css']);
		$GLOBALS['_panel_scss'] = array_merge($GLOBALS['_panel_scss'], $data['_panel_scss']);

    	if(!empty($data['_panel_image'])){
	    	$GLOBALS['_panel_images'][] = $data['_panel_image'];
    	}
    	if(!empty($data['_panel_title'])){
	    	$GLOBALS['_panel_titles'][] = $data['_panel_title'];
    	}
    	if(!empty($data['_panel_description'])){
	    	$GLOBALS['_panel_descriptions'][] = $data['_panel_description'];
    	}
    	
    	if (!$params['_return']){
			print('<!-- embed start -->'.$data['html'].'<!-- embed end -->');
    	} else {
    		return $data['html'];
    	}
    }
    
    function _t($cms_text_id, $omit_html = false){
    	
    	$ci =& get_instance();
    	$ci->load->model('cms_text_model');
    	$text = $ci->cms_text_model->get_cms_text($cms_text_id);
    	
    	if (empty($text['text'])){
    		$text['text'] = '['.$cms_text_id.']';
    	}
    	
    	if(!empty($_SESSION['cms_user']['cms_user_id']) && $omit_html === false){
    		print('<span class="admin_edit_text" data-cms_text_id="'.$cms_text_id.'">'.$text['text'].'</span>');
    	} else {
	    	print($text['text']);
    	}
    	
    }
    
    function t($cms_text_id, $omit_html = false){
    	
    	$ci =& get_instance();
    	$ci->load->model('cms_text_model');
    	$text = $ci->cms_text_model->get_cms_text($cms_text_id);
    	
    	if (empty($text['text'])){
    		$text['text'] = '['.$cms_text_id.']';
    	}
    	
    	if(!empty($_SESSION['cms_user']['cms_user_id']) && $omit_html === false){
    		$return = '<span class="admin_edit_text" data-cms_text_id="'.$cms_text_id.'">'.$text['text'].'</span>';
    	} else {
	    	$return = $text['text'];
    	}
    	
    	return $return;
    	
    }

    function str_limit($string, $length, $extra = ''){
    	if (strlen($string) > $length){
    		$string = substr($string, 0, $length - strlen($extra));
    		$string = substr($string, 0, strrpos($string, ' '));
    		$string = trim($string, ' -:;,').$extra;

    		$string .= $extra;
    	}
    	return $string;
    }
    
    /**
     * prints out url with full site path where needed
     */
    function _l($url, $print = true){
    	
    	if (is_array($url)){
    		if(!empty($url['url'])){
	    		$url = $url['url'];
    		} else {
    			$url = '';
    		}
    	}

    	if (substr($url, 0, 2) != '//'){
	    	$url = ltrim($url, '/');
    	}
    	
    	if (stristr($url, '#')){
    		list($url, $hash) = explode('#', $url);
    	}
    	
    	if (substr($url, 0, 7) == 'mailto:'){
    		$url = $url;
    	} else if (substr($url, 0, 4) == 'tel:'){
    		$url = $url;
    	} else if (substr($url, 0, 4) != 'http'){
    		
    		if (((int)$url == $url && $url != '') || (!stristr($url, '?') && stristr($url, '='))){
    			// get slug
    			$ci =& get_instance();
    			$ci->load->model('cms_slug_model');
    			$slug = $ci->cms_slug_model->get_cms_slug_by_target($url);
    			if ($slug){
    				$url = $slug.'/';
    			}
    		}

    		$url = $GLOBALS['config']['base_url'].$url;
    	}
    	
    	if (!empty($hash)){
    		$url = $url.'#'.$hash;
    	}
    	
    	if ($print){
    		print($url);
    	} else {
    		return $url;
    	}

    }
 
     /**
     * prints out a href and target with full site path and opening in new window where needed
     */
    function _lh($url){
    	
        if (is_array($url)){
    		if(!empty($url['url'])){
	    		$url = $url['url'];
    		} else {
    			$url = '';
    		}
    	}
    	
    	if(empty($url)){
    		return;
    	}
    	
    	if ($url || $url === ''){
    	
	    	$href = _l($url, false);
	    	
	    	$target = '';
	    	if (substr($url, 0, 4) == 'http'){
		   		$target = 'target="_blank"';
	    	}
	    	print(' href="'.$href.'" '.$target.' ');
	    	
    	}
    	
    }
    
    /**
     * prints link to file from cms file input
     */
    function _lf($filename){
    	
    	if ($filename){
			print(' href="' . _l('files/get/'.str_replace('/', '__', $filename), false) . '" ');
    	}
    	
    }
    
    /**
     * double linebreaks to spacer
     */
    function _dbs($text, $classname){
    	print(str_replace('<br>', ' ', str_replace('<br><br>', '<div class="'.$classname.'"></div>', 
							str_replace(array("\n", "\r", ), array('<br>', '', ), $text))));
    }
    
    function _p($text){
    	print($text);
    }
    
    function _vh($height, $minwidth = 0){
    	
    	print(' data-cms_window_height="'.$height.'" data-cms_window_height_minwidth="'.$minwidth.'"');
    	$GLOBALS['_window_height'] = true;
    	
    }
   
}
