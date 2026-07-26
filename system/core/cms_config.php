<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Full site config: DB, cms_settings, modules, extends, provides.
 * Call cms_config_load_full() after API miss, or from APIs that need DB/Controller.
 */

/**
 * Idempotent full config load (DB + modules). Safe from API entrypoints.
 */
function cms_config_load_full(){

	if (!empty($GLOBALS['cms_config_full'])){
		return;
	}

	// Ensure basic host config first
	if (empty($GLOBALS['config']['base_path'])){
		if (empty($working_directory)){
			$working_directory = str_replace('\\', '/', trim(getcwd()).'/');
		}
		require_once $working_directory.'system/core/cms_config_basic.php';
	}

	$working_directory = $GLOBALS['config']['base_path'];

	// Front default JS packs (page requests)
	if (empty($GLOBALS['config']['js']) || !is_array($GLOBALS['config']['js'])){
		$GLOBALS['config']['js'] = array(
			array(
				'script' => 'modules/cms/js/jquery/jquery.min.js',
				'no_pack' => 1,
				'sync' => (!empty($GLOBALS['config']['jquery_blocks']) ? '' : 'defer'),
			),
			array(
				'script' => 'modules/cms/js/jquery/jquery-ui.min.js',
				'no_pack' => 1,
				'sync' => 'defer',
			),
			array(
				'script' => 'modules/cms/js/cms_site_main.js',
				'sync' => 'defer',
			),
		);
	}

	// One CMS DB per request. Consumers: $GLOBALS['db'] / global $db.
	// Modules needing another DB open their own connection — do not reintroduce multi-conn here.
	try {
		$GLOBALS['db'] = @mysqli_connect(
				$GLOBALS['config']['database']['hostname'],
				$GLOBALS['config']['database']['username'],
				$GLOBALS['config']['database']['password'],
				$GLOBALS['config']['database']['database']
				);
	} catch (Exception $e) {
		print('Can\'t connect database');
		die();
	}

	// Local alias for settings queries in this function; public handle is $GLOBALS['db']
	$db = $GLOBALS['db'] ?? false;

	if ($db === false){

		if (file_exists($working_directory.'_install/install.php')){
			include($working_directory.'_install/install.php');
		} else if (function_exists('_html_error')){
			_html_error('Can\'t connect database!', 500);
		} else {
			print('Can\'t connect database!');
		}

		die();

	}

	$sql = "select b.name, b.value from cms_page_panel a join cms_page_panel_param b on a.cms_page_panel_id = b.cms_page_panel_id ".
			" where a.panel_name = 'cms/cms_settings' and b.name != ''";

	try {

		$query = mysqli_query($db, $sql);

	} catch (Exception $e) {

		if (function_exists('_html_error')){
			_html_error($e->getMessage(), 500);
		}
		print($e->getMessage());
		die();

	}

	if ($query === false){
		if (function_exists('_html_error')){
			_html_error('Database error: '.$db->error, 500);
		}
		print('Database error: '.$db->error);
		die();
	}

	while($result = mysqli_fetch_assoc($query)){

		if (!stristr($result['name'], '.')){
			$GLOBALS['config'][$result['name']] = $result['value'];
		} else {
			list($ra, $rb) = explode('.', $result['name']);
			$GLOBALS['config'][$ra][$rb] = $result['value'];
		}

	}

	// Track whether single_page_mode was stored in CMS settings (before definition defaults)
	$_single_page_mode_from_db = array_key_exists('single_page_mode', $GLOBALS['config']);

	$_settings_def_file = $working_directory.'modules/cms/definitions/cms_settings.json';
	if (file_exists($_settings_def_file)) {
		$_settings_def = json_decode(file_get_contents($_settings_def_file), true);
		if (is_array($_settings_def) && !empty($_settings_def['settings'])) {
			foreach ($_settings_def['settings'] as $_field) {
				$_name = $_field['name'] ?? '';
				if (!$_name) {
					continue;
				}
				if (($_field['type'] ?? '') === 'modules') {
					if (empty($GLOBALS['config']['modules']) || !is_array($GLOBALS['config']['modules']) || count($GLOBALS['config']['modules']) <= 1) {
						$_mods = ['cms'];
						foreach (glob($working_directory.'modules/*', GLOB_ONLYDIR) as $_dir) {
							$_mod = basename($_dir);
							if ($_mod !== 'cms') {
								$_mods[] = $_mod;
							}
						}
						$GLOBALS['config']['modules'] = $_mods;
					}
					continue;
				}
				if (!isset($GLOBALS['config'][$_name]) && array_key_exists('default', $_field)) {
					$GLOBALS['config'][$_name] = $_field['default'];
				}
			}
		}
	}

	// TODO: remove legacy position_wrappers / position_links → single_page_mode translation once all envs migrated
	if (!$_single_page_mode_from_db) {
		if (!empty($GLOBALS['config']['position_wrappers']) && !empty($GLOBALS['config']['position_links'])) {
			$GLOBALS['config']['single_page_mode'] = '1';
		} else {
			$GLOBALS['config']['single_page_mode'] = '0';
		}
	}
	unset($_single_page_mode_from_db);

	// check if email exists
	if (empty($GLOBALS['config']['email'])){
		$GLOBALS['config']['email'] = $_SERVER['SERVER_NAME'].'@narrativecms.com';
		$GLOBALS['config']['from_name'] = $_SERVER['SERVER_NAME'];
	}

	if (empty($GLOBALS['config']['reply_email'])){
		$GLOBALS['config']['reply_email'] = $GLOBALS['config']['email'];
		$GLOBALS['config']['reply_name'] = $GLOBALS['config']['from_name'] ?? '';
	}

	// load module configs
	if (empty($GLOBALS['config']['modules']) || !is_array($GLOBALS['config']['modules'])){
		$GLOBALS['config']['modules'] = ['cms'];
	}

	array_unshift($GLOBALS['config']['modules'], 'cms');

	$GLOBALS['config']['modules'] = array_values(array_unique($GLOBALS['config']['modules']));

	$GLOBALS['config']['extends'] = [];
	$GLOBALS['config']['provides'] = [];

	foreach($GLOBALS['config']['modules'] as $module_name){

		$filename = $GLOBALS['config']['base_path'].'modules/'.$module_name.'/config.json';
		if (file_exists($filename)){
			$GLOBALS['config']['module'][$module_name] = json_decode(file_get_contents($filename), true);
			if (json_last_error() !== JSON_ERROR_NONE) {
				print('Module config bad json: '.$filename);
				die();
			}
		} else {
			$GLOBALS['config']['module'][$module_name] = [];
		}

		if (empty($GLOBALS['config']['module'][$module_name]['panels'])){
			$GLOBALS['config']['module'][$module_name]['panels'] = [];
		}

		if (!empty($GLOBALS['config']['module'][$module_name]['extends'])){
			foreach($GLOBALS['config']['module'][$module_name]['extends'] as $item){
				if (stristr($item['source'], '//')){
					$item['source'] = str_replace('//', $module_name.'/', $item['source']);
				}
				$GLOBALS['config']['extends'][] = $item;
			}
		}

		// Capability registry: module offers a named service implemented by a panel
		if (!empty($GLOBALS['config']['module'][$module_name]['provides']) && is_array($GLOBALS['config']['module'][$module_name]['provides'])){
			foreach($GLOBALS['config']['module'][$module_name]['provides'] as $item){
				if (empty($item['service']) || empty($item['panel'])){
					continue;
				}
				$panel = $item['panel'];
				if (strpos($panel, '//') === 0){
					$panel = $module_name.'/'.substr($panel, 2);
				} else if (strpos($panel, '/') === false){
					$panel = $module_name.'/'.$panel;
				}
				$service = $item['service'];
				if (!isset($GLOBALS['config']['provides'][$service]) || !is_array($GLOBALS['config']['provides'][$service])){
					$GLOBALS['config']['provides'][$service] = [];
				}
				$GLOBALS['config']['provides'][$service][$panel] = [
						'panel' => $panel,
						'module' => $module_name,
						'service' => $service,
						'label' => !empty($item['label']) ? $item['label'] : $panel,
				];
			}
		}

	}

	$GLOBALS['cms_config_full'] = true;

}

// Loading this file loads full config (APIs / front path after basic)
cms_config_load_full();
