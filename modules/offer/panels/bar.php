<?php

namespace offer;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class bar extends \Controller {

	function panel_params($params){

		$this->load->model('offer/offer_model');

		$params['offers'] = array_slice($this->offer_model->get_offers(), 0, 3);

		$images = [];
		if (!empty($params['items']) && is_array($params['items'])){
			foreach($params['items'] as $row){
				if (is_array($row)){
					$images[] = $row['image'] ?? '';
				} else {
					$images[] = $row;
				}
			}
		}
		$params['items'] = $images;

		return $params;

	}

}
