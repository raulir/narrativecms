<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

#[AllowDynamicProperties]

/**
 * Main / panel controller base.
 * Prefer `extends Controller`. `CI_Controller` remains a class_alias for older panels.
 */
class Controller {

	static $instance;

	var $utf8;
	var $log;
	var $uri;
	var $router;
	var $output;
	var $input;
	var $load;
	var $db;
	var $panel_name;
	var $panel_controller;
	/** @var Controller|null temporary handle while running a panel controller (not a separate CI sandbox) */
	var $panel_ci;
	
	/**
	 * Constructor
	 *
	 * First instance in the request is the main controller (get_instance / Loader parent).
	 * Panel controllers loaded as libraries must not steal instance, rebind Loader parent,
	 * or copy core services with =& (that triggers “indirect modification of overloaded property”).
	 */
	public function __construct()
	{

		$is_main = !(self::$instance instanceof self);

		if ($is_main) {
			self::$instance =& $this;

			// Assign bootstrap classes onto the main controller only
			foreach (is_loaded() as $var => $class)
			{
				$this->$var =& load_class($class);
			}

			$this->load =& load_class('Loader', 'core');
			$this->load->parent =& $this;
			$this->load->initialize();
			$this->load->helper('panel_helper');
			$this->load->helper('image_helper');
			$this->load->helper('packer_helper');

			if (!isset($GLOBALS['_panel_titles'])){
				$GLOBALS['_panel_titles'] = array();
			}
			if (!isset($GLOBALS['_panel_descriptions'])){
				$GLOBALS['_panel_descriptions'] = array();
			}
			if (!isset($GLOBALS['_panel_images'])){
				$GLOBALS['_panel_images'] = [];
			}
			if (!isset($GLOBALS['_panel_videos'])){
				$GLOBALS['_panel_videos'] = [];
			}
			if (empty($GLOBALS['_panel_js'])){
				$GLOBALS['_panel_js'] = [];
			}
		} else {
			// Panel library: share main services by object handle (not =& — avoids overloaded property notices).
			// Models stay on main via Loader; undeclared model props resolve through __get.
			$main = self::$instance;
			if (is_object($main)) {
				$this->load = $main->load;
				$this->input = $main->input;
				$this->db = $main->db;
				$this->uri = $main->uri;
				$this->router = $main->router;
				$this->output = $main->output;
			}
		}
		
		// panels stuff
		$this->init_panel();
		
	}

	/**
	 * Panel libraries: resolve load, input, models, etc. from the main request controller.
	 * Do not assign by reference into these names on panel instances.
	 */
	public function __get($key) {

		$main = self::$instance;
		if (is_object($main) && $main !== $this && isset($main->$key)) {
			return $main->$key;
		}

		return null;

	}

	static function &get_instance(){

		return self::$instance;
		
	}
	
	function run_action($name, $params){
		 
		return $this->run_panel_method($name, 'panel_action', $params);
		 
	}
	
	/**
	 * run controller panel_action part for a panel
	 */
	function run_panel_method($panel_name_param, $panel_method, $params = []){
	
		if (!empty($params['_extends'])){
			$files = $this->get_panel_filenames($panel_name_param, $params, $params['_extends']);
		} else {
			$files = $this->get_panel_filenames($panel_name_param, $params);
		}

		// if extended, run extended controller first
		if (!empty($files['extends_controller'])){

			$ci =& get_instance();

			$extends_panel_name = $files['extends_module'].'_'.$files['extends_name'].'_panel';
			$res = $ci->load->library(
					$files['extends_controller'],
					['module' => $files['extends_module'], 'name' => $files['extends_name'], ],
					$extends_panel_name
					);
	
			if (method_exists($ci->{$extends_panel_name}, $panel_method)){
				$ci->{$extends_panel_name}->init_panel(array('name' => $files['extends_name'], 'controller' => $files['extends_controller'], ));
				$params = $ci->{$extends_panel_name}->{$panel_method}($params);
				if (is_array($params)){
					$params['_no_cache'] = 1;
				}
			}

		}
	
		// if there is a normal controller, do this
		if (!empty($files['controller']) && is_array($params)){

			$ci =& get_instance();
			
			// load panel stuff into this sandbox - it will be the same as sandbox is singleton for itself
			$panel_name = $files['module'].'_'.$files['name'].'_panel';

			$res = $ci->load->library(
					$files['controller'],
					['module' => $files['module'], 'name' => $files['name'], ],
					$panel_name
					);

			if (method_exists($ci->{$panel_name}, $panel_method)){
	
				// define this controller as panel
				$ci->{$panel_name}->init_panel(array('name' => $files['name'], 'controller' => $files['controller'], ));
	
				// get params through panel controller
				$params = $ci->{$panel_name}->{$panel_method}($params);
				if (is_array($params)){
					$params['_no_cache'] = 1;
				}
		   
			}

		} else if ($panel_method != 'panel_action' && method_exists($this, $panel_method)){
	
			$params = $this->{$panel_method}($params);
			if (is_array($params)){
				$params['_no_cache'] = 1;
			}
	
		}
	
		return $params;
	
	}

