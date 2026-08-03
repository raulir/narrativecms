<?php

/**
 * Narrative CMS clean install wizard
 *
 * Place at web root as _install/install.php (recommended) or install.php.
 * When run from _install/, CMS files and config install to the parent (site root).
 * Access with a browser; downloads core from master updater, creates schema + seed.
 */

$update_url = 'https://update.narrativecms.com/';
// $update_url = 'http://cms.localhost/';

// ---------------------------------------------------------------------------
// Paths: install into site root, never into _install/
// ---------------------------------------------------------------------------

function install_script_dir(){

	return str_replace('\\', '/', rtrim(dirname(__FILE__), " /\\")).'/';

}

/**
 * Site root for files, config, cache, img.
 * If this script lives in …/_install/, root is the parent directory.
 */
function install_root_dir(){

	$script_dir = install_script_dir();
	$base = basename(rtrim($script_dir, '/'));
	if ($base === '_install'){
		return str_replace('\\', '/', rtrim(dirname(rtrim($script_dir, '/')), " /\\")).'/';
	}
	return $script_dir;

}

function install_master_url(){

	global $update_url;
	return rtrim($update_url, '/').'/cms/updater/';

}

/**
 * Relative paths still missing or not matching release hash/size.
 *
 * @param string $root
 * @param array $by_path filename => manifest row
 * @return string[]
 */
function install_files_needed($root, $by_path){

	$needed = [];
	foreach ($by_path as $rel => $file){
		$local = $root.$rel;
		if (!file_exists($local)){
			$needed[] = $rel;
			continue;
		}
		if (!empty($file['hash']) && @md5_file($local) !== $file['hash']){
			$needed[] = $rel;
			continue;
		}
		if (isset($file['size']) && (int)@filesize($local) !== (int)$file['size']){
			$needed[] = $rel;
		}
	}
	return $needed;

}

/**
 * Write one base64 file; verify hash/size when known.
 * Empty string base64 is valid (0-byte files in the release).
 * @return string error or ''
 */
function install_write_file($root, $rel, $b64, $meta = []){

	if ($rel === '' || strpos($rel, '..') !== false){
		return 'bad path';
	}
	// 0-byte release files arrive as "" (empty base64) — not invalid
	if ($b64 === '' || $b64 === null){
		$bin = '';
	} else {
		$bin = base64_decode((string)$b64, true);
		if ($bin === false){
			return 'invalid base64';
		}
	}
	$pathinfo = pathinfo($root.$rel);
	if (!empty($pathinfo['dirname']) && !is_dir($pathinfo['dirname'])){
		if (!@mkdir($pathinfo['dirname'], 0777, true) && !is_dir($pathinfo['dirname'])){
			return 'mkdir failed: '.$pathinfo['dirname'];
		}
	}
	// file_put_contents returns 0 for empty write — only false is failure
	if (@file_put_contents($root.$rel, $bin) === false){
		return 'write failed';
	}
	if (!empty($meta['hash']) && @md5_file($root.$rel) !== $meta['hash']){
		return 'hash mismatch after write';
	}
	if (isset($meta['size']) && (int)@filesize($root.$rel) !== (int)$meta['size']){
		return 'size mismatch after write';
	}
	return '';

}

/**
 * POST to master updater API; returns decoded array or null.
 */
function install_master_post($fields){

	$postdata = http_build_query($fields);
	$context = stream_context_create([
			'http' => [
					'method' => 'POST',
					'header' => "Content-type: application/x-www-form-urlencoded\r\n",
					'content' => $postdata,
					'timeout' => 60,
			],
	]);
	$raw = @file_get_contents(install_master_url(), false, $context);
	if ($raw === false || $raw === ''){
		return null;
	}
	$data = json_decode($raw, true);
	return is_array($data) ? $data : null;

}

function install_json_ok($extra = []){

	print(json_encode(array_merge(['ok' => 1], $extra)));
	die();

}

function install_json_fail($error = '', $extra = []){

	print(json_encode(array_merge(['ok' => 0, 'error' => $error], $extra)));
	die();

}

// ---------------------------------------------------------------------------
// AJAX actions
// ---------------------------------------------------------------------------

