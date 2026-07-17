<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

// static system config (CI heritage, deprecated)
$config['system']['charset'] = 'UTF-8';
$config['system']['log_path'] = '';
$config['system']['log_date_format'] = 'Y-m-d H:i:s';

require_once($working_directory.'system/helpers/json_helper.php');

/*
 * LOAD CONFIG
 */

$config['config_file'] = $working_directory.'config/'.strtolower($_SERVER['SERVER_NAME']).'.json';

if (file_exists($config['config_file'])){
	
	$json = file_get_contents($config['config_file']);
	$config_file = cms_json_decode($json, $config['config_file']);
	
	if (empty($config_file)){
		die();
	}

	$config = array_merge($config, $config_file);

	if ($config['base_path'] == '_auto_'){
		$config['base_path'] = rtrim(str_replace("\\", "/", trim(getcwd(), " \\")), '/').'/';
	}
	
	if (substr($config['upload_path'], 0, 1) !== '/' && substr($config['upload_path'], 1, 1) !== ':'){
		$config['upload_path'] = $config['base_path'].$config['upload_path'];
	}
	
	if (substr($config['upload_url'], 0, 2) !== '//' && substr($config['upload_path'], 1, 4) !== 'http'){
		$config['upload_url'] = $config['base_url'].$config['upload_url'];
	}
	
} else {
	
	$config['config_file'] = $working_directory.'config/'.strtolower($_SERVER['SERVER_NAME']).'.php';
	if (file_exists($config['config_file'])){

		// if config file for host exists, load config file
		include_once($config['config_file']);
		
		// check if base_path is set correctly
		if (!file_exists($config['base_path'].'config/'.strtolower($_SERVER['SERVER_NAME']).'.php')){
			print('Bad config base path: "'.$config['base_path'].'"');
			die();
		}
	
	} else {
			
		// check if install script is present
		if (file_exists($working_directory.'_install/install.php')){
			include($working_directory.'_install/install.php');
		} else {
			print('No config file for this host found: '.$working_directory.'config/'.strtolower($_SERVER['SERVER_NAME']).'.json or '.$config['config_file']);
		}
		
		die();
	
	}

}

if (empty($GLOBALS['config']['base_host'])){
	$GLOBALS['config']['base_host'] = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443)
			? 'https://' : 'http://' ).$_SERVER['HTTP_HOST'];
}

/*
 * common config for all project environments:
 */

// what css and js to load on all pages
// TODO: to be refactored like css to cms cssjs settings
$config['js'] = array(
		array(
				'script' => 'modules/cms/js/jquery/jquery.min.js',
				'no_pack' => 1,
				'sync' => (!empty($config['jquery_blocks']) ? '' : 'defer'),
		),
		array(
				'script' => 'modules/cms/js/jquery/jquery-ui.min.js',
				// already minified (ES6); re-pack/minify breaks syntax (missing ) after argument list)
				'no_pack' => 1,
				'sync' => 'defer',
		),
		array(
				'script' => 'modules/cms/js/cms_site_main.js',
				'sync' => 'defer',
		),
);

$config['protocol'] = (empty($_SERVER['HTTPS']) OR strtolower($_SERVER['HTTPS']) === 'off') ? 'http' : 'https';

$GLOBALS['config'] = $config;

// connect to db (if mysqli)
if ($GLOBALS['config']['database']['dbdriver'] = 'mysqli') {
	
	$conn_hash = md5($GLOBALS['config']['database']['hostname'].$GLOBALS['config']['database']['username'].
			$GLOBALS['config']['database']['password'].$GLOBALS['config']['database']['database']);
	
	try {
		$GLOBALS['dbconnections'][$conn_hash] = @mysqli_connect($GLOBALS['config']['database']['hostname'],
				$GLOBALS['config']['database']['username'], $GLOBALS['config']['database']['password'], $GLOBALS['config']['database']['database']);
	} catch (Exception $e) {
		print('Can\'t connect database');
		die();
	}
	
}

// load config from db
$db = $GLOBALS['dbconnections'][md5($GLOBALS['config']['database']['hostname'].$GLOBALS['config']['database']['username'].
		$GLOBALS['config']['database']['password'].$config['database']['database'])];

if ($db === false){
	
	// check if install script is present
	if (file_exists($working_directory.'_install/install.php')){
		include($working_directory.'_install/install.php');
	} else {
		_html_error('Can\'t connect database!', 500);
	}
	
	die();

}

$sql = "select b.name, b.value from cms_page_panel a join cms_page_panel_param b on a.cms_page_panel_id = b.cms_page_panel_id ".
		" where a.panel_name = 'cms/cms_settings' and b.name != ''";

try {

	$query = mysqli_query($db, $sql);

} catch (Exception $e) {

	_html_error($e->getMessage(), 500);
	
}

if ($query === false){
	_html_error('Database error: '.$db->error, 500);
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
	// e.g. { "service": "shop_checkout", "panel": "//checkout" } → shopify/checkout
	// Stored as provides[service][panel] so shop settings can list all options
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