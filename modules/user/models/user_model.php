<?php

namespace user;

defined('BASEPATH') OR exit('No direct script access allowed');

class user_model extends \Model {
	
	function create_user($data){
		
		$return = ['errors' => []];

		$this->load->model('cms/cms_page_panel_model');
		$data['email'] = $this->normalise_email($data['email'] ?? '');

		if (!empty($data['email'])){
			
			$check_user = $this->cms_page_panel_model->get_cms_page_panels_by(['panel_name' => 'user/user', 'email' => $data['email'], ]);
			$check_user = reset($check_user);
			 
			if ($check_user !== false){
			
				$return['errors'][] = 'emailexists';
			
			}
			
		}
		 
		// Username uniqueness/length only when username is the login name (not email)
		$settings = $this->cms_page_panel_model->get_cms_page_panel_settings('user/user_settings');
		$username_is_login = !empty($settings['show_username']);

		if (!empty($data['username']) && $username_is_login){

			$check_user = $this->cms_page_panel_model->get_cms_page_panels_by(['panel_name' => 'user/user', 'username' => $data['username'], ]);
			$check_user = reset($check_user);

			if ($check_user !== false){
				$return['errors'][] = 'usernameexists';
			}

			if (strlen($data['username']) < 3) {
				$return['errors'][] = 'usernamelength';
			}

		}
		
		if (!empty($data['email'])){
			
			if (!stristr($data['email'], '@') || !stristr($data['email'], '.')){
					
				$return['errors'][] = 'bademail';
			
			}
			
		}
		
		if (!empty($return['errors'])){
			return $return;
		}

		$this->load->model('cms/cms_access_model');
		$default_access = $this->cms_access_model->get_default_access_for_new_user();
		
		$meta = !empty($data['meta']) && is_array($data['meta']) ? $data['meta'] : [];

		if (in_array('music', $GLOBALS['config']['modules'] ?? [])){
			$this->load->model('music/music_user_model');
			$meta = $this->music_user_model->merge_new_user_meta($meta);
		}

		$user = [
				'panel_name' => 'user/user',
				'show' => 1,
				'sort' => 'first',
				'username' => $data['username'],
				'first_name' => $data['first_name'],
				'last_name' => $data['last_name'],
				'email' => $data['email'],
				'phone' => $data['phone'],
				'password' => !empty($data['password']) ? $this->hash_password($data['password']) : '',
				'email_verified' => !empty($data['email_verified']) ? $data['email_verified'] : 'no',
				'image' => '',
				'meta' => json_encode($meta, JSON_PRETTY_PRINT),
		];
		
		if (!empty($default_access)){
			$user['access'] = $this->cms_access_model->keys_to_repeater($default_access);
		}
		 
		$user_id = $this->cms_page_panel_model->create_cms_page_panel($user);
		$user['user_id'] = $user_id;

		return ['data' => $user];
	
	}
	
	function get_user_by_username($username){
		
		$this->load->model('cms/cms_page_panel_model');
		
		$return = $this->cms_page_panel_model->get_cms_page_panels_by(['panel_name' => 'user/user', 'username' => $username, 'show' => 1, ]);
		
		if (!empty($return[0])){
			return $return[0];
		} else {
			return [];
		}

	}

	function get_user_by_email($email){
		
		$this->load->model('cms/cms_page_panel_model');
		
		$return = $this->cms_page_panel_model->get_cms_page_panels_by(['panel_name' => 'user/user', 'email' => $this->normalise_email($email), 'show' => 1, ]);
		
		if (!empty($return[0])){
			return $return[0];
		} else {
			return [];
		}

	}
	
	function set_user_password($user_id, $password){
		
		$this->load->model('cms/cms_page_panel_model');
		
		$this->cms_page_panel_model->update_cms_page_panel($user_id, [
				'password' => $this->hash_password($password)
		]);

	}

	/**
	 * Validate new password + confirmation (reminder and logged-in change).
	 * Returns error key or empty string when ok.
	 */
	function validate_new_password($password, $password2){

		return $this->password_error($password, $password2);

	}

