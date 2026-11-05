<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Ajax_api extends Controller {
	
    public function __construct() {
    	
        parent::__construct();
        $this->params = $this->_ajax_drop_internal_keys($this->input->post());
        // Set after the strip so a posted _from_ajax cannot survive.
        $this->params['_from_ajax'] = 1;
   	}

	/**
	 * Leading-underscore keys are internal (set by PHP after this) or belong to
	 * another request protocol. Panel ajax must not accept them from the client.
	 */
	function _ajax_drop_internal_keys($params){

		if (!is_array($params)){
			return [];
		}

		foreach (array_keys($params) as $key){
			if (is_string($key) && $key !== '' && $key[0] === '_'){
				unset($params[$key]);
			}
		}

		return $params;

	}

	function _ajax_output_keys(){

		return [
				'cms_html',
				'cms_js',
				'cms_css',
				'cms_panel_css',
				'cms_panel_css_force',
				'cms_panel_js',
				'cms_error',
		];

	}

	function _ajax_input_keys(){

		return [
				'cms_do',
				'cms_no_html',
				'cms_no_js',
				'cms_no_css',
				'cms_session_id',
				'cms_page_panel_id',
				'cms_page_id',
		];

	}

	/**
	 * "cms" in the list means the fixed set. Any other name, including one cms_* key, is literal.
	 */
	function _ajax_allow_map($list, $fixed){

		$allow = [];

		if (!is_array($list)){
			return $allow;
		}

		foreach ($list as $name){
			if (!is_string($name) || $name === ''){
				continue;
			}
			if ($name === 'cms'){
				foreach ($fixed as $key){
					$allow[$key] = 1;
				}
				continue;
			}
			$allow[$name] = 1;
		}

		return $allow;

	}

	function _ajax_panel_config($panel_name){

		if (!is_string($panel_name) || !str_contains($panel_name, '/')){
			return [];
		}

		$this->load->model('cms/cms_panel_model');
		$config = $this->cms_panel_model->get_cms_panel_config($panel_name);

		return is_array($config) ? $config : [];

	}

	function _ajax_normalise_names($params){

		$pairs = [
				'do' => 'cms_do',
				'no_html' => 'cms_no_html',
				'session_id' => 'cms_session_id',
		];

		foreach ($pairs as $old => $new){
			if (array_key_exists($old, $params) && !array_key_exists($new, $params)){
				$params[$new] = $params[$old];
			}
		}

		return $params;

	}

	function _ajax_filter_input($panel_name, $params){

		$config = $this->_ajax_panel_config($panel_name);
		$list = $config['ajax']['input'] ?? null;

		if (!is_array($list)){
			return $params;
		}

		$allow = $this->_ajax_allow_map($list, $this->_ajax_input_keys());
		$allow['_from_ajax'] = 1;

		// An older updater posts do, no_html, and session_id. Keep them on this panel
		// even when ajax.input lists only the cms set.
		if ($panel_name === 'cms/cms_update'){
			foreach (['do', 'no_html', 'session_id', 'area', 'module', 'protocol', 'filenames', 'filename'] as $key){
				$allow[$key] = 1;
			}
		}

		foreach (array_keys($params) as $key){
			if (!isset($allow[$key])){
				unset($params[$key]);
			}
		}

		return $params;

	}

	function _ajax_rename_output($data){

		if (!is_array($data)){
			return [];
		}

		$map = [
				'_html' => 'cms_html',
				'html' => 'cms_html',
				'js' => 'cms_js',
				'css' => 'cms_css',
				'_panel_css' => 'cms_panel_css',
				'_panel_css_force' => 'cms_panel_css_force',
				'_panel_js' => 'cms_panel_js',
				'error' => 'cms_error',
		];

		$renamed = [];

		foreach ($data as $key => $value){
			if (!is_string($key) || $key === 'scss' || $key === '_panel_scss'){
				continue;
			}
			$out_key = $map[$key] ?? $key;
			if ($out_key === 'cms_html' && $key === 'html' && array_key_exists('cms_html', $renamed)){
				continue;
			}
			$renamed[$out_key] = $value;
		}

		return $renamed;

	}

	function _ajax_filter_output($panel_name, $data){

		$data = $this->_ajax_rename_output($data);
		$config = $this->_ajax_panel_config($panel_name);
		$list = $config['ajax']['output'] ?? null;

		if (!is_array($list)){
			$allow = [];
			foreach ($this->_ajax_output_keys() as $key){
				$allow[$key] = 1;
			}
		} else {
			$allow = $this->_ajax_allow_map($list, $this->_ajax_output_keys());
		}

		$filtered = [];

		foreach ($data as $key => $value){
			if (isset($allow[$key])){
				$filtered[$key] = $value;
			}
		}

		return $this->_ajax_update_legacy_output($panel_name, $filtered);

	}

	/**
	 * Older updater scripts post do / no_html and read _html and _panel_css.
	 * Those installs update from this master, so the update panels keep both names.
	 */
	function _ajax_update_legacy_output($panel_name, $data){

		$legacy_panels = [
				'cms/cms_update',
				'cms/cms_update_popup',
				'cms/cms_schema',
		];

		if (!in_array($panel_name, $legacy_panels, true) || !is_array($data)){
			return $data;
		}

		if (isset($data['cms_html'])){
			if (!array_key_exists('_html', $data)){
				$data['_html'] = $data['cms_html'];
			}
			if (!array_key_exists('html', $data)){
				$data['html'] = $data['cms_html'];
			}
		}

		$pairs = [
				'cms_js' => 'js',
				'cms_css' => 'css',
				'cms_panel_css' => '_panel_css',
				'cms_panel_css_force' => '_panel_css_force',
				'cms_panel_js' => '_panel_js',
				'cms_error' => 'error',
		];

		foreach ($pairs as $new => $old){
			if (array_key_exists($new, $data) && !array_key_exists($old, $data)){
				$data[$old] = $data[$new];
			}
		}

		return $data;

	}
   	
   	function get_panel(){

   		if (empty($this->params['panel_id'])){
    		print(json_encode(array('result' => array(), 'error' => array('message' => 'Missing panel_id', ))));
    		die();
   		}

   		if (extension_loaded('newrelic')) {
    		
			newrelic_set_appname(trim(str_replace('#page#', '', $GLOBALS['config']['site_title']), ' '.$GLOBALS['config']['site_title_delimiter']));
   			newrelic_name_transaction('/ajax_api/'.$this->params['panel_id']);

		}
		
		$this->params = $this->_ajax_normalise_names($this->params);
		if (!empty($this->params['cms_do']) && empty($this->params['do'])){
			$this->params['do'] = $this->params['cms_do'];
		}
		if (!empty($this->params['cms_no_html']) && empty($this->params['no_html'])){
			$this->params['no_html'] = $this->params['cms_no_html'];
		}

		if (!empty($this->params['cms_page_panel_id'])){

			$this->load->model('cms/cms_panel_model');
			$this->load->model('cms/cms_page_panel_model');

			$panel_config = $this->cms_panel_model->get_cms_panel_config($this->params['panel_id']);

			$is_action = !empty($this->params['do']) || !empty($this->params['no_html']);
			$is_cms_admin = !empty($_SESSION['cms_user']['cms_user_id']);
			$allow_ajax_panels = !empty($GLOBALS['config']['allow_ajax_panels']) && $GLOBALS['config']['allow_ajax_panels'] !== '0';
			// Frontend translate opens settings rows that are not placed on a page (show = 0).
			$is_page_translation = ($this->params['panel_id'] === 'user/page_translation');
			$can_translate = $is_cms_admin;
			if (!$can_translate && $is_page_translation){
				$this->load->model('cms/cms_access_model');
				$can_translate = $this->cms_access_model->user_has_access('cms_translate');
			}

			if ($is_action || $is_cms_admin || !empty($panel_config['list']['ajax_target']) || $allow_ajax_panels || $can_translate){
				$instance = $this->cms_page_panel_model->get_cms_page_panel($this->params['cms_page_panel_id']);
				if (!is_array($instance)){
					print(json_encode(array('result' => array(), 'error' => array('message' => 'Panel not found', ))));
					die();
				}
				// Show comes from the saved block. ajax_panel merges that block last.
				if (!$is_action && !$is_cms_admin && empty($instance['show']) && !$can_translate){
					print(json_encode(array('result' => array(), 'error' => array('message' => 'Can\'t show', ))));
					die();
				}
			} else {
				print(json_encode(array('result' => array(), 'error' => array('message' => 'List ajax blocked', ))));
				die();
			}

		}
   		
		$panel_name = (string)$this->params['panel_id'];
		$this->params = $this->_ajax_filter_input($panel_name, $this->params);
		if (!empty($this->params['cms_do'])){
			$this->params['do'] = $this->params['cms_do'];
		}
		if (!empty($this->params['cms_no_html'])){
			$this->params['no_html'] = $this->params['cms_no_html'];
		}
		unset($this->params['panel_id']);

		$data = $this->ajax_panel($panel_name, $this->params);

		print(json_encode(['result' => $this->_ajax_filter_output($panel_name, $data)]));
   	}
   	
   	function get_panel_anchor(){
   		
   		$this->load->model('cms/cms_page_panel_model');
   		$cms_page_panels = $this->cms_page_panel_model->get_cms_page_panels_by(['submenu_anchor' => $this->params['anchor']]);

		if (count($cms_page_panels)){
	   		
   			$cms_page_panel = reset($cms_page_panels);
   			
   			$posted = $this->_ajax_drop_internal_keys($this->input->post());
   			$posted['_from_ajax'] = 1;
   			$posted = $this->_ajax_normalise_names($posted);
   			$panel_name = (string)$cms_page_panel['panel_name'];
   			$posted = $this->_ajax_filter_input($panel_name, $posted);
   			if (!empty($posted['cms_do'])){
   				$posted['do'] = $posted['cms_do'];
   			}
   			if (!empty($posted['cms_no_html'])){
   				$posted['no_html'] = $posted['cms_no_html'];
   			}
   			$data = $this->_ajax_filter_output($panel_name, $this->ajax_panel($panel_name, array_merge($posted, $cms_page_panel)));
   			
   			print(json_encode(['result' => ['cms_html' => $data['cms_html'] ?? '']], JSON_PRETTY_PRINT));
   			die();
   			
   		}
   		
   		print(json_encode(array('result' => [], 'error' => ['message' => 'Can\'t show', ])));
   		die();
   		
   	}
    
}
