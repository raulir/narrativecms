<?php

namespace cms;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_update extends \Controller {

	function __construct(){

		parent::__construct();

		// check if user
		if(empty($_SESSION['cms_user']['cms_user_id'])){
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}

	}

	function panel_action($params){

		$this->load->model('cms/cms_update_model');

		$do = $this->input->post('do');
		
		$area = $this->input->post('area');
		if (empty($area) && $area !== ''){
			$area = $this->input->post('module');
		}

		$area_norm = $this->cms_update_model->normalise_area_name($area === null ? '' : $area);
		if ($area_norm === false){
			$params['result'] = ['error' => 'invalid_area'];
			return $params;
		}
		$area = $area_norm;

		// Master Release does not require update.allow (that flag is for client pulls)
		if ($do == 'cms_update_release'){

			if (!$this->cms_update_model->is_area_master($area)){
				$params['result'] = ['error' => 'not_master'];
				return $params;
			}

			$released = $this->cms_update_model->release_area($area);
			if (!empty($released['error'])){
				$params['result'] = $released;
				return $params;
			}

			$row = $this->cms_update_model->confirm_area($area);

			ob_start();
			include $GLOBALS['config']['base_path'].'modules/cms/templates/cms_update_row.tpl.php';
			$row_html = ob_get_clean();

			$params['result'] = [
					'area' => $area,
					'version' => $released['version'] ?? '',
					'row' => $row,
					'row_html' => $row_html,
					'message' => 'Released '.($released['version'] ?? ''),
			];

			return $params;

		}

		if (empty($GLOBALS['config']['update']['allow'])){
			return $params;
		}
		
		if ($do == 'cms_update_list'){

			if (!$this->cms_update_model->client_may_use_area($area)){
				$params['result'] = [];
				return $params;
			}

			$params['result'] = $this->cms_update_model->get_needed_files($area);
			 
		} else if ($do == 'cms_update' && !empty($GLOBALS['config']['update']['allow'])){

			$params['result'] = $this->cms_update_model->update();
			$params['ajax'] = true;

			$version_data = $this->cms_update_model->get_version('');
			$params['local_version'] = $version_data['version'];

			$version_data = $this->cms_update_model->get_master_version();
			
			$params['master_version'] = !empty($version_data['version']) ? $version_data['version'] : '';

		} else if ($do == 'cms_update_file'){ // stage file(s) from master into cache/update/

			if (!$this->cms_update_model->client_may_use_area($area)){
				$params['result'] = ['error' => 'not_allowed'];
				return $params;
			}

			$filenames = $this->input->post('filenames');
			if (is_string($filenames) && $filenames !== ''){
				$decoded = json_decode($filenames, true);
				if (is_array($decoded)){
					$filenames = $decoded;
				}
			}

			if (is_array($filenames) && count($filenames)){

				$staged = $this->cms_update_model->update_files($filenames, $area);
				$params['result'] = [
						'done' => $staged['done'] ?? [],
						'letters' => $this->input->post('letters'),
				];

			} else {

				// Legacy single-file
				$filename = $this->input->post('filename');
				$this->cms_update_model->update_file($filename, $area);
				$params['result']['filename'] = $filename;
				$params['result']['fn_hash'] = md5($filename);
				$params['result']['letter'] = $this->input->post('letter');
				$params['result']['done'] = [[
						'filename' => $filename,
						'fn_hash' => md5($filename),
				]];

			}

		} else if ($do == 'cms_update_copy'){

			if (!$this->cms_update_model->client_may_use_area($area)){
				$params['result'] = ['error' => 'not_allowed'];
				return $params;
			}

			$this->cms_update_model->update_copy($area);
			
			// check and update version information
			$master_data = $this->cms_update_model->get_master_version($area);
			$this->cms_update_model->rebuild();
			$local_data = $this->cms_update_model->get_version($area);
			
			$master_hash = !empty($master_data['version_hash']) ? $master_data['version_hash'] : 'error';
			// Prefer current_hash from master when version_hash missing
			if ($master_hash === 'error' && !empty($master_data['current_hash'])){
				$master_hash = $master_data['current_hash'];
			}
			if ($local_data['current_hash'] == $master_hash){
				// update local json
				$this->cms_update_model->update_version_cache($area, [
						'version' => !empty($master_data['version']) ? $master_data['version'] : '',
						'version_hash' => !empty($master_data['version_hash']) ? $master_data['version_hash'] : (!empty($master_data['current_hash']) ? $master_data['current_hash'] : ''),
						'version_time' => !empty($master_data['version_time']) ? $master_data['version_time'] : '',
						'update_time' => time(),
				]);
			}

		} else if ($do == 'cms_update_cleanup'){

			if (!$this->cms_update_model->client_may_use_area($area)){
				$params['result'] = ['error' => 'not_allowed'];
				return $params;
			}

			$this->cms_update_model->update_cleanup($area);
			
		} else if ($do == 'cms_update_enable'){

			if (!$this->cms_update_model->client_may_use_area($area) || $area === ''){
				$params['result'] = ['error' => 'not_allowed'];
				return $params;
			}

			$params['result'] = $this->cms_update_model->enable_module_penultimate($area);

		} else if ($do == 'cms_update_confirm'){

			if (!$this->cms_update_model->client_may_use_area($area)){
				$params['result'] = ['error' => 'not_allowed'];
				return $params;
			}

			$row = $this->cms_update_model->confirm_area($area);

			ob_start();
			include $GLOBALS['config']['base_path'].'modules/cms/templates/cms_update_row.tpl.php';
			$row_html = ob_get_clean();

			$params['result'] = [
					'area' => $area,
					'row' => $row,
					'row_html' => $row_html,
			];

		}
		
		return $params;

	}

	function panel_params($params){
		
		if (!empty($params['ajax'])){
			return $params;
		}

		$this->load->model('cms/cms_update_model');

		$params['available'] = [];
		$params['rows_local_only'] = [];
		$params['row_core'] = null;
		$params['rows_modules'] = [];

		if (empty($GLOBALS['config']['update']['master'])){
			$GLOBALS['config']['update']['master'] = [];
		}

		if (!empty($GLOBALS['config']['update']['is_master'])){
			$GLOBALS['config']['update']['master'][] = '';
		}

		// Rebuild local hashes, then group for display (local only → core → modules)
		$data = $this->cms_update_model->rebuild();

		foreach ($data as $area){

			$row = $this->cms_update_model->build_area_display_row($area);
			$is_core = ($row['area'] === '');

			if (!empty($row['local_only'])){
				$params['rows_local_only'][] = $row;
				continue;
			}

			if ($is_core){
				$params['row_core'] = $row;
			} else {
				$params['rows_modules'][] = $row;
			}

		}

		if (!empty($GLOBALS['config']['update']['allow']) && !empty($GLOBALS['config']['cms_update_url'])){
			$params['available'] = $this->cms_update_model->get_installable_modules();
		}

		return $params;

	}

}
