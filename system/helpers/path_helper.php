<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Runtime dir mkdir (install + CMS Update only) and one-time master migrate.
 * cms_path / cms_path_url / cms_cache_rel live in cms_config_basic.php.
 */

function cms_ensure_runtime_dirs(){

	foreach (['cache', 'tmp', 'log', 'session'] as $key){
		$p = rtrim(cms_path($key), '/');
		if ($p !== '' && !is_dir($p)){
			@mkdir($p, ($key === 'session') ? 0700 : 0755, true);
		}
	}

}

function cms_dir_is_nonempty($dir){

	$dir = rtrim(str_replace('\\', '/', (string)$dir), '/');
	if ($dir === '' || !is_dir($dir)){
		return false;
	}
	$h = @opendir($dir);
	if ($h === false){
		return false;
	}
	while (($e = readdir($h)) !== false){
		if ($e !== '.' && $e !== '..' && $e !== '.gitkeep'){
			closedir($h);

			return true;
		}
	}
	closedir($h);

	return false;

}

function cms_migrate_update_master_dirs($project = '', $tmp = ''){

	if ($project === ''){
		$project = (string)($GLOBALS['config']['base_path'] ?? '');
	}
	if ($tmp === ''){
		$tmp = cms_path('tmp');
	}
	$project = str_replace('\\', '/', (string)$project);
	$tmp = str_replace('\\', '/', (string)$tmp);
	$project = ($project === '') ? '' : rtrim($project, '/').'/';
	$tmp = ($tmp === '') ? '' : rtrim($tmp, '/').'/';
	if ($project === '' || $tmp === ''){
		return;
	}

	foreach (['master', 'update'] as $name){
		$old = $project.'cache/'.$name.'/';
		$new = $tmp.$name.'/';
		if (!is_dir($old)){
			continue;
		}
		if (is_dir($new) && cms_dir_is_nonempty($new)){
			continue;
		}
		if (is_dir($new) && !cms_dir_is_nonempty($new)){
			@rmdir($new);
		}
		$parent = rtrim($tmp, '/');
		if (!is_dir($parent)){
			@mkdir($parent, 0755, true);
		}
		@rename(rtrim($old, '/'), rtrim($new, '/'));
	}

}
