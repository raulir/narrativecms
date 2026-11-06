<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Ajax_api extends Controller {
	
    public function __construct() {
    	
        parent::__construct();
        $this->params = $this->_request_drop_internal_keys($_POST);
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

	/**
	 * The posted cms_page_panel_id is untrusted. Its saved block is merged into the request
	 * only when it is a block of the requested panel and the caller may see it (shown, or a
	 * CMS user / translator). Anything else returns null: the id stays a plain request value
	 * the panel may use itself (e.g. the admin editor's target block), but its stored fields
	 * never reach this panel's params or output.
	 */
	function _ajax_vet_block($instance, $panel_name, $privileged){

		if (!is_array($instance) || ($instance['panel_name'] ?? null) !== $panel_name){
			return null;
		}

		if (!$privileged && empty($instance['show'])){
			return null;
		}

		return $instance;

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
		$config = $this->_request_panel_config($panel_name);
		$list = $config['ajax']['output'] ?? null;

		if (!is_array($list)){
			// No ajax.output: cms_* only. A panel lists every other key it returns in its
			// definition (ajax.output) — explicit, so nothing is echoed by accident.
			$allow = [];
			foreach ($this->_ajax_output_keys() as $key){
				$allow[$key] = 1;
			}
		} else {
			$allow = $this->_request_allow_map($list, $this->_ajax_output_keys());
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

		// Saved block to merge into this request (vetted below), or null
		$block = null;

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
				if (!$is_action && !$is_cms_admin && empty($instance['show']) && !$can_translate){
					print(json_encode(array('result' => array(), 'error' => array('message' => 'Can\'t show', ))));
					die();
				}
				$block = $this->_ajax_vet_block($instance, (string)$this->params['panel_id'], $is_cms_admin || $can_translate);
			} else {
				print(json_encode(array('result' => array(), 'error' => array('message' => 'List ajax blocked', ))));
				die();
			}

		}

		$panel_name = (string)$this->params['panel_id'];
		$this->params = $this->_request_filter_input($panel_name, $this->params);
		if (!empty($this->params['cms_do'])){
			$this->params['do'] = $this->params['cms_do'];
		}
		if (!empty($this->params['cms_no_html'])){
			$this->params['no_html'] = $this->params['cms_no_html'];
		}
		unset($this->params['panel_id']);
		// Internal key, set after the _* strip and the input filter: the client cannot supply it
		if ($block !== null){
			$this->params['_cms_block'] = $block;
		}

		$data = $this->ajax_panel($panel_name, $this->params);

		print(json_encode(['result' => $this->_ajax_filter_output($panel_name, $data)]));
   	}
   	
   	function get_panel_anchor(){
   		
   		$this->load->model('cms/cms_page_panel_model');
   		$cms_page_panels = $this->cms_page_panel_model->get_cms_page_panels_by(['submenu_anchor' => $this->params['anchor']]);

		if (count($cms_page_panels)){
	   		
   			$cms_page_panel = reset($cms_page_panels);
   			$panel_name = (string)$cms_page_panel['panel_name'];

   			// Same rule as get_panel: hidden blocks only for CMS users
   			$block = $this->_ajax_vet_block($cms_page_panel, $panel_name, !empty($_SESSION['cms_user']['cms_user_id']));
   			if ($block === null){
   				print(json_encode(array('result' => [], 'error' => ['message' => 'Can\'t show', ])));
   				die();
   			}

   			$posted = $this->_request_drop_internal_keys($_POST);
   			$posted = $this->_ajax_normalise_names($posted);
   			$posted = $this->_request_filter_input($panel_name, $posted);
   			if (!empty($posted['cms_do'])){
   				$posted['do'] = $posted['cms_do'];
   			}
   			if (!empty($posted['cms_no_html'])){
   				$posted['no_html'] = $posted['cms_no_html'];
   			}
   			// Block merged last in ajax_panel (after settings), as on get_panel
   			$posted['_cms_block'] = $block;
   			$data = $this->_ajax_filter_output($panel_name, $this->ajax_panel($panel_name, $posted));
   			
   			print(json_encode(['result' => ['cms_html' => $data['cms_html'] ?? '']], JSON_PRETTY_PRINT));
   			die();
   			
   		}
   		
   		print(json_encode(array('result' => [], 'error' => ['message' => 'Can\'t show', ])));
   		die();
   		
   	}
    
}
