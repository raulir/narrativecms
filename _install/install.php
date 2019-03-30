<?php

/**
 * BC CMS install script
 */

// detect hostname

$hostname = $_SERVER['SERVER_NAME'];

if ($hostname == 'localhost'){
	
	$url_parts = parse_url($_SERVER['REQUEST_URI']);
	$url_parts = explode('/', $url_parts['path']);
	
	$project_name = $url_parts[1];
	
} else {
	
	$project_name = $hostname;
	
}

$project_name = str_replace(['www.','.com','.dev','.co.uk','-','0','1','2','3','4','5','6','7','8','9',], '', $project_name);

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
				<label style="display: block; " for="page_title">Page title (can be seen on the top of the browser window, Google search and when shared in social media, uppercase first letters): </label>
				<input id="page_title" value="<?= ucfirst($project_name); ?>"><br><br>
			</div>
			
			<div>
				<label style="display: block; " for="project_name">Project name (only lowercase letters, max 10 letters): </label>
				<input id="project_name" value="<?= substr($project_name, 0, 10) ?>"><br><br>
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
				<label style="display: block; " for="db_createnew">I want to create new database<br>(Selecting "No" may cause losing existing data in database): </label>
				<select id="db_createnew">
					<option value="yes" selected="selected">Yes</option>
					<option value="no">No (Add tables to existing database)</option>
					<option value="exists">Exists (Use database with existing CMS data or import manually)</option>
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
				<label style="display: block; " for="admin_pass">CMS admin password: </label>
				<input id="admin_pass" value=""> <span class="generate_admin_password" style="cursor: pointer; ">generate new</span><br><br><br>
			</div>
			
			<div class="step_3_next" style="cursor: pointer; ">NEXT</div>
		
		</div>
		
		<div class="step_4" style="display: none; ">
		
			<div>STEP 4 (checks):<br><br></div>
			
			<div>
				<span class="is_writable">&nbsp;</span> Is directory writable?
			</div>
			
			<div>
				<span class="is_db_accessible">&nbsp;</span> Is database accessible?
			</div>
			
			<div>
				<span class="is_db_name_available">&nbsp;</span> Is database name available?
			</div>
			
			<div>
				<span class="is_db_can_database">&nbsp;</span> Is database user able to create database?
			</div>
			
			<div>
				<span class="is_db_can_tables">&nbsp;</span> Is database user able to create tables?
			</div>
			
			<div>
				<span class="is_db_can_user">&nbsp;</span> Is database user able to create user?
			</div>
			
			<div>
				<span class="is_internet">&nbsp;</span> Is this possible to download files?
			</div>
						
			<div class="check_again" style="cursor: pointer; ">CHECK AGAIN</div>
			
			<div class="step_4_next" style="cursor: pointer; ">NEXT</div>
		
		</div>

		<script>

			function make_string(length){
			    var text = '';
			    var possible = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz2345678923456789';
	
			    for( var i=0; i < length; i++ )
			        text += possible.charAt(Math.floor(Math.random() * possible.length));
	
			    return text;
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

					$('#db_pass').val(make_string(8));
					
				});

				/**
				*	step 2 functionality
				*/
				
				$('.step_2_next').on('click', function(){

					// show step 3
					$('.step_2').css({'display':'none'});
					$('.step_3').css({'display':'block'});

					$('#admin_user').val($('#project_name').val());
					$('#admin_pass').val(make_string(8));
					
				});
				
				$('.generate_db_password').on('click', function(){
					$('#db_pass').val(make_string(8));
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

					// show step 3
					$('.step_3').css({'display':'none'});
					$('.step_4').css({'display':'block'});
					
				});
				
				$('.generate_admin_password').on('click', function(){
					$('#admin_pass').val(make_string(8));
				});
	
			});
		
		</script>
	
	</body>

</html>
