<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * CodeIgniter
 *
 * An open source application development framework for PHP 5.1.6 or newer
 *
 * @package		CodeIgniter
 * @author		ExpressionEngine Dev Team
 * @copyright	Copyright (c) 2008 - 2014, EllisLab, Inc.
 * @license		http://codeigniter.com/user_guide/license.html
 * @link		http://codeigniter.com
 * @since		Version 1.0
 * @filesource
 */

// ------------------------------------------------------------------------

/**
 * CodeIgniter Application Controller Class
 *
 * This class object is the super class that every library in
 * CodeIgniter will be assigned to.
 *
 * @package		CodeIgniter
 * @subpackage	Libraries
 * @category	Libraries
 * @author		ExpressionEngine Dev Team
 * @link		http://codeigniter.com/user_guide/general/controllers.html
 */
class CI_Controller {

	static $instance;

	/**
	 * Constructor
	 */
	public function __construct()
	{
		
		self::$instance =& $this;

		// Assign all the class objects that were instantiated by the
		// bootstrap file (CodeIgniter.php) to local class variables
		// so that CI can run as one big super object.
		foreach (is_loaded() as $var => $class)
		{
			$this->$var =& load_class($class);
		}

		$this->load =& load_class('Loader', 'core');

		$this->load->initialize();
		/**
		 * past in MY Controller
		 */
		$this->load->model('cms/cms_update_model');
		$this->cms_update_model->up();
		
		$this->load->helper('panel_helper');
		$this->load->helper('image_helper');
		$this->load->helper('packer_helper');
		
		// panels stuff
		$this->init_panel();
		
		$this->js = array();
		$this->css = array();
		$this->scss = array();
		
		if (!isset($GLOBALS['_panel_titles'])){
			$GLOBALS['_panel_titles'] = array();
		}
		if (!isset($GLOBALS['_panel_descriptions'])){
			$GLOBALS['_panel_descriptions'] = array();
		}
		if (!isset($GLOBALS['_panel_images'])){
			$GLOBALS['_panel_images'] = array();
		}
		
		if (!isset($GLOBALS['_panel_css'])){
			$GLOBALS['_panel_css'] = array();
		}
		if (!isset($GLOBALS['_panel_scss'])){
			$GLOBALS['_panel_scss'] = array();
		}
		if (!isset($GLOBALS['_panel_js'])){
			$GLOBALS['_panel_js'] = array();
		}
		
	}

	public static function &get_instance()
	{

		return self::$instance;
		
	}
	
	/**
	 * past in MY Controller
	 */
	
	function panel_heading($params){
		 
		$return = !empty($params['heading']) ? $params['heading'] : '';
		 
		if (empty($params['heading']) && !empty($params['cms_page_panel_id'])){
			$return = $params['panel_name'].'='.$params['cms_page_panel_id'];
		}
	
		return $return;
		 
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
			 
			// temporarily create new ci sandbox for panel
			$this->panel_ci =& get_instance();
	
			$extends_panel_name = $files['extends_module'].'_'.$files['extends_name'].'_panel';
			$this->panel_ci->load->library(
					$files['extends_controller'],
					['module' => $files['extends_module'], 'name' => $files['extends_name'], ],
					$extends_panel_name
					);
	
			if (method_exists($this->panel_ci->{$extends_panel_name}, $panel_method)){
				$this->panel_ci->{$extends_panel_name}->init_panel(array('name' => $files['extends_name'], 'controller' => $files['extends_controller'], ));
				$params = $this->panel_ci->{$extends_panel_name}->{$panel_method}($params);
				if (is_array($params)){
					$params['_no_cache'] = 1;
				}
			}
	
			// clear temporary resource
			unset($this->panel_ci);
	
		}
	
