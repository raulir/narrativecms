<?php

namespace cms;

defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Admin panel translation grid. Requires CMS admin session.
 * Frontend translators use user/page_translation instead.
 */
class cms_translation extends \Controller {

	function __construct(){

		parent::__construct();

		if (empty($_SESSION['cms_user']['cms_user_id'])){
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}

		add_css('modules/cms/css/cms_input.scss');
		add_css('modules/cms/css/cms_input_grid.scss');
		add_css('modules/cms/css/cms_translate_string.scss');
		add_css('modules/cms/css/cms_translation.scss');

		$GLOBALS['_panel_js'][] = 'modules/cms/js/cms_translate_string.js';
		$GLOBALS['_panel_js'][] = 'modules/cms/js/cms_translation.js';

	}

	function panel_action($params){

		$do = $this->input->post('do');

		if ($do !== 'cms_translation_save' && $do !== 'cms_translation_ai'){
			return $params;
		}

		$this->load->model('cms/cms_translation_model');

		// Prefer dedicated key so ajax_api does not merge the target panel into params
		$cms_page_panel_id = (int)$this->input->post('translation_cms_page_panel_id');
		if ($cms_page_panel_id < 1){
			$cms_page_panel_id = (int)$this->input->post('cms_page_panel_id');
		}
		$cms_language = $this->input->post('cms_language');

		if ($do === 'cms_translation_ai'){
			// Long-running external API
			@set_time_limit(180);
			$result = $this->cms_translation_model->suggest_translations(
					$cms_page_panel_id,
					$cms_language
			);
			print(json_encode(['result' => $result], JSON_PRETTY_PRINT));
			die();
		}

		$values = $this->input->post('values');

		if (is_string($values)){
			$values = cms_json_decode($values, 'cms_translation_values');
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

		$this->load->model('cms/cms_page_panel_model');
		$this->load->model('cms/cms_translation_model');
		$this->load->model('cms/cms_page_model');
		$this->load->model('cms/cms_panel_model');

		$cms_page_panel_id = (int)($params['cms_page_panel_id'] ?? 0);
		$cms_language = $this->cms_page_panel_model->get_cms_language();

		$grid = $this->cms_translation_model->get_panel_translation_grid($cms_page_panel_id, $cms_language);

		if (!empty($grid['error'])){
			$params['error'] = $grid['error'];
			$params['cms_page_panel_id'] = $cms_page_panel_id;
			$params['breadcrumb'] = [
					['text' => 'Translations', 'url' => ''],
			];
			return $params;
		}

		$params = array_merge($params, $grid);

		// Breadcrumb: reuse panel context lightly
		$block = $this->cms_page_panel_model->get_cms_page_panel($cms_page_panel_id, '');
		$panel_config = !empty($block['panel_name'])
				? $this->cms_panel_model->get_cms_panel_config($block['panel_name'])
				: [];

		$breadcrumb = [];
		$is_panel_settings = empty($block['cms_page_id'])
				&& empty($block['parent_id'])
				&& empty($block['sort']);

		if (!empty($block['parent_id'])){
			$parent = $this->cms_page_panel_model->get_cms_page_panel($block['parent_id'], '');
			if (!empty($parent['cms_page_id'])){
				$page = $this->cms_page_model->get_page($parent['cms_page_id']);
				$breadcrumb[] = ['text' => 'Pages', 'url' => 'admin/'];
				$breadcrumb[] = [
						'text' => !empty($page['title']) ? $page['title'] : '[ no title ]',
						'url' => 'admin/page/'.$parent['cms_page_id'].'/',
				];
			} else if (!empty($panel_config['list'])){
				$breadcrumb[] = [
						'text' => $panel_config['list']['list_title'] ?? 'List',
						'url' => 'admin/cms_list/'.str_replace('/', '__', $parent['panel_name'] ?? '').'/',
				];
			}
			$parent_title = $this->cms_page_panel_model->get_panel_admin_title($parent);
			$breadcrumb[] = [
					'text' => $parent_title !== '' ? $parent_title : ($parent['title'] ?? 'Parent'),
					'url' => 'admin/cms_page_panel/'.$block['parent_id'].'/',
			];
		} else if (!empty($block['cms_page_id'])){
			$page = $this->cms_page_model->get_page($block['cms_page_id']);
			$breadcrumb[] = ['text' => 'Pages', 'url' => 'admin/'];
			$breadcrumb[] = [
					'text' => !empty($page['title']) ? $page['title'] : '[ no title ]',
					'url' => 'admin/page/'.$block['cms_page_id'].'/',
			];
		} else if (!empty($panel_config['list']) && !$is_panel_settings){
			$breadcrumb[] = [
					'text' => $panel_config['list']['list_title'] ?? 'List',
					'url' => 'admin/cms_list/'.str_replace('/', '__', $block['panel_name']).'/',
			];
		} else if (!empty($panel_config['label'])){
			$breadcrumb[] = [
					'text' => $panel_config['label'],
					'url' => '',
			];
		}

		$title = $params['admin_title'] !== '' ? $params['admin_title'] : ($block['title'] ?? 'Panel');
		$breadcrumb[] = [
				'text' => $title,
				'url' => 'admin/cms_page_panel/'.$cms_page_panel_id.'/',
		];
		$breadcrumb[] = [
				'text' => 'Translations',
				'url' => '',
		];

		$params['breadcrumb'] = $breadcrumb;
		$params['cms_page_panel_id'] = $cms_page_panel_id;

		return $params;

	}

}
