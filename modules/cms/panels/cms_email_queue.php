<?php

namespace cms;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Cron task: process async email queue (cache/email_queue/*.json).
 * Register under CMS repeating tasks as cms/cms_email_queue (e.g. every 5 minutes).
 */
class cms_email_queue extends \Controller {

	function panel_action($params = []){

		$this->load->model('cms/cms_email_model');
		return $this->cms_email_model->process_mail_queue();

	}

}
