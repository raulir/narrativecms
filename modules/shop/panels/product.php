<?php

namespace shop;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Base product catalogue panel. Site modules extend via config.json //shop_product
 * (template, CSS, JS, panel_params, optional panel_heading — last extender wins for heading).
 */
class product extends \Controller {

	function panel_heading($params){

		$this->load->model('cms/cms_page_panel_model');

		$addon = '';
		if (!empty($params['subcategory_id'])){
			$subcategory = $this->cms_page_panel_model->get_cms_page_panel($params['subcategory_id']);
			if (is_array($subcategory) && !empty($subcategory['cms_page_panel_id'])){
				$parts = [];
				if (!empty($subcategory['category_id'])){
					$category = $this->cms_page_panel_model->get_cms_page_panel($subcategory['category_id']);
					if (is_array($category) && !empty($category['heading'])){
						$parts[] = $category['heading'];
					}
				}
				if (!empty($subcategory['heading'])){
					$parts[] = $subcategory['heading'];
				}
				if ($parts){
					$addon = ' ('.implode(' - ', $parts).')';
				}
			}
		}

		$return = '<div class="cms_heading_colour" style="background-color: '.($params['colour'] ?? '').'; "></div> '.($params['heading'] ?? '').$addon;

		return $return;

	}

}