	/**
	 * The one password rule (register, reminder reset, password change): at least $min_length (never under 8)
	 * characters with a letter and a digit. '' when ok, else passwords_mismatch or password_short.
	 */
	function password_error($password, $password2, $min_length = 8){

		$password = (string)$password;
		if ($password !== (string)$password2){
			return 'passwords_mismatch';
		}
		if (mb_strlen($password) < max(8, (int)$min_length) || !preg_match('/\pL/u', $password) || !preg_match('/\d/', $password)){
			return 'password_short';
		}

		return '';

	}

	/** Emails are kept and looked up in lowercase (phones capitalise the first letter). */
	function normalise_email($email){

		return mb_strtolower(trim((string)$email));

	}

	/** The client address as the 404 log has it. */
	function _client_address(){

		return preg_replace('/\s+/', '', (string)($_SERVER['REMOTE_ADDR'] ?? ''));

	}

	function _attempts_file($kind, $key){

		return cms_path('tmp').'user_limits/'.preg_replace('/[^a-z0-9_]/', '', (string)$kind).'_'.sha1((string)$key).'.json';

	}

	/**
	 * Rate limit: whether $key already has $max or more attempts of $kind in the last $seconds.
	 * Keys are a client address ("ip:…") or a lowercased login name ("name:…"). attempt_note() records one.
	 */
	function attempts_over($kind, $key, $max, $seconds){

		$file = $this->_attempts_file($kind, $key);
		if (!is_file($file)){
			return false;
		}
		$times = json_decode((string)@file_get_contents($file), true);
		if (!is_array($times)){
			return false;
		}
		$since = time() - (int)$seconds;
		$recent = array_filter($times, function($t) use ($since){
			return (int)$t > $since;
		});

		return count($recent) >= (int)$max;

	}

	function attempt_note($kind, $key, $keep_seconds = 3600){

		$file = $this->_attempts_file($kind, $key);
		$this->json_file_update($file, function($times) use ($keep_seconds){
			$since = time() - (int)$keep_seconds;
			$times = array_values(array_filter(is_array($times) ? $times : [], function($t) use ($since){
				return (int)$t > $since;
			}));
			$times[] = time();
			return $times;
		});

	}

	/** One line for fail2ban in the 404 log's format: log/user_auth.log (CMS fail2ban.md § user-auth). */
	function auth_log($what){

		$line = date('Y-m-d H:i:s').' [client '.$this->_client_address().'] USER AUTH '.preg_replace('/[^a-z0-9_]/', '', (string)$what)."\n";
		@file_put_contents(cms_path('log').'user_auth.log', $line, FILE_APPEND | LOCK_EX);

	}

	/**
	 * Origins (scheme + host) of the installed web app that this in-app backend serves: config
	 * app_web_origins when set, otherwise base_site without its leading "app." / "app_" label
	 * (https://app.scoretutor.com → https://scoretutor.com). A host without that label has none.
	 */
	function app_web_origins(){

		$list = $GLOBALS['config']['app_web_origins'] ?? null;
		if (is_array($list)){
			return array_values(array_filter(array_map(function($origin){
				return strtolower(rtrim(trim((string)$origin), '/'));
			}, $list)));
		}

		$site = strtolower(trim((string)($GLOBALS['config']['base_site'] ?? '')));
		if (!preg_match('#^(https?://)app[._]([a-z0-9.-]+)(:[0-9]+)?/?$#', $site, $m)){
			return [];
		}

		return [$m[1].$m[2].($m[3] ?? '')];

	}

