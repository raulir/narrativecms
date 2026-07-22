<?php

namespace cms;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_images_unused_purge extends \Controller {

	function __construct(){

		parent::__construct();

		if(empty($_SESSION['cms_user']['cms_user_id'])){
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}

	}

	function panel_action($params = []){

		if (!is_array($params)){
			$params = [];
		}

		$do = '';
		if (is_object($this->input) && method_exists($this->input, 'post')){
			$do = $this->input->post('do');
		}
		if ($do === null || $do === false || $do === ''){
			$do = $params['do'] ?? ($_POST['do'] ?? '');
		}

		$min_months = $params['min_months'] ?? ($_POST['min_months'] ?? 3);
		if (is_object($this->input) && method_exists($this->input, 'post')){
			$posted = $this->input->post('min_months');
			if ($posted !== null && $posted !== false && $posted !== ''){
				$min_months = $posted;
			}
		}
		$min_months = (int)$min_months;
		if ($min_months < 0){
			$min_months = 0;
		}

		// null = all categories; '' = empty/no category only; string = exact
		$category = null;
		$raw_cat = null;
		if (array_key_exists('category', $params)){
			$raw_cat = $params['category'];
		}
		if (array_key_exists('category', $_POST)){
			$raw_cat = $_POST['category'];
		}
		if (is_object($this->input) && method_exists($this->input, 'post')){
			$posted_cat = $this->input->post('category');
			if ($posted_cat !== null && $posted_cat !== false){
				$raw_cat = $posted_cat;
			}
		}
		if ($raw_cat !== null && $raw_cat !== false && $raw_cat !== ''){
			if ((string)$raw_cat === '0'){
				$category = '';
			} else {
				$category = trim(preg_replace("/[^[:alnum:]_]/ui", '', (string)$raw_cat));
			}
		}

		$this->load->model('cms/cms_image_model');

		if ($do == 'unused_purge_status'){

			$params['result'] = $this->cms_image_model->unused_purge_status_read();

		} else if ($do == 'unused_purge_test'){

			$params['result'] = $this->cms_image_model->estimate_unused_cms_images_purge($min_months, $category);

		} else if ($do == 'unused_purge_start'){

			set_time_limit(0);
			if (function_exists('ignore_user_abort')){
				ignore_user_abort(true);
			}

			// Release session lock so status polls can run while purge works
			if (session_status() === PHP_SESSION_ACTIVE){
				session_write_close();
			}

			$params['result'] = $this->cms_image_model->purge_unused_cms_images(100, $min_months, $category);

		}

		return $params;

	}

	function panel_params($params){

		$this->load->model('cms/cms_image_model');

		if (!isset($params['min_months']) || $params['min_months'] === '' || $params['min_months'] === null){
			$params['min_months'] = 3;
		}
		$params['min_months'] = (int)$params['min_months'];

		if (!isset($params['category'])){
			$params['category'] = '';
		}

		if (empty($params['label'])){
			$params['label'] = 'Images older than months';
		}

		$params['categories'] = $this->cms_image_model->get_cms_image_categories();

		add_css('modules/cms/css/cms_images_unused_purge.scss');
		add_js('modules/cms/js/cms_images_unused_purge.js');

		return $params;

	}

}
