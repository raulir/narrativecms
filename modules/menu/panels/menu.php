<?php

namespace menu;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class menu extends \Controller {

	function __construct(){

		parent::__construct();

		$GLOBALS['_panel_js'][] = [
				'script' => 'modules/cms/js/cms_change_hash.js',
				'sync' => 'defer',
		];
		$GLOBALS['_panel_js'][] = [
				'script' => 'modules/cms/js/cms_scroll_to.js',
				'sync' => 'defer',
		];

	}

	function panel_params($params){

		// Viewing page (injected as _cms_page_id when menu sits on header/footer)
		$current_page_id = !empty($params['_cms_page_id']) ? $params['_cms_page_id'] : 0;

		if (!empty($params['links'])){

			foreach ($params['links'] as $key => $link){

				$link_data = !empty($link['link']) && is_array($link['link']) ? $link['link'] : [];
				$link_page_id = isset($link_data['cms_page_id']) ? $link_data['cms_page_id'] : '';
				$link_target = isset($link_data['target']) ? $link_data['target'] : '';
				$link_url = isset($link_data['url']) ? $link_data['url'] : '';

				// Only pure scroll / no-target items skip _lh — never real page links.
				// Same-page _page links must keep _lh for SPA: header is not re-rendered when
				// only main changes, so scroll-only items become stuck on the wrong page.
				if ($link_target === '_none' || ($link_url === '' && $link_page_id === '')){

					if (empty($link['hash'])){
						$params['links'][$key]['hash'] = '_top';
					}
					$params['links'][$key]['cms_scroll_to'] = true;
					$params['links'][$key]['is_current'] = 0;

				} else {

					$params['links'][$key]['cms_scroll_to'] = false;
					$params['links'][$key]['is_current'] =
							((string)$link_page_id !== '' && (string)$link_page_id === (string)$current_page_id)
							? 1 : 0;

				}

			}

		} else {

			$params['links'] = [];

		}

		return $params;

	}

}
