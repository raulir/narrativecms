<?php

namespace imagemaker;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Provides service image_compose.
 */
class compose extends \Controller {

	function panel_action($params){

		$do = $params['do'] ?? '';
		if ($do === '' && !empty($this->input) && is_object($this->input) && method_exists($this->input, 'post')){
			$do = $this->input->post('do');
		}
		if ($do !== 'compose' && $do !== 'image_compose'){
			return $params;
		}

		$this->load->model('imagemaker/imagemaker_model');

		$overlay = trim((string)($params['overlay'] ?? ''));
		$base = trim((string)($params['base'] ?? ''));
		$transform = $params['transform'] ?? '';
		$blending = $this->imagemaker_model->style_blending_enabled($params['blending'] ?? 'on');

		$pair = $this->imagemaker_model->add_image($overlay, $base, $transform, $blending);
		$image = trim((string)($pair['image'] ?? ''));
		$mask = trim((string)($pair['mask'] ?? ''));
		$reason = trim((string)($pair['error'] ?? ''));

		if ($image === '' || $reason !== ''){
			if ($reason === ''){
				$reason = 'compose failed';
			}
			error_log_user('CMS error [imagemaker/compose]: '.$reason);
			if (!empty($params['return_result'])){
				$params['image'] = '';
				$params['mask'] = '';
				$params['_reason'] = $reason;
				return $params;
			}
			return $params;
		}

		if (!empty($params['return_result'])){
			$params['image'] = $image;
			$params['mask'] = $mask;
			$params['_reason'] = '';
			return $params;
		}

		return $params;

	}

}