	/** "scheme://host[:port]" of a URL in lowercase, or '' (default ports dropped). */
	function _origin_of($url){

		$parts = parse_url(trim((string)$url));
		if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])){
			return '';
		}
		$scheme = strtolower($parts['scheme']);
		$port = isset($parts['port']) ? (int)$parts['port'] : 0;
		if (($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443)){
			$port = 0;
		}

		return $scheme.'://'.strtolower($parts['host']).($port ? ':'.$port : '');

	}

	/**
	 * Origins a sign-in may be posted from: this site (base_site, base_host, the request host), and on an
	 * in-app backend also the web app's origins and the Android app's own (androidScheme http → http://localhost).
	 */
	function own_origins(){

		$origins = [];
		foreach ([$GLOBALS['config']['base_site'] ?? '', $GLOBALS['config']['base_host'] ?? ''] as $url){
			$origin = $this->_origin_of($url);
			if ($origin !== ''){
				$origins[] = $origin;
			}
		}
		if (!empty($_SERVER['HTTP_HOST'])){
			$https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
			$origins[] = $this->_origin_of(($https ? 'https://' : 'http://').$_SERVER['HTTP_HOST']);
		}
		if (!empty($GLOBALS['config']['inapp'])){
			foreach ($this->app_web_origins() as $origin){
				$origins[] = $this->_origin_of($origin);
			}
			$origins[] = 'http://localhost';
			$origins[] = 'https://localhost';
			$origins[] = 'capacitor://localhost';
		}

		return array_values(array_unique(array_filter($origins)));

	}

	/**
	 * Whether this sign-in request came from one of our own pages (own_origins), so that another site
	 * can't sign a visitor in to an account of its choosing. Origin header first, else the Referer's
	 * origin. "null" or a foreign origin: false. Neither header (not a browser form or fetch): true.
	 */
	function request_from_own_site(){

		$origin = trim((string)($_SERVER['HTTP_ORIGIN'] ?? ''));
		if ($origin === ''){
			$referer = trim((string)($_SERVER['HTTP_REFERER'] ?? ''));
			if ($referer === ''){
				return true;
			}
			$origin = $this->_origin_of($referer);
		} else if (strtolower($origin) === 'null'){
			return false;
		} else {
			$origin = $this->_origin_of($origin);
		}

		if ($origin !== '' && in_array($origin, $this->own_origins(), true)){
			return true;
		}

		$this->auth_log('bad_origin');

		return false;

	}

	/**
	 * Load, change and save a JSON file under one exclusive lock: $fn gets the decoded array (or []) and returns
	 * the new one. Returns what $fn returned. Used for the token and limit files, which parallel requests change.
	 */
	function json_file_update($file, callable $fn){

		$dir = dirname($file);
		if (!is_dir($dir)){
			@mkdir($dir, 0775, true);
		}
		$handle = @fopen($file, 'c+');
		if ($handle === false){
			error_log_user('user json_file_update: cannot open '.$file);
			return $fn([]);
		}
		flock($handle, LOCK_EX);
		$data = json_decode((string)stream_get_contents($handle), true);
		$result = $fn(is_array($data) ? $data : []);
		ftruncate($handle, 0);
		rewind($handle);
		fwrite($handle, json_encode($result, JSON_PRETTY_PRINT));
		fflush($handle);
		flock($handle, LOCK_UN);
		fclose($handle);

		return $result;

	}

	/**
	 * Set password for a user panel id (same as set_user_password; named for call-site clarity).
	 */
	function change_user_password($user_id, $password){

		$this->set_user_password($user_id, $password);

	}

	/**
	 * Notify user that password associated with their email was updated.
	 */
	function send_password_updated_email($user){

		if (empty($user['email'])){
			return false;
		}

		$this->load->model('cms/cms_email_model');

		$title = trim(str_replace('#page#', '', $GLOBALS['config']['site_title'] ?? ''),
				($GLOBALS['config']['site_title_delimiter'] ?? '').' ');

		return $this->cms_email_model->send_mail(
				$user['email'],
				(!empty($GLOBALS['config']['environment']) ? '['.$GLOBALS['config']['environment'].'] ' : '').
				'Password update at '.$title,
				'Password associated with this email updated.',
				['auto_submitted' => 1, 'send_now' => 1]
		);

	}
	
	function get_current(){

		if (empty($_SESSION['user'])){
			return false;
		}
		
		$user_id = $_SESSION['user']['cms_page_panel_id'] ?? 0;
		
		if (empty($user_id)){
			$this->_clear_stale_user_session();
			return false;
		}
		
		$return = $this->get_user($user_id);
		
		if (empty($return['cms_page_panel_id']) || ($return['panel_name'] ?? '') !== 'user/user' || empty($return['show'])){
			$this->_clear_stale_user_session();
			return false;
		}

		// The session keeps the password hash it was made with: a changed or reset password ends the other sessions
		if (array_key_exists('password', $_SESSION['user']) && (string)$_SESSION['user']['password'] !== (string)($return['password'] ?? '')){
			$this->_clear_stale_user_session();
			return false;
		}

		$return['user_id'] = $return['cms_page_panel_id'];
		
		return $return;
		
	}
	
	function is_logged_in(){
		
		return $this->get_current() !== false;
		
	}
	
	function _clear_stale_user_session(){
		
		$this->load->model('cms/cms_access_model');
		$this->cms_access_model->_clear_user_session();
		
	}

	function _parse_user_meta($user){

		if (empty($user['meta'])){
			return [];
		}

		$meta = cms_json_decode($user['meta'], 'user meta');

		return is_array($meta) ? $meta : [];

	}

	function get_user_meta_value($key, $default = ''){

		$user = $this->get_current();

		if (empty($user)){
			return $default;
		}

		$user_id = (int)($user['user_id'] ?? $user['cms_page_panel_id'] ?? 0);
		if ($user_id < 1){
			return $default;
		}

		return $this->get_user_meta_value_for_id($user_id, $key, $default);

	}

	function get_user_meta_value_for_id($user_id, $key, $default = ''){

		$user_id = (int)$user_id;
		if ($user_id < 1){
			return $default;
		}

		$user = $this->get_user($user_id);
		if (empty($user)){
			return $default;
		}

		$meta = $this->_parse_user_meta($user);

		return array_key_exists($key, $meta) ? $meta[$key] : $default;

	}

	function set_user_meta_value($key, $value){

		$user = $this->get_current();

		if (empty($user)){
			return false;
		}

		$user_id = (int)($user['user_id'] ?? $user['cms_page_panel_id'] ?? 0);
		return $this->set_user_meta_for_id($user_id, $key, $value);

	}

	/**
	 * Set one meta key for a user by id (e.g. Stripe webhooks — no session user).
	 * Value may be scalar or array (stored as nested JSON object).
	 */
	function set_user_meta_for_id($user_id, $key, $value){

		return $this->update_user_meta_for_id($user_id, [$key => $value]);

	}

	/**
	 * Apply multiple meta key sets and optional unsets in one write.
	 *
	 * @param array $set key => value (arrays become nested JSON objects)
	 * @param array $unset list of top-level keys to remove
	 */
	function update_user_meta_for_id($user_id, $set = [], $unset = []){

		$user_id = (int)$user_id;
		if ($user_id < 1){
			return false;
		}

		if (!is_array($set)){
			$set = [];
		}
		if (!is_array($unset)){
			$unset = [];
		}
		if ($set === [] && $unset === []){
			return false;
		}

		// Meta is one JSON blob: two requests writing at once would each drop the other's key. Write under
		// a lock, on a fresh read (this request may hold an older copy of the user from before).
		$lock = 'user_meta_'.$user_id;
		$query = $this->db->query('SELECT GET_LOCK(?, 10) AS l', [$lock]);
		$row = (is_object($query)) ? $query->row_array() : [];
		$got = (is_array($row) && $row) ? (int)reset($row) : 0;
		if ($got !== 1){
			return false;
		}
		try {
			return $this->_update_user_meta_locked($user_id, $set, $unset);
		} finally {
			$this->db->query('SELECT RELEASE_LOCK(?) AS l', [$lock]);
		}

	}

	function _update_user_meta_locked($user_id, $set, $unset){

		$this->load->model('cms/cms_page_panel_model');
		$this->cms_page_panel_model->_invalidate_panel_load_slot($user_id);
		$user = $this->cms_page_panel_model->get_cms_page_panel($user_id);
		if (empty($user) || !is_array($user)){
			return false;
		}

		$meta = $this->_parse_user_meta($user);

		foreach ($set as $key => $value){
			if ($key === '' || $key === null){
				continue;
			}
			$meta[$key] = $value;
		}

		foreach ($unset as $key){
			if ($key === '' || $key === null){
				continue;
			}
			unset($meta[$key]);
		}

		$meta_json = json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

		$this->cms_page_panel_model->update_cms_page_panel($user_id, [
				'meta' => $meta_json,
		]);

		if (!empty($_SESSION['user']['cms_page_panel_id']) && (int)$_SESSION['user']['cms_page_panel_id'] === $user_id){
			$_SESSION['user']['meta'] = $meta_json;
		}

		return true;

	}

	function get_user_naming(){

		$allowed = ['anglo_american', 'german', 'solfege'];
		$naming = $this->get_user_meta_value('naming', 'anglo_american');

		return in_array($naming, $allowed, true) ? $naming : 'anglo_american';

	}
	
	function get_user($user_id){
		
		$this->load->model('cms/cms_page_panel_model');
		
		$return = $this->cms_page_panel_model->get_cms_page_panel($user_id);
		
		if (!empty($return['cms_page_panel_id'])){
			
			$return['user_id'] = $return['cms_page_panel_id'];

			$config = $this->cms_page_panel_model->get_cms_page_panel_settings('user/user_settings');
			if (empty($config['show_username'])){
				$return['loginname'] = $return['email'];
			} else {
				$return['loginname'] = $return['username'];
			}
			
			return $return;
		
		} else {
			
			return [];
		
		}
		
	}
	
	function get_users(){
	
		$this->load->model('cms/cms_page_panel_model');
		return $this->cms_page_panel_model->get_list('user/user');
	
	}
		
	function get_link_settings(){
		
		$settings = $this->get_user_settings();
		
		return [
				'login_link' => $settings['login_link'] ?? [],
				'user_link' => $settings['user_link'] ?? [],
				'logout_link' => $settings['logout_link'] ?? [],
		];
		
	}
	
	function get_login_redirect_url(){
		
		$links = $this->get_link_settings();
		
		if (!empty($links['login_link'])){
			return _l($links['login_link'], false);
		}
		
		return $GLOBALS['config']['base_url'];
		
	}
	
	function get_login_redirect_text(){
		
		return 'Login';
		
	}
	
	function get_login_redirect(){
		
		return [
			'url' => $this->get_login_redirect_url(),
			'text' => $this->get_login_redirect_text(),
		];
		
	}
	
	function get_user_redirect_url(){
		
		$links = $this->get_link_settings();
		
		if (!empty($links['user_link'])){
			return _l($links['user_link'], false);
		}
		
		return $GLOBALS['config']['base_url'];
		
	}
	
	function get_logout_redirect_url(){
		
		$links = $this->get_link_settings();
		
		if (!empty($links['logout_link'])){
			return _l($links['logout_link'], false);
		}
		
		return $GLOBALS['config']['base_url'];
		
	}
	
	function hash_password($password){
		
		return password_hash($password, PASSWORD_DEFAULT);
		
	}
	
	function verify_password($password, $stored_hash){
		
		if (empty($stored_hash)){
			return false;
		}
		
		if (strpos($stored_hash, '$2y$') === 0 || strpos($stored_hash, '$argon2') === 0){
			return password_verify($password, $stored_hash);
		}
		
		$legacy = sha1((!empty($GLOBALS['settings']['salt']) ? $GLOBALS['settings']['salt'] : 'cms').$password);
		
		return hash_equals($legacy, $stored_hash);
		
	}
	
	function upgrade_password_hash($user_id, $password){
		
		$this->set_user_password($user_id, $password);
		
	}
	
	function get_user_settings(){
		
		$this->load->model('cms/cms_page_panel_model');
		
		return $this->cms_page_panel_model->get_cms_page_panel_settings('user/user_settings');
		
	}

	function get_progress_message(){

		$settings = $this->get_user_settings();
		$msg = trim((string)($settings['progress_message'] ?? ''));

		return $msg !== '' ? $msg : 'One moment...';

	}

	/**
	 * Full-page lighten overlay for slow auth (register / Google). JS API: user_progress_show / user_progress_hide
	 */
	function enqueue_progress_overlay($with_google_callback = false){

		add_css('modules/user/css/user_progress.scss');
		add_js('modules/user/js/user_progress.js');

		if ($with_google_callback){
			add_js('modules/user/js/user_google_button.js');
		}

	}
	
	function email_confirmation_required(){
		
		$settings = $this->get_user_settings();
		
		return !empty($settings['email_confirmation']);
		
	}
	
	function is_email_verified($user){
		
		return empty($user['email_verified']) || $user['email_verified'] === 'yes';
		
	}
	
	function login_allowed($user){
		
		if (!$this->email_confirmation_required()){
			return true;
		}
		
		return $this->is_email_verified($user);
		
	}
	
	function _site_title_for_email(){

		return trim(str_replace('#page#', '', $GLOBALS['config']['site_title']), $GLOBALS['config']['site_title_delimiter'].' ');

	}

	function _email_subject_prefix(){

		return !empty($GLOBALS['config']['environment']) ? '['.$GLOBALS['config']['environment'].'] ' : '';

	}

	/** A link token: 64 hex characters from random_bytes. */
	function new_token(){

		return bin2hex(random_bytes(32));

	}

	/**
	 * The password reset link mailed to the user: this site's address and the page that shows
	 * user/reminder, never a posted value. Without such a page, <base_url>reminder/.
	 */
	function reminder_link($token){

		$path = '';
		$row = $this->db->query("select p.cms_page_id from cms_page_panel p join cms_page pg on pg.cms_page_id = p.cms_page_id "
				."where p.panel_name = 'user/reminder' and p.show = 1 and pg.position = 'main' order by p.cms_page_id limit 1")->row_array();
		if (!empty($row['cms_page_id'])){
			$url = _l((string)(int)$row['cms_page_id'], false);
			if (is_string($url) && strlen($url) > 1 && $url[0] === '/' && $url[1] !== '/'){
				$path = $url;
			}
		}
		if ($path === ''){
			$path = $GLOBALS['config']['base_url'].'reminder/';
		}

		$base_site = $GLOBALS['config']['base_site'] ?? $GLOBALS['config']['base_host'] ?? '';

		return $base_site.$path.'?token='.rawurlencode($token);

	}

	function send_email_verification($user_id){
		
		$user = $this->get_user($user_id);
		
		if (empty($user['email'])){
			return false;
		}
		
		$filename = cms_path('tmp').'user_email_verifications.json';
		$token = $this->new_token();
		// The link signs in only in this browser (register or resend): verify_email_signs_in()
		$mark = $this->verify_mark();

		$this->json_file_update($filename, function($tokens) use ($token, $user_id, $user, $mark){
			$tokens[$token] = [
					'token' => $token,
					'user_id' => $user_id,
					'email' => $user['email'],
					'time' => time(),
					'mark' => sha1($mark),
			];
			return $tokens;
		});
		
		$base_site = $GLOBALS['config']['base_site'] ?? $GLOBALS['config']['base_host'] ?? '';
		$verify_url = $base_site.$GLOBALS['config']['base_url'].'verify-email/?token='.$token;
		
		$title = $this->_site_title_for_email();
		
		$this->load->model('cms/cms_email_model');

		return $this->cms_email_model->send_mail(
				$user['email'],
				$this->_email_subject_prefix().'Confirm your email for '.$title,
				"Please confirm your email by opening this link:\n\n".$verify_url,
				['auto_submitted' => 1, 'send_now' => 1]
		);
		
	}

	/**
	 * Welcome mail after registration (Google, or form when login is allowed without confirm).
	 * Subject/body from user/user_settings; {{name}} replaced. Plain text.
	 * When email confirmation is mandatory, send only after successful verify (see verify_email_token).
	 */
	function send_registration_welcome_email($user_id){

		$user = $this->get_user($user_id);

		if (empty($user['email'])){
			return false;
		}

		$settings = $this->get_user_settings();

		$subject = trim((string)($settings['welcome_email_subject'] ?? ''));
		if ($subject === ''){
			$subject = 'Welcome';
		}

		$body = (string)($settings['welcome_email_body'] ?? '');
		if ($body === ''){
			$body = "Hello {{name}},\n\nWelcome. Your account has been created successfully. ".
					'You can sign in any time with the email address you registered with.';
		}

		$name = trim((string)($user['first_name'] ?? ''));
		if ($name === ''){
			$name = trim((string)($user['username'] ?? ''));
		}
		if ($name === ''){
			$name = $user['email'];
		}

		$subject = str_replace('{{name}}', $name, $subject);
		$body = str_replace('{{name}}', $name, $body);

		$this->load->model('cms/cms_email_model');

		return $this->cms_email_model->send_mail(
				$user['email'],
				$this->_email_subject_prefix().$subject,
				$body,
				['auto_submitted' => 1]
		);

	}
	
	function verify_email_token($token){
		
		$filename = cms_path('tmp').'user_email_verifications.json';
		$token = (string)$token;

		if ($token === '' || !file_exists($filename)){
			return ['error' => 'invalid_token'];
		}

		// Single use: the token leaves the file under the lock, whatever the outcome below
		$row = null;
		$this->json_file_update($filename, function($tokens) use ($token, &$row){
			if (!empty($tokens[$token])){
				$row = $tokens[$token];
				unset($tokens[$token]);
			}
			return $tokens;
		});

		if (empty($row)){
			return ['error' => 'invalid_token'];
		}

		if ((time() - (int)($row['time'] ?? 0)) >= 604800){
			return ['error' => 'expired_token'];
		}

		// The account may have been deleted since the email was sent
		$user = $this->get_user((int)($row['user_id'] ?? 0));
		if (empty($user['cms_page_panel_id']) || ($user['panel_name'] ?? '') !== 'user/user'){
			return ['error' => 'invalid_token'];
		}

		$this->load->model('cms/cms_page_panel_model');
		$this->cms_page_panel_model->update_cms_page_panel($row['user_id'], [
				'email_verified' => 'yes',
		]);

		// Deferred welcome when confirmation was required (not sent at form register)
		if ($this->email_confirmation_required()){
			$this->send_registration_welcome_email($row['user_id']);
		}

		return ['user_id' => $row['user_id'], 'same_browser' => $this->_verify_mark_matches($row['mark'] ?? '') ? 1 : 0];

	}

	/**
	 * A random mark of this browser's session, made when a confirmation mail is sent from it (register,
	 * resend). The mail's token keeps its sha1: the link signs in only where the mark matches, so a
	 * confirmation link of someone else's account confirms but never signs this visitor in.
	 */
	function verify_mark(){

		$mark = (string)($_SESSION['user_verify_mark'] ?? '');
		if (!preg_match('/^[a-f0-9]{32}$/', $mark)){
			$mark = bin2hex(random_bytes(16));
			$_SESSION['user_verify_mark'] = $mark;
		}

		return $mark;

	}

	function _verify_mark_matches($stored){

		$mark = (string)($_SESSION['user_verify_mark'] ?? '');

		return $mark !== '' && is_string($stored) && $stored !== '' && hash_equals((string)$stored, sha1($mark));

	}
	
	function refresh_session_for_user_id($user_id){
		
		$user = $this->get_user($user_id);
		
		if (empty($user)){
			return;
		}
		
		$this->load->model('cms/cms_access_model');
		$this->cms_access_model->refresh_user_session($user);
		
	}
	
	function get_logged_in_header_page_id(){
		
		$this->load->model('cms/cms_page_model');
		
		$links = $this->get_link_settings();
		$user_page_id = 0;
		
		if (!empty($links['user_link']['cms_page_id'])){
			$user_page_id = (int)$links['user_link']['cms_page_id'];
		} else if (!empty($links['user_link']['url']) && ctype_digit((string)$links['user_link']['url'])){
			$user_page_id = (int)$links['user_link']['url'];
		}
		
		if (!$user_page_id){
			$user_page_id = 2;
		}
		
		$page = $this->cms_page_model->get_page($user_page_id);
		
		if (empty($page['positions'])){
			return 0;
		}
		
		foreach ($page['positions'] as $position){
			if (!empty($position['name']) && $position['name'] === 'header' && !empty($position['value'])){
				return (int)$position['value'];
			}
		}
		
		return 0;
		
	}

}
