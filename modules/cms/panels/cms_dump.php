<?php

namespace cms;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_dump extends \Controller {

	const COMPRESS_MAX_PX = 1400;

	function __construct(){

		parent::__construct();

		if (empty($_SESSION['cms_user']['cms_user_id'])){
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}

	}

	function rrmdir($dir) {
		if (is_dir($dir)) {
			$objects = scandir($dir);
			foreach ($objects as $object) {
				if ($object != '.' && $object != '..') {
					if (is_dir($dir.'/'.$object)){
						$this->rrmdir($dir.'/'.$object);
					} else {
						unlink($dir.'/'.$object);
					}
				}
			}
			rmdir($dir);
		}
	}

	function makesize($size){

		if ($size < 512){
			return $size.' B';
		}

		$size = $size / 1024;

		if ($size < 100){
			return round($size, 1).' kB';
		} else if ($size < 512){
			return round($size).' kB';
		}

		$size = $size / 1024;

		if ($size < 100){
			return round($size, 1).' MB';
		} else if ($size < 512){
			return round($size).' MB';
		}

		$size = $size / 1024;

		return round($size, 1).' GB';

	}

	function _backup_dir(){
		return $GLOBALS['config']['base_path'].'cache/backup/';
	}

	function _ensure_backup_dir(){
		$dir = $this->_backup_dir();
		if (!is_dir($dir)){
			mkdir($dir, 0755, true);
		}
		return $dir;
	}

	/**
	 * @return array module => table names
	 */
	function _get_tables_by_module(){
		$this->load->model('cms/cms_schema_model');
		return $this->cms_schema_model->get_schema_tables_by_module();
	}

	/**
	 * Flat list of all schema-owned table names.
	 */
	function _get_all_schema_tables(){
		$tables = [];
		foreach ($this->_get_tables_by_module() as $module_tables){
			foreach ($module_tables as $table){
				$tables[] = $table;
			}
		}
		return $tables;
	}

	/**
	 * Resource picker tree: last two years by month, older years as year-only.
	 *
	 * @return array
	 */
	function _get_resources_tree(){

		$upload = rtrim(str_replace('\\', '/', $GLOBALS['config']['upload_path']), '/');
		if ($upload === '' || !is_dir($upload)){
			$upload = rtrim(str_replace('\\', '/', $GLOBALS['config']['base_path'].$GLOBALS['config']['upload_path']), '/');
		}

		$current_year = (int)date('Y');
		$prev_year = $current_year - 1;
		$default_months = $this->_default_resource_months();

		$tree = [];

		// Current and previous year: month rows (newest first; current year: no future months)
		$current_month = (int)date('n');
		foreach ([$current_year, $prev_year] as $year){
			$months = [];
			$month_max = ($year === $current_year) ? $current_month : 12;
			for ($m = $month_max; $m >= 1; $m--){
				$key = sprintf('%04d/%02d', $year, $m);
				$months[] = [
						'key' => $key,
						'label' => $key,
						'selected' => in_array($key, $default_months, true),
				];
			}
			$tree[] = [
					'type' => 'year_months',
					'year' => (string)$year,
					'months' => $months,
			];
		}

		// Older years present on disk
		$older = [];
		if (is_dir($upload)){
			foreach (scandir($upload) as $entry){
				if ($entry === '.' || $entry === '..'){
					continue;
				}
				if (!preg_match('/^\d{4}$/', $entry)){
					continue;
				}
				$y = (int)$entry;
				if ($y < $prev_year && is_dir($upload.'/'.$entry)){
					$older[] = $entry;
				}
			}
		}
		rsort($older, SORT_STRING);
		foreach ($older as $year){
			$tree[] = [
					'type' => 'year',
					'key' => $year,
					'label' => $year,
					'selected' => false,
			];
		}

		return $tree;

	}

	/**
	 * Last two calendar months as YYYY/MM.
	 */
	function _default_resource_months(){
		$keys = [];
		$ts = strtotime(date('Y-m-01'));
		for ($i = 0; $i < 2; $i++){
			$keys[] = date('Y/m', strtotime('-'.$i.' month', $ts));
		}
		return $keys;
	}

