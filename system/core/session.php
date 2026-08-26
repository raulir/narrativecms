<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * PHP session helpers. Include this file, then call cms_session_boot() when the
 * request needs the site session (front pages, admin). Module APIs should boot
 * only if a session cookie is already present (cms_session_has_cookie()).
 * Cookie/GC from Site setting session_length_days (default 30).
 */

if (!function_exists('cms_session_boot')){

	function cms_session_cookie_is_secure(){

		if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'){
			return true;
		}
		if ((string)($_SERVER['SERVER_PORT'] ?? '') === '443'){
			return true;
		}

		$fwd = strtolower(trim((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')));
		if ($fwd !== ''){
			if (strpos($fwd, ',') !== false){
				$fwd = strtolower(trim(explode(',', $fwd, 2)[0]));
			}
			if ($fwd === 'https'){
				return true;
			}
		}

		return false;

	}

	function cms_session_length_seconds(){

		$session_days = (int)($GLOBALS['config']['session_length_days'] ?? 30);
		if ($session_days < 1 || $session_days > 365){
			$session_days = 30;
		}

		return $session_days * 86400;

	}

	function cms_session_name(){

		if (!empty($GLOBALS['config']['base_url']) && $GLOBALS['config']['base_url'] !== '/'){
			return 's_'.md5($GLOBALS['config']['base_url']);
		}

		return session_name();

	}

	function cms_session_has_cookie(){

		$name = cms_session_name();

		return $name !== '' && !empty($_COOKIE[$name]);

	}

	function cms_session_mark_cms_password_checked(){

		$_SESSION['cms_password_last_checked'] = time();

	}

	function cms_session_clear_cms_admin(){

		unset($_SESSION['cms_user']);
		unset($_SESSION['cms_password_last_checked']);

	}

	function cms_session_enforce_cms_admin(){

		if (empty($_SESSION['cms_user']['cms_user_id'])){
			return;
		}

		$checked_at = (int)($_SESSION['cms_password_last_checked'] ?? 0);
		if ($checked_at < 1 || (time() - $checked_at) >= 86400){
			cms_session_clear_cms_admin();
		}

	}

	function cms_session_boot(){

		if (!session_id()){

			session_name(cms_session_name());

			$session_seconds = cms_session_length_seconds();
			$secure = cms_session_cookie_is_secure();

			ini_set('session.gc_maxlifetime', (string)$session_seconds);
			session_set_cookie_params([
					'lifetime' => $session_seconds,
					'path' => '/',
					'secure' => $secure,
					'httponly' => true,
					'samesite' => 'Lax',
			]);

			session_start();

			$session_cookie_at = (int)($_SESSION['_session_cookie_at'] ?? 0);
			if ($session_cookie_at < 1){
				$_SESSION['_session_cookie_at'] = time();
			} else if ((time() - $session_cookie_at) >= 86400){
				$cookie = session_get_cookie_params();
				setcookie(session_name(), session_id(), [
						'expires' => time() + $session_seconds,
						'path' => $cookie['path'] !== '' ? $cookie['path'] : '/',
						'domain' => $cookie['domain'] ?? '',
						'secure' => !empty($cookie['secure']),
						'httponly' => true,
						'samesite' => 'Lax',
				]);
				$_SESSION['_session_cookie_at'] = time();
			}

			if (!empty($_SESSION['timezone'])){
				date_default_timezone_set($_SESSION['timezone']);
			}

			if (!isset($_SESSION['webp'])){

				$_SESSION['webp'] = false;

				$_SERVER['HTTP_USER_AGENT'] ??= '';
				$is_safari = (bool)stristr($_SERVER['HTTP_USER_AGENT'], 'safari/') && !(bool)stristr($_SERVER['HTTP_USER_AGENT'], 'chrome/')
						&& !(bool)stristr($_SERVER['HTTP_USER_AGENT'], 'chromium/');

				if (!empty($GLOBALS['config']['images_webp']) && !empty($_SERVER['HTTP_ACCEPT'])){

					if (!$is_safari && empty($_SESSION['webp']) && strpos($_SERVER['HTTP_ACCEPT'], 'image/webp') !== false){
						$_SESSION['webp'] = true;
					}

				}

			}

			if (!isset($_SESSION['mobile'])){

				$_SESSION['mobile'] = false;

				if (!empty($_SERVER['HTTP_USER_AGENT'])){

					$useragent = $_SERVER['HTTP_USER_AGENT'];
					if (preg_match('/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od|ad)|iris'.
							'|kindle|lge |maemo|midp|mmp|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo'.
							'|up\.(browser|link)|vodafone|wap|windows (ce|phone)|xda|xiino/i',$useragent)||preg_match('/1207|6310|6590|3gso|4thp|50[1-6]i|770s'.
							'|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck'.
							'|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte'.
							'|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|'.
							'gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-('.
							'20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-'.
							'|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa'.
							'|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-'.
							'|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt'.
							'|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-'.
							'|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01'.
							'|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750'.
							'|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|'.
							'yas\-|your|zeto|zte\-/i',substr($useragent,0,4))){

						$_SESSION['mobile'] = true;

					}

				}

			}

		}

		cms_session_enforce_cms_admin();

	}

}
