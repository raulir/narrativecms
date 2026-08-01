<?php

namespace user;

defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Frontend translation grid (visitor language). Separate from admin cms/cms_translation.
 * Data layer: cms_translation_model / cms_page_panel_model only.
 */
class page_translation extends \Controller {

	function _can_translate(){

		if (!empty($_SESSION['cms_user']['cms_user_id'])){
			return true;
		}

		$this->load->model('cms/cms_access_model');

		return $this->cms_access_model->user_has_access('cms_translate');

	}

	function _deny(){

		print(json_encode(['result' => ['error' => 'access_denied']], JSON_PRETTY_PRINT));
		die();

	}

	function panel_action($params){

		$do = $this->input->post('do');

		if ($do !== 'page_translation_save' && $do !== 'page_translation_ai'){
			return $params;
		}

		if (!$this->_can_translate()){
			$this->_deny();
		}

		$this->load->model('cms/cms_translation_model');
		$this->load->model('cms/cms_page_panel_model');

		$cms_page_panel_id = (int)$this->input->post('translation_cms_page_panel_id');
		if ($cms_page_panel_id < 1){
			$cms_page_panel_id = (int)$this->input->post('cms_page_panel_id');
		}

		// Always visitor language for FE translators (cms_user may pass explicit language)
		$cms_language = $this->input->post('cms_language');
		if ($cms_language === null || $cms_language === '' || empty($_SESSION['cms_user']['cms_user_id'])){
			$cms_language = $this->cms_page_panel_model->get_current_language();
		}

		if ($do === 'page_translation_ai'){
			@set_time_limit(180);
			// Current editor values (may be emptied without save) for missing detection
			$ui_values = $this->input->post('values');
			if (is_string($ui_values)){
				$ui_values = cms_json_decode($ui_values, 'page_translation_ai_values');
			}
			if (!is_array($ui_values)){
				$ui_values = null;
			}
			$result = $this->cms_translation_model->suggest_translations(
					$cms_page_panel_id,
					$cms_language,
					$ui_values
			);
			print(json_encode(['result' => $result], JSON_PRETTY_PRINT));
			die();
		}

		$values = $this->input->post('values');
		if (is_string($values)){
			$values = cms_json_decode($values, 'page_translation_values');
		}

		$result = $this->cms_translation_model->save_panel_translations(
				$cms_page_panel_id,
				$cms_language,
				$values
		);

		print(json_encode(['result' => $result], JSON_PRETTY_PRINT));
		die();

	}

	function panel_params($params){

		if (!$this->_can_translate()){
			$params['error'] = 'Access denied';
			$params['rows'] = [];
			return $params;
		}

		// CSS already added by page_translate mount; ensure available when opened alone via ajax
		add_css('modules/user/css/page_translation.scss');
		$GLOBALS['_panel_js'][] = 'modules/user/js/page_translation.js';

		$this->load->model('cms/cms_page_panel_model');
		$this->load->model('cms/cms_translation_model');
		$this->load->model('cms/cms_panel_model');

		$cms_page_panel_id = (int)($params['cms_page_panel_id'] ?? 0);
		if ($cms_page_panel_id < 1){
			$cms_page_panel_id = (int)$this->input->post('cms_page_panel_id');
		}

		// Visitor language
		$cms_language = $params['cms_language'] ?? null;
		if ($cms_language === null || $cms_language === ''){
			$cms_language = $this->input->post('cms_language');
		}
		if ($cms_language === null || $cms_language === ''){
			$cms_language = $this->cms_page_panel_model->get_current_language();
		}

		$is_cms_user = !empty($_SESSION['cms_user']['cms_user_id']);

		$grid = $this->cms_translation_model->get_panel_translation_grid($cms_page_panel_id, $cms_language);

		if (!empty($grid['error'])){
			$params['error'] = $grid['error'];
			$params['cms_page_panel_id'] = $cms_page_panel_id;
			$params['cms_language'] = $cms_language;
			$params['is_cms_user'] = $is_cms_user ? 1 : 0;
			$params['breadcrumb'] = [
					['text' => 'Translations', 'url' => ''],
			];
			return $params;
		}

		$params = array_merge($params, $grid);

		$block = $this->cms_page_panel_model->get_cms_page_panel($cms_page_panel_id, '');
		$panel_config = !empty($block['panel_name'])
				? $this->cms_panel_model->get_cms_panel_config($block['panel_name'])
				: [];

		// Text-only breadcrumb (links only for cms_user)
		$breadcrumb = [];
		$title = !empty($params['admin_title']) ? $params['admin_title'] : ($block['title'] ?? '');
		if ($title === '' && !empty($panel_config['label'])){
			$title = $panel_config['label'];
		}
		if ($title === ''){
			$title = 'Panel';
		}

		$panel_url = $is_cms_user ? 'admin/cms_page_panel/'.$cms_page_panel_id.'/' : '';
		$breadcrumb[] = [
				'text' => $title,
				'url' => $panel_url,
		];
		$breadcrumb[] = [
				'text' => 'Translations',
				'url' => '',
		];

		$params['breadcrumb'] = $breadcrumb;
		$params['cms_page_panel_id'] = $cms_page_panel_id;
		$params['is_cms_user'] = $is_cms_user ? 1 : 0;

		return $params;

	}

}
