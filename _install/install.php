<?php

/**
 * BC CMS install script
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
		 
		$master_data = @file_get_contents('http://cms.bytecrackers.com/cms/updater/', false, $context);

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
			
		$master_data = @file_get_contents('http://cms.bytecrackers.com/cms/updater/', false, $context);
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
		
				$master_file_data = file_get_contents('http://cms.bytecrackers.com/cms/updater/', false, $context);
				$master_file_data = json_decode($master_file_data, true);
					
				// replace local file
				$file_content = base64_decode($master_file_data['file']);
				file_put_contents($dir.$file['filename'], $file_content);
				
				$counter += 1;
				file_put_contents($dir.'cache/install.txt', $counter.'/'.$master_length);
				
				// doesn't delete extra existing files
				
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
	
		<script src="https://code.jquery.com/jquery-3.1.1.min.js"></script>
	
	</head>

	<body>
	
		<div>BC CMS install<br><br></div>
		
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
				<label style="display: block; " for="db_admin_user">DB username: </label>
				<input id="db_admin_user" value="root"><br><br>
			</div>
			
			<div>
				<label style="display: block; " for="db_admin_pass">DB password: </label>
				<input id="db_admin_pass" value=""><br><br><br>
			</div>

			<div>
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
			
			<div class="step_4_next" style="cursor: pointer; ">NEXT (INSTALL)</div>
		
		</div>
		
		<div class="step_5" style="display: none; ">
		
			<div class="q_files">
				<span class="install_files">&nbsp;</span> Install files <span class="step_5_files"></span>
			</div>

			<div class="step_5_next" style="cursor: pointer; ">DONE</div>
		
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
				
				/**
				*	step 5 functionality
				*/
				
				$('.step_5_next').on('click', function(){

					// show step 5 - installation
					//					$('.step_4').css({'display':'none'});
					//					$('.step_4').css({'display':'block'});
					
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
						}, 500);
					}, 200);

					function try_files(){
						
						return do_task('install_files', {'no_red':true}).then(function(){
							$.get('cache/install.txt', data => {
								if (data != 'done'){
									return try_files();
								} else {
									return;
								}
							});
						});

					}
					
					try_files().then(console.log('next thing!'));

				}

			});
		
		</script>
	
	</body>

</html>
