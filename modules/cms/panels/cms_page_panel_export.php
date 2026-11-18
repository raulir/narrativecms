<?php

namespace cms;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Export popup. The walk and the zip are in cms_page_panel_export_model (cms_export.md).
 */
class cms_page_panel_export extends \Controller {

	function __construct(){

		parent::__construct();

		if(empty($_SESSION['cms_user']['cms_user_id'])){
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}

	}

	function panel_params($params){

		if (!empty($params['export_view']) && $params['export_view'] === 'settings'){
			$params['image_cutoff_px'] = !empty($params['image_cutoff_px']) ? (int)$params['image_cutoff_px'] : 1200;
			$params['video_quality'] = !empty($params['video_quality']) ? $params['video_quality'] : 'hd';
		}

		return $params;

	}

	function panel_action($params){

		$this->load->model('cms/cms_page_panel_export_model');
		$export = $this->cms_page_panel_export_model;

		$do = $params['cms_do'] ?? false;
		$cms_page_panel_id = (int)($params['export_id'] ?? 0);
		$export_ids = $export->main_ids($cms_page_panel_id, $params);
		$params['export_heading'] = $export->heading(count($export_ids));
		$params['export_ids_json'] = json_encode(array_values($export_ids));

		if ($do === 'cms_page_panel_export_settings'){

			$params['export_view'] = 'settings';
			$params['export_id'] = !empty($export_ids[0]) ? $export_ids[0] : $cms_page_panel_id;
			$params['image_cutoff_px'] = 1200;
			$params['video_quality'] = 'hd';
			$params['last_export'] = $export->latest_export($cms_page_panel_id);

			return $params;

		}

		if ($do === 'cms_page_panel_export_preview'){

			$params = array_merge($params, $export->preview($export_ids, $export->parse_options($params)));
			$params['result'] = 'ok';

			return $params;

		}

		if ($do === 'cms_page_panel_export'){

			$done = $export->export($export_ids, $export->parse_options($params));
			$params['filename'] = $done['filename'];
			$params['stats'] = $done['stats'];
			$params['export_view'] = 'result';

			return $params;

		}

		return $params;

	}

}