	/*
	 * controller as panel stuff
	 */
	function panel($name, $params = []){

		$this->load->model('cms/cms_access_model');
		if (!$this->cms_access_model->enforce_panel_access($name, $params)){
			return [
					'_html' => $this->cms_access_model->get_panel_access_skipped_html($name),
					'js' => [],
					'css' => [],
					'scss' => [],
			];
		}

		if (!empty($params['_extends'])){
			$files = $this->get_panel_filenames($name, $params, $params['_extends']);
		} else {
			$files = $this->get_panel_filenames($name, $params);
		}

		$panel_js = $files['js'];
		$panel_css = $files['css'];
		$panel_scss = $files['scss'];

		// if controller found, rework params
		if(!empty($files['controller']) || !empty($files['extends_controller'])){
	
			$controller_timer_start = round(microtime(true) * 1000);
	
			$params['module'] = $files['module'];
	
			// if extended, run extended controller first
			if (!empty($files['extends_controller'])){
				 
				// Panel controllers are libraries on the main controller (same get_instance())
				$this->panel_ci = get_instance();
				 
				$extends_panel_name = $files['extends_module'].'_'.$files['extends_name'].'_panel';
				$this->panel_ci->load->library(
						$files['extends_controller'],
						['module' => $files['extends_module'], 'name' => $files['extends_name'], ],
						$extends_panel_name
						);
				$this->panel_ci->{$extends_panel_name}->init_panel(array('name' => $files['extends_name'], 'controller' => $files['extends_controller'], ));
				$params = $this->panel_ci->{$extends_panel_name}->panel_params($params);
	
				$this->panel_ci = null;
				 
			}
	
			// if there is a normal controller, do this
			if (!empty($files['controller'])){
	
				$this->panel_ci = get_instance();
				 
				// load panel controller as library on main instance
				$panel_name = $files['module'].'_'.$files['name'].'_panel';
	
				$this->panel_ci->load->library(
						$files['controller'],
						['module' => $files['module'], 'name' => $files['name'], ],
						$panel_name
						);
		   
				// define this controller as panel
				$this->panel_ci->{$panel_name}->init_panel(array('name' => $files['name'], 'controller' => $files['controller'], ));
		   
				// get params through panel controller
				$params = $this->panel_ci->{$panel_name}->panel_params($params);
	
				$this->panel_ci = null;
	
			}
	
			$controller_timer_end = round(microtime(true) * 1000);
	
		}

		// render view when needed only
		if(!empty($files['template'])) {
	
			$template_timer_start = round(microtime(true) * 1000);
	
			// cant pass non array to view
			if (!is_array($params)){
				$params = array();
			}
	
			$return = $this->load->view($files['template'], $params, true);
	
			$template_timer_end = round(microtime(true) * 1000);
	
		} else if (empty($params['panel_id'])){
			$return = _html_error('Missing panel template: '.$name, ['silent' => true, ]);
		} else {
			$return = '';
		}
	
		// if submenu anchor
		if(!empty($params['submenu_anchor'])){
			$return = '<div class="cms_anchor" id="'.$params['submenu_anchor'].'" name="'.$params['submenu_anchor'].'"></div>'.$return;
		}

		// add debug data
		$return = "\n".'<!-- panel "' . $files['module'] . '/' . $files['name'] . '" '.
						(!empty($params['_extends']['panel']) ? 'extends "'.$params['_extends']['panel'].'" ' : '' ).'start -->'."\n".
				(!empty($params['_extends']['panel']) && empty($params['_extends']['no_wrapper']) ? 
						'<span class="cms_wrapper cms_wrapper_'.$files['module'].'_'.$files['name'].'">'."\n" : '').
				$return .
				(!empty($params['_extends']['panel']) && empty($params['_extends']['no_wrapper']) ? "\n</span>" : '').
				"\n".'<!-- panel "' . $files['module'] . '/' . $files['name'] . 
						'" ( '.(!empty($controller_timer_start) ? ' controller: '.($controller_timer_end - $controller_timer_start).'ms ' : '').
				(!empty($template_timer_start) ? ' template: '.($template_timer_end - $template_timer_start).'ms' : ''). ' ) end -->'."\n";

		// add js, css, scss to global page files
		$GLOBALS['_panel_js'] = array_merge($GLOBALS['_panel_js'], $panel_js);
		
		foreach($panel_css as $css_file){
			add_css($css_file);
		}
		foreach($panel_scss as $css_file){
			add_css($css_file);
		}

		// save panel result params for returning them when ajax panel is requested
		$this->view_params = $params;

		return [
				'_html' => $return,
				'js' => $panel_js,
				'css' => $panel_css,
				'scss' => $panel_scss,
		];

	}
	
	// overload for calculating panel view parameters
	function panel_params($params){
		return $params;
	}
	