	/**
	 * Project slug for backup filenames (sanitized DB name).
	 */
	function _project_slug(){

		$raw = (string)($GLOBALS['config']['database']['database'] ?? 'site');
		$slug = strtolower(preg_replace('/[^a-zA-Z0-9_]+/', '_', $raw));
		$slug = trim($slug, '_');
		if ($slug === ''){
			$slug = 'site';
		}
		return $slug;

	}

	/**
	 * Next free basename: dump_<project>_YYYY_MM_DD or …_N
	 */
	function _allocate_backup_basename(){

		$dir = $this->_ensure_backup_dir();
		$base = 'dump_'.$this->_project_slug().'_'.date('Y_m_d');

		if (!file_exists($dir.$base.'.zip')){
			return $base;
		}

		$n = 1;
		while (file_exists($dir.$base.'_'.$n.'.zip')){
			$n++;
		}

		return $base.'_'.$n;

	}

	/**
	 * Safe basename under cache/backup/ (no path traversal).
	 * Returns basename without .zip, or empty string if invalid.
	 */
	function _safe_backup_basename($name){

		$name = trim((string)$name);
		$name = preg_replace('/\.zip$/i', '', $name);
		if ($name === '' || !preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_.-]*$/', $name)){
			return '';
		}
		if (strpos($name, '..') !== false){
			return '';
		}
		$path = $this->_backup_dir().$name.'.zip';
		$real_dir = realpath($this->_backup_dir());
		$real_file = realpath($path);
		if ($real_dir === false || $real_file === false){
			return '';
		}
		$real_dir = rtrim(str_replace('\\', '/', $real_dir), '/').'/';
		$real_file = str_replace('\\', '/', $real_file);
		if (strpos($real_file, $real_dir) !== 0){
			return '';
		}
		return $name;

	}

	/**
	 * Read dump.json from inside a zip (if present).
	 */
	function _read_meta_from_zip($zip_path){

		if (!is_file($zip_path)){
			return [];
		}
		$zip = new \ZipArchive();
		if ($zip->open($zip_path) !== true){
			return [];
		}
		$raw = $zip->getFromName('dump.json');
		$zip->close();
		if ($raw === false || $raw === ''){
			return [];
		}
		$decoded = cms_json_decode($raw, 'dump.json');
		return is_array($decoded) ? $decoded : [];

	}

	/**
	 * Write sidecar JSON and embed dump.json inside the zip.
	 * filesize in meta is approximate after embedding (sidecar is source of truth for list).
	 */
	function _write_backup_meta($basename, $meta){

		$dir = $this->_ensure_backup_dir();
		$zip_path = $dir.$basename.'.zip';
		$json_path = $dir.$basename.'.json';

		if (empty($meta['created'])){
			$meta['created'] = date('Y-m-d H:i:s');
		}
		if (empty($meta['project'])){
			$meta['project'] = $this->_project_slug();
		}
		if (is_file($zip_path)){
			$meta['filesize'] = filesize($zip_path);
		}

		$json = json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

		if (is_file($zip_path)){
			$zip = new \ZipArchive();
			if ($zip->open($zip_path) === true){
				$zip->deleteName('dump.json');
				$zip->addFromString('dump.json', $json);
				$zip->close();
				$meta['filesize'] = filesize($zip_path);
				$json = json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
			}
		}

		file_put_contents($json_path, $json);

		return $meta;

	}

	/**
	 * Scan cache/backup for dump_*.zip (legacy + project-prefixed).
	 * Meta: sidecar first, else dump.json inside zip.
	 */
	function _scan_backups(){

		$dir = $this->_backup_dir();
		$list = [];

		if (!is_dir($dir)){
			return $list;
		}

		foreach (glob($dir.'dump_*.zip') as $zip_path){
			$basename = basename($zip_path, '.zip');
			$json_path = $dir.$basename.'.json';
			$meta = [];
			if (is_file($json_path)){
				$raw = file_get_contents($json_path);
				$decoded = cms_json_decode($raw, $basename.'.json');
				if (is_array($decoded)){
					$meta = $decoded;
				}
			}
			if (empty($meta)){
				$meta = $this->_read_meta_from_zip($zip_path);
			}

			$size = filesize($zip_path);
			$created = !empty($meta['created'])
					? $meta['created']
					: date('Y-m-d H:i:s', filemtime($zip_path));

			$list[] = [
					'basename' => $basename,
					'filename' => $basename.'.zip',
					'created' => $created,
					'filesize' => $size,
					'size_label' => $this->makesize($size),
					'project' => $meta['project'] ?? '',
					'tables' => $meta['tables'] ?? [],
					'resources' => $meta['resources'] ?? [],
					'resize_images' => !empty($meta['resize_images']) || !empty($meta['compress_images']),
					'resize_max_px' => (int)($meta['resize_max_px'] ?? $meta['compress_max_px'] ?? 0),
					'mtime' => filemtime($zip_path),
			];
		}

		usort($list, function($a, $b){
			return ($b['mtime'] ?? 0) <=> ($a['mtime'] ?? 0);
		});

		return $list;

	}

	/**
	 * Parse CREATE TABLE names from a SQL dump.
	 */
	function _tables_from_sql_file($sqlfile){

		$tables = [];
		if (!is_file($sqlfile)){
			return $tables;
		}
		$raw = file_get_contents($sqlfile);
		if ($raw === false || $raw === ''){
			return $tables;
		}
		if (preg_match_all('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`([^`]+)`/i', $raw, $m)){
			foreach ($m[1] as $t){
				$tables[] = $t;
			}
		}
		return array_values(array_unique($tables));

	}

	/**
	 * Import SQL dump: DROP IF EXISTS each CREATE TABLE, then run statements.
	 * No MySQL *_bu shadow tables.
	 */
	function _import_sql_file($sqlfile){

		$this->load->model('cms/cms_update_model');

		foreach ($this->_tables_from_sql_file($sqlfile) as $table){
			$this->cms_update_model->run_sql('DROP TABLE IF EXISTS `'.$table.'`');
		}

		$templine = '';
		$lines = file($sqlfile);
		if ($lines === false){
			return;
		}
		foreach ($lines as $line) {
			if (substr($line, 0, 2) == '--' || $line == ''){
				continue;
			}
			$templine .= $line;
			if (substr(trim($line), -1, 1) == ';') {
				$this->cms_update_model->run_sql($templine);
				$templine = '';
			}
		}

	}

	/**
	 * Apply a backup zip: extract resources (skip dump.json), import db.sql if present.
	 */
	function _apply_backup_zip($zip_path){

		$upload = $this->_upload_path_abs();
		$tmp = $GLOBALS['config']['base_path'].'cache/dump_restore/';
		if (is_dir($tmp)){
			$this->rrmdir($tmp);
		}
		mkdir($tmp, 0755, true);

		$zip = new \ZipArchive();
		if ($zip->open($zip_path) !== true){
			$this->rrmdir($tmp);
			_html_error('Could not open backup zip', 500);
			return;
		}

		// Extract everything to temp first
		$zip->extractTo($tmp);
		$zip->close();

		// Move resource files into upload_path; handle db.sql separately
		$sql_tmp = $tmp.'db.sql';
		$has_sql = is_file($sql_tmp);

		$iterator = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator($tmp, \FilesystemIterator::SKIP_DOTS),
				\RecursiveIteratorIterator::SELF_FIRST
		);

		foreach ($iterator as $item){
			$full = $item->getPathname();
			$rel = str_replace('\\', '/', substr($full, strlen(rtrim(str_replace('\\', '/', $tmp), '/')) + 1));
			if ($rel === '' || $rel === 'db.sql' || $rel === 'dump.json'){
				continue;
			}
			$dest = $upload.$rel;
			if ($item->isDir()){
				if (!is_dir($dest)){
					mkdir($dest, 0755, true);
				}
				continue;
			}
			$dest_dir = dirname($dest);
			if (!is_dir($dest_dir)){
				mkdir($dest_dir, 0755, true);
			}
			// overwrite resource file
			copy($full, $dest);
		}

		if ($has_sql){
			$this->_import_sql_file($sql_tmp);
		}

		$this->rrmdir($tmp);

	}

	function _redirect_dump(){
		header('Location: '.$GLOBALS['config']['base_url'].'admin/dump/', true, 302);
		die();
	}

	/**
	 * Encode selected tables for JSON: full module or module/table.
	 */
	function _encode_tables_meta($tables_by_module, $selected){

		$selected_map = array_fill_keys($selected, true);
		$out = [];

		foreach ($tables_by_module as $module => $tables){
			if (empty($tables)){
				continue;
			}
			$picked = [];
			foreach ($tables as $table){
				if (isset($selected_map[$table])){
					$picked[] = $table;
				}
			}
			if (empty($picked)){
				continue;
			}
			if (count($picked) === count($tables)){
				$out[] = $module;
			} else {
				foreach ($picked as $table){
					$out[] = $module.'/'.$table;
				}
			}
		}

		return $out;

	}

	/**
	 * Encode resource selection for JSON.
	 * Current calendar year always as year/month entries (never whole year).
	 */
	function _encode_resources_meta($selected_keys){

		$current_year = date('Y');
		$by_year = [];
		$year_only = [];

		foreach ($selected_keys as $key){
			$key = trim((string)$key);
			if ($key === ''){
				continue;
			}
			if (preg_match('/^\d{4}$/', $key)){
				$year_only[$key] = true;
				continue;
			}
			if (preg_match('#^(\d{4})/(\d{2})$#', $key, $m)){
				$by_year[$m[1]][] = $m[2];
			}
		}

		$out = [];

		// Year-only (never for current year)
		foreach (array_keys($year_only) as $year){
			if ($year === $current_year){
				// Expand to all months of current year for metadata
				for ($m = 1; $m <= 12; $m++){
					$out[] = sprintf('%s/%02d', $year, $m);
				}
			} else {
				$out[] = $year;
			}
		}

		foreach ($by_year as $year => $months){
			$months = array_values(array_unique($months));
			sort($months);
			// Current year: always list months
			// Other years: if all 12 months selected and no year-only, still list months if partial;
			// if all 12 and not current year, could collapse to year — user asked current year always months;
			// for other years with 12 months selected as months, list months (selection was month-level)
			foreach ($months as $mm){
				$entry = $year.'/'.$mm;
				if (!in_array($entry, $out, true)){
					$out[] = $entry;
				}
			}
		}

		sort($out);
		return array_values(array_unique($out));

	}

	/**
	 * Expand UI resource keys to concrete YYYY/MM month paths for packing.
	 */
	function _expand_resource_months($selected_keys){

		$months = [];
		foreach ($selected_keys as $key){
			$key = trim((string)$key);
			if ($key === ''){
				continue;
			}
			if (preg_match('/^\d{4}$/', $key)){
				for ($m = 1; $m <= 12; $m++){
					$months[] = sprintf('%s/%02d', $key, $m);
				}
				continue;
			}
			if (preg_match('#^\d{4}/\d{2}$#', $key)){
				$months[] = $key;
			}
		}
		$months = array_values(array_unique($months));
		sort($months);
		return $months;

	}

	function _upload_path_abs(){
		$path = $GLOBALS['config']['upload_path'];
		if ($path === '' || $path === false){
			return $GLOBALS['config']['base_path'].'img/';
		}
		// Absolute or already full
		if (preg_match('#^[a-zA-Z]:[\\\\/]#', $path) || strpos($path, '/') === 0){
			return rtrim(str_replace('\\', '/', $path), '/').'/';
		}
		return rtrim(str_replace('\\', '/', $GLOBALS['config']['base_path'].$path), '/').'/';
	}

	/**
	 * CMS derivative basename pattern: _{name}.{width}.{ext}
	 */
	function _is_image_derivative_basename($basename){
		return (bool)preg_match('/^_.+\.\d+\.[a-z0-9]+$/i', $basename);
	}

	/**
	 * On-disk derivative path next to original: dir/_{name}.{max_px}.{ext}
	 */
	function _image_derivative_path($source_path, $max_px){

		$dir = dirname($source_path);
		$base = pathinfo($source_path, PATHINFO_FILENAME);
		$ext = strtolower(pathinfo($source_path, PATHINFO_EXTENSION));
		if ($ext === 'jpeg'){
			$ext = 'jpg';
		}

		return $dir.DIRECTORY_SEPARATOR.'_'.$base.'.'.(int)$max_px.'.'.$ext;

	}

	function add_month_to_zip($zip, $month_string, $resize_images = false, $resize_max_px = 0){

		$imagesdir = $this->_upload_path_abs().$month_string;

		if (!is_dir($imagesdir)){
			return;
		}

		$iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($imagesdir));
		$files = array_keys(iterator_to_array($iterator, true));

		foreach ($files as $file) {
			if (!is_file($file)){
				continue;
			}

			$new_name = $month_string.'/'.str_replace('\\', '/', trim(str_replace(trim($imagesdir, '/\\'), '', $file), '/\\'));

			$source = $file;
			if ($resize_images && $resize_max_px > 0){
				$basename = basename($file);
				// Only resize library originals — keep existing derivatives as-is
				if (!$this->_is_image_derivative_basename($basename)){
					$resized = $this->_resize_image_for_backup($file, $resize_max_px);
					if ($resized !== '' && is_file($resized)){
						$source = $resized;
					}
				}
			}

			// Zip entry keeps the original relative path (import-safe names)
			$zip->addFile($source, $new_name);
		}

	}

	/**
	 * If image max side exceeds $max_px, ensure dir/_{name}.{max_px}.{ext} exists and return it.
	 * Reuses existing derivative when present. Zip caller still packs under the original filename.
	 */
	function _resize_image_for_backup($source_path, $max_px){

		$max_px = (int)$max_px;
		if ($max_px < 1 || !is_file($source_path)){
			return $source_path;
		}

		$imagetype = @exif_imagetype($source_path);
		if (!in_array($imagetype, [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)){
			return $source_path;
		}

		$size = @getimagesize($source_path);
		if (empty($size[0]) || empty($size[1])){
			return $source_path;
		}

		$width = (int)$size[0];
		$height = (int)$size[1];
		$max_dim = max($width, $height);
		if ($max_dim <= $max_px){
			return $source_path;
		}

		$target = $this->_image_derivative_path($source_path, $max_px);
		if (is_file($target) && filesize($target) > 0){
			return $target;
		}

		if ($width >= $height){
			$new_width = $max_px;
			$new_height = (int)max(1, round($height * $max_px / $width));
		} else {
			$new_height = $max_px;
			$new_width = (int)max(1, round($width * $max_px / $height));
		}

		if ($imagetype === IMAGETYPE_JPEG){
			$src = @imagecreatefromjpeg($source_path);
		} else {
			$src = @imagecreatefrompng($source_path);
		}

		if (empty($src)){
			return $source_path;
		}

		$tmp_img = imagecreatetruecolor($new_width, $new_height);
		if ($imagetype === IMAGETYPE_PNG){
			imagealphablending($tmp_img, false);
			imagesavealpha($tmp_img, true);
		}

		imagecopyresampled($tmp_img, $src, 0, 0, 0, 0, $new_width, $new_height, $width, $height);

		$ok = false;
		if ($imagetype === IMAGETYPE_JPEG){
			$ok = imagejpeg($tmp_img, $target, 85);
		} else {
			$ok = imagepng($tmp_img, $target, 6);
		}

		imagedestroy($src);
		imagedestroy($tmp_img);

		if (!$ok || !is_file($target)){
			return $source_path;
		}

		return $target;

	}

	function _post_array($key){
		if (!isset($_POST[$key])){
			return [];
		}
		$val = $_POST[$key];
		if (!is_array($val)){
			return $val === '' || $val === null ? [] : [(string)$val];
		}
		$out = [];
		foreach ($val as $v){
			$v = trim((string)$v);
			if ($v !== ''){
				$out[] = $v;
			}
		}
		return $out;
	}

	function panel_action($params){

		$this->load->model('cms/cms_update_model');

		$do = $params['do'] ?? ($_POST['do'] ?? '');

		if (empty($do)){
			return $params;
		}

		ini_set('memory_limit', '1G');
		set_time_limit(600);

		if ($do === 'generate_backup' || $do === 'generate'){

			$tables_by_module = $this->_get_tables_by_module();
			$all_tables = $this->_get_all_schema_tables();

			$selected_tables = $this->_post_array('tables');
			if ($do === 'generate_backup'){
				// Whitelist against schema inventory (empty = no tables by design)
				$allowed = array_fill_keys($all_tables, true);
				$selected_tables = array_values(array_filter($selected_tables, function($t) use ($allowed){
					return isset($allowed[$t]);
				}));
			} else if (empty($selected_tables)){
				// Legacy do=generate without selection → all schema tables
				$selected_tables = $all_tables;
			}

			$selected_resources = $this->_post_array('resources');
			if ($do !== 'generate_backup' && empty($selected_resources)){
				$selected_resources = $this->_default_resource_months();
			}

			$resize_images = !empty($_POST['resize_images']) || !empty($_POST['compress_images']);
			$resize_max_px = self::COMPRESS_MAX_PX;
			if (isset($_POST['resize_max_px']) && (int)$_POST['resize_max_px'] > 0){
				$resize_max_px = (int)$_POST['resize_max_px'];
			} else if (isset($_POST['compress_max_px']) && (int)$_POST['compress_max_px'] > 0){
				$resize_max_px = (int)$_POST['compress_max_px'];
			}
			// Keep size sane for path safety
			if ($resize_max_px < 1){
				$resize_max_px = self::COMPRESS_MAX_PX;
			}
			if ($resize_max_px > 10000){
				$resize_max_px = 10000;
			}

			include_once($GLOBALS['config']['base_path'].'system/vendor/mysqldump/mysqldump.php');

			$backup_dir = $this->_ensure_backup_dir();
			$basename = $this->_allocate_backup_basename();
			$outfile = $backup_dir.$basename.'.zip';
			$sql_temp = $GLOBALS['config']['base_path'].'cache/_database_'.$basename.'.sql';
			if (file_exists($sql_temp)){
				unlink($sql_temp);
			}

			$zip = new \ZipArchive();
			if ($zip->open($outfile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
				_html_error('Could not create backup zip: '.$outfile, 500);
				return $params;
			}

			$months = $this->_expand_resource_months($selected_resources);
			foreach ($months as $month_string){
				$this->add_month_to_zip($zip, $month_string, $resize_images, $resize_max_px);
			}

			if (!empty($selected_tables)){
				Export_Database(
						$GLOBALS['config']['database']['hostname'],
						$GLOBALS['config']['database']['username'],
						$GLOBALS['config']['database']['password'],
						$GLOBALS['config']['database']['database'],
						$selected_tables,
						$sql_temp
				);

				if (is_file($sql_temp)){
					$zip->addFile($sql_temp, 'db.sql');
				}
			}

			$zip->close();

			if (is_file($sql_temp)){
				unlink($sql_temp);
			}

			$meta = [
					'project' => $this->_project_slug(),
					'created' => date('Y-m-d H:i:s'),
					'tables' => $this->_encode_tables_meta($tables_by_module, $selected_tables),
					'resources' => $this->_encode_resources_meta($selected_resources),
					'resize_images' => $resize_images ? 1 : 0,
					'resize_max_px' => $resize_images ? $resize_max_px : 0,
			];
			$this->_write_backup_meta($basename, $meta);

			$this->_redirect_dump();

		}

		// Upload zip into backup library (does not restore)
		if ($do === 'cms_dump_upload' || $do === 'upload_backup'){

			$tmp_dir = $GLOBALS['config']['base_path'].'cache/dump_upload/';
			if (is_dir($tmp_dir)){
				$this->rrmdir($tmp_dir);
			}
			mkdir($tmp_dir, 0755, true);

			$this->load->library('upload', [
					'allowed_types' => 'zip',
					'upload_path' => $tmp_dir,
			]);

			if ( ! $this->upload->do_upload('file')) {
				$this->rrmdir($tmp_dir);
				_html_error('Problem with file upload: '.$this->upload->display_errors().'<br>'.
						'Upload path: '.$tmp_dir.'<br>'.
						'Filename: '.($_FILES['file']['name'] ?? ''), 500);
			}

			$upload_data = $this->upload->data();
			$tmp_file = $tmp_dir.$upload_data['file_name'];

			$backup_dir = $this->_ensure_backup_dir();
			$basename = $this->_allocate_backup_basename();
			$dest = $backup_dir.$basename.'.zip';

			if (!@rename($tmp_file, $dest)){
				if (!@copy($tmp_file, $dest)){
					$this->rrmdir($tmp_dir);
					_html_error('Could not store uploaded backup', 500);
				}
			}
			$this->rrmdir($tmp_dir);

			$meta = $this->_read_meta_from_zip($dest);
			if (empty($meta)){
				$meta = [
						'project' => $this->_project_slug(),
						'created' => date('Y-m-d H:i:s'),
						'tables' => [],
						'resources' => [],
						'source' => 'upload',
				];
			} else {
				$meta['source'] = 'upload';
				if (empty($meta['created'])){
					$meta['created'] = date('Y-m-d H:i:s');
				}
			}
			$this->_write_backup_meta($basename, $meta);

			$this->_redirect_dump();

		}

		// Restore from server-side backup zip
		if ($do === 'restore_backup'){

			$basename = $this->_safe_backup_basename($_POST['basename'] ?? $params['basename'] ?? '');
			if ($basename === ''){
				_html_error('Invalid backup file', 400);
			}
			$zip_path = $this->_backup_dir().$basename.'.zip';
			if (!is_file($zip_path)){
				_html_error('Backup not found', 404);
			}

			$this->_apply_backup_zip($zip_path);
			$this->_redirect_dump();

		}

		// Download backup zip
		if ($do === 'download_backup'){

			$basename = $this->_safe_backup_basename($_POST['basename'] ?? $params['basename'] ?? $_GET['basename'] ?? '');
			if ($basename === ''){
				_html_error('Invalid backup file', 400);
			}
			$zip_path = $this->_backup_dir().$basename.'.zip';
			if (!is_file($zip_path)){
				_html_error('Backup not found', 404);
			}

			header('Content-Disposition: attachment; filename="'.$basename.'.zip"');
			header('Content-Type: application/zip');
			header('Content-Length: '.filesize($zip_path));
			ini_set('memory_limit', '1G');
			readfile($zip_path);
			exit();

		}

		// Delete backup zip + sidecar
		if ($do === 'delete_backup'){

			$basename = $this->_safe_backup_basename($_POST['basename'] ?? $params['basename'] ?? '');
			if ($basename === ''){
				_html_error('Invalid backup file', 400);
			}
			$dir = $this->_backup_dir();
			$zip_path = $dir.$basename.'.zip';
			$json_path = $dir.$basename.'.json';
			if (is_file($zip_path)){
				unlink($zip_path);
			}
			if (is_file($json_path)){
				unlink($json_path);
			}
			$this->_redirect_dump();

		}

		return $params;

	}

	function panel_params($params){

		$tables_by_module = $this->_get_tables_by_module();

		$params['tables_by_module'] = $tables_by_module;
		$params['resources_tree'] = $this->_get_resources_tree();
		$params['backups'] = $this->_scan_backups();
		$params['resize_max_px'] = self::COMPRESS_MAX_PX;
		$params['page_title'] = 'Data and backup';

		return $params;

	}

}