		// if there is a normal controller, do this
		if (!empty($files['controller'])){
	
			// temporarily create new ci sandbox for panel
			$this->panel_ci =& get_instance();
	
			// load panel stuff into this sandbox - it will be the same as sandbox is singleton for itself
			$panel_name = $files['module'].'_'.$files['name'].'_panel';
	
			$this->panel_ci->load->library(
					$files['controller'],
					['module' => $files['module'], 'name' => $files['name'], ],
					$panel_name
					);
	
			if (method_exists($this->panel_ci->$panel_name, $panel_method)){
	
				// define this controller as panel
				$this->panel_ci->{$panel_name}->init_panel(array('name' => $files['name'], 'controller' => $files['controller'], ));
	
				// get params through panel controller
				$params = $this->panel_ci->{$panel_name}->{$panel_method}($params);
				if (is_array($params)){
					$params['_no_cache'] = 1;
				}
		   
			}
	
			// clear temporary resource
			unset($this->panel_ci);
	
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
	function panel($name, $params = array()){

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
				 
				// temporarily create new ci sandbox for panel
				$this->panel_ci =& get_instance();
				 
				$extends_panel_name = $files['extends_module'].'_'.$files['extends_name'].'_panel';
				$this->panel_ci->load->library(
						$files['extends_controller'],
						['module' => $files['extends_module'], 'name' => $files['extends_name'], ],
						$extends_panel_name
						);
				$this->panel_ci->{$extends_panel_name}->init_panel(array('name' => $files['extends_name'], 'controller' => $files['extends_controller'], ));
				$params = $this->panel_ci->{$extends_panel_name}->panel_params($params);
	
				// clear temporary resource
				unset($this->panel_ci);
				 
			}
	
			// if there is a normal controller, do this
			if (!empty($files['controller'])){
	
				// temporarily create new ci sandbox for panel
				$this->panel_ci =& get_instance();
				 
				// load panel stuff into this sandbox - it will be the same as sandbox is singleton for itself
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
	
				// get js, css, title and description back and automatically load named js and css from panels dirs
				$panel_js = array_merge($panel_js, $this->panel_ci->{$panel_name}->js);
				$panel_css = array_merge($panel_css, $this->panel_ci->{$panel_name}->css);
				$panel_scss = array_merge($panel_scss, $this->panel_ci->{$panel_name}->scss);
		   
				// clear temporary resource
				unset($this->panel_ci);
	
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
	
		} else if (empty($params['panel_id']) && !empty($GLOBALS['config']['errors_visible'])){
			$return = html_error('Missing panel template: '.$name);
		} else {
			$return = '';
		}
	
		// if submenu anchor
		if(!empty($params['submenu_anchor'])){
			$return = '<div class="cms_anchor" id="'.$params['submenu_anchor'].'" name="'.$params['submenu_anchor'].'"></div>'.$return;
		}
	
		// add debug data
		$return = "\n".'<!-- panel "' . $files['module'] . '/' . $files['name'] . '" start -->'."\n".
				$return .
				"\n".'<!-- panel "' . $files['module'] . '/' . $files['name'] . '" ( '.(!empty($controller_timer_start) ? ' controller: '.($controller_timer_end - $controller_timer_start).'ms ' : '').
				(!empty($template_timer_start) ? ' template: '.($template_timer_end - $template_timer_start).'ms' : ''). ' ) end -->'."\n";

		// add js, css, scss to global page files
		$this->js = array_merge($this->js, $panel_js);
		$this->css = array_merge($this->css, $panel_css);
		$this->scss = array_merge($this->scss, $panel_scss);

		// save panel result params for returning them when ajax panel is requested
		$this->view_params = $params;

		return [
				'html' => $return,
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
	function output($layout_name, $page_id, $panel_data = array()){
		
		$page = $this->load->layout($layout_name, $panel_data);

		// get css part of page head
		$css_str = $this->get_page_css($page_id);

		// put together mandatory config js and panel/controller loaded js
		if (!empty($GLOBALS['config']['js'])){
			$jss = array_merge($GLOBALS['config']['js'], $this->js);
		} else {
			$jss = array();
		}
		$jss = array_merge($jss, $GLOBALS['_panel_js']);
		$js_str = pack_js($jss);
	
		// images, descriptions and titles from panels
		$image_str = '';
		if (!empty($GLOBALS['_panel_images'])){
			$GLOBALS['_panel_images'] = array_slice($GLOBALS['_panel_images'], 0, 3); // maximum 3 images
			$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? 'https://' : 'http://';
			foreach($GLOBALS['_panel_images'] as $image){
				if (!empty($image)){
					$image_data = _iw($image, array('width' => 800, ));
					$image_str .= '<meta property="og:image" content="'.
							(empty($GLOBALS['config']['cdn_url']) ? $protocol.$_SERVER['HTTP_HOST'] : '').
							$GLOBALS['config']['upload_url'].$image_data['image'].'" />'."\n";
							$image_str .= '<meta property="og:image:width" content="'.$image_data['width'].'" />'."\n";
							$image_str .= '<meta property="og:image:height" content="'.$image_data['height'].'" />'."\n";
				}
			}
		}
	
		$favicon_str = '';
		if (!empty($GLOBALS['config']['favicon'])){
			//			$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? 'https://' : 'http://';
			$icon_data = _iw($GLOBALS['config']['favicon'], array('width' => 48, 'output' => 'ico', ));
			$favicon_str .= '<link href="'.$GLOBALS['config']['upload_url'].$icon_data['image'].'" rel="shortcut icon">'."\n";
			$icon_data = _iw($GLOBALS['config']['favicon'], array('width' => 192, 'output' => 'png', ));
			$favicon_str .= '<link href="'.$GLOBALS['config']['upload_url'].$icon_data['image'].'" rel="icon" type="image/png" sizes="192x192">'."\n";
			$icon_data = _iw($GLOBALS['config']['favicon'], array('width' => 180, 'output' => 'png', ));
			$favicon_str .= '<link href="'.$GLOBALS['config']['upload_url'].$icon_data['image'].'" rel="apple-touch-icon" sizes="180x180">'."\n";
		}
	
		if (!empty($GLOBALS['_panel_descriptions'])){
			$_description = trim(implode(' - ', $GLOBALS['_panel_descriptions']), ' -');
		} else {
			$_description = '';
		}
	
		if (!empty($GLOBALS['_panel_titles'])){
			$_title = trim(implode(' '.(!empty($GLOBALS['config']['site_title_delimiter']) ? $GLOBALS['config']['site_title_delimiter'] : '-').' ', $GLOBALS['_panel_titles']), ' -');
		} else {
			$_title = '';
		}
		$_title = (!empty($GLOBALS['config']['environment']) ? '['.$GLOBALS['config']['environment'].'] ' : '') .
		str_replace('#page#', $_title, (!empty($GLOBALS['config']['site_title']) ? $GLOBALS['config']['site_title'] : 'New website - #page#'));
	
		print(str_replace(
	
				'</head>',
	
				'<title>'.$_title.'</title>'."\n".
				'<meta name="description" content="'.strip_tags($_description).'" />'."\n".
				$css_str."\n".
				$js_str."\n".
				'<meta property="og:url" content="'.
				((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443)
						? 'https://' : 'http://' ).$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'].'" />'."\n".
				'<meta property="og:title" content="'.$_title.'" />'."\n".
				'<meta property="og:description" content="'.strip_tags($_description).'" />'."\n".
				$image_str.
				$favicon_str.
				'</head>',
	
				$page
	
				));
	
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
		
				if (substr($css_item, -4) === '.css'){
					array_unshift($this->css, ['script' => $css_item, 'top' => 2, ]);
				} else {
					array_unshift($this->scss, ['script' => $css_item, 'top' => 2, ]);
				}
		
			}
		}
	
		// merge config css and panel/controller loaded css
		$csss = array_merge($this->css, $GLOBALS['_panel_css']);
		 
		// scss
		$scsss = array_merge($this->scss, $GLOBALS['_panel_scss']);
		 
		// compile files together
		$css_arr = pack_css($csss, $scsss);
		
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
	function ajax_panel($name, $params = array()){
	
		// new method for w/o html
		if (!empty($params['no_html']) && stristr($name, '/')){
	
			$this->load->model('cms/cms_panel_model');
	
			// get config if exists
			$panel_config = $this->cms_panel_model->get_cms_panel_config($name);
			if (!empty($panel_config['extends'])){
				$params['_extends'] = $panel_config['extends'];
			}
	
			$params = $this->run_action($name, $params);
	
			return $params;
	
		}
	
		$this->load->model('cms/cms_page_panel_model');
		 
		$return = array();
		 
		if (!is_array($params)){
			$params = array('data' => $params, );
		}
		 
		// get page panel settings
		$params = array_merge($this->cms_page_panel_model->get_cms_page_panel_settings($name), $params);
		 
		// do panel action
		$action_result = $this->run_action($name, $params);
		if (is_array($action_result)){
			$params = array_merge($params, $action_result);
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
	
			$return['_panel_js'] = $this->js;
				
			if (empty($params['_no_css'])){
				$return['_panel_css'] = $this->css;
				$return['_panel_scss'] = $this->scss;
			} else {
				$return['_panel_css'] = [];
				$return['_panel_scss'] = [];
			}
	
		} else if (empty($params['no_html'])){
	
			$this->js = array_merge($GLOBALS['_panel_js'], $this->js);
	
			if (empty($params['_no_css'])){
				$this->css = array_merge($GLOBALS['_panel_css'], $this->css);
				$this->scss = array_merge($GLOBALS['_panel_scss'], $this->scss);
			} else {
				$this->css = [];
				$this->scss = [];
			}
				
			// prepare css for onpage loading
			$css_arr = pack_css($this->css, $this->scss);
	
			if (count($css_arr)){
	
				$css_arr_prep = [];
				foreach ($css_arr as $css_inc){
					$css_arr_prep[] = $css_inc['script'];
				}
				$css_arr_str = implode('\', \'', $css_arr_prep);
	
				$css_str .= '<script class="cms_load_css cms_load_css_'.md5($css_arr_str).'" type="text/javascript">'."\n";
				$css_str .= 'cms_load_css([\'';
				$css_str .=	$css_arr_str;
				$css_str .= '\'], '. (!empty($GLOBALS['config']['cache']['force_download']) ? 'true' : 'false') .', \'cms_load_css_'. md5($css_arr_str) .'\');'."\n".'</script>'."\n";
	
			}
				
			// get js
			$js_str = pack_js($this->js);
	
		}
	
		if (!empty($this->view_params)){
			$return = array_merge($return, $this->view_params);
			$this->view_params = array();
		}
		 
		if (empty($params['no_html'])){
			$return['html'] .= "\n".$css_str."\n".$js_str;
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
	
		foreach($page_config as $key => $panel_config){
			if (stristr($panel_config['panel'], '/')){
				list($module, $panel_name) = explode('/', $panel_config['panel']);
				$page_config[$key]['module'] = $module;
			}
		}
	
		// do panel actions
		foreach($page_config as $key => $panel_config){
			if (empty($panel_config['params'])){
				$panel_config['params'] = array();
			}
			$action_result = $this->run_action($panel_config['panel'], (!empty($panel_config['params']) ? $panel_config['params'] : array()));
			$page_config[$key]['params'] =
			(!empty($action_result) && is_array($action_result) ? array_merge($panel_config['params'], $action_result) : $panel_config['params']);
		}
	
		$return = array();
		// output panels
		foreach($page_config as $key => $panel_config){
	
			$params = !empty($panel_config['params']) ? $panel_config['params'] : array();
			if (empty($params['cms_page_panel_id'])) $params['cms_page_panel_id'] = 0;
	
			// meta images
			if (!empty($params['_images'])){
				$GLOBALS['_panel_images'] = array_merge(array_values($GLOBALS['_panel_images']), array_values($params['_images']));
			}

			// check for cache
			if (empty($action_result['_no_cache']) && !(!empty($params['module']) && $params['module'] == 'cms')
					&& (!empty($GLOBALS['config']['panel_cache']) || (isset($params['_cache_time']) && $params['_cache_time'] > 0))
					&& empty($GLOBALS['config']['cache']['force_download'])){
	
						$params['module'] = !empty($panel_config['module']) ? $panel_config['module'] : '';
				
						// if cache file exists
						$filename = $GLOBALS['config']['base_path'].'cache/_'.$params['cms_page_panel_id'].'_'.str_replace('/', '__', $panel_config['panel']).
								'_'.substr(md5($panel_config['panel'].serialize($params).$_SESSION['config']['targets']['hash'].
								$_SESSION['webp']), 0, 6).'.txt';
								
						if (is_file($filename)){
	
							// if panel cache time is different from empty, keep it, else use global cache time setting
							if (empty($params['_cache_time'])) {
								$params['_cache_time'] = 0;
							}
							$cache_time = $params['_cache_time'] != 0 ? $params['_cache_time'] : (!empty($GLOBALS['config']['panel_cache']) ? $GLOBALS['config']['panel_cache'] : 0);
	
							if ((time() - filemtime($filename)) < $cache_time){
								 
								$panel_data = unserialize(file_get_contents($filename));
	
								// add js, css, scss to global page files
								$this->js = array_merge($this->js, $panel_data['js']);
								$this->css = array_merge($this->css, $panel_data['css']);
								$this->scss = array_merge($this->scss, $panel_data['scss']);
								 
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
									$_SESSION['config']['targets']['hash'].$_SESSION['webp']), 0, 6).'.txt';
										
							$panel_data['html'] .= '<!-- cached: '.date('Y-m-d H:i:s').' -->'."\n";
							file_put_contents($filename, serialize($panel_data));
							 
				}
	
			}
	
			$return[$panel_config['position'].$key] = $panel_data['html'];
	
			unset($panel_data);
	
		}
		
		
		return $return;
		 
	}
	
	function get_panel_filenames($panel_name, $params = [], $extends = []){
	
		if (!empty($GLOBALS['_panel_files'][$panel_name])){
			return $GLOBALS['_panel_files'][$panel_name];
		}
		 
		$return = [];
	
		if (!empty($extends['panel'])){
			if (!stristr($extends['panel'], '/') && !empty($GLOBALS['config']['errors_visible'])){
				_html_error('Bad panel extension panel name (definition has to be "module/panel", save page panel in CMS after fixing)');
			} else {
				$extends_files = $this->get_panel_filenames($extends['panel']);
				list($return['extends_module'], $return['extends_name']) = explode('/', $extends['panel']);
			}
		}
	
		$module = '';
	
		if (stristr($panel_name, '/')){
	
			// search for controller, when name has slash
			list($module, $name) = explode('/', $panel_name);
			$controller_filename = $GLOBALS['config']['base_path'].'modules/'.$module.'/panels/'.$name.'.php';
			$template_filename = $GLOBALS['config']['base_path'].'modules/'.$module.'/templates/'.$name.'.tpl.php';
	
		} else {
	
			// check if any of modules has panel with this name
			foreach ($GLOBALS['config']['modules'] as $hmodule){
				if (file_exists($GLOBALS['config']['base_path'].'modules/'.$hmodule.'/panels/'.$panel_name.'.php')){
					$controller_filename = $GLOBALS['config']['base_path'].'modules/'.$hmodule.'/panels/'.$panel_name.'.php';
					$module = $hmodule;
				}
				if (file_exists($GLOBALS['config']['base_path'].'modules/'.$hmodule.'/templates/'.$panel_name.'.tpl.php')){
					$template_filename = $GLOBALS['config']['base_path'].'modules/'.$hmodule.'/templates/'.$panel_name.'.tpl.php';
					$module = $hmodule;
				}
			}
			 
		}
	
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
		} else {
			$return['template'] = '';
		}
		 
		$return['module'] = $module;
		$return['name'] = !empty($name) ? $name : $panel_name;
	
		// collect panel related js files
		$return['js'] = [];

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
		if (file_exists($GLOBALS['config']['base_path'].'modules/'.$return['module'].'/css/'.$return['module'].'.scss')) {
			$return['scss'][] = array(
					'script' => 'modules/'.$return['module'].'/css/'.$return['module'].'.scss',
					'top' => 1,
					'related' => array(),
					'css' => 'cache/'.$return['module'].'__'.$return['module'].'.css',
					'module_path' => 'modules/'.$return['module'].'/',
			);
		}
		if (file_exists($GLOBALS['config']['base_path'].'modules/'.$return['module'].'/css/'.$return['name'].'.scss')) {
			$return['scss'][] = array(
					'script' => 'modules/'.$return['module'].'/css/'.$return['name'].'.scss',
					'related' => file_exists($GLOBALS['config']['base_path'].'modules/'.$return['module'].'/css/'.$return['module'].'.scss') ?
					array('modules/'.$return['module'].'/css/'.$return['module'].'.scss', ) : array(),
					'css' => 'cache/'.$return['module'].'__'.$return['name'].'.css',
			);
			$panel_css_exists = true; // scss replaces css here
		}
	
		if (empty($panel_css_exists) && !empty($extends_files['scss']) && empty($extends['join_css'])){
			$return['css'] = array_merge($return['css'], $extends_files['css']);
			$return['scss'] = array_merge($return['scss'], $extends_files['scss']);
		}
	
		// cache this
		$GLOBALS['_panel_files'][$panel_name] = $return;
	
		return $return;
	
	}
	
	function get_panel_view_filename($panel_name){
		$filename = 'views/panels/' . $panel_name . '.tpl.php';
		if (!file_exists(APPPATH . $filename)){
			foreach($GLOBALS['config']['modules'] as $module){
				$filename = $GLOBALS['config']['base_path'].'modules/'.$module.'/templates/'.$panel_name.'.tpl.php';
				if (file_exists($filename)){
	
					return array('full' => $filename, 'relative' => $filename, 'module' => $module, );
	
				}
			}
		} else {
			return array('full' => APPPATH . $filename, 'relative' => '../' . $filename, 'module' => '', );
		}
	}

}
// END Controller class

/* End of file Controller.php */
/* Location: ./system/core/Controller.php */