if (!empty($_POST['do'])){

	$do = $_POST['do'];
	$root = install_root_dir();

	// --- preflight ---

	if ($do == 'is_writable'){

		if (is_writable($root) || (!file_exists($root) && is_writable(dirname(rtrim($root, '/'))))){
			install_json_ok();
		}
		install_json_fail('Root not writable: '.$root);

	}

	if ($do == 'is_db_accessible'){

		$link = @mysqli_connect($_POST['db_host'], $_POST['db_admin_user'], $_POST['db_admin_pass']);
		if ($link){
			mysqli_close($link);
			install_json_ok();
		}
		install_json_fail('Cannot connect as DB admin');

	}

	if ($do == 'is_db_name_available'){

		$link = @mysqli_connect($_POST['db_host'], $_POST['db_admin_user'], $_POST['db_admin_pass']);
		if (!$link){
			install_json_fail('Cannot connect as DB admin');
		}
		$result = $link->query('show databases');
		$data = [];
		while ($row = $result->fetch_assoc()){
			$data[] = $row['Database'];
		}
		mysqli_close($link);
		if (!in_array($_POST['db_db'], $data, true)){
			install_json_ok();
		}
		install_json_fail('Database name already exists');

	}

	if ($do == 'is_db_user_available'){

		$link = @mysqli_connect($_POST['db_host'], $_POST['db_admin_user'], $_POST['db_admin_pass']);
		if (!$link){
			install_json_fail('Cannot connect as DB admin');
		}
		// MySQL 8 may restrict mysql.user — treat query failure as skip (soft ok)
		$result = @$link->query("select User from mysql.user");
		if (!$result){
			mysqli_close($link);
			install_json_ok(); // cannot list users; allow continue
		}
		$data = [];
		while ($row = $result->fetch_assoc()){
			$data[] = $row['User'];
		}
		mysqli_close($link);
		if (!in_array($_POST['db_user'], $data, true)){
			install_json_ok();
		}
		install_json_fail('DB user already exists');

	}

	if ($do == 'is_internet'){

		$master = install_master_post(['do' => 'version', 'module' => '', 'area' => '']);
		if ($master === null){
			install_json_fail('Cannot reach master updater');
		}
		// Released package has version/hash; unreleased returns error key
		if (!empty($master['error']) && empty($master['version'])){
			install_json_fail((string)$master['error']);
		}
		install_json_ok(['version' => $master['version'] ?? '']);

	}

	// --- files (core package from release snapshot) ---

	if ($do == 'install_files'){

		$starttime = time();
		// Last few files: more time + always single-file if batch fails
		$time_budget = 12;
		$batch_size = 20;

		$master_data = install_master_post(['do' => 'files', 'module' => '', 'area' => '']);
		if ($master_data === null){
			install_json_fail('Cannot fetch file list from master');
		}
		if (!empty($master_data['error']) && empty($master_data['files'])){
			install_json_fail((string)$master_data['error']);
		}

		$files = !empty($master_data['files']) && is_array($master_data['files']) ? $master_data['files'] : [];
		// Index by path for hash checks after write
		$by_path = [];
		foreach ($files as $file){
			$rel = $file['filename'] ?? '';
			if ($rel !== '' && strpos($rel, '..') === false){
				$by_path[$rel] = $file;
			}
		}
		$master_length = count($by_path);
		if ($master_length < 1){
			install_json_fail('No release files on master — use Release on master first');
		}

		if (!file_exists($root.'cache/')){
			mkdir($root.'cache/', 0777, true);
		}
		if (!file_exists($root.'img/')){
			mkdir($root.'img/', 0777, true);
		}

		$progress_file = $root.'cache/install.txt';
		$stall_file = $root.'cache/install_stall.json';

		$needed = install_files_needed($root, $by_path);
		$done = $master_length - count($needed);
		file_put_contents($progress_file, $done.'/'.$master_length);

		// All present and matching
		if ($needed === []){
			@unlink($stall_file);
			file_put_contents($progress_file, 'done');
			install_json_ok();
		}

		// Few remaining → longer budget, prefer single-file
		if (count($needed) <= 5){
			$time_budget = 25;
			$batch_size = 1;
		}

		$errors = [];
		$written = 0;

		// One pass over needed within time budget (no infinite re-queue spin)
		$queue = $needed;
		while ($queue !== [] && (time() - $starttime) < $time_budget){

			$chunk = array_splice($queue, 0, $batch_size);
			$got = [];

			if (count($chunk) > 1){
				$batch = install_master_post([
						'do' => 'file',
						'module' => '',
						'area' => '',
						'filenames' => array_values($chunk),
				]);
				// Note: empty-file base64 is "" — valid (0-byte release files); use array_key_exists
				if (is_array($batch) && isset($batch['files']) && is_array($batch['files'])){
					foreach ($chunk as $rel){
						if (array_key_exists($rel, $batch['files']) && $batch['files'][$rel] !== null){
							$got[$rel] = (string)$batch['files'][$rel];
						} else if (!empty($batch['errors'][$rel])){
							$errors[$rel] = (string)$batch['errors'][$rel];
						}
					}
				}
			}

			// Single-file for anything batch missed (or batch_size 1)
			foreach ($chunk as $rel){
				if (array_key_exists($rel, $got)){
					continue;
				}
				if ((time() - $starttime) >= $time_budget){
					// leave for next HTTP request — not an error yet
					continue;
				}
				$one = install_master_post([
						'do' => 'file',
						'module' => '',
						'area' => '',
						'filename' => $rel,
				]);
				// Legacy {file: base64} — "" is valid for 0-byte files (e.g. empty .tpl.php)
				if (is_array($one) && array_key_exists('file', $one) && $one['file'] !== null
						&& (empty($one['error']) || (string)$one['file'] !== '')){
					$got[$rel] = (string)$one['file'];
					unset($errors[$rel]);
				} else if (is_array($one) && isset($one['files'][$rel])){
					// Batch-shaped reply to single-file request
					$got[$rel] = (string)$one['files'][$rel];
					unset($errors[$rel]);
				} else {
					$errors[$rel] = is_array($one)
							? (string)($one['error'] ?? ($one['errors'][$rel] ?? 'empty response from master'))
							: 'empty response from master';
				}
			}

			foreach ($got as $rel => $b64){
				$write_err = install_write_file($root, $rel, $b64, $by_path[$rel] ?? []);
				if ($write_err !== ''){
					$errors[$rel] = $write_err;
					continue;
				}
				$written++;
				unset($errors[$rel]);
			}

			// Progress from disk (authoritative)
			$still = install_files_needed($root, $by_path);
			$done = $master_length - count($still);
			file_put_contents($progress_file, $done.'/'.$master_length);

			if ($still === []){
				@unlink($stall_file);
				file_put_contents($progress_file, 'done');
				install_json_ok();
			}
		}

		$still = install_files_needed($root, $by_path);
		$done = $master_length - count($still);
		file_put_contents($progress_file, $done.'/'.$master_length);

		if ($still === []){
			@unlink($stall_file);
			file_put_contents($progress_file, 'done');
			install_json_ok();
		}

		// Stall detection: same remaining set with no writes
		$stall = [
				'files' => array_values($still),
				'written' => $written,
				'errors' => $errors,
		];
		$prev = [];
		if (is_file($stall_file)){
			$prev = json_decode((string)file_get_contents($stall_file), true);
			if (!is_array($prev)){
				$prev = [];
			}
		}
		$same = !empty($prev['files']) && $prev['files'] === $stall['files'] && $written === 0;
		$rounds = $same ? ((int)($prev['rounds'] ?? 0) + 1) : 1;
		$stall['rounds'] = $rounds;
		file_put_contents($stall_file, json_encode($stall, JSON_PRETTY_PRINT));

		if ($rounds >= 3 && $written === 0){
			$sample = array_slice($still, 0, 5);
			$err_bits = [];
			foreach ($sample as $f){
				$err_bits[] = $f.(isset($errors[$f]) ? ' ('.$errors[$f].')' : '');
			}
			install_json_fail(
					'Stuck downloading '.count($still).' file(s): '.implode('; ', $err_bits),
					['remaining' => $still, 'errors' => $errors]
					);
		}

		// Continue next request (partial progress)
		print(json_encode([
				'ok' => 0,
				'remaining' => count($still),
				'sample' => array_slice($still, 0, 5),
				'errors' => $errors,
		]));
		die();

	}

	// --- database: create + schema + seed ---

	if ($do == 'install_database'){

		if (!is_dir($root.'system') || !is_dir($root.'modules/cms/schema')){
			install_json_fail('CMS files missing — run install files first');
		}

		$db_host = (string)($_POST['db_host'] ?? 'localhost');
		$db_admin_user = (string)($_POST['db_admin_user'] ?? 'root');
		$db_admin_pass = (string)($_POST['db_admin_pass'] ?? '');
		$db_db = (string)($_POST['db_db'] ?? '');
		$db_user = (string)($_POST['db_user'] ?? '');
		$db_pass = (string)($_POST['db_pass'] ?? '');
		$page_title = trim((string)($_POST['page_title'] ?? 'Homepage'), "\n\t -");

		if ($db_db === '' || $db_user === ''){
			install_json_fail('Database name and user required');
		}

		$mysqli = @mysqli_connect($db_host, $db_admin_user, $db_admin_pass);
		if (!$mysqli){
			install_json_fail('Cannot connect as DB admin');
		}
		$mysqli->set_charset('utf8mb4');

		// Create database
		$db_esc = '`'.str_replace('`', '``', $db_db).'`';
		if (!$mysqli->query('CREATE DATABASE IF NOT EXISTS '.$db_esc.' CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci')){
			install_json_fail('CREATE DATABASE failed: '.$mysqli->error);
		}

		// Create app user (MySQL 8+)
		$db_host_for_user = ($db_host === 'localhost' || $db_host === '127.0.0.1') ? 'localhost' : '%';
		$user_esc = $mysqli->real_escape_string($db_user);
		$pass_esc = $mysqli->real_escape_string($db_pass);
		$host_esc = $mysqli->real_escape_string($db_host_for_user);

		// Ignore errors if user already exists
		@$mysqli->query("CREATE USER '{$user_esc}'@'{$host_esc}' IDENTIFIED BY '{$pass_esc}'");
		@$mysqli->query("CREATE USER IF NOT EXISTS '{$user_esc}'@'{$host_esc}' IDENTIFIED BY '{$pass_esc}'");
		// Grant (works if user created or already present)
		if (!$mysqli->query("GRANT ALL PRIVILEGES ON {$db_esc}.* TO '{$user_esc}'@'{$host_esc}'")){
			// Some hosts need IDENTIFIED BY on GRANT (older MySQL)
			@$mysqli->query("GRANT ALL PRIVILEGES ON {$db_esc}.* TO '{$user_esc}'@'{$host_esc}' IDENTIFIED BY '{$pass_esc}'");
		}
		$mysqli->query('FLUSH PRIVILEGES');
		$mysqli->select_db($db_db);

		// Schema via cms_schema_model
		$schema_error = install_apply_cms_schema($root, [
				'hostname' => $db_host,
				'username' => $db_user,
				'password' => $db_pass,
				'database' => $db_db,
		], $mysqli);

		if ($schema_error !== ''){
			install_json_fail($schema_error);
		}

		// Seed data only (structure from schema)
		$seed_error = install_seed_data($mysqli, $page_title, $update_url);
		if ($seed_error !== ''){
			install_json_fail($seed_error);
		}

		mysqli_close($mysqli);
		install_json_ok();

	}

	// --- config + optional self-delete ---

	if ($do == 'install_config'){

		if (!is_dir($root.'system')){
			install_json_fail('CMS files missing');
		}

		// base_url: site root URL path (parent of _install when applicable)
		$script_name = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/install.php'));
		$url_dir = str_replace('\\', '/', dirname($script_name));
		if (basename($url_dir) === '_install'){
			$url_dir = dirname($url_dir);
		}
		if ($url_dir === '/' || $url_dir === '\\' || $url_dir === '.' || $url_dir === ''){
			$base_url = '/';
		} else {
			$base_url = rtrim($url_dir, '/').'/';
		}

		$env = (string)($_POST['environment'] ?? 'DEV');
		$project = (string)($_POST['project_name'] ?? 'cms');

		$config = [
				'base_path' => '_auto_',
				'base_url' => $base_url,
				'upload_path' => 'img/',
				'upload_url' => 'img/',
				'environment' => $env,
				'errors_visible' => 1,
				'errors_log' => 'cache/errors_'.$project.'.log',
				'analytics' => 0,
				'cache' => [
						'force_download' => ($env === 'DEV' || $env === 'STG') ? 1 : 0,
						'pack_js' => ($env === 'DEV') ? 0 : 1,
						'pack_css' => ($env === 'DEV') ? 0 : 1,
				],
				'update' => [
						'allow' => ($env === 'DEV') ? ['*'] : 0,
				],
				'images_webp' => extension_loaded('gd') ? 'gd' : '',
				'database' => [
						'hostname' => (string)($_POST['db_host'] ?? 'localhost'),
						'username' => (string)($_POST['db_user'] ?? ''),
						'password' => (string)($_POST['db_pass'] ?? ''),
						'database' => (string)($_POST['db_db'] ?? ''),
						'dbdriver' => 'mysqli',
				],
				'admin_username' => (string)($_POST['admin_user'] ?? ''),
				'admin_password' => (string)($_POST['admin_pass'] ?? ''),
		];

		if (!file_exists($root.'config')){
			mkdir($root.'config', 0777, true);
		}
		$host_file = $root.'config/'.strtolower((string)$_SERVER['SERVER_NAME']).'.json';
		file_put_contents($host_file, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

		$server_name = (string)$_SERVER['SERVER_NAME'];
		$htaccess = install_htaccess_body($server_name);
		file_put_contents($root.'.htaccess', $htaccess);

		// Optional delete install script (default: keep)
		$delete_install = !empty($_POST['delete_install']) && (string)$_POST['delete_install'] === '1';
		if ($delete_install){
			$script = install_script_dir().'install.php';
			if (file_exists($script)){
				if (!file_exists($root.'cache/')){
					mkdir($root.'cache/', 0777, true);
				}
				@rename($script, $root.'cache/install.tmp');
			}
			// Remove empty _install dir
			$install_dir = rtrim(install_script_dir(), '/');
			if (basename($install_dir) === '_install' && is_dir($install_dir)){
				$left = @scandir($install_dir);
				if (is_array($left) && count(array_diff($left, ['.', '..'])) === 0){
					@rmdir($install_dir);
				}
			}
		}

		install_json_ok(['deleted_install' => $delete_install ? 1 : 0]);

	}

	install_json_fail('Unknown action');

}

// ---------------------------------------------------------------------------
// Schema bootstrap + seed helpers
// ---------------------------------------------------------------------------

/**
 * Apply modules/cms/schema via cms_schema_model::fix_schema('cms').
 * @return string error message or ''
 */
function install_apply_cms_schema($root, $db_cfg, $mysqli_admin){

	// Use app DB user connection for schema work when possible
	$conn = @mysqli_connect(
			$db_cfg['hostname'],
			$db_cfg['username'],
			$db_cfg['password'],
			$db_cfg['database']
			);
	if (!$conn){
		// Fall back to admin connection already selected
		$conn = $mysqli_admin;
	} else {
		mysqli_set_charset($conn, 'utf8mb4');
	}

	if (!defined('BASEPATH')){
		define('BASEPATH', $root.'system/');
	}

	$GLOBALS['config'] = [
			'base_path' => $root,
			'base_url' => '/',
			'upload_path' => $root.'img/',
			'upload_url' => '/img/',
			'database' => [
					'hostname' => $db_cfg['hostname'],
					'username' => $db_cfg['username'],
					'password' => $db_cfg['password'],
					'database' => $db_cfg['database'],
					'dbdriver' => 'mysqli',
			],
			'modules' => ['cms'],
			'module' => [],
			'extends' => [],
			'extends_by_target' => [],
			'extend_sources' => [],
			'errors_visible' => 0,
			'errors_log' => '',
	];

	$cms_cfg_path = $root.'modules/cms/config.json';
	if (is_file($cms_cfg_path)){
		$cms_cfg = json_decode(file_get_contents($cms_cfg_path), true);
		$GLOBALS['config']['module']['cms'] = is_array($cms_cfg) ? $cms_cfg : [];
	} else {
		$GLOBALS['config']['module']['cms'] = ['panels' => []];
	}
	if (empty($GLOBALS['config']['module']['cms']['panels'])){
		$GLOBALS['config']['module']['cms']['panels'] = [];
	}

	$GLOBALS['db'] = $conn;
	$GLOBALS['cms_config_full'] = 1;

	// Minimal helpers + bootstrap + controller (same idea as cms/updater API)
	if (!function_exists('_html_error')){
		function _html_error($error, $exit = 0, $extra = []){
			// silent during install
			return '';
		}
	}

	require_once $root.'system/helpers/json_helper.php';
	require_once $root.'system/helpers/string_helper.php';
	require_once BASEPATH.'core/cms_bootstrap.php';
	require_once BASEPATH.'core/Model.php';
	require_once BASEPATH.'core/cms_db.php';
	require_once BASEPATH.'core/Loader.php';
	require_once BASEPATH.'core/controller.php';

	try {
		$ci = new Controller();
		// Ensure db on CI
		if (empty($ci->db)){
			$ci->db = new cms_db();
		}
		$ci->load->model('cms/cms_schema_model');
		$ok = $ci->cms_schema_model->fix_schema('cms');
		$errors = $ci->cms_schema_model->get_fix_errors();
		if (!empty($errors)){
			$msgs = [];
			foreach ($errors as $e){
				$msgs[] = ($e['key'] ?? '').': '.($e['message'] ?? '');
			}
			return 'Schema fix errors: '.implode('; ', array_slice($msgs, 0, 5));
		}
		// fix_schema returns false if nothing to do when already ok — re-check
		$remaining = $ci->cms_schema_model->check_schema();
		// Only care about cms module keys
		$cms_left = [];
		foreach ($remaining as $k => $msg){
			if (strpos($k, 'cms:') === 0 || $k === 'cms'){
				$cms_left[$k] = $msg;
			}
		}
		if (!empty($cms_left)){
			// Try module-level fix again
			$ci->cms_schema_model->fix_schema('cms');
			$remaining2 = $ci->cms_schema_model->check_schema();
			$cms_left2 = [];
			foreach ($remaining2 as $k => $msg){
				if (strpos($k, 'cms:') === 0 || $k === 'cms'){
					$cms_left2[$k] = $msg;
				}
			}
			if (!empty($cms_left2)){
				$sample = array_slice($cms_left2, 0, 3, true);
				$parts = [];
				foreach ($sample as $k => $m){
					$parts[] = $k.' — '.$m;
				}
				return 'Schema still incomplete: '.implode('; ', $parts);
			}
		}
	} catch (Throwable $e){
		return 'Schema bootstrap failed: '.$e->getMessage();
	}

	return '';

}

/**
 * Minimal seed: homepage + cms settings panels + route.
 * @return string error or ''
 */
function install_seed_data($mysqli, $page_title, $update_url){

	$site_title = '#page# - '.$page_title;
	$update_endpoint = rtrim($update_url, '/').'/cms/updater/';

	// Skip if already seeded
	$check = $mysqli->query('SELECT cms_page_id FROM cms_page WHERE cms_page_id = 1 LIMIT 1');
	if ($check && $check->num_rows > 0){
		return '';
	}

	$meta = json_encode([
			'title' => 'Homepage',
			'status' => '0',
			'seo_title' => '',
			'description' => '',
			'image' => '',
			'layout' => 'cms/rem',
	], JSON_UNESCAPED_SLASHES);

	$stmt = $mysqli->prepare(
			'INSERT INTO cms_page (cms_page_id, position, sort, slug, meta) VALUES (1, ?, 1, ?, ?)'
			);
	if (!$stmt){
		return 'Seed prepare page failed: '.$mysqli->error;
	}
	$position = 'main';
	$slug = 'homepage';
	$stmt->bind_param('sss', $position, $slug, $meta);
	if (!$stmt->execute()){
		return 'Seed page failed: '.$stmt->error;
	}
	$stmt->close();

	// Settings panels (sort 0 = settings)
	$sql_panels = "INSERT INTO cms_page_panel
		(cms_page_panel_id, cms_page_id, parent_id, `show`, sort, title, panel_name, submenu_anchor, submenu_title)
		VALUES
		(1, 0, 0, 0, 0, '', 'cms/cms_settings', '', ''),
		(2, 0, 0, 0, 0, '', 'cms/cms_cssjs_settings', '', '')";
	if (!$mysqli->query($sql_panels)){
		return 'Seed panels failed: '.$mysqli->error;
	}

	$settings = [
			'favicon' => '',
			'site_title' => $site_title,
			'site_title_delimiter' => '-',
			'landing_page' => [
					'target' => '_page',
					'cms_page_id' => '1',
					'url' => 'homepage/',
					'text' => 'Homepage',
					'target_id' => '',
					'_value' => '1',
			],
			'email' => '',
			'panel_cache' => '0',
			'inline_limit' => '100000',
			'targets_enabled' => '0',
			'cron_trigger' => 'visits',
			'cms_update_url' => $update_endpoint,
			'layout' => 'cms/rem',
			'modules' => ['000' => 'cms'],
			'rem_px' => '1400',
			'rem_ratio' => '2.0',
			'rem_m_px' => '900',
			'rem_switched' => '0',
			'rem_k' => '100',
			'rem_m_k' => '50',
			'images_quality' => '85',
			'images_1x' => '1',
			'images_2x' => '1.5',
			'images_textarea' => '0.5',
			'cms_background' => '',
			'images_rows' => '4',
			'input_link_order' => '0',
	];

	$cssjs = [
			'css' => ['000' => 'modules/cms/css/cms_mini_normalise.scss'],
	];

	$blob_settings = json_encode($settings, JSON_UNESCAPED_SLASHES);
	$blob_cssjs = json_encode($cssjs, JSON_UNESCAPED_SLASHES);

	// Named rows + empty-name cache blobs (language '')
	$params = [];
	$params[] = [2, '', 'css.000', 'modules/cms/css/cms_mini_normalise.scss', 0];
	$params[] = [2, '', '', $blob_cssjs, 0];

	$flat = [
			'favicon' => '',
			'site_title' => $site_title,
			'site_title_delimiter' => '-',
			'landing_page.target' => '_page',
			'landing_page.cms_page_id' => '1',
			'landing_page.url' => 'homepage/',
			'landing_page.text' => 'Homepage',
			'landing_page.target_id' => '',
			'landing_page._value' => '1',
			'email' => '',
			'panel_cache' => '0',
			'inline_limit' => '100000',
			'targets_enabled' => '0',
			'cron_trigger' => 'visits',
			'cms_update_url' => $update_endpoint,
			'layout' => 'cms/rem',
			'modules.000' => 'cms',
			'rem_px' => '1400',
			'rem_ratio' => '2.0',
			'rem_m_px' => '900',
			'rem_switched' => '0',
			'rem_k' => '100',
			'rem_m_k' => '50',
			'images_quality' => '85',
			'images_1x' => '1',
			'images_2x' => '1.5',
			'images_textarea' => '0.5',
			'cms_background' => '',
			'images_rows' => '4',
			'input_link_order' => '0',
	];
	foreach ($flat as $name => $value){
		$params[] = [1, '', $name, $value, 0];
	}
	$params[] = [1, '', '', $blob_settings, 0];

	$stmt = $mysqli->prepare(
			'INSERT INTO cms_page_panel_param (cms_page_panel_id, language, name, value, search) VALUES (?,?,?,?,?)'
			);
	if (!$stmt){
		return 'Seed params prepare failed: '.$mysqli->error;
	}
	foreach ($params as $row){
		$pid = (int)$row[0];
		$lang = $row[1];
		$name = $row[2];
		$value = $row[3];
		$search = (int)$row[4];
		$stmt->bind_param('isssi', $pid, $lang, $name, $value, $search);
		if (!$stmt->execute()){
			return 'Seed param failed ('.$name.'): '.$stmt->error;
		}
	}
	$stmt->close();

	// Public route (cms_route, not cms_slug)
	$stmt = $mysqli->prepare('INSERT INTO cms_route (slug, target, status) VALUES (?,?,?)');
	if (!$stmt){
		return 'Seed route prepare failed: '.$mysqli->error;
	}
	$route_slug = 'homepage';
	$route_target = '1';
	$route_status = 1;
	$stmt->bind_param('ssi', $route_slug, $route_target, $route_status);
	if (!$stmt->execute()){
		return 'Seed route failed: '.$stmt->error;
	}
	$stmt->close();

	return '';

}

function install_htaccess_body($server_name){

	$host_re = str_replace('.', '\.', $server_name);

	$tpl = <<<'HTA'
DirectoryIndex index.php
Options -Indexes

AddType application/vnd.ms-fontobject    .eot
AddType application/x-font-opentype      .otf
AddType image/svg+xml                    .svg
AddType application/x-font-ttf           .ttf
AddType application/font-woff            .woff

<IfModule mod_expires.c>
ExpiresActive On
ExpiresByType image/jpg "access 1 year"
ExpiresByType image/jpeg "access 1 year"
ExpiresByType image/gif "access 1 year"
ExpiresByType image/png "access 1 year"
ExpiresByType image/ico "access 1 year"
ExpiresByType image/svg+xml "access 1 year"
ExpiresByType text/css "access 1 year"
ExpiresByType text/html "access 1 year"
ExpiresByType application/pdf "access 1 year"
ExpiresByType application/x-javascript "access 1 year"
ExpiresByType text/javascript "access 1 year"
ExpiresByType application/javascript "access 1 year"
ExpiresByType image/x-icon "access 1 year"
ExpiresByType application/font-woff "access 1 year"
ExpiresByType application/font-ttf "access 1 year"
ExpiresByType application/x-font-ttf "access 1 year"
ExpiresByType application/font-otf "access 1 year"
ExpiresByType application/x-font-opentype "access 1 year"
ExpiresByType application/vnd.ms-fontobject "access 1 year"
ExpiresDefault "access 1 year"
</IfModule>

<IfModule mod_deflate.c>
AddOutputFilterByType DEFLATE text/plain
AddOutputFilterByType DEFLATE text/html
AddOutputFilterByType DEFLATE text/xml
AddOutputFilterByType DEFLATE text/css
AddOutputFilterByType DEFLATE text/javascript
AddOutputFilterByType DEFLATE application/xml
AddOutputFilterByType DEFLATE application/xhtml+xml
AddOutputFilterByType DEFLATE application/rss+xml
AddOutputFilterByType DEFLATE application/javascript
AddOutputFilterByType DEFLATE application/x-javascript
AddOutputFilterByType DEFLATE application/x-httpd-php
AddOutputFilterByType DEFLATE application/x-httpd-fastphp
AddOutputFilterByType DEFLATE application/x-font
AddOutputFilterByType DEFLATE application/x-font-truetype
AddOutputFilterByType DEFLATE application/x-font-ttf
AddOutputFilterByType DEFLATE application/x-font-otf
AddOutputFilterByType DEFLATE application/x-font-opentype
AddOutputFilterByType DEFLATE application/vnd.ms-fontobject
AddOutputFilterByType DEFLATE image/svg+xml
AddOutputFilterByType DEFLATE image/x-icon
AddOutputFilterByType DEFLATE font/ttf
AddOutputFilterByType DEFLATE font/otf
AddOutputFilterByType DEFLATE font/opentype
</IfModule>

RewriteEngine on

# Protect cache
RewriteCond %{REQUEST_URI} ^/cache [NC]
RewriteCond %{REQUEST_URI} !\.(css|js|xml)$ [NC]
RewriteRule .* - [F,L]

# Everything not set domain → set domain
RewriteCond %{HTTP_HOST} !^(.*)\.localhost
RewriteCond %{HTTP_HOST} !^stg\.
RewriteCond %{HTTP_HOST} !^__HOST_RE__
RewriteRule ^(.*)$ http://__SERVER_NAME__%{REQUEST_URI} [R=302,L]

RewriteCond %{ENV:REDIRECT_STATUS} ^$
RewriteCond $1 !^(index\.php|modules|img|css|js|robots\.txt|favicon\.ico)
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ ./index.php?/$1 [L,QSA]
HTA;

	return str_replace(
			['__HOST_RE__', '__SERVER_NAME__'],
			[$host_re, $server_name],
			$tpl
			);

}

// ---------------------------------------------------------------------------
// UI (GET)
// ---------------------------------------------------------------------------

$hostname = $_SERVER['SERVER_NAME'] ?? 'localhost';

if ($hostname == 'localhost'){
	$url_parts = parse_url($_SERVER['REQUEST_URI'] ?? '/');
	$url_parts = explode('/', $url_parts['path'] ?? '/');
	$project_name = $url_parts[1] ?? 'cms';
} else {
	$project_name = $hostname;
}

$project_name = str_replace(['www.', '.com', '.localhost', '.co.uk', '-', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'], '', $project_name);
if (stristr($project_name, '.')){
	$project_name = str_replace('.', '_', $project_name);
}
$project_name = strtolower($project_name);
if ($project_name === ''){
	$project_name = 'cms';
}

$install_root_display = install_root_dir();
$site_href = (basename(rtrim(install_script_dir(), '/')) === '_install') ? '../' : './';

?>
<html>
<head>
	<meta charset="utf-8">
	<title>Narrative CMS install</title>
	<script src="https://code.jquery.com/jquery-3.4.1.min.js"></script>
	<style>
		body { font-family: sans-serif; max-width: 40rem; margin: 1.5rem; line-height: 1.4; }
		.install_fake_check { font-family: monospace; margin-right: 0.4rem; }
		.install_toggle_row { cursor: pointer; user-select: none; margin: 1rem 0; }
		.install_opt_hidden { position: absolute; opacity: 0; width: 0; height: 0; pointer-events: none; }
		.install_error { color: #a00; margin-top: 0.5rem; }
		input, select { max-width: 100%; }
	</style>
</head>
<body>

	<div><b>Narrative CMS install</b><br>
		<small>Install root: <?= htmlspecialchars($install_root_display) ?></small><br><br>
	</div>

	<div class="step_1">
		<div>STEP 1 (general):<br><br></div>
		<div>
			<label style="display: block;" for="page_title">Page title (browser / search / social): </label>
			<input id="page_title" value="<?= htmlspecialchars(ucfirst($project_name)) ?>"><br><br>
		</div>
		<div>
			<label style="display: block;" for="project_name">Project name (lowercase, max 12): </label>
			<input id="project_name" value="<?= htmlspecialchars(substr($project_name, 0, 12)) ?>"><br><br>
		</div>
		<div>
			<label style="display: block;" for="environment">Environment: </label>
			<select id="environment">
				<option value="DEV" selected="selected">DEV - development</option>
				<option value="STG">STG - staging</option>
				<option value="">LIVE</option>
			</select><br><br>
		</div>
		<div class="step_1_next" style="cursor: pointer;">NEXT</div>
	</div>

	<div class="step_2" style="display: none;">
		<div>STEP 2 (database):<br><br></div>
		<div>
			<label style="display: block;" for="db_host">DB host: </label>
			<input id="db_host" value="localhost"><br><br>
		</div>
		<div>
			<label style="display: block;" for="db_db">DB database: </label>
			<input id="db_db" value=""><br><br><br>
		</div>
		<div>DB admin access for creating database (leave as root on local XAMPP)<br><br></div>
		<div>
			<label style="display: block;" for="db_admin_user">DB admin username: </label>
			<input id="db_admin_user" value="root"><br><br>
		</div>
		<div>
			<label style="display: block;" for="db_admin_pass">DB admin password: </label>
			<input id="db_admin_pass" value=""><br><br><br>
		</div>
		<div style="display: none;">
			<select id="db_createnew">
				<option value="yes" selected="selected">Yes</option>
			</select>
		</div>
		<div class="db_extra">
			<label style="display: block;" for="db_user">New DB username: </label>
			<input id="db_user"><br><br>
		</div>
		<div class="db_extra">
			<label style="display: block;" for="db_pass">New DB password: </label>
			<input id="db_pass"> <span class="generate_db_password" style="cursor: pointer;">generate new</span><br><br>
		</div>
		<div class="step_2_previous" style="cursor: pointer;">PREVIOUS</div>
		<div class="step_2_next" style="cursor: pointer;">NEXT</div>
	</div>

	<div class="step_3" style="display: none;">
		<div>STEP 3 (project):<br><br></div>
		<div>
			<label style="display: block;" for="admin_user">CMS admin username: </label>
			<input id="admin_user" value=""><br><br>
		</div>
		<div>
			<label style="display: block;" for="admin_pass">CMS admin password (write this down):</label>
			<input id="admin_pass" value=""> <span class="generate_admin_password" style="cursor: pointer;">generate new</span><br><br><br>
		</div>
		<div class="step_3_previous" style="cursor: pointer;">PREVIOUS</div>
		<div class="step_3_next" style="cursor: pointer;">NEXT</div>
	</div>

	<div class="step_4" style="display: none;">
		<div>STEP 4 (checks):<br><br></div>
		<div class="q_writable">
			<span class="is_writable">&nbsp;</span> Is install root writable?
		</div>
		<div class="q_db_accessible">
			<span class="is_db_accessible">&nbsp;</span> Is database accessible?
		</div>
		<div class="q_db_name_available">
			<span class="is_db_name_available">&nbsp;</span> Is database name available?
		</div>
		<div class="q_db_user_available">
			<span class="is_db_user_available">&nbsp;</span> Is database user available?
		</div>
		<div class="q_internet">
			<span class="is_internet">&nbsp;</span> Can download from master updater?
		</div>

		<div class="install_toggle_row" id="delete_install_row">
			<span class="install_fake_check">[ ]</span>
			<input type="checkbox" id="delete_install" class="install_opt_hidden" value="1">
			<span class="install_check_label">Delete install script after successful install</span>
		</div>
		<div style="font-size: 0.85rem; color: #666; margin-bottom: 1rem;">
			Default: keep <code>_install/install.php</code> on the server.
		</div>

		<div class="check_again" style="cursor: pointer;">CHECK AGAIN</div>
		<div class="step_4_previous" style="cursor: pointer;">PREVIOUS</div>
		<div class="step_4_next" style="cursor: pointer;">NEXT (INSTALL)</div>
		<div class="install_error step_4_error"></div>
	</div>

	<div class="step_5" style="display: none;">
		<div class="q_files">
			<span class="install_files">&nbsp;</span> Install files <span class="step_5_files"></span>
		</div>
		<div class="q_database">
			<span class="install_database">&nbsp;</span> Install database (schema + seed)
		</div>
		<div class="q_config">
			<span class="install_config">&nbsp;</span> Set up config files
		</div>
		<div class="install_error step_5_error"></div>
		<div class="step_5_next" style="cursor: pointer; display: none;">OK</div>
	</div>

	<div class="step_6" style="display: none;">
		<div>
			Thank you!<br><br>
			<a href="<?= htmlspecialchars($site_href) ?>">&gt; Homepage</a><br>
			<a href="<?= htmlspecialchars($site_href) ?>admin/">&gt; CMS admin</a>
			<div><span>Username: </span><span class="step_6_username"></span></div>
			<div><span>Password: </span><span class="step_6_password"></span></div>
			<div class="step_6_install_kept" style="display: none; margin-top: 1rem; font-size: 0.9rem; color: #666;">
				Install script kept at <code>_install/install.php</code>.
			</div>
		</div>
	</div>

	<script>
		function make_string(length){
			var text = '';
			var possible = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz2345678923456789';
			var extended = possible + possible + possible;
			for (var i = 0; i < length; i++){
				text += extended.charAt(Math.floor(Math.random() * extended.length));
			}
			return text;
		}

		function sync_delete_install_check(){
			var $row = $('#delete_install_row');
			var $input = $('#delete_install');
			var $fake = $row.find('.install_fake_check');
			$fake.text($input.is(':checked') ? '[v]' : '[ ]');
		}

		function do_task(check_name, params){
			var deferred = $.Deferred();
			if (!params) params = {};
			params['do'] = check_name;
			$.ajax({
				type: 'POST',
				url: window.location.toString(),
				dataType: 'json',
				data: params,
				success: function(data){
					if (data && data.ok){
						$('.' + check_name).css({'background-color': 'green'});
					} else {
						if (!params.no_red){
							$('.' + check_name).css({'background-color': 'red'});
						}
						if (data && data.error){
							$('.step_4_error, .step_5_error').filter(':visible').first().text(data.error);
						}
					}
					deferred.resolve(data || {ok: 0});
				},
				error: function(){
					if (!params.no_red){
						$('.' + check_name).css({'background-color': 'red'});
					}
					deferred.resolve({ok: 0, error: 'Request failed'});
				}
			});
			return deferred.promise();
		}

		$(document).ready(function(){

			// Dump-style virtual checkbox
			$('#delete_install_row').on('click', function(e){
				e.preventDefault();
				var $input = $('#delete_install');
				$input.prop('checked', !$input.prop('checked'));
				sync_delete_install_check();
			});
			sync_delete_install_check();

			$('.step_1_next').on('click', function(){
				$('.step_1').hide();
				$('.step_2').show();
				$('#db_db').val($('#project_name').val());
				$('#db_user').val($('#project_name').val());
				$('#db_pass').val(make_string(10));
			});

			$('.step_2_next').on('click', function(){
				$('.step_2').hide();
				$('.step_3').show();
				$('#admin_user').val($('#project_name').val());
				$('#admin_pass').val(make_string(10));
			});

			$('.generate_db_password').on('click', function(){
				$('#db_pass').val(make_string(10));
			});

			$('.step_2_previous').on('click', function(){
				$('.step_2').hide();
				$('.step_1').show();
			});

			$('.step_3_next').on('click', function(){
				$('.step_3').hide();
				$('.step_4').show();
				$('.step_4_error').text('');
				run_checks();
			});

			$('.generate_admin_password').on('click', function(){
				$('#admin_pass').val(make_string(10));
			});

			$('.step_3_previous').on('click', function(){
				$('.step_3').hide();
				$('.step_2').show();
			});

			$('.step_4_next').on('click', function(){
				$('.step_4').hide();
				$('.step_5').show();
				$('.step_5_error').text('');
				run_installation();
			});

			$('.check_again').on('click', run_checks);

			function run_checks(){
				$('.step_4_error').text('');
				$('.is_writable, .is_db_accessible, .is_db_name_available, .is_db_user_available, .is_internet')
						.css({'background-color': ''});
				do_task('is_writable')
					.then(function(){
						return do_task('is_db_accessible', {
							db_host: $('#db_host').val(),
							db_admin_user: $('#db_admin_user').val(),
							db_admin_pass: $('#db_admin_pass').val()
						});
					})
					.then(function(){
						return do_task('is_db_name_available', {
							db_host: $('#db_host').val(),
							db_admin_user: $('#db_admin_user').val(),
							db_admin_pass: $('#db_admin_pass').val(),
							db_db: $('#db_db').val()
						});
					})
					.then(function(){
						return do_task('is_db_user_available', {
							db_host: $('#db_host').val(),
							db_admin_user: $('#db_admin_user').val(),
							db_admin_pass: $('#db_admin_pass').val(),
							db_user: $('#db_user').val()
						});
					})
					.then(function(){
						return do_task('is_internet');
					});
			}

			$('.step_4_previous').on('click', function(){
				$('.step_4').hide();
				$('.step_3').show();
			});

			function progress_path(){
				// Progress file is always under site root cache/
				var path = window.location.pathname || '';
				if (path.indexOf('/_install/') !== -1){
					return path.replace(/\/_install\/.*$/, '/cache/install.txt');
				}
				if (path.indexOf('install.php') !== -1){
					return path.replace(/install\.php.*$/, 'cache/install.txt');
				}
				return '../cache/install.txt';
			}

			function run_installation(){
				$('.step_5_files').html('starting');
				var installinterval = setInterval(function(){
					$.get(progress_path(), function(data){
						if (data == 'done'){
							$('.step_5_files').html('done');
							clearInterval(installinterval);
						} else {
							$('.step_5_files').html(data);
						}
					}).fail(function(){ /* ignore until cache exists */ });
				}, 1000);

				function try_files(deferred){
					deferred = deferred || $.Deferred();
					do_task('install_files', {no_red: true}).then(function(data){
						if (data && data.ok){
							deferred.resolve(data);
							return;
						}
						if (data && data.error){
							var msg = data.error;
							if (data.sample && data.sample.length){
								msg += ' — ' + data.sample.join(', ');
							}
							$('.step_5_error').text(msg);
							$('.install_files').css({'background-color': 'red'});
							deferred.resolve(data);
							return;
						}
						// incomplete — show remaining if provided
						if (data && data.remaining){
							$('.step_5_files').html(function(){
								return $(this).text() + ' (' + data.remaining + ' left)';
							});
						}
						try_files(deferred);
					});
					return deferred.promise();
				}

				try_files()
					.then(function(data){
						if (!data || !data.ok){
							return $.Deferred().reject(data).promise();
						}
						return do_task('install_database', {
							db_host: $('#db_host').val(),
							db_admin_user: $('#db_admin_user').val(),
							db_admin_pass: $('#db_admin_pass').val(),
							db_db: $('#db_db').val(),
							db_user: $('#db_user').val(),
							db_pass: $('#db_pass').val(),
							page_title: $('#page_title').val()
						});
					})
					.then(function(data){
						if (!data || !data.ok){
							$('.step_5_error').text((data && data.error) ? data.error : 'Database install failed');
							return $.Deferred().reject(data).promise();
						}
						return do_task('install_config', {
							project_name: $('#project_name').val(),
							environment: $('#environment').val(),
							db_host: $('#db_host').val(),
							db_db: $('#db_db').val(),
							db_user: $('#db_user').val(),
							db_pass: $('#db_pass').val(),
							admin_user: $('#admin_user').val(),
							admin_pass: $('#admin_pass').val(),
							delete_install: $('#delete_install').is(':checked') ? '1' : '0'
						});
					})
					.then(function(data){
						clearInterval(installinterval);
						if (!data || !data.ok){
							$('.step_5_error').text((data && data.error) ? data.error : 'Config install failed');
							return;
						}
						$('.step_5').hide();
						$('.step_6').show();
						$('.step_6_username').text($('#admin_user').val());
						$('.step_6_password').text($('#admin_pass').val());
						if (!(data.deleted_install)){
							$('.step_6_install_kept').show();
						}
					})
					.fail(function(){
						clearInterval(installinterval);
					});
			}

		});
	</script>
</body>
</html>
