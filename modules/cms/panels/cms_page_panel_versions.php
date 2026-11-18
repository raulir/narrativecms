<?php

namespace cms;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Page panel versions popup (gears menu → Versions, cms_versions.md): the list, the compare view and restore.
 */
class cms_page_panel_versions extends \Controller {

	function __construct(){

		parent::__construct();

		if (empty($_SESSION['cms_user']['cms_user_id'])){
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}

	}

	function panel_action($params){

		$this->load->model('cms/cms_page_panel_version_model');
		$versions = $this->cms_page_panel_version_model;

		$do = $params['cms_do'] ?? '';
		$id = (int)($params['version_panel_id'] ?? 0);
		$file = (string)($params['version_file'] ?? '');

		$params['version_panel_id'] = $id;
		$params['view'] = 'list';
		$params['message'] = '';

		if ($do === 'cms_page_panel_versions_restore'){
			$result = $versions->restore($id, $file);
			return ['ok' => !empty($result['ok']) ? 1 : 0, 'message' => (string)($result['message'] ?? '')];
		}

		if ($do === 'cms_page_panel_versions_compare'){
			$params['view'] = 'compare';
			$compared = $versions->compare($id, $file);
			$params['message'] = (string)($compared['message'] ?? '');
			$params['groups'] = $compared['groups'] ?? [];
			$params['resources'] = $compared['resources'] ?? [];
			$params['version_time'] = '';
			foreach ($versions->list_versions($id) as $version){
				if ($version['file'] === $file){
					$params['version_time'] = $version['time'];
				}
			}
			return $params;
		}

		$params['versions'] = $versions->list_versions($id);
		$params['max_versions'] = $versions->max_versions();

		return $params;

	}

}