	/**
	 * 
	 * puts together and outputs page html
	 * 
	 * @param layout module/layout
	 * @param page_id for caching
	 * @param panel_data
	 * 
	 */
	function output($layout_name, $page_id, $panel_data = array(), $page_cache_context = null){
		
		$page = $this->load->layout($layout_name, $panel_data);

		// get css part of page head
		$css_str = $this->get_page_css($page_id);

		// put together mandatory config js and panel/controller loaded js
		if (!empty($GLOBALS['config']['js'])){
			$jss = $GLOBALS['config']['js'];
		} else {
			$jss = array();
		}
		$jss = array_merge($jss, $GLOBALS['_panel_js']);
		$js_str = pack_js($jss);
	
		// images, descriptions and titles from panels
		$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? 'https://' : 'http://';
		
		$image_str = '';
		
		foreach($GLOBALS['_panel_images'] as $ik => $image){
			if (empty($image)){
				unset($GLOBALS['_panel_images'][$ik]);
			}
		}
		
		if(empty($GLOBALS['_panel_images']) && !empty($GLOBALS['config']['meta_image'])){
			$GLOBALS['_panel_images'] = [$GLOBALS['config']['meta_image']];
		}
		
		if (!empty($GLOBALS['_panel_images'])){
			$GLOBALS['_panel_images'] = array_slice($GLOBALS['_panel_images'], 0, 3); // maximum 3 images
			foreach($GLOBALS['_panel_images'] as $image){
				$image_data = _iw($image, 800);
				$image_str .= '<meta property="og:image" content="'.
						(empty($GLOBALS['config']['cdn_url']) ? $protocol.$_SERVER['HTTP_HOST'] : '').
						$GLOBALS['config']['upload_url'].$image_data['image'].'" />'."\n";
						$image_str .= '<meta property="og:image:width" content="'.$image_data['width'].'" />'."\n";
						$image_str .= '<meta property="og:image:height" content="'.$image_data['height'].'" />'."\n";
			}
		}
		
		$video_str = '';
		if (!empty($GLOBALS['_panel_videos'])){
			$video = array_pop($GLOBALS['_panel_videos']);
			$video_str .= '<meta property="og:video" content="'.(empty($GLOBALS['config']['cdn_url']) ? $protocol.$_SERVER['HTTP_HOST'] : '').
					$GLOBALS['config']['upload_url'].$video.'" />'."\n";
			$video_str .= '<meta property="og:video:secure_url" content="'.(empty($GLOBALS['config']['cdn_url']) ? $protocol.$_SERVER['HTTP_HOST'] : '').
					$GLOBALS['config']['upload_url'].$video.'" />'."\n";
			$video_str .= '<meta property="og:video:type" content="video/mp4" />'."\n";
			$video_str .= '<meta property="og:video:width" content="1920">';
			$video_str .= '<meta property="og:video:height" content="1080">';
		}
		
		$favicon_str = '';
		
		if (empty($GLOBALS['config']['favicon'])){
			$favicon = 'cms/cms_icon_black.png';
		} else {
			$favicon = $GLOBALS['config']['favicon'];
		}
		
		$icon_data = _iw($favicon, array('width' => 48, 'output' => 'ico', ));
		$favicon_str .= '<link href="'.$GLOBALS['config']['upload_url'].$icon_data['image'].'" rel="shortcut icon">'."\n";
		$icon_data = _iw($favicon, array('width' => 192, 'output' => 'png', ));
		$favicon_str .= '<link href="'.$GLOBALS['config']['upload_url'].$icon_data['image'].'" rel="icon" type="image/png" sizes="192x192">'."\n";
		$icon_data = _iw($favicon, array('width' => 180, 'output' => 'png', ));
		$favicon_str .= '<link href="'.$GLOBALS['config']['upload_url'].$icon_data['image'].'" rel="apple-touch-icon" sizes="180x180">'."\n";
	
		if (!empty($GLOBALS['_panel_descriptions'])){
			$_description = trim(implode(' - ', $GLOBALS['_panel_descriptions']), ' -');
		} else {
			$_description = '';
		}
		
		if (strlen($_description) > 300){
			$_description = substr($_description, 0, strrpos($_description, ' '));
		}
		$_description = strip_tags($_description);
	
		$_title = $this->compile_page_title();
		$_url = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443)
						? 'https://' : 'http://' ).$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];

		$html = str_replace(
	
				'</head>',
	
				'<title>'.$_title.'</title>'."\n".
				'<meta name="description" content="'.$_description.'" />'."\n".
				$css_str."\n".
				$js_str."\n".
				'<meta property="og:type" content="website">'.
				'<meta property="og:url" content="'.$_url.'" />'."\n".
				'<meta property="og:title" content="'.$_title.'" />'."\n".
				'<meta property="og:description" content="'.$_description.'" />'."\n".
				$image_str.
				$video_str.
				
				(
						(!empty($GLOBALS['_panel_video_id']) && $GLOBALS['config']['meta_video_twitter'] == 'player') ?
				
						'<meta name="twitter:card" content="player" />'.
						'<meta name="twitter:player" content="https://www.youtube.com/embed/'.$GLOBALS['_panel_video_id'].'">'.
						'<meta name="twitter:player:width" content="1280">'.
						'<meta name="twitter:player:height" content="720">'
						
						:
						
						'<meta name="twitter:card" content="summary_large_image" />'
						
				)."\n".
								
				$favicon_str.
				'</head>',
	
				$page
	
				);

		print($html);

		if (!empty($page_cache_context['ttl']) && !empty($page_cache_context['page'])) {
			$this->load->library('cache');
			$stem = $this->cache->build_stem($page_cache_context['route_target'] ?? '', $page_cache_context['request_uri'] ?? '');
			if ($stem !== '') {
				$meta = $this->cache->build_write_meta(
						$page_cache_context['page'],
						$page_cache_context['position_pages'] ?? [],
						$page_cache_context['route_target'] ?? '',
						$page_cache_context['request_uri'] ?? '',
						$page_cache_context['ttl']
				);
				$this->cache->write($this->cache->stem_with_hash($stem), $html, $meta);
			}
		}
	
	}
	
	function compile_page_title(){
		
		if (!empty($GLOBALS['_panel_titles'])){
			
			$_title = trim(implode(' '.(!empty($GLOBALS['config']['site_title_delimiter']) ? $GLOBALS['config']['site_title_delimiter'] : '-').
					' ', $GLOBALS['_panel_titles']), ' -');
			
			if (!mb_detect_encoding($_title, 'UTF-8', true)){
				$_title = utf8_encode($_title);
			}
		
		} else {
			
			$_title = '';
		
		}
		
		$_title = (!empty($GLOBALS['config']['environment']) ? '['.$GLOBALS['config']['environment'].'] ' : '') .
		
		str_replace('#page#', $_title, (!empty($GLOBALS['config']['site_title']) ? $GLOBALS['config']['site_title'] : 'New website - #page#'));
		
		return $_title;
		
	}
	
	function get_page_css($page_id){
		
		$starttime = microtime(true);

		// check if repack is needed
		if ($page_id !== false && !empty($GLOBALS['config']['cache']['vcs_check'])){
			
			$page_id_clean = str_replace(['/', '='], '_', $page_id);
			$page_css_filename = $GLOBALS['config']['base_path'].'cache/page_css_'.$page_id_clean.'.txt';
		
			$last_modification = 0;
				
			if ($GLOBALS['config']['cache']['vcs_check'] == 'git' && file_exists($GLOBALS['config']['base_path'].'.git/index')){
				$last_modification = filemtime($GLOBALS['config']['base_path'].'.git/index');
			}
				
			if ($GLOBALS['config']['cache']['vcs_check'] == 'svn' && file_exists($GLOBALS['config']['base_path'].'.svn/wc.db')){
				$last_modification = filemtime($GLOBALS['config']['base_path'].'.svn/wc.db');
			}
				
			$vcs_check_filename = $GLOBALS['config']['base_path'].'cache/vcs_check.json';
			if (file_exists($vcs_check_filename)){
				
				if (!empty($last_modification)){
			
					$vcs_check = json_decode(file_get_contents($vcs_check_filename), true);
			
					if (empty($vcs_check[$page_id_clean]) || $vcs_check[$page_id_clean] < $last_modification){
							
						// needs update
						$vcs_check[$page_id_clean] = $last_modification;
						file_put_contents($vcs_check_filename, json_encode($vcs_check, JSON_PRETTY_PRINT));
	
					} else {
						
						if (file_exists($page_css_filename)){
							return file_get_contents($page_css_filename);
						}
						
					}
			
				}
				
			} else {
				
				file_put_contents($vcs_check_filename, json_encode([$page_id_clean => $last_modification], JSON_PRETTY_PRINT));
				
			}
				
		}
		 
		// get global css
		$global_css = [];
		if (file_exists($GLOBALS['config']['base_path'].'cache/cms_cssjs_settings.json')){
	
			$global_css = json_decode(file_get_contents($GLOBALS['config']['base_path'].'cache/cms_cssjs_settings.json'), true);
			 
		} else {
			
			$ci =& get_instance();
			$ci->load->model('cms/cms_page_panel_model');
			
			$cssjs_settings = $ci->cms_page_panel_model->get_cms_page_panel_settings('cms/cms_cssjs_settings');

			if (!empty($cssjs_settings['css'])){
				$global_css = array_reverse(array_values($cssjs_settings['css']));
				file_put_contents($GLOBALS['config']['base_path'].'cache/cms_sccjs_settings.json', json_encode($global_css));
			}
			 
		}

		if (!empty($global_css) && is_array($global_css)){
			foreach($global_css as $css_item){
				
				add_css(['script' => $css_item, 'top' => 2, ]);
		
			}
		}

		// compile files together
		$css_arr = pack_css($GLOBALS['_panel_scss']);
		
		if (empty($GLOBALS['config']['inline_css'])){
		
			$css_str = '';
			if (!empty($css_arr)){
				foreach($css_arr as $css_line){
					$css_str .= '<link rel="stylesheet" type="text/css" href="'.$css_line['script'].'" />'."\n";
				}
			}
		
		} else {
		
			if (!empty($css_arr)){
		
				// check for cache
				$hash = substr(md5('inline_'.serialize($css_arr).'_'.(!empty($GLOBALS['config']['inline_limit']) ? $GLOBALS['config']['inline_limit'] : 0)), 0, 8);
				$css_filename = $GLOBALS['config']['base_path'].'cache/'.$hash.'.css';
				$css2_filename = $GLOBALS['config']['base_path'].'cache/'.$hash.'_2.css';
				$css2_url = $GLOBALS['config']['base_url'].'cache/'.$hash.'_2.css';
		
				if (file_exists($css_filename) && file_exists($css2_filename)){
		
					$css_str = file_get_contents($css_filename);
					$css2_str = file_get_contents($css2_filename);
		
				} else {
		
					$css_str = '';
					$css2_str = '';
		
					foreach($css_arr as $css_line){
		
						$css_tmp = str_replace("url('../", "url('".$GLOBALS['config']['base_url'], file_get_contents($css_line['filename']))."\n";
						$css_tmp = str_replace(' {', '{', $css_tmp);
						$css_tmp = preg_replace('!([;}{,:])\s+!s', '$1', $css_tmp);
						$css_tmp = preg_replace('!/\*.*?\*/!s', ' ', $css_tmp);
		
						if (empty($GLOBALS['config']['inline_limit']) || strlen($css_str) < $GLOBALS['config']['inline_limit']){
							$css_str .= $css_tmp;
						} else {
							$css2_str .= $css_tmp;
						}
		
					}
		
					file_put_contents($css_filename, $css_str);
					file_put_contents($css2_filename, $css2_str);
		
				}
		
			}
		
			$css_str = '<style type="text/css">'."\n".$css_str."\n".'</style>'."\n";
			if (!empty($css2_str)){
				$css_str .= '<link rel="preload" href="'.$css2_url.(!empty($GLOBALS['config']['cache']['force_download']) ? '?v='.time() : '').'" as="style" onload="this.rel=\'stylesheet\'">'."\n";
			}
		
		}
		
		$debug_extra = '';
		if ($page_id !== false && !empty($GLOBALS['config']['cache']['vcs_check'])){
			file_put_contents($page_css_filename, $css_str.'<!-- css: '.date('Y-m-d H:i:s').' -->'."\n");
			$debug_extra = ' (saved to cache)';
		}

		return $css_str.'<!-- css live: '.round((microtime(true) - $starttime)*1000).'ms'.$debug_extra.' -->'."\n";
		 
	}
	
	/**
	 * @return panel source which can be inserted to the page with jquery or _panel helper
	 *
	 * @param no_html 	returns only array returned from panel view
	 * @param embed 	returns only html part and adds js and css to ci (this) object
	 *
	 */
	function ajax_panel($name, $params = []){
	
		$this->load->model('cms/cms_page_panel_model');
		$this->load->model('cms/cms_panel_model');
		
		$panel_config = $this->cms_panel_model->get_cms_panel_config($name);
		if (!empty($panel_config['extends'])){
			$params['_extends'] = $panel_config['extends'];
		}

		if (is_numeric($name)){
			$params_db = $this->cms_page_panel_model->get_cms_page_panel($name);
			if ($params_db['show']){
				$params = array_merge($params, $params_db);
				$name = $params['panel_name'];
			}
		}

		$return = array();
		 
		if (!is_array($params)){
			$params = array('data' => $params, );
		}
		 
		// get page panel settings (admin request → CMS language; site → visitor language)
		$params = array_merge($this->cms_page_panel_model->get_cms_page_panel_settings($name, $this->cms_page_panel_model->get_content_language()), $params);

		$this->load->model('cms/cms_access_model');
		if (!$this->cms_access_model->enforce_panel_access($name, $params)){
			if (!empty($params['no_html'])){
				return [];
			}
			return [
					'_html' => $this->cms_access_model->get_panel_access_skipped_html($name),
					'js' => [],
					'css' => [],
					'scss' => [],
			];
		}

		$params['_access_ok'] = 1;

		// do panel action
		$action_result = $this->run_action($name, $params);
		if (is_array($action_result)){
			$params = array_merge($params, $action_result);
		}
		
		// leave after action when no html needed
		if (!empty($params['no_html']) && stristr($name, '/')){
		
			return $params;
		
		}
		 
		// get panel
		$return = $this->panel($name, $params);
		
		// meta images
		if (!empty($params['_images'])){
			$GLOBALS['_panel_images'] = array_merge(array_values($GLOBALS['_panel_images']), array_values($params['_images']));
		}

		// js and css
		$css_str = '';
		$js_str = '';
		if (!empty($params['embed'])){
	
			$return['_panel_js'] = [];
			$return['_panel_scss'] = $return['scss'];
	
		} else if (empty($params['no_html'])){
	
			$js = $GLOBALS['_panel_js'];
	
			if (empty($params['_no_css'])){
				
				$scss = array_merge($return['scss'], $return['css']);
				
				if(!empty($GLOBALS['_panel_scss'])){
					$scss = array_merge($scss, $GLOBALS['_panel_scss']);
				}
				
				$css_arr = pack_css($scss);
	
				if (count($css_arr)){
		
					$css_arr_prep = [];
					foreach ($css_arr as $css_inc){
						$css_arr_prep[] = $css_inc['script'];
					}
					$return['_panel_css'] = $css_arr_prep;
					$return['_panel_css_force'] = !empty($GLOBALS['config']['cache']['force_download']) ? 1 : 0;
		
				}
			
			}

			if (empty($params['_no_js'])){
				$js_str = pack_js($js);
			}
	
		}
	
		if (!empty($this->view_params)){
			$return = array_merge($return, $this->view_params);
			$this->view_params = array();
		}
		 
		if (empty($params['no_html'])){
			$return['_html'] .= "\n".$css_str."\n".$js_str;
		}
	
		return $return;
		 
	}
	
	// panel name is filled when this controller is panel
	function init_panel($params = array()){
		 
		if (!empty($params['name'])){
			$this->panel_name = $params['name'];
		} else {
			$this->panel_name = '';
		}
		 
		if (!empty($params['controller'])){
			$this->panel_controller = $params['controller'];
		} else {
			$this->panel_controller = '';
		}
	
	}
	
	/*
	 * for main controller to generate panels output as texts
	 */
	function render($page_config){

		$this->load->model('cms/cms_page_panel_model');

		foreach($page_config as $key => $panel_config){
			
			if (!empty($panel_config['_inline_access_denied'])){
				continue;
			}
			
			if (stristr($panel_config['panel'], '/')){
				list($module, $panel_name) = explode('/', $panel_config['panel']);
				$page_config[$key]['module'] = $module;
			} else {
				_html_error('Bad panel name in render: '.$panel_config['panel']);
			}
		}
		
		// load module configs
		foreach($page_config as $key => $panel_config){
			if (!empty($panel_config['module'])){

				if (!isset($GLOBALS['config']['module'][$panel_config['module']]['config'])){
					
					$GLOBALS['config']['module'][$panel_config['module']]['config'] = 
							$this->cms_page_panel_model->get_cms_page_panel_settings($panel_config['module'].'/'.$panel_config['module']);
					
				}
				
				$page_config[$key]['params']['config'] = $GLOBALS['config']['module'][$panel_config['module']]['config'];
				
			}
		}
	
		$page_cache_deferred = [];
		if (!empty($GLOBALS['cms_page_cache_write'])) {
			$this->load->library('cache');
			$this->load->model('cms/cms_panel_model');
			$GLOBALS['page_cache_deferred_meta'] = [
				'panels' => [],
				'positions' => [],
				'panels_by_position' => [],
			];
			$GLOBALS['position_panel_scss'] = [];
			foreach ($page_config as $key => $panel_config) {
				if (!empty($panel_config['_inline_access_denied'])) {
					continue;
				}
				$panel_definition = $this->cms_panel_model->get_cms_panel_config($panel_config['panel']);
				if ($this->cache->panel_is_deferred($panel_definition)) {
					$page_cache_deferred[$key] = true;
					$GLOBALS['page_cache_deferred_meta']['panels'][] = $panel_config['panel'];
					$GLOBALS['page_cache_deferred_meta']['positions'][$panel_config['position']] = 1;
					if (empty($GLOBALS['page_cache_deferred_meta']['panels_by_position'][$panel_config['position']])) {
						$GLOBALS['page_cache_deferred_meta']['panels_by_position'][$panel_config['position']] = [];
					}
					$GLOBALS['page_cache_deferred_meta']['panels_by_position'][$panel_config['position']][] = $panel_config['panel'];
				}
			}
		}

		// do panel actions
		$this->load->model('cms/cms_access_model');
		foreach($page_config as $key => $panel_config){
			
			if (!empty($panel_config['_inline_access_denied'])){
				continue;
			}

			if (!empty($page_cache_deferred[$key])) {
				continue;
			}
			
			if (empty($panel_config['params'])){
				$panel_config['params'] = array();
			}

			if (empty($panel_config['panel'])){
				continue;
			}

			if (!$this->cms_access_model->enforce_panel_access($panel_config['panel'], $panel_config['params'])){
				$page_config[$key]['_access_skipped'] = 1;
				continue;
			}

			$action_result = $this->run_action($panel_config['panel'], (!empty($panel_config['params']) ? $panel_config['params'] : array()));
			$page_config[$key]['params'] =
					(!empty($action_result) && is_array($action_result) ? array_merge($panel_config['params'], $action_result) : $panel_config['params']);
			$page_config[$key]['params']['_access_ok'] = 1;
		}

		$page_config = $this->_swap_page_config_header($page_config);
	
		$return = array();
		// output panels
		foreach($page_config as $key => $panel_config){
	
			$params = !empty($panel_config['params']) ? $panel_config['params'] : array();
			if (empty($params['cms_page_panel_id'])) $params['cms_page_panel_id'] = 0;
			
			if (!empty($panel_config['_inline_access_denied'])){
				
				$return[$panel_config['position'].'_'.$key.'_'.(int)$params['cms_page_id']] =
						$this->cms_access_model->get_access_denied_inline_html();
				continue;
				
			}

			if (!empty($panel_config['_access_skipped'])){

				$return[$panel_config['position'].'_'.$key.'_'.(!empty($params['cms_page_id']) ? $params['cms_page_id'] : '0')] =
						$this->cms_access_model->get_panel_access_skipped_html($panel_config['panel']);
				continue;

			}

			if (!empty($page_cache_deferred[$key])) {
				$return[$panel_config['position'].'_'.$key.'_'.(!empty($params['cms_page_id']) ? $params['cms_page_id'] : '0')] =
						$this->cache->deferred_mount_html($panel_config['panel']);
				continue;
			}
			
			// add _page_id for real page id
			if (empty($params['_cms_page_id'])) $params['_cms_page_id'] = 
					(!empty($panel_config['_cms_page_id']) ? $panel_config['_cms_page_id'] : 
							(!empty($panel_config['params']['cms_page_id']) ? $panel_config['params']['cms_page_id'] : 0));

			// meta images
			if (!empty($params['_images'])){
				$GLOBALS['_panel_images'] = array_merge(array_values($GLOBALS['_panel_images']), array_values($params['_images']));
			}

			$access_cache_hash = $this->cms_access_model->get_cache_access_hash($panel_config['panel']);

			if (!empty($GLOBALS['cms_page_cache_write'])){
				$scss_count_before = count($GLOBALS['_panel_scss'] ?? []);
			}

			// check for cache
			if (empty($action_result['_no_cache']) && !(!empty($params['module']) && $params['module'] == 'cms')
					&& (!empty($GLOBALS['config']['panel_cache']) || (isset($params['_cache_time']) && $params['_cache_time'] > 0))
					&& empty($GLOBALS['config']['cache']['force_download'])){
	
				$params['module'] = !empty($panel_config['module']) ? $panel_config['module'] : '';
		
				// if cache file exists
				$filename = $GLOBALS['config']['base_path'].'cache/_'.$params['cms_page_panel_id'].'_'.str_replace('/', '__', $panel_config['panel']).
						'_'.substr(md5($panel_config['panel'].serialize($params).$_SESSION['config']['targets']['hash'].
						$_SESSION['webp'].$access_cache_hash), 0, 6).'.txt';
						
				if (is_file($filename)){

					// if panel cache time is different from empty, keep it, else use global cache time setting
					if (empty($params['_cache_time'])) {
						$params['_cache_time'] = 0;
					}
					
					$cache_time = $params['_cache_time'] != 0 ? $params['_cache_time'] : (!empty($GLOBALS['config']['panel_cache']) ? $GLOBALS['config']['panel_cache'] : 0);

					if ((time() - filemtime($filename)) < $cache_time){
						 
						$panel_data = unserialize(file_get_contents($filename));

						// add js, css, scss to global page files (same source of truth as live panel())
						$GLOBALS['_panel_js'] = array_merge($GLOBALS['_panel_js'] ?? [], $panel_data['js']);

						foreach($panel_data['scss'] as $scss_file){
							add_css($scss_file);
						}
						 
					} else {
						
						unlink($filename);
						
					}

				}
	
			}

			// if no data from cache
			if (empty($panel_data)){

				$params['module'] = !empty($panel_config['module']) ? $panel_config['module'] : '';
				$panel_data = $this->panel($panel_config['panel'], $params);

				// check if to save to cache file
				if (empty($action_result['_no_cache']) && !(!empty($params['module']) && $params['module'] == 'cms')
						&& (!empty($GLOBALS['config']['panel_cache']) || (isset($params['_cache_time']) && $params['_cache_time'] > 0))
						&& empty($GLOBALS['config']['cache']['force_download'])){
	
							$filename = $GLOBALS['config']['base_path'].'cache/_'.$params['cms_page_panel_id'].'_'.
									str_replace('/', '__', $panel_config['panel']).'_'.substr(md5($panel_config['panel'].serialize($params).
									$_SESSION['config']['targets']['hash'].$_SESSION['webp'].$access_cache_hash), 0, 6).'.txt';
										
							$panel_data['_html'] .= '<!-- cached: '.date('Y-m-d H:i:s').' -->'."\n";
							file_put_contents($filename, serialize($panel_data));
							 
				}
	
			}

			$panel_html = $panel_data['_html'];

			if (!empty($_COOKIE['cms_preview_highlight'])
					&& (int)$_COOKIE['cms_preview_highlight'] === (int)$params['cms_page_panel_id']
					&& (int)$params['cms_page_panel_id'] > 0) {
				$panel_html = '<div class="cms_preview_site_marker"></div>'.$panel_html;
			}

			$return[$panel_config['position'].'_'.$key.'_'.(!empty($params['cms_page_id']) ? $params['cms_page_id'] : '0')] = $panel_html;

			if (!empty($GLOBALS['cms_page_cache_write'])){
				$new_scss = array_slice($GLOBALS['_panel_scss'] ?? [], $scss_count_before);
				if (!empty($new_scss)){
					if (empty($GLOBALS['position_panel_scss'][$panel_config['position']])){
						$GLOBALS['position_panel_scss'][$panel_config['position']] = [];
					}
					array_push($GLOBALS['position_panel_scss'][$panel_config['position']], ...$new_scss);
				}
			}
	
			unset($panel_data);
	
		}

		return $return;
		 
	}
	
	function _append_extend_module_js(&$js, $ext_module, $ext_panel){

		$ext_config = $this->cms_panel_model->get_cms_panel_config($ext_module.'/'.$ext_panel);
		if (!empty($ext_config['js'])){
			foreach($ext_config['js'] as $js_entry){
				list($js_module, $js_file) = explode('/', $js_entry);
				$js[] = 'modules/'.$js_module.'/js/'.$js_file.'.js';
			}
		}

		if (file_exists($GLOBALS['config']['base_path'].'modules/'.$ext_module.'/js/'.$ext_module.'.js')) {
			$js[] = 'modules/'.$ext_module.'/js/'.$ext_module.'.js';
		}
		if (file_exists($GLOBALS['config']['base_path'].'modules/'.$ext_module.'/js/'.$ext_panel.'.js')) {
			$js[] = 'modules/'.$ext_module.'/js/'.$ext_panel.'.js';
		}

	}

	function get_panel_filenames($panel_name, $params = [], $extends = []){

		if (!empty($GLOBALS['_panel_files'][$panel_name])){
			return $GLOBALS['_panel_files'][$panel_name];
		}
		 
		$return = [];
	
		if (!empty($extends['panel'])){
			if (!stristr($extends['panel'], '/') && !empty($GLOBALS['config']['errors_visible'])){
				_html_error('Bad panel extension panel name '.$extends['panel'].' (definition has to be "module/panel", save page panel in CMS after fixing)');
			} else {
				$extends_files = $this->get_panel_filenames($extends['panel']);
				list($return['extends_module'], $return['extends_name']) = explode('/', $extends['panel']);
			}
		}

		$module = '';

		if (is_numeric($panel_name)) {
			$this->load->model('cms/cms_page_panel_model');
			$original = $this->cms_page_panel_model->get_cms_page_panel($panel_name);
			$panel_name = $original['panel_name'];
		}

		if (empty($params['module']) && stristr($panel_name, '/')){
			list($module, $name) = explode('/', $panel_name);
		} else if(!empty($params['module'])){
			$module = $params['module'];
			$name = str_replace($module.'/', '', $panel_name);
		} else {
			_html_error('Bad panel name '.$panel_name.' (definition has to be "module/panel" or module has to be specified in parameters)');
			die();
		}
		
		$controller_filename = $GLOBALS['config']['base_path'].'modules/'.$module.'/panels/'.$name.'.php';
		$template_filename = $GLOBALS['config']['base_path'].'modules/'.$module.'/templates/'.$name.'.tpl.php';

		if (!empty($controller_filename) && file_exists($controller_filename)){
			$return['controller'] = $controller_filename;
		} else {
			$return['controller'] = '';
		}
	
		if (!empty($extends_files['controller'])){
			$return['extends_controller'] = $extends_files['controller'];
		}

		if (!empty($template_filename) && file_exists($template_filename)){
			$return['template'] = $template_filename;
		} else if (!empty($extends_files['template'])){ // if no template, but has extends template, use this
			$return['template'] = $extends_files['template'];
			$return['template_extends'] = true;
		} else {
			$return['template'] = '';
		}
		 
		$return['module'] = $module;
		$return['name'] = !empty($name) ? $name : $panel_name;
	
		// collect panel related js files
		$return['js'] = [];

		$this->load->model('cms/cms_panel_model');
		$panel_config = $this->cms_panel_model->get_cms_panel_config($module.'/'.$return['name']);
		if (!empty($panel_config['js'])){
			foreach($panel_config['js'] as $js){
				list($js_module, $js_file) = explode('/', $js);
				$return['js'][] = 'modules/'.$js_module.'/js/'.$js_file.'.js';
			}
		}

		if (!empty($params['_js'])){
			$return['js'] = array_merge($return['js'], array_values($params['_js']));
		}
	
		if (!empty($extends['join_js']) && !empty($extends['panel'])){
			$return['js'] = array_merge($return['js'], $extends_files['js']);
		}
		if (file_exists($GLOBALS['config']['base_path'].'modules/'.$return['module'].'/js/'.$return['module'].'.js')) {
			$return['js'][] = 'modules/'.$return['module'].'/js/'.$return['module'].'.js';
		}
		if (file_exists($GLOBALS['config']['base_path'].'modules/'.$return['module'].'/js/'.$return['name'].'.js')) {
			$return['js'][] = 'modules/'.$return['module'].'/js/'.$return['name'].'.js';
			$panel_js_exists = true;
		}
		// if no panel js exists, there is extends js and not already joined, use this (but keep module js from panel)
		if (empty($panel_js_exists) && !empty($extends_files['js']) && empty($extends['join_js'])){
			$return['js'] = array_merge($return['js'], $extends_files['js']);
		}
	
		// collect panel related css files
		$return['css'] = [];
		if (!empty($extends['join_css']) && !empty($extends['panel'])){
			$return['css'] = $extends_files['css'];
		}
		if (file_exists($GLOBALS['config']['base_path'].'modules/'.$return['module'].'/css/'.$return['module'].'.css')) {
			$return['css'][] = array('script' => 'modules/'.$return['module'].'/css/'.$return['module'].'.css', 'top' => 1, );
		}
		if (file_exists($GLOBALS['config']['base_path'].'modules/'.$return['module'].'/css/'.$return['name'].'.css')) {
			$return['css'][] = array('script' => 'modules/'.$return['module'].'/css/'.$return['name'].'.css', );
			$panel_css_exists = true;
		}
		// scss files
		$return['scss'] = [];
		 
		if (!empty($params['_css'])){
			$return['scss'] = array_merge($return['scss'], $params['_css']);
		}
		 
		if (!empty($extends['join_css']) && !empty($extends['panel'])){
			$return['scss'] = array_merge($return['scss'], $extends_files['scss']);
		}
		
		// main module scss
		if (file_exists($GLOBALS['config']['base_path'].'modules/'.$return['module'].'/css/'.$return['module'].'.scss')) {
			$return['scss'][] = array(
					'script' => 'modules/'.$return['module'].'/css/'.$return['module'].'.scss',
					'top' => 1,
					'related' => array(),
					'css' => 'cache/'.$return['module'].'__'.$return['module'].'.css',
					'module_path' => 'modules/'.$return['module'].'/',
			);
		}
		
		// panel scss
		if (file_exists($GLOBALS['config']['base_path'].'modules/'.$return['module'].'/css/'.$return['name'].'.scss')) {
			$return['scss'][] = array(
					'script' => 'modules/'.$return['module'].'/css/'.$return['name'].'.scss',
					'related' => file_exists($GLOBALS['config']['base_path'].'modules/'.$return['module'].'/css/'.$return['module'].'.scss') ?
					array('modules/'.$return['module'].'/css/'.$return['module'].'.scss', ) : array(),
					'css' => 'cache/'.$return['module'].'__'.$return['name'].'.css',
			);
			$panel_css_exists = true; // scss replaces css here
		}
		
		// extensions from module config.json (definition + scss + js)
		foreach($GLOBALS['config']['extends'] as $item){
			if ($item['target'] == $return['module'].'/'.$return['name']){
				
				list($ext_module, $ext_panel) = explode('/', $item['source']);
				
				if (file_exists($GLOBALS['config']['base_path'].'modules/'.$ext_module.'/css/'.$ext_module.'.scss')){
					$return['scss'][] = [
							'script' => 'modules/'.$ext_module.'/css/'.$ext_module.'.scss',
							'top' => 1,
							'related' => [],
							'css' => 'cache/'.$ext_module.'__'.$ext_module.'.css',
							'module_path' => 'modules/'.$ext_module.'/',
					];
				}
				
				if (file_exists($GLOBALS['config']['base_path'].'modules/'.$ext_module.'/css/'.$ext_panel.'.scss')){

					$return['scss'][] = [
							'script' => 'modules/'.$ext_module.'/css/'.$ext_panel.'.scss',
							'related' => file_exists($GLOBALS['config']['base_path'].'modules/'.$ext_module.'/css/'.$ext_module.'.scss') ?
							['modules/'.$ext_module.'/css/'.$ext_module.'.scss', ] : [],
							'css' => 'cache/'.$ext_module.'__'.$ext_panel.'.css',
					];
					
					
					$panel_css_exists = true; // scss replaces css here
					
				}

				$this->_append_extend_module_js($return['js'], $ext_module, $ext_panel);
			}
		}

		if (empty($panel_css_exists) && !empty($extends_files['scss']) && empty($extends['join_css'])){
			$return['css'] = array_merge($return['css'], $extends_files['css']);
			$return['scss'] = array_merge($return['scss'], $extends_files['scss']);
		}
	
		// cache this
		$GLOBALS['_panel_files'][$panel_name] = $return;
	
		return $return;
	
	}

	function _swap_page_config_header($page_config){

		$header_page_id = 0;
		$main_cms_page_id = 0;

		foreach ($page_config as $panel_config){
			if (!empty($panel_config['params']['_swap_header_page_id'])){
				$header_page_id = (int)$panel_config['params']['_swap_header_page_id'];
				if (!empty($panel_config['_cms_page_id'])){
					$main_cms_page_id = (int)$panel_config['_cms_page_id'];
				} else if (!empty($panel_config['params']['cms_page_id'])){
					$main_cms_page_id = (int)$panel_config['params']['cms_page_id'];
				}
				break;
			}
		}

		if (!$header_page_id){
			return $page_config;
		}

		$header_insert_at = null;
		$new_config = [];

		foreach ($page_config as $key => $panel_config){

			if ($header_insert_at === null && !empty($panel_config['position']) && $panel_config['position'] === 'header'){
				$header_insert_at = count($new_config);
				continue;
			}

			if (!empty($panel_config['position']) && $panel_config['position'] === 'header'){
				continue;
			}

			if (!empty($panel_config['params']['_swap_header_page_id'])){
				unset($panel_config['params']['_swap_header_page_id']);
			}

			$new_config[] = $panel_config;

		}

		if ($header_insert_at === null){
			$header_insert_at = 0;
		}

		$header_panels = $this->_load_position_page_panels($header_page_id, $main_cms_page_id);

		array_splice($new_config, $header_insert_at, 0, $header_panels);

		return $new_config;

	}

	function _load_position_page_panels($page_id, $main_cms_page_id = 0){

		$this->load->model('cms/cms_page_panel_model');

		$blocks = $this->cms_page_panel_model->get_cms_page_panels_by(['cms_page_id' => $page_id, 'show' => 1, ]);
		$panels = [];

		foreach ($blocks as $block){

			if (!$this->cms_page_panel_model->panel_matches_visitor_targets($block)){
				continue;
			}

			if (is_numeric($block['panel_name']) && (int)$block['panel_name'] == $block['panel_name']){
				$block = $this->cms_page_panel_model->get_cms_page_panel($block['panel_name']);
				$block['cms_page_id'] = $page_id;
			}

			$panels[] = [
				'position' => 'header',
				'panel' => $block['panel_name'],
				'params' => $block,
				'_cms_page_id' => $main_cms_page_id,
			];

		}

		return $panels;

	}

}

// Legacy name — prefer extends Controller; CI_Controller kept for older panels
class_alias('Controller', 'CI_Controller');