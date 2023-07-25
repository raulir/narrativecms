<?php

/**
 * Narrative CMS install script
 */

// actions
if (!empty($_POST['do'])){
	$do = $_POST['do'];
	
	if ($do == 'is_writable'){
	
		$current_dir = str_replace("\\", "/", rtrim(getcwd(), " /\\")).'/';
		$dir_writable = is_writable($current_dir);
		if ($dir_writable){
			print(json_encode(['ok' => 1]));
		} else {
			print(json_encode(['ok' => 0]));
		}
		die();
	
	}
	
	if ($do == 'is_db_accessible'){

		$link = mysqli_connect($_POST['db_host'], $_POST['db_admin_user'], $_POST['db_admin_pass']);

		if ($link){
			print(json_encode(['ok' => 1]));
		} else {
			print(json_encode(['ok' => 0]));
		}
		die();
	
	}
	
	if ($do == 'is_db_name_available'){
		
		$link = mysqli_connect($_POST['db_host'], $_POST['db_admin_user'], $_POST['db_admin_pass']);
		
		$result = $link->query('show databases');
		
		$data = [];
		while ($row = $result->fetch_assoc()) {
  			$data[] = $row['Database'];
		}
		
		if (!in_array($_POST['db_db'], $data)){
			print(json_encode(['ok' => 1]));
		} else {
			print(json_encode(['ok' => 0]));
		}
		die();
		
	}
	
	if ($do == 'is_db_user_available'){
		
		$link = mysqli_connect($_POST['db_host'], $_POST['db_admin_user'], $_POST['db_admin_pass']);
		
		$result = $link->query('select User from mysql.user');
		
		$data = [];
		while ($row = $result->fetch_assoc()) {
  			$data[] = $row['User'];
		}
		
		if (!in_array($_POST['db_user'], $data)){
			print(json_encode(['ok' => 1]));
		} else {
			print(json_encode(['ok' => 0]));
		}
		die();
		
	}
	
	if ($do == 'is_internet'){
	
		$postdata = http_build_query(array('do' => 'version', ));
		$context  = stream_context_create(array('http' => array(
				'method'  => 'POST',
				'header'  => 'Content-type: application/x-www-form-urlencoded',
				'content' => $postdata
		)));
		 
		$master_data = @file_get_contents('http://update.narrativecms.com/cms/updater/', false, $context);

		if ($master_data === false){
			print(json_encode(['ok' => 0]));
		} else {
			print(json_encode(['ok' => 1]));
		}
		die();
	
	}
	
	if ($do == 'install_files'){
		
		$starttime = time();
	
		$postdata = http_build_query(array('do' => 'files', ));
		$context  = stream_context_create(array('http' => array(
				'method'  => 'POST',
				'header'  => 'Content-type: application/x-www-form-urlencoded',
				'content' => $postdata
		)));
			
		$master_data = @file_get_contents('http://update.narrativecms.com/cms/updater/', false, $context);
		$master_data = json_decode($master_data, true);
		
		$master_length = count($master_data['files']);
		
		$dir = str_replace("\\", "/", rtrim(getcwd(), " /\\")).'/';
		
		if (!file_exists($dir.'cache/')){
			mkdir($dir.'cache/');
		}
		
		if (!file_exists($dir.'img/')){
			mkdir($dir.'img/');
		}
		
		if (!file_exists($dir.'cache/install.txt') || file_get_contents($dir.'cache/install.txt') == 'done'){
			file_put_contents($dir.'cache/install.txt', '0/'.$master_length);
		}
		
		list($counter, $rest) = explode('/', file_get_contents($dir.'cache/install.txt'));
		
		foreach($master_data['files'] as $file){
			
			if (!file_exists($dir.$file['filename']) || md5_file($dir.$file['filename']) != $file['hash'] || filesize($dir.$file['filename']) != $file['size']){
				
				// create cache folder if not exists
				$pathinfo = pathinfo($dir . $file['filename']);
				if (!file_exists($pathinfo['dirname'])) {
					mkdir($pathinfo['dirname'], 0777, true);
				}
				
				// get master file
				$postdata = http_build_query(array('do' => 'file', 'filename' => $file['filename'], ));
				$context  = stream_context_create(array('http' => array(
				        'method'  => 'POST',
		        		'header'  => 'Content-type: application/x-www-form-urlencoded',
		        		'content' => $postdata
		    	)));
		
				$master_file_data = file_get_contents('http://update.narrativecms.com/cms/updater/', false, $context);
				$master_file_data = json_decode($master_file_data, true);
					
				// replace local file
				$file_content = base64_decode($master_file_data['file']);
				file_put_contents($dir.$file['filename'], $file_content);
				
				$counter += 1;
				file_put_contents($dir.'cache/install.txt', $counter.'/'.$master_length);
				
				// TODO: doesn't delete extra existing files
				
				// if more than 10s, then next try
				if (time() - $starttime > 10){
					print(json_encode(['ok' => 0]));
					die();
				}

			}
			
		}
		
		file_put_contents($dir.'cache/install.txt', 'done');
		print(json_encode(['ok' => 1]));

		die();
	
	}
	
	if ($do == 'install_database'){
		
		// create database
		$mysqli = mysqli_connect($_POST['db_host'], $_POST['db_admin_user'], $_POST['db_admin_pass']);
		$mysqli->set_charset('utf8mb4');
		
		// database
		$mysqli->query('create database '.$_POST['db_db']);
		
		// user
		$db_relative = $_POST['db_host'] == 'localhost' ? 'localhost' : '%';
		
		$query = $mysqli->prepare('insert into `mysql`.`user` (Host, User, Password) values (?,?,password(?))');
		$query->bind_param('sss', $db_relative, $_POST['db_user'], $_POST['db_pass']);
		$query->execute();
		$query->close();
		
		$mysqli->query('grant all privileges on '.$_POST['db_db'].'.* to \''.$_POST['db_user'].'\'@\''.$db_relative.'\' '.
				'identified by \''.$_POST['db_pass'].'\' ');
		
		$mysqli->query('flush privileges');
		
		// init database
		$mysqli->select_db($_POST['db_db']);
		
		$sql = '
CREATE TABLE `cms_file` (
  `cms_file_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `cms_user_id` int(10) unsigned NOT NULL,
  `sort` int(10) unsigned NOT NULL,
  `filename` varchar(100) NOT NULL,
  `name` varchar(100) NOT NULL,
  `icon` varchar(100) NOT NULL,
  `title` varchar(500) NOT NULL,
  `date_posted` datetime NOT NULL,
  PRIMARY KEY (`cms_file_id`),
  KEY `user_id` (`cms_user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4;

CREATE TABLE `cms_image` (
  `cms_image_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `filename` varchar(100) NOT NULL,
  `hash` varchar(40) NOT NULL,
  `title` varchar(500) NOT NULL DEFAULT \'\',
  `description` varchar(500) NOT NULL DEFAULT \'\',
  `category` varchar(30) NOT NULL,
  `meta` mediumtext NOT NULL,
  `keyword` varchar(200) NOT NULL,
  PRIMARY KEY (`cms_image_id`),
  KEY `filename_idx` (`filename`(10)),
  KEY `category_idx` (`category`(10)),
  KEY `hash_idx` (`hash`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4;

CREATE TABLE `cms_keyword` (
  `cms_keyword_id` varchar(100) NOT NULL,
  PRIMARY KEY (`cms_keyword_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `cms_page` (
  `cms_page_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `sort` int(11) unsigned NOT NULL,
  `slug` varchar(100) NOT NULL,
  `meta` mediumtext NOT NULL,
  PRIMARY KEY (`cms_page_id`),
  KEY `slug_idx` (`slug`(4))
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4;

CREATE TABLE `cms_page_panel` (
  `cms_page_panel_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `cms_page_id` int(10) unsigned NOT NULL,
  `parent_id` int(10) unsigned NOT NULL,
  `show` int(10) unsigned NOT NULL,
  `sort` int(10) unsigned NOT NULL,
  `title` varchar(100) NOT NULL,
  `panel_name` varchar(50) NOT NULL,
  `submenu_anchor` varchar(50) NOT NULL,
  `submenu_title` varchar(100) NOT NULL,
  PRIMARY KEY (`cms_page_panel_id`),
  KEY `page_idx` (`cms_page_id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4;

CREATE TABLE `cms_page_panel_param` (
  `cms_page_panel_param_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `cms_page_panel_id` int(10) unsigned NOT NULL,
  `name` varchar(50) NOT NULL,
  `value` mediumtext NOT NULL,
  `search` int(10) unsigned NOT NULL,
  PRIMARY KEY (`cms_page_panel_param_id`),
  UNIQUE KEY `cms_page_panel_idx` (`cms_page_panel_id`,`name`),
  KEY `search_idx` (`search`),
  KEY `value_idx` (`value`(10))
) ENGINE=InnoDB AUTO_INCREMENT=438 DEFAULT CHARSET=utf8mb4;

CREATE TABLE `cms_search_cache` (
  `term` varchar(30) NOT NULL,
  `cached_time` int(11) NOT NULL,
  `result` mediumtext NOT NULL,
  PRIMARY KEY (`term`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `cms_slug` (
  `cms_slug_id` varchar(100) NOT NULL,
  `target` varchar(100) NOT NULL,
  `status` int(10) unsigned NOT NULL,
  PRIMARY KEY (`cms_slug_id`),
  KEY `target_idx` (`target`(10))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `cms_text` (
  `cms_text_id` varchar(50) NOT NULL,
  `text` mediumtext NOT NULL,
  KEY `cms_text_id` (`cms_text_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `cms_user` (
  `cms_user_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(50) NOT NULL,
  `access` varchar(250) NOT NULL,
  `name` varchar(50) NOT NULL,
  `email` varchar(50) NOT NULL,
  `sort` int(10) unsigned NOT NULL,
  PRIMARY KEY (`cms_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `cms_api` (
  `data_id` varchar(20) NOT NULL,
  `type` varchar(20) NOT NULL,
  `data` varchar(1000) NOT NULL,
  `created` bigint(20) NOT NULL,
  `updated` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
				
ALTER TABLE `cms_api`
  ADD UNIQUE KEY `data_idx` (`data_id`);

CREATE TABLE `cms_api_token` (
  `token` char(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `ip` varchar(20) NOT NULL,
  `valid` int(10) UNSIGNED NOT NULL,
  `created` int(10) UNSIGNED NOT NULL,
  `updated` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
				
ALTER TABLE `cms_api_token`
  ADD PRIMARY KEY (`token`);

INSERT INTO cms_page VALUES
("1","1","homepage","{\"title\":\"Homepage\",\"status\":\"0\",\"seo_title\":\"\",\"description\":\"\",\"image\":\"\",\"layout\":\"rem\"}");

INSERT INTO cms_page_panel VALUES
("1","0","0","0","0","","cms/cms_settings","",""),
("2","0","0","0","0","","cms/cms_cssjs_settings","","");				

INSERT INTO cms_page_panel_param VALUES
("372","2","css.000","modules/cms/css/cms_mini_normalise.scss","0"),
("373","2","","{\"css\":{\"000\":\"modules\\/cms\\/css\\/cms_mini_normalise.scss\"}}","0"),
("406","1","favicon","","0"),
("407","1","site_title","#page# - '.trim($_POST['page_title'], "\n\t -").'","0"),
("408","1","site_title_delimiter","-","0"),
("409","1","landing_page.target","_page","0"),
("410","1","landing_page.cms_page_id","1","0"),
("411","1","landing_page.url","homepage/","0"),
("412","1","landing_page.text","Homepage","0"),
("413","1","landing_page.target_id","","0"),
("414","1","landing_page._value","1","0"),
("415","1","email","","0"),
("416","1","panel_cache","0","0"),
("417","1","inline_limit","100000","0"),
("418","1","targets_enabled","0","0"),
("419","1","cron_trigger","visits","0"),
("420","1","cms_update_url","http://update.narrativecms.com/cms/updater/","0"),
("421","1","layout","cms/rem","0"),
("422","1","modules.000","cms","0"),
("424","1","rem_px","1400","0"),
("425","1","rem_ratio","2.0","0"),
("426","1","rem_m_px","900","0"),
("427","1","rem_switched","0","0"),
("428","1","rem_k","100","0"),
("429","1","rem_m_k","50","0"),
("430","1","images_quality","85","0"),
("431","1","images_1x","1","0"),
("432","1","images_2x","1.5","0"),
("433","1","images_textarea","0.5","0"),
("434","1","cms_background","","0"),
("435","1","images_rows","4","0"),
("436","1","input_link_order","0","0"),
("437","1","","{\"cms_background\":\"\",\"cms_update_url\":\"http:\\/\\/update.narrativecms.com\\/cms\\/updater\\/\",\"cron_trigger\":\"visits\",\"email\":\"\",'.
'\"favicon\":\"\",\"images_1x\":\"1\",\"images_2x\":\"1.5\",\"images_quality\":\"85\",\"images_rows\":\"4\",\"images_textarea\":\"0.5\",'.
'\"inline_limit\":\"100000\",\"input_link_order\":\"0\",\"landing_page\":{\"cms_page_id\":\"1\",\"target\":\"_page\",\"target_id\":\"\",'.
'\"text\":\"Homepage\",\"url\":\"homepage\\/\",\"_value\":\"1\"},\"layout\":\"rem\",\"modules\":{\"000\":\"cms\"},\"panel_cache\":\"0\",'.
'\"rem_k\":\"100\",\"rem_m_k\":\"50\",\"rem_m_px\":\"900\",\"rem_px\":\"1400\",\"rem_ratio\":\"1.0\",\"rem_switched\":\"0\",'.
'\"site_title\":\"#page# - '.trim($_POST['page_title'], "\n\t -").'\",\"site_title_delimiter\":\"-\",\"targets_enabled\":\"0\"}","0");

INSERT INTO cms_slug VALUES
("homepage","1","1");
		';
		
		$mysqli->multi_query($sql);
		
		print(json_encode(['ok' => 1]));
		
		die();
		
	}
	
	if ($do == 'install_config'){
		
		$current_dir = str_replace("\\", "/", rtrim(getcwd(), " /\\")).'/';
	
		$config = [
				'base_path' => '_auto_',
				'base_url' => str_replace('\\', '/', pathinfo(parse_url($_SERVER['REQUEST_URI'])['path'].'.php')['dirname']).'/',
				'upload_path' => 'img/',
				'upload_url' => 'img/',
				'environment' => $_POST['environment'],
				'errors_visible' => 1,
				'errors_log' => 'cache/errors_'.$_POST['project_name'].'.log',
				'analytics' => 0,
				'cache' => [
						'force_download' => ($_POST['environment'] == 'DEV' || $_POST['environment'] == 'STG' ? 1 : 0),
						'pack_js' => ($_POST['environment'] == 'DEV' ? 0 : 1),
						'pack_css' => ($_POST['environment'] == 'DEV' ? 0 : 1),
				],
				'update' => [
						'allow' => ($_POST['environment'] == 'DEV' ? ['*'] : 0),
				],
				'images_webp' => extension_loaded('gd') ? 'gd' : '',
				'database' => [
						'hostname' => $_POST['db_host'],
						'username' => $_POST['db_user'],
						'password' => $_POST['db_pass'],
						'database' => $_POST['db_db'],
						'dbdriver' => 'mysqli',
				],
				'admin_username' => $_POST['admin_user'],
				'admin_password' => $_POST['admin_pass'],
		];
		
		if ($config['base_url'] == '//') {
			$config['base_url'] = '/';
		}

		if (!file_exists($current_dir.'config')){
			mkdir($current_dir.'config');
		}
		file_put_contents($current_dir.'config/'.strtolower($_SERVER['SERVER_NAME']).'.json', json_encode($config, JSON_PRETTY_PRINT));
		
		// htaccess
		$htaccess = '
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
RewriteCond %{REQUEST_URI} ^/_ [NC]
RewriteCond %{REQUEST_URI} !\.(css|js|xml)$ [NC]
RewriteRule .* - [F,L]

# Everything, what is not set domain goes to set domain
RewriteCond %{HTTP_HOST} !^(.*)\.localhost
RewriteCond %{HTTP_HOST} !^stg\.
RewriteCond %{HTTP_HOST} !^'.str_replace('.', '\.', $_SERVER['SERVER_NAME']).'
RewriteRule ^(.*)$ http://'.$_SERVER['SERVER_NAME'].'%{REQUEST_URI} [R=302,L]

RewriteCond %{ENV:REDIRECT_STATUS} ^$
RewriteCond $1 !^(index\.php|modules|img|css|js|robots\.txt|favicon\.ico)
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ ./index.php?/$1 [L,QSA]';
		
		file_put_contents($current_dir.'.htaccess', $htaccess);
		
		// clean up
		if (file_exists($current_dir.'_install/install.php')){
			rename($current_dir.'_install/install.php', $current_dir.'cache/install.tmp');
			rmdir($current_dir.'_install');
		} else if (file_exists($current_dir.'install.php')){
			rename($current_dir.'install.php', $current_dir.'cache/install.tmp');
		}
		
		print(json_encode(['ok' => 1]));
		die();
	
	}
	
}

// detect hostname

$hostname = $_SERVER['SERVER_NAME'];

if ($hostname == 'localhost'){
	
	$url_parts = parse_url($_SERVER['REQUEST_URI']);
	$url_parts = explode('/', $url_parts['path']);
	
	$project_name = $url_parts[1];
	
} else {
	
	$project_name = $hostname;
	
}

$project_name = str_replace(['www.','.com','.localhost','.co.uk','-','0','1','2','3','4','5','6','7','8','9',], '', $project_name);

if (stristr($project_name, '.')){
	$project_name = str_replace('.', '_', $project_name);
}

$project_name = strtolower($project_name);

?>
<html>

	<head>
	
		<script src="https://code.jquery.com/jquery-3.4.1.min.js"></script>
	
	</head>

	<body>
	
		<div>Narrative CMS install<br><br></div>
		
		<div class="step_1">
		
			<div>STEP 1 (general):<br><br></div>
			
			<div>
				<label style="display: block; " for="page_title">Page title (can be seen on the top of the browser window, Google search and when
						shared in social media, uppercase first letters): </label>
				<input id="page_title" value="<?= ucfirst($project_name); ?>"><br><br>
			</div>
			
			<div>
				<label style="display: block; " for="project_name">Project name (only lowercase letters, max 12 letters): </label>
				<input id="project_name" value="<?= substr($project_name, 0, 12) ?>"><br><br>
			</div>
			
			<div>
				<label style="display: block; " for="environment">Environment: </label>
				<select id="environment">
					<option value="DEV" selected="selected">DEV - development</option>
					<option value="STG">STG - staging</option>
					<option value="">LIVE</option>
				</select><br><br>
			</div>
			
			<div class="step_1_next" style="cursor: pointer; ">NEXT</div>
		
		</div>
		
		<div class="step_2" style="display: none; ">
		
			<div>STEP 2 (database):<br><br></div>
			
			<div>
				<label style="display: block; " for="db_host">DB host: </label>
				<input id="db_host" value="localhost"><br><br>
			</div>
			
			<div>
				<label style="display: block; " for="db_db">DB database: </label>
				<input id="db_db" value=""><br><br><br>
			</div>
			
			<div>DB admin access for making database, if not available or not needed, do not change<br><br></div>
			
			<div>
				<label style="display: block; " for="db_admin_user">DB admin username: </label>
				<input id="db_admin_user" value="root"><br><br>
			</div>
			
			<div>
				<label style="display: block; " for="db_admin_pass">DB admin password: </label>
				<input id="db_admin_pass" value=""><br><br><br>
			</div>

			<div style="display: none; ">
				<label style="display: block; " for="db_createnew">I want to create new database<!-- br>(Selecting "No" may cause losing existing data in 
						database)-->: </label>
				<select id="db_createnew">
					<option value="yes" selected="selected">Yes (only option at the moment)</option>
					<!-- option value="no">No (Add tables to existing database)</option>
					<option value="exists">Exists (Use database with existing CMS data or import manually)</option -->
				</select><br><br>
			</div>
			
			<div class="db_extra">
				<label style="display: block; " for="db_user">New DB username: </label>
				<input id="db_user"><br><br>
			</div>
			
			<div class="db_extra">
				<label style="display: block; " for="db_password">New DB password: </label>
				<input id="db_pass"> <span class="generate_db_password" style="cursor: pointer; ">generate new</span><br><br>
			</div>
			
			<div class="step_2_previous" style="cursor: pointer; ">PREVIOUS</div>
			<div class="step_2_next" style="cursor: pointer; ">NEXT</div>
		
		</div>
	
		<div class="step_3" style="display: none; ">
		
			<div>STEP 3 (project):<br><br></div>
			
			<div>
				<label style="display: block; " for="admin_user">CMS admin username: </label>
				<input id="admin_user" value=""><br><br>
			</div>
			
			<div>
				<label style="display: block; " for="admin_pass">CMS admin password (write this down):</label>
				<input id="admin_pass" value=""> <span class="generate_admin_password" style="cursor: pointer; ">generate new</span><br><br><br>
			</div>
			
			<div class="step_3_previous" style="cursor: pointer; ">PREVIOUS</div>
			<div class="step_3_next" style="cursor: pointer; ">NEXT</div>
		
		</div>
		
		<div class="step_4" style="display: none; ">
		
			<div>STEP 4 (checks):<br><br></div>
			
			<div class="q_writable">
				<span class="is_writable">&nbsp;</span> Is directory writable?
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
				<span class="is_internet">&nbsp;</span> Is this possible to download files?
			</div>
						
			<div class="check_again" style="cursor: pointer; ">CHECK AGAIN</div>
			
			<div class="step_4_previous" style="cursor: pointer; ">PREVIOUS</div>
			<div class="step_4_next" style="cursor: pointer; ">NEXT (INSTALL)</div>
		
		</div>
		
		<div class="step_5" style="display: none; ">
		
			<div class="q_files">
				<span class="install_files">&nbsp;</span> Install files <span class="step_5_files"></span>
			</div>
			
			<div class="q_database">
				<span class="install_database">&nbsp;</span> Install database
			</div>
			
			<div class="q_config">
				<span class="install_config">&nbsp;</span> Set up config files
			</div>

			<div class="step_5_next" style="cursor: pointer; ">OK</div>
		
		</div>

		<div class="step_6" style="display: none; ">
		
			<div class="q_files">
				Thank you!<br>
				<br>
				<a href="./">&gt; Homepage</a><br>
				<a href="./admin/">&gt; CMS admin</a>			
			</div>
		
		</div>

		<script>

			function make_string(length){
			    var text = '';
			    var possible = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz2345678923456789';
			    var extended = possible + possible + possible;
	
			    for( var i=0; i < length; i++ )
			        text += extended.charAt(Math.floor(Math.random() * extended.length));
	
			    return text;
			}
			
			// generalised ajax task
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

						if (data.ok){
							$('.' + check_name).css({'background-color':'green'});
						} else {
							if (!params.no_red){
								$('.' + check_name).css({'background-color':'red'});
							}
						}
						deferred.resolve();

					}
				});

				return deferred.promise();

			}

			$(document).ready(function() {

				/**
				*	step 1 functionality
				*/
				
				$('.step_1_next').on('click', function(){

					// db user is root for localhost and project name for others
					if ($('#environment').val() != 'DEV'){
						$('#db_user').val($('#project_name').val());
					} else {
						$('#db_user').val('root');
					}

					// show step 2
					$('.step_1').css({'display':'none'});
					$('.step_2').css({'display':'block'});
					
					$('#db_db').val($('#project_name').val());
					$('#db_user').val($('#project_name').val());

					$('#db_pass').val(make_string(10));
					
				});

				/**
				*	step 2 functionality
				*/
				
				$('.step_2_next').on('click', function(){

					// show step 3
					$('.step_2').css({'display':'none'});
					$('.step_3').css({'display':'block'});

					$('#admin_user').val($('#project_name').val());
					$('#admin_pass').val(make_string(10));
					
				});
				
				$('.generate_db_password').on('click', function(){
					$('#db_pass').val(make_string(10));
				});

				$('#db_createnew').on('change', function(){
					if ($(this).val() != 'yes'){
						$('.db_extra').css({'display':'none'});
					} else {
						$('.db_extra').css({'display':''});
					}
				});

				$('.step_2_previous').on('click', function(){
					$('.step_2').css({'display':'none'});
					$('.step_1').css({'display':'block'});
				});
				
				/**
				*	step 3 functionality
				*/
				
				$('.step_3_next').on('click', function(){

					// show step 4
					$('.step_3').css({'display':'none'});
					$('.step_4').css({'display':'block'});

					// show questions
					if ($('#db_createnew').val() == 'yes'){
						$('.q_db_can_tables').css({'opacity':'0.2'});
					}

					run_checks();
					
				});
				
				$('.generate_admin_password').on('click', function(){
					$('#admin_pass').val(make_string(10));
				});

				$('.step_3_previous').on('click', function(){
					$('.step_3').css({'display':'none'});
					$('.step_2').css({'display':'block'});
				});

				/**
				*	step 4 functionality
				*/
				
				$('.step_4_next').on('click', function(){

					// show step 5 - installation
					$('.step_4').css({'display':'none'});
					$('.step_5').css({'display':'block'});

					run_installation();
					
				});

				$('.check_again').on('click', run_checks);
				
				function run_checks(){

					// checks for new db
					if ($('#db_createnew').val() == 'yes'){
						do_task('is_writable')
							.then(() => do_task('is_db_accessible', {
								db_host: $('#db_host').val(),
								db_admin_user: $('#db_admin_user').val(),
								db_admin_pass: $('#db_admin_pass').val()
							}))
							.then(() => do_task('is_db_name_available', {
								db_host: $('#db_host').val(),
								db_admin_user: $('#db_admin_user').val(),
								db_admin_pass: $('#db_admin_pass').val(),
								db_db: $('#db_db').val()
							}))
							.then(() => do_task('is_db_user_available', {
								db_host: $('#db_host').val(),
								db_admin_user: $('#db_admin_user').val(),
								db_admin_pass: $('#db_admin_pass').val(),
								db_user: $('#db_user').val()
							}))
							.then(() => do_task('is_internet'));
					}

				}
				
				$('.step_4_previous').on('click', function(){
					$('.step_4').css({'display':'none'});
					$('.step_3').css({'display':'block'});
				});

				function run_installation(){

					// files installation progress
					$('.step_5_files').html('starting');
					setTimeout(() => {
						var installinterval = setInterval(function(){
							$.get('cache/install.txt', function(data){
								if (data == 'done'){
									$('.step_5_files').html('done');
									clearInterval(installinterval);
								} else {
									$('.step_5_files').html(data);
								}
							});
						}, 1000);
					}, 200);

					function try_files(deferred){

						deferred = deferred || $.Deferred();
						
						do_task('install_files', {'no_red':true}).then(() => {
							$.get('cache/install.txt', data => {
								if (data != 'done'){
									try_files(deferred);
								} else {
									deferred.resolve();
								}
							});
						});

						return deferred.promise();

					}
					
					try_files()
						.then(() => do_task('install_database', {
							db_host: $('#db_host').val(),
							db_admin_user: $('#db_admin_user').val(),
							db_admin_pass: $('#db_admin_pass').val(),
							db_db: $('#db_db').val(),
							db_user: $('#db_user').val(),
							db_pass: $('#db_pass').val(),
							page_title: $('#page_title').val()
						}))
						.then(() => do_task('install_config', {
							project_name: $('#project_name').val(),
							environment: $('#environment').val(),
							db_host: $('#db_host').val(),
							db_db: $('#db_db').val(),
							db_user: $('#db_user').val(),
							db_pass: $('#db_pass').val(),
							admin_user: $('#admin_user').val(),
							admin_pass: $('#admin_pass').val()
						}));

				}


				/**
				*	step 5 functionality
				*/
				
				$('.step_5_next').on('click', function(){

					// show step 6 - done
					$('.step_5').css({'display':'none'});
					$('.step_6').css({'display':'block'});
					
				});

			});
		
		</script>
	
	</body>

</html>
