<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class export extends MY_Controller{
	
	function recurse_copy($src,$dst) {
		
		$dir = opendir($src);
		@mkdir($dst);
		while(false !== ( $file = readdir($dir)) ) {
			if (( $file != '.' ) && ( $file != '..' )) {
				if ( is_dir($src . '/' . $file) ) {
					recurse_copy($src . '/' . $file,$dst . '/' . $file);
				}
				else {
					copy($src . '/' . $file,$dst . '/' . $file);
				}
			}
		}
		closedir($dir);
	
	}
	
	/**
	 * 
	 * parses fields to acf format
	 * 
	 */
	function parse_fields($fields_data, $panel_name, $append_to = [], $prefix = ''){
	    
	    // load settings
	    $settings = $this->cms_page_panel_model->get_cms_page_panel_settings('acfsync/export');
	    
		foreach($fields_data as $field){
				
			if (!empty($field['name'])){
	
				$target_field_key = 'field_'.str_replace('/', '__', $panel_name).'__'.$field['name'].'_zzz';
					
				if ($field['type'] == 'repeater'){
						
					$append_to[$target_field_key]['key'] = $target_field_key;
					$append_to[$target_field_key]['label'] = $field['label'];
					$append_to[$target_field_key]['name'] = $prefix.$field['name'];
					$append_to[$target_field_key]['type'] = $field['type'];
					$append_to[$target_field_key]['instructions'] = !empty($field['help']) ? $field['help'] : '';
					
					// sub fields recursively
					$append_to[$target_field_key]['sub_fields'] = $this->parse_fields($field['fields'], str_replace('/', '__', $panel_name).'__'.$field['name']);
					
				} else if ($field['type'] == 'text'){
						
					$append_to[$target_field_key]['key'] = $target_field_key;
					$append_to[$target_field_key]['label'] = $field['label'];
					$append_to[$target_field_key]['name'] = $prefix.$field['name'];
					$append_to[$target_field_key]['type'] = $field['type'];
					$append_to[$target_field_key]['instructions'] = !empty($field['help']) ? $field['help'] : '';
					$append_to[$target_field_key]['default_value'] = !empty($field['default']) ? $field['default'] : '';
						
				} elseif ($field['type'] == 'select'){
						
					$append_to[$target_field_key]['key'] = $target_field_key;
					$append_to[$target_field_key]['label'] = $field['label'];
					$append_to[$target_field_key]['name'] = $prefix.$field['name'];
					$append_to[$target_field_key]['type'] = $field['type'];
					$append_to[$target_field_key]['instructions'] = (!empty($field['help']) ? $field['help'] : '');
					$append_to[$target_field_key]['default_value'] = !empty($field['default']) ? $field['default'] : '';
					$append_to[$target_field_key]['choices'] = $field['values'];
						
				} elseif ($field['type'] == 'image'){
						
					$append_to[$target_field_key]['key'] = $target_field_key;
					$append_to[$target_field_key]['label'] = $field['label'];
					$append_to[$target_field_key]['name'] = $prefix.$field['name'];
					$append_to[$target_field_key]['type'] = $field['type'];
					$append_to[$target_field_key]['instructions'] = (!empty($field['help']) ? $field['help'] : '');
					$append_to[$target_field_key]['library'] = 'content';
					$append_to[$target_field_key]['return_format'] = 'array';

				} elseif ($field['type'] == 'textarea'){
				
					if (empty($field['html'])){
					
						$append_to[$target_field_key]['key'] = $target_field_key;
						$append_to[$target_field_key]['label'] = $field['label'];
						$append_to[$target_field_key]['name'] = $prefix.$field['name'];
						$append_to[$target_field_key]['type'] = $field['type'];
						$append_to[$target_field_key]['instructions'] = (!empty($field['help']) ? $field['help'] : '');
						
					} else {
					
						$append_to[$target_field_key]['key'] = $target_field_key;
						$append_to[$target_field_key]['label'] = $field['label'];
						$append_to[$target_field_key]['name'] = $prefix.$field['name'];
						$append_to[$target_field_key]['type'] = 'wysiwyg';
						$append_to[$target_field_key]['instructions'] = (!empty($field['help']) ? $field['help'] : '');
						$append_to[$target_field_key]['toolbar'] = 'full';
						
						if (!stristr($field['html'], 'M')){
							$append_to[$target_field_key]['media_upload'] = 0;
						}
						
					}
				
				} elseif ($field['type'] == 'link'){
				
					$append_to[$target_field_key]['key'] = $target_field_key;
					$append_to[$target_field_key]['label'] = $field['label'];
					$append_to[$target_field_key]['name'] = $prefix.$field['name'];
					$append_to[$target_field_key]['type'] = $field['type'];
					$append_to[$target_field_key]['instructions'] = !empty($field['help']) ? $field['help'] : '';

				} elseif ($field['type'] == 'fk'){
					
					list($module_name, $list_name) = explode('/', $field['list']);
				
					$append_to[$target_field_key]['key'] = $target_field_key;
					$append_to[$target_field_key]['label'] = $field['label'];
					$append_to[$target_field_key]['name'] = $prefix.$field['name'];
					$append_to[$target_field_key]['type'] = 'post_object';
					$append_to[$target_field_key]['instructions'] = !empty($field['help']) ? $field['help'] : '';
					$append_to[$target_field_key]['post_type'] = [$list_name];
					$append_to[$target_field_key]['return_format'] = 'id';
						
				} elseif ($field['type'] == 'file'){
					
					$append_to[$target_field_key]['key'] = $target_field_key;
					$append_to[$target_field_key]['label'] = $field['label'];
					$append_to[$target_field_key]['name'] = $prefix.$field['name'];
					$append_to[$target_field_key]['type'] = $field['type'];
					$append_to[$target_field_key]['instructions'] = !empty($field['help']) ? $field['help'] : '';
						
				} elseif ($field['type'] == 'cms_page_panels'){
					
					// "panels":"articletext,articlecolsimage,articleimage,downloads"
				
					$append_to[$target_field_key]['key'] = $target_field_key;
					$append_to[$target_field_key]['label'] = $field['label'];
					$append_to[$target_field_key]['name'] = $prefix.$field['name'];
					$append_to[$target_field_key]['type'] = 'flexible_content';
					$append_to[$target_field_key]['instructions'] = !empty($field['help']) ? $field['help'] : '';
					$append_to[$target_field_key]['layouts'] = [];
					
					// add new data
					$target_fields = [];
					$page_panels = explode(',', $field['panels']);
					
					foreach($page_panels as $panel_id){
					
					    $field_key = $target_field_key.'_sub_'.$panel_id;
					    
				        $target_fields[$field_key] = [
				            'key' => $field_key,
				            'name' => $panel_id,
				            'label' => ucfirst($panel_id),
				            'display' => 'block',
				        ];
					        
					    // load array from correct file, this is where _zzz is replaced
					    
					    $groupname_sub = 'group_panel_eg__'.$panel_id;
					    $filename_sub = $GLOBALS['config']['base_path'].$settings['target_folder'].'acf-json/'.$groupname_sub.'.json';
					    
					    $subfield_data = json_decode(str_replace('_zzz', '_'.$field_key, file_get_contents($filename_sub)), true);
					    
					    // print_r($subfield_data);
					    $target_fields[$field_key]['sub_fields'] = $subfield_data['fields'];

					}
					
					$append_to[$target_field_key]['layouts'] = $target_fields;
					/*
					
					        {
            "key": "field_5ae9c3e19123f",
            "label": "here come article panels",
            "name": "panels",
            "type": "flexible_content",
            "instructions": "",
            "required": 0,
            "conditional_logic": 0,
            "wrapper": {
                "width": "",
                "class": "",
                "id": ""
            },
            "layouts": {
                "5ae9c3fe2b43a": {
                    "key": "5ae9c3fe2b43a",
                    "name": "subpanel_one",
                    "label": "subpanel one label",
                    "display": "block",
                    "sub_fields": [
                        {
                            "key": "field_5ae9c42991240",
                            "label": "testfield1",
                            "name": "testfield1",
                            "type": "text",
                            "instructions": "",
                            "required": 0,
                            "conditional_logic": 0,
                            "wrapper": {
                                "width": "",
                                "class": "",
                                "id": ""
                            },
                            "default_value": "",
                            "placeholder": "",
                            "prepend": "",
                            "append": "",
                            "maxlength": ""
                        },
                        {
                            "key": "field_5ae9c47991242",
                            "label": "testfield2",
                            "name": "testfield2",
                            "type": "text",
                            "instructions": "",
                            "required": 0,
                            "conditional_logic": 0,
                            "wrapper": {
                                "width": "",
                                "class": "",
                                "id": ""
                            },
                            "default_value": "",
                            "placeholder": "",
                            "prepend": "",
                            "append": "",
                            "maxlength": ""
                        }
                    ],
                    "min": "",
                    "max": ""
                }
            },
            "button_label": "Add Row",
            "min": "",
            "max": ""
        }
					
					*/
					
				}
	
			}
				
		}
			
		return $append_to;
			
	}
	
	function panel_action($params){

		$do = $this->input->post('do');
        if ($do == 'export'){

			$this->load->model('cms/cms_page_panel_model');
			$this->load->model('cms/cms_panel_model');
			$this->load->model('cms/cms_page_model');

			// load settings
			$settings = $this->cms_page_panel_model->get_cms_page_panel_settings('acfsync/export');
			
			// check folders
			if (!file_exists($GLOBALS['config']['base_path'].$settings['target_folder']) || !is_dir($GLOBALS['config']['base_path'].$settings['target_folder'])){
				print('bad folder');
				die();
			}
			
			// go over lists
			$list_target_folder = $GLOBALS['config']['base_path'].$settings['target_folder'].'panels/cpts/';
			if (!file_exists($list_target_folder)){
				mkdir($list_target_folder);
			}
				
			$lists_all = $this->cms_page_panel_model->get_lists();
			$lists = [];
			$lists_to_export = [];
				
			foreach($lists_all as $list){
					
				list($module_name, $list_name) = explode('/', $list);
			
				if ($module_name == $settings['main_module']) {
						
					$list_definition = $this->cms_panel_model->get_cms_panel_config($list);
						
					$lists[] = [
							'name' => $list_name,
							'singular' => $list_definition['list']['item_title'],
							'plural' => $list_definition['list']['list_title'],
					];

					// add list to panels to be exported
					$lists_to_export[$list] = $list;
						
				}
					
			}
				
			$list_template = file_get_contents(__DIR__.'/../templates/export_list.tpl.txt');
			
			foreach ($lists as $list){
				
				$list_code = str_replace(['#singular#', '#plural#', '#name#', ], [$list['singular'], $list['plural'], $list['name'], ], $list_template);
				file_put_contents($list_target_folder.$list['name'].'.php', $list_code);
				
			}
			
			foreach($lists_to_export as $list_panel_name){
				
				$found = false;
				foreach($settings['panels'] as $panel){
					if ($panel['panel'] == $list_panel_name){
						$found = true;
					}
				}
				
				if (!$found){
					$settings['panels'][] = ['panel' => $list_panel_name];
				}
				
			}
			
			// add header and footer panels
			$headers_to_export = [];
			foreach($settings['header_panels'] as $hpanel){
				$settings['panels'][] = $hpanel;
				$headers_to_export[] = $hpanel['panel'];
			}
			$footers_to_export = [];
			foreach($settings['footer_panels'] as $fpanel){
				$settings['panels'][] = $fpanel;
				$footers_to_export[] = $fpanel['panel'];
			}
				
			// target folder for acf json
			
			if (!file_exists($GLOBALS['config']['base_path'].$settings['target_folder'].'acf-json/')){
				mkdir($GLOBALS['config']['base_path'].$settings['target_folder'].'acf-json/');
			}
			
			$exported_panels = [];
			
			// go over panels
			foreach($settings['panels'] as $key => $panel){
				
				list($module_name, $panel_name) = explode('/', $panel['panel']);
				
				$exported_panels[] = $panel['panel'];
				
				$groupname = 'group_panel_'.str_replace('/', '__', $panel['panel']);
				$filename = $GLOBALS['config']['base_path'].$settings['target_folder'].'acf-json/'.$groupname.'.json';
				
				$target_data = [];
				
				if (file_exists($filename)){
					
					$target_data = json_decode(file_get_contents($filename), true);
				
				}
				
				// to debug
//				$settings['panels'][$key]['target_data'] = $target_data;
				
				$target_data['key'] = $groupname;
				$target_data['title'] = 'Panel '.str_replace('/', ' ', $panel['panel']);
				$target_data['location'] = [];
				$target_data['active'] = 1;
				$target_data['modified'] = time();
				$target_data['fields'] = [];
				
				// load panel definition
				$panel_config = $this->cms_panel_model->get_cms_panel_config($panel['panel']);
				
				// add templates
				if (!empty($panel_config['list']['link_target'])){

					array_unshift($panel_config['item'], [
							'type' => 'select',
							'name' => '_template',
							'label' => 'Post page template',
							'values' => !empty($panel_config['list']['templates']) ? $panel_config['list']['templates'] : [$panel_name => $panel_name, ],
							'default' => $panel_name,
					]);

				}
				
				$target_fields = [];
				
				// make existing field data to key'ed array
				if (!empty($target_data['fields'])){
					foreach($target_data['fields'] as $target_field){
						$target_fields[$target_field['key']] = $target_field;
					}
				}

				// add new data
				if (!in_array($panel['panel'], $headers_to_export) && !in_array($panel['panel'], $footers_to_export)){
					$target_fields = $this->parse_fields($panel_config['item'], $panel['panel'], $target_fields);
				} else {
					// if goes to settings, then prefix with panel name as otherwise they clash
					$target_fields = $this->parse_fields($panel_config['item'], $panel['panel'], [], $panel_name.'__');
				}
				
				$target_data['fields'] = array_values($target_fields);
					
				// add list panel fields to custom post type 
				if (in_array($panel['panel'], $lists_to_export)){
					$target_data['location'] = [[[
							'param' => 'post_type',
							'operator' => '==',
							'value' => $panel_name,
					]]];
				}
								
				// header panels
				if (in_array($panel['panel'], $headers_to_export)){
					$target_data['location'] = [[[
							'param' => 'options_page',
							'operator' => '==',
							'value' => 'header-settings',
					]]];
				}
				
				// footer panels
				if (in_array($panel['panel'], $footers_to_export)){
					$target_data['location'] = [[[
							'param' => 'options_page',
							'operator' => '==',
							'value' => 'footer-settings',
					]]];
				}
				
				$json_string = json_encode($target_data, JSON_PRETTY_PRINT);
				
				if (in_array($panel['panel'], $headers_to_export)){
					$json_string = str_replace('_zzz', '_zhz', $json_string);
				}
				if (in_array($panel['panel'], $footers_to_export)){
					$json_string = str_replace('_zzz', '_zfz', $json_string);
				}

				if($this->should_output_file($json_string, $filename)){
				    file_put_contents($filename, $json_string);
				}

				// export settings too
				if (!empty($panel_config['settings'])){
					
					$target_data = [];
					
					// to debug
					$settings['panels'][$key]['settings_data'] = $target_data;
					
					$target_data['key'] = $groupname.'_settings';
					
					$target_data['title'] = 'Panel settings: '.str_replace('/', ' ', $panel['panel']);
					$target_data['location'] = [[[
								'param' => 'options_page',
								'operator' => '==',
								'value' => 'panel-settings',
							]]];
					
					$target_data['active'] = 1;
					$target_data['modified'] = time();
					
					$target_data['fields'] = $this->parse_fields($panel_config['settings'], $panel['panel'], [], $panel_name.'__');
					
					$filename = $GLOBALS['config']['base_path'].$settings['target_folder'].'acf-json/'.$groupname.'_settings.json';

					$json_string = json_encode($target_data, JSON_PRETTY_PRINT);

					if($this->should_output_file($json_string, $filename)){
					    file_put_contents($filename, $json_string);
					}

				}

				// move template
				$templates_target_folder = $GLOBALS['config']['base_path'].$settings['target_folder'].'panels/templates/';
				
				if (!file_exists($GLOBALS['config']['base_path'].$settings['target_folder'].'panels/')){
					mkdir($GLOBALS['config']['base_path'].$settings['target_folder'].'panels/');
				}
				
				if (!file_exists($GLOBALS['config']['base_path'].$settings['target_folder'].'panels/templates/')){
					mkdir($GLOBALS['config']['base_path'].$settings['target_folder'].'panels/templates/');
				}
								
				if (file_exists($GLOBALS['config']['base_path'].'modules/'.$module_name.'/templates/'.$panel_name.'.tpl.php') && (!file_exists($templates_target_folder.$panel_name.'.tpl.php') || 
						filemtime($templates_target_folder.$panel_name.'.tpl.php') < filemtime($GLOBALS['config']['base_path'].'modules/'.$module_name.'/templates/'.$panel_name.'.tpl.php'))){
					
					copy($GLOBALS['config']['base_path'].'modules/'.$module_name.'/templates/'.$panel_name.'.tpl.php', $templates_target_folder.$panel_name.'.tpl.php');
							
				}
				
				// css
				$css_target_folder = $GLOBALS['config']['base_path'].$settings['target_folder'].'panels/css/';
				
				if (!file_exists($GLOBALS['config']['base_path'].$settings['target_folder'].'panels/css/')){
					mkdir($GLOBALS['config']['base_path'].$settings['target_folder'].'panels/css/');
				}
				
				if (file_exists($GLOBALS['config']['base_path'].'cache/'.$module_name.'__'.$panel_name.'.css') && (!file_exists($css_target_folder.$panel_name.'.css') || 
						filemtime($css_target_folder.$panel_name.'.css') < filemtime($GLOBALS['config']['base_path'].'cache/'.$module_name.'__'.$panel_name.'.css'))){
					
					copy($GLOBALS['config']['base_path'].'cache/'.$module_name.'__'.$panel_name.'.css', $css_target_folder.$panel_name.'.css');
							
				}
				
				// js
				$js_target_folder = $GLOBALS['config']['base_path'].$settings['target_folder'].'panels/js/';
				
				if (!file_exists($GLOBALS['config']['base_path'].$settings['target_folder'].'panels/js/')){
					mkdir($GLOBALS['config']['base_path'].$settings['target_folder'].'panels/js/');
				}
				
				if (file_exists($GLOBALS['config']['base_path'].'modules/'.$module_name.'/js/'.$panel_name.'.js') && (!file_exists($css_target_folder.$panel_name.'.js') || 
						filemtime($css_target_folder.$panel_name.'.js') < filemtime($GLOBALS['config']['base_path'].'modules/'.$module_name.'/js/'.$panel_name.'.js'))){
								
							copy($GLOBALS['config']['base_path'].'modules/'.$module_name.'/js/'.$panel_name.'.js', $js_target_folder.$panel_name.'.js');
								
				}
				
				// panel controller
				$panel_target_folder = $GLOBALS['config']['base_path'].$settings['target_folder'].'panels/panels/';
				
				if (!file_exists($GLOBALS['config']['base_path'].$settings['target_folder'].'panels/panels/')){
					mkdir($GLOBALS['config']['base_path'].$settings['target_folder'].'panels/panels/');
				}
				
				if (file_exists($GLOBALS['config']['base_path'].'modules/'.$module_name.'/panels/'.$panel_name.'.php') && (!file_exists($panel_target_folder.$panel_name.'.php') ||
						filemtime($panel_target_folder.$panel_name.'.php') < filemtime($GLOBALS['config']['base_path'].'modules/'.$module_name.'/panels/'.$panel_name.'.php'))){
				
							copy($GLOBALS['config']['base_path'].'modules/'.$module_name.'/panels/'.$panel_name.'.php', $panel_target_folder.$panel_name.'.php');
				
				}
				
			}
			
			// base css
			if (!file_exists($css_target_folder.$module_name.'.css') ||
					filemtime($css_target_folder.$module_name.'.css') < filemtime($GLOBALS['config']['base_path'].'cache/'.$module_name.'__'.$module_name.'.css')){
				
				$base_css = file_get_contents($GLOBALS['config']['base_path'].'cache/'.$module_name.'__'.$module_name.'.css');
				
				$base_css = str_replace('../modules/'.$module_name, '/panels', $base_css);

				file_put_contents($css_target_folder.'base.css', $base_css);

			}
			
			// copy over fonts etc
			$dirs = glob($GLOBALS['config']['base_path'].'modules/'.$module_name.'/css/*', GLOB_ONLYDIR);
			foreach($dirs as $dir){
				$this->recurse_copy($dir, $css_target_folder.basename($dir));
			}
			
			// go over pages
			foreach($settings['pages'] as $key => $page){
			
				$target_data = [];
				
				$page_data = $this->cms_page_model->get_page($page['page']);
				
				$groupname = 'group_page_'.(!empty($page_data['slug']) ? $page_data['slug'] : 'homepage');
				
				$filename = $GLOBALS['config']['base_path'].$settings['target_folder'].'acf-json/'.$groupname.'.json';
				
				if (file_exists($filename)){
						
					$target_data = json_decode(file_get_contents($filename), true);
				
				}
				
				// to debug
				$settings['panels'][$key]['target_data'] = $target_data;
				
				$target_data['key'] = $groupname;
				$target_data['title'] = 'Page '.(!empty($page_data['slug']) ? $page_data['slug'] : 'homepage');
				$target_data['active'] = 1;
				$target_data['modified'] = time();
				
				if (empty($target_data['hide_on_screen']) || !in_array('the_content', $target_data['hide_on_screen'])){
					$target_data['hide_on_screen'][] = 'the_content';
				}

				$target_data['fields'] = [
					[
						'key' => 'field_flexible_'.(!empty($page_data['slug']) ? $page_data['slug'] : 'homepage'),
						'label' => ucfirst((!empty($page_data['slug']) ? $page_data['slug'] : 'homepage').' modules'),
						'name' => (!empty($page_data['slug']) ? $page_data['slug'] : 'homepage').'_modules',
						'type' => 'flexible_content',
						'layouts' => [],
					]
				];
				
				// add new data
				$target_fields = [];
				$page_panels = $this->cms_page_panel_model->get_cms_page_panels_by(['cms_page_id' => $page['page'], ]);
				
				// replace shortcuts with the real panel data
				foreach($page_panels as $key => $panel){
					if (is_numeric($panel['panel_name']) && ((int)$panel['panel_name'] == $panel['panel_name'])){
						
						$page_panels[$key] = $this->cms_page_panel_model->get_cms_page_panel($panel['panel_name']);
						
					}
				}
				
				$already_on_page = [];
				foreach($page_panels as $panel){

					// add to page group
					if (in_array($panel['panel_name'], $exported_panels) && !in_array($panel['panel_name'], $already_on_page) && !in_array($panel['panel_name'], $headers_to_export) && !in_array($panel['panel_name'], $footers_to_export)){
						
						$already_on_page[] = $panel['panel_name'];
						
						$field_key = $groupname.'_field_'.str_replace('/', '__', $panel['panel_name']);
						
						list($panel_module, $panel_name) = explode('/', $panel['panel_name']);
						
						$target_fields[$field_key] = [
								'key' => $field_key,
								'name' => $panel_name,
								'label' => ucfirst($panel_name),
								'display' => 'block',
						];
						
						// load array from correct file, this is where _zzz is replaced
						
						$groupname_sub = 'group_panel_'.str_replace('/', '__', $panel['panel_name']);
						$filename_sub = $GLOBALS['config']['base_path'].$settings['target_folder'].'acf-json/'.$groupname_sub.'.json';
						
						$subfield_data = json_decode(str_replace('_zzz', '_'.(!empty($page_data['slug']) ? $page_data['slug'] : 'homepage'), file_get_contents($filename_sub)), true);
// print_r($subfield_data);
						$target_fields[$field_key]['sub_fields'] = $subfield_data['fields'];
						
					}
					
				}
				
				$target_data['fields'][0]['layouts'] = $target_fields;
// print_r($target_data);

				if($this->should_output_file($target_data, $filename)){
					file_put_contents($filename, json_encode($target_data, JSON_PRETTY_PRINT));
				}

			}

			print(json_encode(['result' => 'ok', 'settings' => $settings, ]));

			die();

        }

		return $params;

	}

	/**
	 * Check if we should update the file contents
	 *
	 * @param $data
	 * @param string $filename
	 *
	 * @return bool
	 */
	function should_output_file($data, $filename){
	    
	    if ( ! file_exists($filename)){
	        return true;
	    }
	    
	    if ( !is_array($data) && strpos($data, '{') !== false ){
	        $data = json_decode($data, true);
	    }
	    
	    $data_to_compare = $data;
	    
	    $raw_data = file_get_contents($filename);
	    $existing_data = !empty($raw_data) ? json_decode($raw_data, true) : '';
	    
	    if( empty($existing_data) ){
	        return true;
	    }
	    
	    unset($data_to_compare['modified'], $existing_data['modified']);
	    
	    if (serialize($data_to_compare) === serialize($existing_data) ){
	        return false;
	    }
	    
	    return true;
	    
	}
	
}
