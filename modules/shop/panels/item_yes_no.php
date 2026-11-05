<?php

namespace shop;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Yes/no cell for the product variant grid. Writes the grid log, not the item, until Save.
 */
class item_yes_no extends \Controller {

	function panel_params($params){

		$value = (string)($params['value'] ?? '1');
		if ($value !== '0'){
			$value = '1';
		}
		$params['value'] = $value;
		$params['field'] = (string)($params['name'] ?? 'availability');

		return $params;

	}

}
