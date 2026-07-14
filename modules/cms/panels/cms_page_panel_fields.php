<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_page_panel_fields extends CI_Controller {

	function __construct(){

		parent::__construct();

		// check if user
		if(empty($_SESSION['cms_user']['cms_user_id'])){
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}

		$this->load->helper('cms/cms_fields_helper');

	}

	// Admin form uses definition fields + stored block only.
	// Do not call the page panel's panel_params here — that is frontend-only
	// (redirects, score HTML, etc.). Custom field types prepare themselves via
	// their own panel_params when print_fields() renders them with _panel().
	// See modules/cms/docs/cms_panel_params.md

}
