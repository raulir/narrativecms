<?php

namespace shopify;

require_once 'vendor/autoload.php';

use Shopify\Auth\FileSessionStorage;
use Shopify\Clients\Rest;
use Shopify\Rest\Admin2025_07\Collection;
use Shopify\Utils;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class shopify_product_model extends \Model {
	
	function __construct(){
		
		$api_key = $GLOBALS['config']['shopify_api_key'];
		$api_secret = $GLOBALS['config']['shopify_api_secret'];
		$tmp_dir = $GLOBALS['config']['base_path'].'cache/';
		
		\Shopify\Context::initialize(
				apiKey: $api_key,
				apiSecretKey: $api_secret,
				scopes: ['read_products', 'read_product_listings', 'read_orders', 'read_product_feeds'],
				hostName: 'tim-sanders.myshopify.com',
				sessionStorage: new FileSessionStorage($tmp_dir),
				apiVersion: '2026-10',
		);
		
		$this->client = new Rest('tim-sanders.myshopify.com', $GLOBALS['config']['shopify_api_token']);

	}
	
	/**
	 * Shopify module settings (panel settings shopify/shopify).
	 */
	function get_shopify_settings(){

		if (!empty($GLOBALS['shopify_settings_cache']) && is_array($GLOBALS['shopify_settings_cache'])){
			return $GLOBALS['shopify_settings_cache'];
		}

		$this->load->model('cms/cms_page_panel_model');
		$settings = $this->cms_page_panel_model->get_cms_page_panel_settings('shopify/shopify');
		if (!is_array($settings)){
			$settings = [];
		}

		// Integer TTL/budget defaults
		$int_defaults = [
				'thumb_html_ttl' => 900,
				'shopify_data_ttl' => 3600,
				'product_page_recheck_ttl' => 300,
				'max_refresh_time' => 30,
		];
		foreach ($int_defaults as $key => $value){
			if (!isset($settings[$key]) || $settings[$key] === '' || $settings[$key] === null){
				$settings[$key] = $value;
			} else {
				$settings[$key] = (int)$settings[$key];
			}
		}

		// Collection mapping suffixes (Shopify naming → CMS type). shop/category is manual.
		if (!isset($settings['collection_subcategory_suffix']) || $settings['collection_subcategory_suffix'] === null
				|| $settings['collection_subcategory_suffix'] === ''){
			$settings['collection_subcategory_suffix'] = 'range';
		}
		if (empty($settings['collection_collection_suffixes']) || !is_array($settings['collection_collection_suffixes'])){
			$settings['collection_collection_suffixes'] = [];
		}

		$GLOBALS['shopify_settings_cache'] = $settings;

		return $settings;

	}

	/**
	 * Per-request Admin API refresh budget (max_refresh_time seconds).
	 * Force=1 admin/sync paths should pass $respect_budget = false.
	 */
	function _refresh_budget_ok($respect_budget = true){

		if (!$respect_budget){
			return true;
		}

		if (empty($GLOBALS['shopify_refresh']) || !is_array($GLOBALS['shopify_refresh'])){
			$settings = $this->get_shopify_settings();
			$GLOBALS['shopify_refresh'] = [
					'started' => microtime(true),
					'max' => max(1, (float)($settings['max_refresh_time'] ?? 30)),
					'exhausted' => false,
			];
		}

		if (!empty($GLOBALS['shopify_refresh']['exhausted'])){
			return false;
		}

		$elapsed = microtime(true) - $GLOBALS['shopify_refresh']['started'];
		if ($elapsed >= $GLOBALS['shopify_refresh']['max']){
			$GLOBALS['shopify_refresh']['exhausted'] = true;
			return false;
		}

		return true;

	}

	/**
	 * Load last good disk cache for an endpoint, or empty array.
	 */
	function _call_read_cache($filename){

		if (!file_exists($filename)){
			return [];
		}

		$data = cms_json_decode(file_get_contents($filename), $filename);
		if (!is_array($data)){
			return [];
		}

		return $data;

	}

	/**
	 * True when Shopify response is a confirmed missing resource (hide product).
	 */
	function _is_shopify_not_found($data, $status_code = 0){

		if ((int)$status_code === 404){
			return true;
		}

		if (!is_array($data) || empty($data['errors'])){
			return false;
		}

		$errors = $data['errors'];
		if (is_string($errors)){
			$lower = strtolower($errors);
			return (strpos($lower, 'not found') !== false || strpos($lower, '404') !== false);
		}

		if (is_array($errors)){
			$flat = strtolower(json_encode($errors));
			return (strpos($flat, 'not found') !== false);
		}

		return false;

	}

	function call($endpoint, $params = []){
		
		$caching = $params['force'] ?? 0;
		// -1 - never update if cache available
		// 0 - 300s caching
		// 1 - always update cache
		// Meta: prefer disk cache when budget exhausted (page traffic only)
		$respect_budget = !empty($params['_respect_budget']);
		
		if (isset($params['force'])){
			unset($params['force']);
		}
		if (isset($params['_respect_budget'])){
			unset($params['_respect_budget']);
		}
		
		$filename = $GLOBALS['config']['base_path'].'cache/shopify_'.substr(md5($endpoint.json_encode($params)), 0, 16).'.json';
		
		$needs_update = 0;
		if (!file_exists($filename)){
			$needs_update = 1;
		} else if (($caching === 0 || $caching === false) && (time() - filemtime($filename)) > 300){
			$needs_update = 1;
		} else if ($caching === 1){
			$needs_update = 1;
		}

		// force -1: disk only — never open Admin API
		if ($caching == -1){
			if (file_exists($filename)){
				return $this->_call_read_cache($filename);
			}
			return ['_soft_fail' => 1, '_reason' => 'no_disk_cache', ];
		}

		// Budget exhausted: never open new Admin API for page traffic; serve disk/CMS
		if ($needs_update && $respect_budget && !$this->_refresh_budget_ok(true)){
			$cached = $this->_call_read_cache($filename);
			if (!empty($cached) && empty($cached['errors']) && empty($cached['_soft_fail']) && empty($cached['_not_found'])){
				return $cached;
			}
			return ['_soft_fail' => 1, '_reason' => 'budget', ];
		}

		if ($needs_update){
			
			$more = true;
			$data = [];
			$api_ok = false;
			$status_code = 0;
			
			try {

				while ($more){
			
					if (empty($params)){
						$response = $this->client->get(path: $endpoint);
					} else {
						$response = $this->client->get(path: $endpoint, query: $params);
					}

					if (is_object($response) && method_exists($response, 'getStatusCode')){
						$status_code = (int)$response->getStatusCode();
					}
					
					$request_data = $response->getDecodedBody();

					if (!is_array($request_data)){
						$data = ['_soft_fail' => 1, '_reason' => 'decode', ];
						$more = false;
						$api_ok = false;
					} else if (!empty($request_data['errors'])){

						if ($this->_is_shopify_not_found($request_data, $status_code)){
							$data = ['_not_found' => 1, 'errors' => $request_data['errors'], ];
						} else if ($status_code >= 500 || $status_code === 0){
							$data = ['_soft_fail' => 1, '_reason' => 'http_'.$status_code, 'errors' => $request_data['errors'], ];
						} else if ($status_code === 404 || $this->_is_shopify_not_found($request_data, $status_code)){
							$data = ['_not_found' => 1, 'errors' => $request_data['errors'], ];
						} else {
							// Other client errors: treat as soft fail (do not hide products)
							$data = ['_soft_fail' => 1, '_reason' => 'errors', 'errors' => $request_data['errors'], ];
						}
						$more = false;
						$api_ok = false;
						
					} else if (stristr($endpoint, '/') && count($request_data) == 1){
						
						$data = reset($request_data);
						$more = false;
						$api_ok = true;

					} else {
		
						$request_data = $request_data[array_key_first($request_data)];
					
						foreach($request_data as $record){
							$data[] = $record;
						}

						$headers = $response->getHeaders();

						if (empty($headers['link'])){
							$more = false;
						} 

						if ($more){
							
							$last_record = $request_data[array_key_last($request_data)];
							$params['since_id'] = $last_record['id'];

							if (empty($last_record['id'])){
								$more = false;
							} else {
								usleep(1000000);
							}
							
						}

						$api_ok = true;
					
					}

				}

			} catch (\Exception $e){

				$data = ['_soft_fail' => 1, '_reason' => 'exception', '_message' => $e->getMessage(), ];
				$api_ok = false;

			} catch (\Throwable $e){

				$data = ['_soft_fail' => 1, '_reason' => 'exception', '_message' => $e->getMessage(), ];
				$api_ok = false;

			}

			// Only overwrite disk cache with successful product/list payloads
			if ($api_ok && is_array($data) && empty($data['errors']) && empty($data['_soft_fail']) && empty($data['_not_found'])){
				file_put_contents($filename, json_encode($data, JSON_PRETTY_PRINT));
			} else if (!empty($data['_not_found'])){
				// Confirmed missing: do not leave a stale good product in cache as authority
				// Keep previous file if any; caller handles hide. Soft-fail on network uses previous file.
			} else if (!empty($data['_soft_fail'])){
				// Prefer previous good cache over soft-fail empty
				$cached = $this->_call_read_cache($filename);
				if (!empty($cached) && empty($cached['errors']) && empty($cached['_soft_fail']) && empty($cached['_not_found'])){
					$cached['_from_stale_cache'] = 1;
					return $cached;
				}
			}
				
		} else {
				
			$data = $this->_call_read_cache($filename);
				
		}

		return $data;

	}

	/**
	 * get shopify products
	 */
	function get_products(){
		
		$collections = $this->call('custom_collections');
// _print_r($collections);
		foreach($collections as $collection){
			if ($collection['handle'] == 'frontpage'){
				$collection_id = $collection['id'];
			}
		}
		
		$return = $this->call('collections/'.$collection_id.'/products', ['limit' => 250, 'force' => 1, ]); //, ['collection_id' => $collection_id, ]);
// _print_r($return); die();
		return $return;
	
	}

	/**
	 * Fetch one product from Shopify Admin (with disk cache).
	 * Returns product array, or meta flags: _not_found, _soft_fail.
	 *
	 * @param int|string $product_shopify_id
	 * @param int $force -1 disk only if present; 0 ~300s; 1 always API
	 * @param bool $respect_budget apply per-request max_refresh_time
	 */
	function get_product($product_shopify_id, $force = 0, $respect_budget = false){
		
		$product = $this->call('products/'.$product_shopify_id, [
				'force' => $force,
				'_respect_budget' => $respect_budget ? 1 : 0,
		]);

		if (!is_array($product)){
			return ['_soft_fail' => 1, '_reason' => 'empty', ];
		}

		if (!empty($product['_not_found'])){
			return $product;
		}

		if (!empty($product['_soft_fail'])){
			return $product;
		}

		if (!empty($product['errors'])){
			if ($this->_is_shopify_not_found($product)){
				return ['_not_found' => 1, 'errors' => $product['errors'], ];
			}
			return ['_soft_fail' => 1, '_reason' => 'errors', 'errors' => $product['errors'], ];
		}

		if (empty($product['id'])){
			// Empty body without id — soft fail (do not hide)
			return ['_soft_fail' => 1, '_reason' => 'no_id', ];
		}
		
		return $product;
		
	}

	/**
	 * Product page / display: refresh Shopify only when shopify_checked_at is older than TTL.
	 * $mode: 'page' uses product_page_recheck_ttl; 'thumb' uses shopify_data_ttl.
	 */
	function get_product_by_id($product_id, $mode = 'page'){

		$this->load->model('cms/cms_page_panel_model');

		$settings = $this->get_shopify_settings();
		$cms_product = $this->cms_page_panel_model->get_cms_page_panel($product_id);

		if (empty($cms_product['cms_page_panel_id'])){
			return [];
		}

		$checked_at = (int)($cms_product['shopify_checked_at'] ?? 0);
		$age = $checked_at > 0 ? (time() - $checked_at) : PHP_INT_MAX;

		$data_ttl = (int)$settings['shopify_data_ttl'];
		$page_ttl = (int)$settings['product_page_recheck_ttl'];
		$recheck_ttl = ($mode === 'thumb') ? $data_ttl : $page_ttl;

		// Fresh enough: CMS + last Shopify disk payload (no Admin API)
		if ($age <= $recheck_ttl){
			return $this->_product_from_cms($cms_product, -1, false);
		}

		// Stale for page recheck but within full data TTL: soft recheck (300s file / force 0)
		// Older than full data TTL: force live Admin when budget allows
		$force = ($age > $data_ttl) ? 1 : 0;

		return $this->refresh_product($product_id, $force, true);

	}

	/**
	 * Attach options/variants/images from last good Shopify disk cache onto CMS product row.
	 */
	function _product_from_cms($cms_product, $force = -1, $respect_budget = false){

		if (empty($cms_product['shopify_id'])){
			return $cms_product;
		}

		$shopify_product = $this->get_product($cms_product['shopify_id'], $force, $respect_budget);

		if (!empty($shopify_product['id'])){
			$cms_product['options'] = $shopify_product['options'] ?? [];
			$cms_product['variants'] = $shopify_product['variants'] ?? [];
			$cms_product['shopify_images'] = $shopify_product['images'] ?? [];
		} else {
			$cms_product['options'] = $cms_product['options'] ?? [];
			$cms_product['variants'] = $cms_product['variants'] ?? [];
			$cms_product['shopify_images'] = $cms_product['shopify_images'] ?? [];
		}

		return $cms_product;

	}

	/**
	 * Productthumb HTML cache: rendered markup under cache/productthumbs/.
	 */
	function _productthumb_cache_dir(){

		return $GLOBALS['config']['base_path'].'cache/productthumbs/';

	}

	function _productthumb_cache_path($cms_page_panel_id){

		return $this->_productthumb_cache_dir().'productthumb_'.(int)$cms_page_panel_id.'.html';

	}

	/**
	 * Drop productthumb HTML so next list/related render rebuilds markup.
	 */
	function invalidate_product_display_cache($cms_page_panel_id){

		$cms_page_panel_id = (int)$cms_page_panel_id;
		if ($cms_page_panel_id <= 0){
			return;
		}

		$path = $this->_productthumb_cache_path($cms_page_panel_id);
		if (file_exists($path)){
			@unlink($path);
		}

	}

	/**
	 * Read cached productthumb HTML if within thumb_html_ttl and not older than product update_time.
	 * Returns HTML string or null.
	 */
	function get_productthumb_html_cache($cms_page_panel_id, $update_time = 0){

		$cms_page_panel_id = (int)$cms_page_panel_id;
		if ($cms_page_panel_id <= 0){
			return null;
		}

		$settings = $this->get_shopify_settings();
		$thumb_ttl = max(1, (int)($settings['thumb_html_ttl'] ?? 900));

		$filename = $this->_productthumb_cache_path($cms_page_panel_id);
		clearstatcache(true, $filename);
		if (!file_exists($filename)){
			return null;
		}

		$filemtime = filemtime($filename);
		$update_time = (int)$update_time;
		if ((time() - $filemtime) > $thumb_ttl){
			return null;
		}
		if ($update_time > 0 && $update_time > $filemtime){
			return null;
		}

		$html = file_get_contents($filename);
		if ($html === false || $html === ''){
			return null;
		}

		return $html;

	}

	/**
	 * Write rendered productthumb HTML.
	 */
	function set_productthumb_html_cache($cms_page_panel_id, $html){

		$cms_page_panel_id = (int)$cms_page_panel_id;
		if ($cms_page_panel_id <= 0 || !is_string($html) || $html === ''){
			return;
		}

		$dir = $this->_productthumb_cache_dir();
		if (!is_dir($dir)){
			@mkdir($dir, 0755, true);
		}

		file_put_contents($this->_productthumb_cache_path($cms_page_panel_id), $html);

	}

	/**
	 * Render productthumb template to HTML (no productthumb_html short-circuit).
	 */
	function _render_productthumb_html($params){

		$render = $params;
		unset($render['productthumb_html']);

		$template = $GLOBALS['config']['base_path'].'modules/timmy/templates/productthumb.tpl.php';
		if (!file_exists($template)){
			return '';
		}

		return $this->load->view($template, $render, true);

	}

	/**
	 * Full productthumb panel_params: HTML cache → rebuild product → render + store HTML.
	 * Parents only pass cms_page_panel_id (+ productthumb settings labels merged by CMS).
	 * Template echoes $productthumb_html when set.
	 */
	function get_productthumb_params($params){

		$this->load->model('cms/cms_page_panel_model');
		$this->load->model('timmy/imagemaker_model');

		$cms_page_panel_id = (int)($params['cms_page_panel_id'] ?? 0);
		if ($cms_page_panel_id <= 0){
			$params['product'] = [
					'heading' => 'Missing product cms id',
					'error' => 1,
			];
			$params['productthumb_html'] = $this->_render_productthumb_html($params);
			return $params;
		}

		$update_time = (int)($params['update_time'] ?? 0);
		$cached_html = $this->get_productthumb_html_cache($cms_page_panel_id, $update_time);
		if ($cached_html !== null){
			$params['productthumb_html'] = $cached_html;
			return $params;
		}

		// Rebuild: CMS-first, Shopify recheck only when data TTL expired + budget
		$product = $this->get_product_by_id($cms_page_panel_id, 'thumb');

		if (empty($product) || empty($product['cms_page_panel_id'])){
			$params['product'] = [
					'heading' => 'Missing product cms id: '.$cms_page_panel_id,
					'error' => 1,
			];
			$params['productthumb_html'] = $this->_render_productthumb_html($params);
			return $params;
		}

		$min_price = round($product['min_price'] ?? 0);

		if (round((float)($product['min_price'] ?? 0), 2) != $min_price){
			$pounds = floor((float)$product['min_price']);
			$min_price = $pounds.'<span class="productthumb_pence">.'.
					round(((float)$product['min_price'] - $pounds) * 100).'</span>';
		}

		if (($product['min_price'] ?? 0) == ($product['max_price'] ?? 0)){
			$product['price'] = ($params['currency_label'] ?? '£').$min_price;
		} else {
			$product['price'] = '<span class="productthumb_from">'.($params['from_label'] ?? 'from').'</span> '.
					($params['currency_label'] ?? '£').$min_price;
		}

		// Category label (via product → subcategory → category)
		$product['category_heading'] = '';
		$product['subcategory_heading'] = '';
		if (!empty($product['subcategory_id'])){
			$subcategory = $this->cms_page_panel_model->get_cms_page_panel($product['subcategory_id']);
			$product['subcategory_heading'] = trim((string)($subcategory['heading'] ?? ''));
			if ($product['subcategory_heading'] === '' && !empty($subcategory['title'])){
				$product['subcategory_heading'] = trim((string)$subcategory['title']);
			}
			if (!empty($subcategory['category_id'])){
				$category = $this->cms_page_panel_model->get_cms_page_panel($subcategory['category_id']);
				$product['category_heading'] = trim((string)($category['heading'] ?? ''));
				if ($product['category_heading'] === '' && !empty($category['title'])){
					$product['category_heading'] = trim((string)$category['title']);
				}
			}
		}

		if (empty($product['thumbnail_image'])){
			if (!empty($product['image'])){
				$product['thumbnail_image'] = $product['image'];
			} else if (!empty($product['images'][0]['image'])){
				$product['thumbnail_image'] = $product['images'][0]['image'];
			} else {
				$product['thumbnail_image'] = $product['image'] ?? '';
			}
		}

		if (!empty($product['imagemaker_style'])){
			$style = $this->cms_page_panel_model->get_cms_page_panel($product['imagemaker_style']);
			$product['generated_1'] = $this->imagemaker_model->add_colour(
					$product['colour'] ?? '',
					$style['print_background'] ?? '',
					$style['colour_mask'] ?? ''
			);
			$product['thumbnail_image'] = $product['generated_1'];
		}

		if (($product['shopify_status'] ?? '') != 'active'){
			$params['sold_out_label'] = $params['unavailable_label'] ?? 'unavailable';
		}

		$params['product'] = $product;

		$html = $this->_render_productthumb_html($params);
		$this->set_productthumb_html_cache($cms_page_panel_id, $html);
		$params['productthumb_html'] = $html;

		return $params;

	}
	
	function refresh_products(){
		
		set_time_limit(300);
		
		$this->load->model('cms/cms_page_panel_model');
		$this->load->model('cms/cms_slug_model');
		
		$shopify_products = $this->get_products();
// _print_r($shopify_products);
// die();


		$slugs_updated = false;
		$n = 0;
		foreach($shopify_products as $product_key => $product){
				
// print(' '.$n++.' '.(round(microtime(true) * 1000) - $GLOBALS['timer']['start']));
			
			$cms_product = $this->cms_page_panel_model->get_cms_page_panels_by(['panel_name' => 'shop/product', 'shopify_id' => $product['id']]);
				
// _print_r($cms_product);
				
			if (empty($cms_product)){

				$new_cms_product = [
						'panel_name' => 'shop/product',
						'show' => 1,
						'sort' => 'first',
						'shopify_id' => $product['id'],
						'heading' => $product['title'],
						'type' => $product['product_type'],
						'colour' => 'white',
				];
					
				$shopify_products[$product_key]['cms_page_panel_id'] = $this->cms_page_panel_model->create_cms_page_panel($new_cms_product);

				// Target must be shop/product={id} so list slugs + thumbs resolve
				$this->_ensure_product_slug(
						(int)$shopify_products[$product_key]['cms_page_panel_id'],
						$product['title'] ?? '',
						1
				);
				$slugs_updated = true;
		
			}
				
		}
		
		if ($slugs_updated){
			$this->cms_slug_model->_regenerate_cache();
			$this->cms_slug_model->_regenerate_sitemap();
		}
		
		return $shopify_products;
		
	}
	
	/**
	 * Full merge of Shopify Admin product into CMS product row.
	 *
	 * @param int $cms_product_id
	 * @param int $force call() force (-1/0/1)
	 * @param bool $respect_budget page traffic budget (sync/admin pass false)
	 */
	function refresh_product($cms_product_id, $force = 0, $respect_budget = false){
		
		$this->load->model('cms/cms_page_panel_model');
		$this->load->model('cms/cms_slug_model');
		$this->load->model('cms/cms_image_model');
		
		$cms_product = $this->cms_page_panel_model->get_cms_page_panel($cms_product_id);
		
		if (empty($cms_product['cms_page_panel_id'])){
			return [];
		}
		
		if (empty($cms_product['shopify_id'])){
			// Local product without Shopify id — hide (config error, not network)
			$this->cms_page_panel_model->update_cms_page_panel($cms_product_id, ['show' => 0, ]);
			return [];
		}
		
		$shopify_product = $this->get_product($cms_product['shopify_id'], $force, $respect_budget);

		// Confirmed missing on Shopify → hide
		if (!empty($shopify_product['_not_found'])){
			$this->cms_page_panel_model->update_cms_page_panel($cms_product_id, ['show' => 0, ]);
			$this->invalidate_product_display_cache($cms_product_id);
			return [];
		}

		// Network / budget / 5xx / decode — keep CMS product visible, no hide
		if (!empty($shopify_product['_soft_fail']) || !is_array($shopify_product) || empty($shopify_product['id'])){
			return $this->_product_from_cms($cms_product, -1, false);
		}

		$needs_update = false;
		
		$cms_product['shopify_status'] ??= 'unconfirmed';
		if (empty($shopify_product['status']) || $shopify_product['status'] != 'active'){
			if ($cms_product['shopify_status'] != 'unavailable'){
				$cms_product['shopify_status'] = 'unavailable';
				$needs_update = true;
			}
		} else {
			if ($cms_product['shopify_status'] != 'active'){
				$cms_product['shopify_status'] = 'active';
				$needs_update = true;
			}
		}

		if (empty($cms_product['heading']) || $cms_product['heading'] != $shopify_product['title']){
			$cms_product['heading'] = $shopify_product['title'];
			$needs_update = true;
		}
		
		if (empty($cms_product['type']) || $cms_product['type'] != $shopify_product['product_type']){
			$cms_product['type'] = $shopify_product['product_type'];
			$needs_update = true;
		}
		
		if (empty($cms_product['text']) || $cms_product['text'] != $shopify_product['body_html']){
			$cms_product['text'] = $shopify_product['body_html'];
			$needs_update = true;
		}
		
		if (empty($shopify_product['image']['src']) && !empty($cms_product['image'])){
			
			$cms_product['image'] = '';
			$cms_product['image_update'] = '';
			$needs_update = true;
			
		} else if (!empty($shopify_product['image']['src']) && (
				empty($cms_product['image']) ||
				empty($cms_product['image_update']) ||
				($cms_product['image_name_hash'] ?? '') != md5(parse_url($shopify_product['image']['src'], PHP_URL_PATH)) ||
				$cms_product['image_update'] != strtotime($shopify_product['image']['updated_at']) ||
				!$this->_cms_image_path_ok($cms_product['image']))){
			
			$image = $this->cms_image_model->scrape_image($shopify_product['image']['src'], 'shopify', 'shopify');
			
			$cms_product['image'] = $image;
			$cms_product['image_update'] = strtotime($shopify_product['image']['updated_at']);
			$cms_product['image_name_hash'] = md5(parse_url($shopify_product['image']['src'], PHP_URL_PATH));
			$needs_update = true;
			
		}
		
		$found_images = [];
		if (empty($cms_product['images']) || !is_array($cms_product['images'])){
			$cms_product['images'] = [];
		}
		foreach($shopify_product['images'] as $shopify_image){
				
			$i_current = -1;
			foreach($cms_product['images'] as $key => $image){
				if (($image['shopify_id'] ?? '') == $shopify_image['id']){
					$i_current = $key;
		
					// empty path must re-scrape (file_exists(upload_path.'') is true for the directory)
					if (empty($image['image']) ||
							$image['image_update'] != strtotime($shopify_image['updated_at']) ||
							!$this->_cms_image_path_ok($image['image'])){
		
						// update image
						$new_image = $this->cms_image_model->scrape_image($shopify_image['src'], 'shopify', 'shopify');
							
						$cms_product['images'][$key]['image'] = $new_image;
						$cms_product['images'][$key]['image_update'] = strtotime($shopify_image['updated_at']);
						$cms_product['images'][$key]['shopify_id'] = $shopify_image['id'];
		
						$needs_update = true;
		
					}
						
					if (!in_array($shopify_image['id'], $found_images)){
						$found_images[] = $shopify_image['id'];
					}
		
				}
			}
				
			if ($i_current == -1){
		
				// new image
				$new_image = $this->cms_image_model->scrape_image($shopify_image['src'], 'shopify', 'shopify');
		
				$cms_product['images'][] = [
						'image' => $new_image,
						'image_update' => strtotime($shopify_image['updated_at']),
						'shopify_id' => $shopify_image['id'],
				];
		
				if (!in_array($shopify_image['id'], $found_images)){
					$found_images[] = $shopify_image['id'];
				}
		
				$needs_update = true;
		
			}
				
		}
		
		// clear unfound images
		foreach($cms_product['images'] as $key => $image){
			if (!in_array($image['shopify_id'] ?? '', $found_images)){
				unset($cms_product['images'][$key]);
				$needs_update = true;
			} else {
				unset($found_images[array_search($image['shopify_id'], $found_images)]);
			}
		}
		if (!empty($cms_product['images'])){
			$cms_product['images'] = array_values($cms_product['images']);
		}
		
		/*
		 foreach ( $product ['shopify_data'] ['images'] as $image ) {
		 $params ['images'] [$image ['id']] = [
		 'image' => $image ['src'],
		 'ids' => [ ]
		 ];
		 $heading_a = [ ];
		 foreach ( $image ['variant_ids'] as $id ) {
		 $params ['images'] [$image ['id']] ['ids'] [] = $id;
		 $heading_a [] = $params ['variants'] [$id] ['heading'];
		 }
		 $params['images'][$image['id']]['heading'] = implode(', ', $heading_a);
		 }
		 */
		
		$min_price = 0;
		$max_price = 0;
		$available = 0;
		foreach($shopify_product['variants'] as $variant){
			if (empty($min_price) || $min_price > $variant['price']) {
				$min_price = $variant['price'];
			}
			if (empty($max_price) || $max_price < $variant['price']) {
				$max_price = $variant['price'];
			}
			$available += $variant['inventory_quantity'];
		}
		
		if (empty($cms_product['min_price']) || $cms_product['min_price'] != $min_price){
			$cms_product['min_price'] = $min_price;
			$needs_update = true;
		}
		if (empty($cms_product['max_price']) || $cms_product['max_price'] != $max_price){
			$cms_product['max_price'] = $max_price;
			$needs_update = true;
		}
		if (empty($cms_product['available']) || $cms_product['available'] != $available){
			$cms_product['available'] = $available;
			$needs_update = true;
		}

		// Shopify product updated_at — used by sync to skip unchanged products
		if (!empty($shopify_product['updated_at'])){
			$shopify_ts = (int)strtotime($shopify_product['updated_at']);
			if ((int)($cms_product['shopify_updated_at'] ?? 0) !== $shopify_ts){
				$cms_product['shopify_updated_at'] = $shopify_ts;
				$needs_update = true;
			}
		}

		// Assign subcategory from Shopify range collection when empty (admin override wins)
		if ($this->_assign_organisation_from_shopify($cms_product)){
			$needs_update = true;
		}

		// Ensure list slug target shop/product={id} (migrate legacy _/product=; create if missing)
		$this->_ensure_product_slug(
				(int)$cms_product_id,
				$cms_product['heading'] ?? ($shopify_product['title'] ?? ''),
				$cms_product['show'] ?? 1
		);

		// Clear force-refresh flag after a full pass
		if (!empty($cms_product['sync_needed'])){
			$cms_product['sync_needed'] = 0;
			$needs_update = true;
		}

		// Stamp successful Admin fetch (drives TTL rechecks). Do not bump update_time
		// for checked_at alone — that would thrash productthumb HTML invalidation.
		$checked_at = time();
		$cms_product['shopify_checked_at'] = $checked_at;

		if ($needs_update){
			$cms_product['update_time'] = time();
			$cms_product['last_update'] = time();
			$this->cms_page_panel_model->update_cms_page_panel($cms_product_id, $cms_product, true);
			// Price/image/status changed — rebuild productthumb display next request
			$this->invalidate_product_display_cache($cms_product_id);
		} else {
			$this->cms_page_panel_model->update_cms_page_panel($cms_product_id, [
					'shopify_checked_at' => $checked_at,
			], true);
		}
		
		// save some data for other funtionality
		$cms_product['options'] = $shopify_product['options'];
		$cms_product['variants'] = $shopify_product['variants'];
		$cms_product['shopify_images'] = $shopify_product['images'];
		
		return $cms_product;
		
	}


	function get_local_products_by_shopify_id(){

		$this->load->model('cms/cms_page_panel_model');

		$panels = $this->cms_page_panel_model->get_cms_page_panels_by([
				'panel_name' => 'shop/product',
		]);

		$map = [];
		foreach ($panels as $panel){
			$shopify_id = $panel['shopify_id'] ?? '';
			if ($shopify_id === '' || $shopify_id === null){
				continue;
			}
			$map[(string)$shopify_id] = [
					'cms_page_panel_id' => (int)($panel['cms_page_panel_id'] ?? 0),
					'shopify_updated_at' => (int)($panel['shopify_updated_at'] ?? 0),
					'shopify_checked_at' => (int)($panel['shopify_checked_at'] ?? 0),
					// force refresh when images/subcategory/flag need work (even if Shopify updated_at matches)
					'sync_needed' => $this->_product_needs_sync($panel),
			];
		}

		return $map;

	}

	/**
	 * Local product with oldest successful Admin check (shopify_checked_at).
	 * Missing/0 counts as oldest. Tie-break: lowest cms_page_panel_id.
	 * $local: map from get_local_products_by_shopify_id().
	 * Returns cms_page_panel_id or 0.
	 */
	function _oldest_checked_product_id($local){

		if (!is_array($local) || empty($local)){
			return 0;
		}

		$best_id = 0;
		$best_checked = null;

		foreach ($local as $row){
			$id = (int)($row['cms_page_panel_id'] ?? 0);
			if ($id <= 0){
				continue;
			}
			$checked = (int)($row['shopify_checked_at'] ?? 0);
			if ($best_checked === null
					|| $checked < $best_checked
					|| ($checked === $best_checked && $id < $best_id)){
				$best_checked = $checked;
				$best_id = $id;
			}
		}

		return $best_id;

	}

	function _sync_status_path(){

		return $GLOBALS['config']['base_path'].'cache/shopify_sync_status.txt';

	}

	function _sync_lock_path(){

		return $GLOBALS['config']['base_path'].'cache/shopify_sync.lock';

	}

	function _sync_status_write($text, $done = false){

		$content = $text;
		if ($done){
			$content .= "\ndone";
		}

		file_put_contents($this->_sync_status_path(), $content, LOCK_EX);
		clearstatcache(true, $this->_sync_status_path());

	}

	function _sync_format_status($found, $new_total, $stale_total, $updated){

		return 'Found '.$found.', new '.$new_total.', stale '.$stale_total.', updated '.$updated;

	}

	/**
	 * Process setup for a sync batch (settings button or cron), then sync_products().
	 */
	function run_sync_batch($max_seconds = 50){

		set_time_limit(0);
		if (function_exists('ignore_user_abort')){
			ignore_user_abort(true);
		}

		// Do not hold session during long sync (visit-triggered cron must not block other requests)
		if (session_status() === PHP_SESSION_ACTIVE){
			session_write_close();
		}

		return $this->sync_products($max_seconds);

	}

	/**
	 * Sync frontpage collection products into CMS.
	 * New: create + full refresh. Stale: full refresh when Shopify updated_at is newer.
	 * Idle (new=0, stale=0): full-refresh one product with oldest shopify_checked_at.
	 * Soft stop after $max_seconds once current product finishes.
	 */
	function sync_products($max_seconds = 50){

		$lock = $this->_sync_lock_path();
		if (file_exists($lock)){
			return [
					'error' => 'busy',
					'text' => 'Wait, sync is running',
					'running' => true,
					'done' => false,
			];
		}

		file_put_contents($lock, (string)time());
		$this->_sync_status_write('Loading products...', false);

		$max_seconds = (int)$max_seconds;
		if ($max_seconds < 1){
			$max_seconds = 50;
		}

		$started = time();
		$stopped = false;
		$slugs_updated = false;

		try {

			$this->load->model('cms/cms_page_panel_model');
			$this->load->model('cms/cms_slug_model');

			// One-shot: force every local product through full refresh (subcategories, images, etc.)
			$this->_mark_all_products_sync_needed_once();

			$shopify_products = $this->get_products();
			if (!is_array($shopify_products) || !empty($shopify_products['errors'])){
				$text = 'Shopify list failed';
				$this->_sync_status_write($text.' - done', true);
				return [
						'error' => 'list_failed',
						'text' => $text.' - done',
						'done' => true,
						'running' => false,
				];
			}

			$list = [];
			foreach ($shopify_products as $row){
				if (!is_array($row) || empty($row['id'])){
					continue;
				}
				$list[] = $row;
			}

			usort($list, function($a, $b){
				return ((int)$a['id']) <=> ((int)$b['id']);
			});

			$local = $this->get_local_products_by_shopify_id();
			$found = count($list);
			$new_list = [];
			$stale_list = [];

			foreach ($list as $product){
				$sid = (string)$product['id'];
				$shopify_ts = !empty($product['updated_at']) ? (int)strtotime($product['updated_at']) : 0;

				if (empty($local[$sid])){
					$new_list[] = $product;
					continue;
				}

				$local_ts = (int)($local[$sid]['shopify_updated_at'] ?? 0);
				if ($local_ts === 0 || $local_ts < $shopify_ts || !empty($local[$sid]['sync_needed'])){
					$stale_list[] = [
							'product' => $product,
							'cms_page_panel_id' => $local[$sid]['cms_page_panel_id'],
					];
				}
			}

			$new_total = count($new_list);
			$stale_total = count($stale_list);
			$updated = 0;

			$this->_sync_status_write(
					$this->_sync_format_status($found, $new_total, $stale_total, $updated),
					false
			);

			foreach ($new_list as $product){

				$new_cms_product = [
						'panel_name' => 'shop/product',
						'show' => 1,
						'sort' => 'first',
						'shopify_id' => $product['id'],
						'heading' => $product['title'] ?? '',
						'type' => $product['product_type'] ?? '',
						'colour' => 'white',
				];

				$cms_page_panel_id = $this->cms_page_panel_model->create_cms_page_panel($new_cms_product);

				$this->_ensure_product_slug(
						(int)$cms_page_panel_id,
						$product['title'] ?? ('product-'.$product['id']),
						1
				);
				$slugs_updated = true;

				$refreshed = $this->refresh_product($cms_page_panel_id, 1);
				if (!empty($refreshed)){
					$updated++;
				}

				$this->_sync_status_write(
						$this->_sync_format_status($found, $new_total, $stale_total, $updated),
						false
				);

				if ((time() - $started) >= $max_seconds){
					$stopped = true;
					break;
				}

			}

			if (!$stopped){
				foreach ($stale_list as $row){

					$refreshed = $this->refresh_product($row['cms_page_panel_id'], 1);
					if (!empty($refreshed)){
						$updated++;
					}

					$this->_sync_status_write(
							$this->_sync_format_status($found, $new_total, $stale_total, $updated),
							false
					);

					if ((time() - $started) >= $max_seconds){
						$stopped = true;
						break;
					}

				}
			}

			// Idle: nothing new or stale — fully refresh one oldest-checked product
			if (!$stopped && $new_total === 0 && $stale_total === 0){
				$idle_id = $this->_oldest_checked_product_id($local);
				if ($idle_id > 0){
					$refreshed = $this->refresh_product($idle_id, 1);
					if (!empty($refreshed)){
						$updated++;
					}
					$this->_sync_status_write(
							$this->_sync_format_status($found, $new_total, $stale_total, $updated),
							false
					);
				}
			}

			if ($slugs_updated){
				$this->cms_slug_model->_regenerate_cache();
				$this->cms_slug_model->_regenerate_sitemap();
			}

			$text = $this->_sync_format_status($found, $new_total, $stale_total, $updated);
			if ($stopped){
				$text .= ' - stopped (50s limit)';
			} else {
				$text .= ' - done';
			}

			$this->_sync_status_write($text, true);

			return [
					'text' => $text,
					'found' => $found,
					'new' => $new_total,
					'stale' => $stale_total,
					'updated' => $updated,
					'stopped' => $stopped,
					'done' => true,
					'running' => false,
			];

		} finally {

			if (file_exists($lock)){
				unlink($lock);
			}

		}

	}

	function _purge_status_path(){

		return $GLOBALS['config']['base_path'].'cache/shopify_purge_status.txt';

	}

	function _purge_lock_path(){

		return $GLOBALS['config']['base_path'].'cache/shopify_purge.lock';

	}

	function purge_status_read(){

		$path = $this->_purge_status_path();
		clearstatcache(true, $path);
		clearstatcache(true, $this->_purge_lock_path());

		$raw = '';
		if (file_exists($path)){
			$raw = (string)@file_get_contents($path);
		}

		$lines = preg_split("/\r\n|\n|\r/", trim($raw));
		$display = $lines[0] ?? '';
		$running = file_exists($this->_purge_lock_path());
		$has_done_marker = false;
		foreach ($lines as $line){
			if (trim($line) === 'done'){
				$has_done_marker = true;
				break;
			}
		}

		return [
				'text' => $display,
				'done' => !$running && $has_done_marker,
				'running' => $running,
		];

	}

	function _purge_status_write($text, $done = false){

		$content = $text;
		if ($done){
			$content .= "\ndone";
		}

		// LOCK_EX so concurrent status readers see a complete write
		file_put_contents($this->_purge_status_path(), $content, LOCK_EX);
		clearstatcache(true, $this->_purge_status_path());

	}

	function _purge_format_status($total, $purged, $kept, $purged_missing = null, $purged_duplicates = null){

		$text = 'Found '.$total.', purged '.$purged.', kept '.$kept;
		if ($purged_missing !== null && $purged_duplicates !== null){
			$text .= ' (missing '.$purged_missing.', duplicates '.$purged_duplicates.')';
		}

		return $text;

	}

	/**
	 * True when $filename is a real image file under upload_path.
	 * Empty string must not pass: file_exists(upload_path) is true for the directory itself.
	 */
	function _cms_image_path_ok($filename){

		if (empty($filename) || !is_string($filename)){
			return false;
		}

		$path = $GLOBALS['config']['upload_path'].$filename;

		return file_exists($path) && !is_dir($path);

	}

	/**
	 * True when main image or any gallery row is missing a real file (scrape failed or hash-reuse bug).
	 */
	function _product_needs_image_repair($panel){

		if (!empty($panel['image']) && !$this->_cms_image_path_ok($panel['image'])){
			return true;
		}

		// Shopify products with images should have a main path after refresh
		if (empty($panel['image']) && !empty($panel['images']) && is_array($panel['images'])){
			return true;
		}

		if (!empty($panel['images']) && is_array($panel['images'])){
			foreach ($panel['images'] as $row){
				if (empty($row['image']) || !$this->_cms_image_path_ok($row['image'] ?? '')){
					return true;
				}
			}
		}

		return false;

	}

	/**
	 * Product should be fully refreshed even when Shopify updated_at is unchanged.
	 *
	 * subcategory_id empty or 0 is normal (no range) — never forces sync.
	 * Subcategory is assigned only when Shopify has a matching range collection.
	 */
	function _product_needs_sync($panel){

		if (!empty($panel['sync_needed']) && $panel['sync_needed'] !== '0' && $panel['sync_needed'] !== 0){
			return true;
		}

		if ($this->_product_needs_image_repair($panel)){
			return true;
		}

		// Never fully stamped from Shopify yet — force one full refresh
		$shopify_updated_at = (int)($panel['shopify_updated_at'] ?? 0);
		if ($shopify_updated_at < 1){
			return true;
		}

		return false;

	}

	function _sync_needed_once_path(){

		return $GLOBALS['config']['base_path'].'cache/shopify_sync_needed_v1.done';

	}

	/**
	 * Mark every local shop/product for full Shopify refresh (sync_needed only).
	 * Must NOT purge params — only merge the flag onto existing product data.
	 * Does not call Admin API — Sync refreshes existing rows by shopify_id.
	 * Returns ['marked' => n, 'already' => n, 'total' => n, 'text' => '...'].
	 */
	function mark_all_products_sync_needed(){

		$this->load->model('cms/cms_page_panel_model');

		$panels = $this->cms_page_panel_model->get_cms_page_panels_by([
				'panel_name' => 'shop/product',
		]);
		if (!is_array($panels)){
			$panels = [];
		}

		$marked = 0;
		$already = 0;
		$total = 0;

		foreach ($panels as $panel){
			$id = (int)($panel['cms_page_panel_id'] ?? 0);
			if (!$id){
				continue;
			}
			$total++;
			if (!empty($panel['sync_needed']) && $panel['sync_needed'] !== '0' && $panel['sync_needed'] !== 0){
				$already++;
				continue;
			}
			// purge=false: merge flag only — never wipe product params
			$this->cms_page_panel_model->update_cms_page_panel($id, ['sync_needed' => 1], false);
			$marked++;
		}

		$text = 'Marked '.$marked.' product'.($marked === 1 ? '' : 's').' for reload';
		if ($already > 0){
			$text .= ' ('.$already.' already queued)';
		}
		$text .= ' — run Sync to refresh';

		return [
				'marked' => $marked,
				'already' => $already,
				'total' => $total,
				'text' => $text,
		];

	}

	/**
	 * After accidental purge: restore shopify_id (+ heading) on products that lost them.
	 * Matches productthumb HTML headings / slug titles to Shopify list titles.
	 * Does not create new panels — only patches existing cms_page_panel rows (purge=false).
	 */
	function recover_missing_shopify_ids(){

		$this->load->model('cms/cms_page_panel_model');
		$this->load->model('cms/cms_slug_model');

		$shopify_products = $this->get_products();
		if (!is_array($shopify_products) || !empty($shopify_products['errors'])){
			return [
					'error' => 'list_failed',
					'text' => 'Shopify list failed — cannot recover',
					'recovered' => 0,
			];
		}

		// title (normalised) => shopify product
		$by_title = [];
		foreach ($shopify_products as $row){
			if (!is_array($row) || empty($row['id']) || empty($row['title'])){
				continue;
			}
			$key = mb_strtolower(trim((string)$row['title']));
			if ($key === ''){
				continue;
			}
			$by_title[$key] = $row;
		}

		// slug basename hints (handle-like) => shopify product
		$by_handle = [];
		foreach ($shopify_products as $row){
			if (!is_array($row) || empty($row['id'])){
				continue;
			}
			$handle = mb_strtolower(trim((string)($row['handle'] ?? '')));
			if ($handle !== ''){
				$by_handle[$handle] = $row;
			}
		}

		$panels = $this->cms_page_panel_model->get_cms_page_panels_by([
				'panel_name' => 'shop/product',
		]);
		if (!is_array($panels)){
			$panels = [];
		}

		$recovered = 0;
		$skipped = 0;
		$failed = 0;

		foreach ($panels as $panel){
			$id = (int)($panel['cms_page_panel_id'] ?? 0);
			if ($id < 1){
				continue;
			}

			$existing_sid = trim((string)($panel['shopify_id'] ?? ''));
			if ($existing_sid !== '' && $existing_sid !== '0'){
				$skipped++;
				continue;
			}

			$heading = trim((string)($panel['heading'] ?? ''));
			if ($heading === ''){
				// productthumb HTML cache
				$thumb = $GLOBALS['config']['base_path'].'cache/productthumbs/productthumb_'.$id.'.html';
				if (file_exists($thumb)){
					$html = file_get_contents($thumb);
					if (preg_match('/class="productthumb_heading"[^>]*>([^<]+)</', $html, $m)){
						$heading = trim(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'));
					}
				}
			}

			$match = null;
			if ($heading !== ''){
				$key = mb_strtolower($heading);
				if (!empty($by_title[$key])){
					$match = $by_title[$key];
				}
			}

			// Fallback: cms_slug → Shopify handle (list slugs often end with -1, -2, …)
			if (empty($match)){
				$sql = "select cms_slug_id from cms_slug where target = ? limit 1 ";
				$query = $this->db->query($sql, ['shop/product='.$id]);
				if ($query && $query->num_rows()){
					$slug = (string)$query->row_array()['cms_slug_id'];
					$handle = mb_strtolower($slug);
					// strip trailing -digits used by duplicate slug disambiguation
					$handle_base = preg_replace('/-\d+$/', '', $handle);
					if (!empty($by_handle[$handle])){
						$match = $by_handle[$handle];
					} else if (!empty($by_handle[$handle_base])){
						$match = $by_handle[$handle_base];
					}
				}
			}

			if (empty($match)){
				$failed++;
				continue;
			}

			$patch = [
					'shopify_id' => (string)$match['id'],
					'sync_needed' => 1,
			];
			if ($heading !== ''){
				$patch['heading'] = $heading;
			} else if (!empty($match['title'])){
				$patch['heading'] = $match['title'];
			}
			if (!empty($match['updated_at'])){
				$patch['shopify_updated_at'] = (int)strtotime($match['updated_at']);
			}

			// Critical: purge=false so we only add/merge keys
			$this->cms_page_panel_model->update_cms_page_panel($id, $patch, false);
			$recovered++;
		}

		$text = 'Recovered shopify_id on '.$recovered.' product'.($recovered === 1 ? '' : 's');
		if ($skipped > 0){
			$text .= ', '.$skipped.' already had id';
		}
		if ($failed > 0){
			$text .= ', '.$failed.' unmatched';
		}
		$text .= ' — run Sync to refill fields';

		return [
				'recovered' => $recovered,
				'skipped' => $skipped,
				'failed' => $failed,
				'text' => $text,
		];

	}

	/**
	 * One-shot after deploy: mark every local product sync_needed so stale list includes them.
	 */
	function _mark_all_products_sync_needed_once(){

		$path = $this->_sync_needed_once_path();
		if (file_exists($path)){
			return;
		}

		$this->mark_all_products_sync_needed();

		file_put_contents($path, (string)time());

	}

	/**
	 * Map of custom collection id => ['id','title','handle'] (request-cached).
	 */
	function _get_custom_collections_map(){

		if (isset($this->_custom_collections_map) && is_array($this->_custom_collections_map)){
			return $this->_custom_collections_map;
		}

		$map = [];
		$collections = $this->call('custom_collections');
		if (!is_array($collections) || !empty($collections['errors'])){
			$this->_custom_collections_map = [];
			return $this->_custom_collections_map;
		}

		foreach ($collections as $col){
			if (!is_array($col) || empty($col['id'])){
				continue;
			}
			$raw = trim((string)($col['title'] ?? ''));
			$map[(string)$col['id']] = [
					'id' => $col['id'],
					'title' => $this->_strip_shopify_collection_parentheses($raw),
					'handle' => $col['handle'] ?? '',
			];
		}

		$this->_custom_collections_map = $map;
		return $map;

	}

	/**
	 * Shopify collections the product belongs to (custom collections only, resolved titles).
	 */
	function _get_product_collections($shopify_product_id){

		$collects = $this->call('collects', [
				'product_id' => $shopify_product_id,
				'limit' => 250,
		]);

		if (!is_array($collects) || !empty($collects['errors'])){
			return [];
		}

		$map = $this->_get_custom_collections_map();
		$return = [];

		foreach ($collects as $collect){
			if (!is_array($collect) || empty($collect['collection_id'])){
				continue;
			}
			$cid = (string)$collect['collection_id'];
			if (!empty($map[$cid])){
				$return[] = $map[$cid];
			}
		}

		return $return;

	}

	/**
	 * Lowercase + collapse whitespace for collection title/suffix matching.
	 */
	function _normalise_collection_key($value){

		$value = strtolower(trim((string)$value));
		$value = preg_replace('/\s+/u', ' ', $value);

		return $value === null ? '' : $value;

	}

	/**
	 * True when title ends with suffix as a trailing token (case-insensitive).
	 */
	function _collection_title_ends_with_suffix($title, $suffix){

		$t = $this->_normalise_collection_key($title);
		$s = $this->_normalise_collection_key($suffix);
		if ($t === '' || $s === ''){
			return false;
		}
		if ($t === $s){
			return true;
		}
		$tail = ' '.$s;
		$len = strlen($tail);
		if (strlen($t) < $len){
			return false;
		}

		return substr($t, -$len) === $tail;

	}

	/**
	 * Remove trailing parentheticals from Shopify collection titles before mapping.
	 * e.g. "Sugar & Sass Range (Cards)" → "Sugar & Sass Range"
	 * (Client titles in Admin usually omit this; API/legacy can still include it.)
	 */
	function _strip_shopify_collection_parentheses($title){

		$title = trim((string)$title);
		if ($title === ''){
			return '';
		}

		// Strip one or more trailing "(…)" segments
		while (preg_match('/^(.*)\s*\([^)]*\)\s*$/u', $title, $m)){
			$title = trim($m[1]);
		}

		return $title;

	}

	/**
	 * Strip trailing suffix from title (case-insensitive), trim.
	 */
	function _strip_collection_suffix($title, $suffix){

		$title = trim((string)$title);
		$suffix = trim((string)$suffix);
		if ($title === '' || $suffix === ''){
			return $title;
		}

		$pattern = '/^(.*)\s+'.preg_quote($suffix, '/').'\s*$/iu';
		if (preg_match($pattern, $title, $m)){
			return trim($m[1]);
		}

		return $title;

	}

	/**
	 * Classify product custom collections by shopify suffix settings.
	 * Order: subcategory suffix → collection suffixes.
	 * shop/category is not auto-created (manual depts). Parent for new subcategories uses fallback.
	 * Always strip trailing "(…)" from titles first, then match suffixes.
	 * @return array{subcategory:?array,collections:array}
	 */
	function _classify_product_collections($collections){

		$out = [
				'subcategory' => null,
				'collections' => [],
		];

		if (empty($collections) || !is_array($collections)){
			return $out;
		}

		$settings = $this->get_shopify_settings();
		// shop/subcategory
		$sub_suf = trim((string)($settings['collection_subcategory_suffix'] ?? 'range'));
		// shop/collection — multi-membership
		$col_sufs = [];
		if (!empty($settings['collection_collection_suffixes']) && is_array($settings['collection_collection_suffixes'])){
			foreach ($settings['collection_collection_suffixes'] as $row){
				$s = trim((string)($row['suffix'] ?? ''));
				if ($s !== ''){
					$col_sufs[] = $s;
				}
			}
		}

		foreach ($collections as $col){

			if (!is_array($col)){
				continue;
			}

			$handle = strtolower(trim((string)($col['handle'] ?? '')));
			$raw_title = trim((string)($col['title'] ?? ''));
			if ($handle === 'frontpage' || strtolower($raw_title) === 'main' || $raw_title === ''){
				continue;
			}

			// Always drop trailing "(Cards)" / similar before suffix matching
			$title = $this->_strip_shopify_collection_parentheses($raw_title);
			if ($title === ''){
				continue;
			}

			// 1) Subcategory suffix → shop/subcategory
			if ($sub_suf !== '' && $this->_collection_title_ends_with_suffix($title, $sub_suf)){
				$out['subcategory'] = [
						'title' => $title,
						'heading' => $this->_strip_collection_suffix($title, $sub_suf),
				];
				continue;
			}

			// 2) Collection suffixes → shop/collection
			foreach ($col_sufs as $cs){
				if ($this->_collection_title_ends_with_suffix($title, $cs)){
					$out['collections'][] = [
							'title' => $title,
							'heading' => $this->_strip_collection_suffix($title, $cs),
							// Original matched word stored on shop/collection.type
							'type' => $cs,
					];
					break;
				}
			}

		}

		return $out;

	}

	/**
	 * Fallback parent category id (prefer heading Cards, else first shop/category).
	 */
	function _get_fallback_category_id(){

		if (isset($this->_fallback_category_id)){
			return $this->_fallback_category_id;
		}

		$this->load->model('cms/cms_page_panel_model');

		$rows = $this->cms_page_panel_model->get_cms_page_panels_by([
				'panel_name' => 'shop/category',
				'heading' => 'Cards',
		]);

		if (!empty($rows[0]['cms_page_panel_id'])){
			$this->_fallback_category_id = (int)$rows[0]['cms_page_panel_id'];
			return $this->_fallback_category_id;
		}

		$all = $this->cms_page_panel_model->get_list('shop/category');
		if (!empty($all) && is_array($all)){
			$first = reset($all);
			if (!empty($first['cms_page_panel_id'])){
				$this->_fallback_category_id = (int)$first['cms_page_panel_id'];
				return $this->_fallback_category_id;
			}
		}

		$this->_fallback_category_id = 0;
		return 0;

	}

	/**
	 * @deprecated use _get_fallback_category_id
	 */
	function _get_cards_category_id(){

		return $this->_get_fallback_category_id();

	}

	/**
	 * Find panel by shopify_collection full title, else by heading (case-insensitive).
	 */
	function _find_panel_by_shopify_collection($panel_name, $full_title, $heading = ''){

		$full_title = trim((string)$full_title);
		$heading = trim((string)$heading);
		if ($full_title === '' && $heading === ''){
			return null;
		}

		$this->load->model('cms/cms_page_panel_model');

		$rows = $this->cms_page_panel_model->get_cms_page_panels_by([
				'panel_name' => $panel_name,
		]);

		$heading_match = null;
		$heading_key = $this->_normalise_collection_key($heading !== '' ? $heading : $full_title);

		foreach ($rows as $row){
			$sc = trim((string)($row['shopify_collection'] ?? ''));
			if ($full_title !== '' && $sc !== '' && $sc === $full_title){
				return $row;
			}
			if ($heading_match === null){
				$row_heading = $this->_normalise_collection_key($row['heading'] ?? '');
				if ($heading_key !== '' && $row_heading === $heading_key){
					$heading_match = $row;
				}
			}
		}

		if ($heading_match !== null){
			if ($full_title !== '' && trim((string)($heading_match['shopify_collection'] ?? '')) === ''){
				$this->cms_page_panel_model->update_cms_page_panel(
						(int)$heading_match['cms_page_panel_id'],
						['shopify_collection' => $full_title],
						true
				);
				$heading_match['shopify_collection'] = $full_title;
			}
			return $heading_match;
		}

		return null;

	}

	function _find_subcategory_by_shopify_collection($collection_title){

		return $this->_find_panel_by_shopify_collection('shop/subcategory', $collection_title, $collection_title);

	}

	function _ensure_list_item_slug($panel_name, $cms_page_panel_id, $slug_string, $show = 1){

		$cms_page_panel_id = (int)$cms_page_panel_id;
		if (!$cms_page_panel_id || $panel_name === ''){
			return;
		}

		$slug_string = trim((string)$slug_string);
		if ($slug_string === ''){
			$slug_string = str_replace('/', '-', $panel_name).'-'.$cms_page_panel_id;
		}

		$this->load->model('cms/cms_slug_model');

		$target = $panel_name.'='.$cms_page_panel_id;
		$existing = $this->cms_slug_model->get_slug_row_by_target($target);
		if (is_array($existing) && !empty($existing['cms_slug_id'])){
			return;
		}

		$status = empty($show) ? '1' : '0';
		$slug = $this->cms_slug_model->generate_list_item_slug($target, $slug_string);
		$this->cms_slug_model->set_page_slug($target, $slug, $status);

	}

	/**
	 * @param string $full_title Full Shopify collection title (after parenthesis strip)
	 * @param string $heading Display heading (suffix stripped)
	 * @param string $type Matched collection suffix word (e.g. "category") — stored as type
	 */
	function _ensure_collection_for_collection($full_title, $heading = '', $type = ''){

		$full_title = trim((string)$full_title);
		$heading = trim((string)$heading);
		$type = trim((string)$type);
		if ($heading === ''){
			$heading = $full_title;
		}
		if ($full_title === '' && $heading === ''){
			return null;
		}

		$existing = $this->_find_panel_by_shopify_collection('shop/collection', $full_title, $heading);
		if (!empty($existing['cms_page_panel_id'])){
			$this->load->model('cms/cms_page_panel_model');
			// Backfill type when empty and we know the matched suffix
			$existing_type = trim((string)($existing['type'] ?? ''));
			if ($existing_type === '' && $type !== ''){
				$this->cms_page_panel_model->update_cms_page_panel(
						(int)$existing['cms_page_panel_id'],
						['type' => $type],
						false
				);
				$existing['type'] = $type;
			}
			// Category page #hash (not a public cms_slug)
			$this->_ensure_collection_hash((int)$existing['cms_page_panel_id'], $heading);
			return $this->cms_page_panel_model->get_cms_page_panel((int)$existing['cms_page_panel_id']);
		}

		$this->load->model('cms/cms_page_panel_model');

		$new_row = [
				'panel_name' => 'shop/collection',
				'show' => 1,
				'sort' => 'first',
				'title' => $heading,
				'heading' => $heading,
				'shopify_collection' => $full_title,
		];
		if ($type !== ''){
			$new_row['type'] = $type;
		}

		$new_id = $this->cms_page_panel_model->create_cms_page_panel($new_row);

		if (empty($new_id)){
			return null;
		}

		$this->_ensure_collection_hash((int)$new_id, $heading);

		return $this->cms_page_panel_model->get_cms_page_panel($new_id);

	}

	/**
	 * Fill shop/collection.hash for category page fragments (no cms_slug).
	 */
	function _ensure_collection_hash($cms_page_panel_id, $heading = ''){

		$cms_page_panel_id = (int)$cms_page_panel_id;
		if ($cms_page_panel_id < 1){
			return;
		}

		$this->load->model('cms/cms_page_panel_model');
		$this->load->model('cms/cms_slug_model');

		$row = $this->cms_page_panel_model->get_cms_page_panel($cms_page_panel_id);
		if (empty($row['cms_page_panel_id'])){
			return;
		}
		if (trim((string)($row['hash'] ?? '')) !== ''){
			return;
		}

		$heading = trim((string)$heading);
		if ($heading === ''){
			$heading = trim((string)($row['heading'] ?? $row['title'] ?? ''));
		}

		$base = $this->cms_slug_model->_slugify_candidate($heading);
		if ($base === ''){
			$base = 'collection';
		}

		$panels = $this->cms_page_panel_model->get_cms_page_panels_by([
				'panel_name' => 'shop/collection',
		]);
		$used = [];
		if (is_array($panels)){
			foreach ($panels as $p){
				$pid = (int)($p['cms_page_panel_id'] ?? 0);
				if ($pid < 1 || $pid === $cms_page_panel_id){
					continue;
				}
				$h = trim((string)($p['hash'] ?? ''));
				if ($h !== ''){
					$used[$h] = true;
				}
			}
		}

		$candidate = $base;
		$i = 1;
		while (isset($used[$candidate])){
			$candidate = $base.'-'.$i;
			$i++;
		}

		$this->cms_page_panel_model->update_cms_page_panel($cms_page_panel_id, [
				'hash' => $candidate,
		], false);

	}

	/**
	 * Ensure list-item slug for shop/product={id}.
	 * Migrates legacy _/product={id} so shop/product slugs and thumbs resolve.
	 */
	function _ensure_product_slug($cms_page_panel_id, $slug_string, $show = 1){

		$cms_page_panel_id = (int)$cms_page_panel_id;
		if (!$cms_page_panel_id){
			return;
		}

		$slug_string = trim((string)$slug_string);
		if ($slug_string === ''){
			$slug_string = 'shopify-product-'.$cms_page_panel_id;
		}

		$this->load->model('cms/cms_slug_model');

		$target = 'shop/product='.$cms_page_panel_id;
		$legacy = '_/product='.$cms_page_panel_id;

		$existing = $this->cms_slug_model->get_slug_row_by_target($target);
		if (is_array($existing) && !empty($existing['cms_slug_id'])){
			return;
		}

		// Move legacy sync target without changing public slug string
		$legacy_row = $this->cms_slug_model->get_slug_row_by_target($legacy);
		if (is_array($legacy_row) && !empty($legacy_row['cms_slug_id'])){
			$sql = 'update cms_slug set target = ? where target = ? ';
			$this->db->query($sql, [$target, $legacy]);
			$this->cms_slug_model->_regenerate_cache();
			$this->cms_slug_model->_regenerate_sitemap();
			return;
		}

		$status = empty($show) ? '1' : '0';
		$slug = $this->cms_slug_model->generate_list_item_slug($target, $slug_string);
		$this->cms_slug_model->set_page_slug($target, $slug, $status);

	}

	/**
	 * Ensure list-item slug for shop/subcategory={id} (link_target panels need this for frontend URLs).
	 */
	function _ensure_subcategory_slug($cms_page_panel_id, $slug_string, $show = 1){

		$cms_page_panel_id = (int)$cms_page_panel_id;
		if (!$cms_page_panel_id){
			return;
		}

		$slug_string = trim((string)$slug_string);
		if ($slug_string === ''){
			$slug_string = 'stock-subcategory-'.$cms_page_panel_id;
		}

		$this->load->model('cms/cms_slug_model');

		$target = 'shop/subcategory='.$cms_page_panel_id;
		$existing = $this->cms_slug_model->get_slug_row_by_target($target);
		if (is_array($existing) && !empty($existing['cms_slug_id'])){
			return;
		}

		// status 0 = visible when show is on (same as cms_page_panel_operations)
		$status = empty($show) ? '1' : '0';
		$slug = $this->cms_slug_model->generate_list_item_slug($target, $slug_string);
		$this->cms_slug_model->set_page_slug($target, $slug, $status);

	}

	/**
	 * @param string $full_title Full Shopify collection title
	 * @param string $heading Stripped display heading
	 * @param int $category_id Parent shop/category id
	 */
	function _ensure_subcategory_for_collection($full_title, $heading = '', $category_id = 0){

		$full_title = trim((string)$full_title);
		$heading = trim((string)$heading);
		if ($heading === ''){
			$heading = $full_title;
		}
		if ($full_title === '' && $heading === ''){
			return null;
		}

		$existing = $this->_find_panel_by_shopify_collection('shop/subcategory', $full_title, $heading);
		if (!empty($existing['cms_page_panel_id'])){
			$this->_ensure_subcategory_slug(
					(int)$existing['cms_page_panel_id'],
					$heading,
					$existing['show'] ?? 1
			);
			// Keep parent shop/category in sync when Shopify mapping provides one
			$category_id = (int)$category_id;
			$old_cat = (int)($existing['category_id'] ?? 0);
			if ($category_id > 0 && $old_cat !== $category_id){
				$this->load->model('cms/cms_page_panel_model');
				$this->cms_page_panel_model->update_cms_page_panel(
						(int)$existing['cms_page_panel_id'],
						['category_id' => $category_id],
						false
				);
				$existing['category_id'] = $category_id;
			}
			return $existing;
		}

		$category_id = (int)$category_id;
		if ($category_id < 1){
			$category_id = $this->_get_fallback_category_id();
		}
		if ($category_id < 1){
			_html_error('Shopify sync: no shop/category available for subcategory "'.$heading.'"');
			return null;
		}

		$this->load->model('cms/cms_page_panel_model');

		$new_id = $this->cms_page_panel_model->create_cms_page_panel([
				'panel_name' => 'shop/subcategory',
				'show' => 1,
				'sort' => 'first',
				'title' => $heading,
				'heading' => $heading,
				'shopify_collection' => $full_title,
				'category_id' => $category_id,
		]);

		if (empty($new_id)){
			return null;
		}

		$this->_ensure_subcategory_slug((int)$new_id, $heading, 1);

		return $this->cms_page_panel_model->get_cms_page_panel($new_id);

	}

	/**
	 * Map Shopify collections → category / subcategory / collections on product.
	 * Overwrites product subcategory from Shopify when a range/category-suffix match exists
	 * (or clears it when Shopify no longer has one). Replaces collections list from Shopify.
	 * @return bool true if $cms_product was modified
	 */
	function _assign_organisation_from_shopify(&$cms_product){

		$shopify_id = $cms_product['shopify_id'] ?? '';
		if ($shopify_id === '' || $shopify_id === null){
			return false;
		}

		$collections = $this->_get_product_collections($shopify_id);
		$class = $this->_classify_product_collections($collections);
		$changed = false;

		// shop/category is manual — new subcategories hang under fallback (e.g. Cards)
		if (!empty($class['subcategory']['title'])){
			$category_id = $this->_get_fallback_category_id();
			$sub = $this->_ensure_subcategory_for_collection(
					$class['subcategory']['title'],
					$class['subcategory']['heading'] ?? '',
					$category_id
			);
			// Always overwrite product subcategory from Shopify classification
			$new_sub_id = !empty($sub['cms_page_panel_id']) ? (int)$sub['cms_page_panel_id'] : 0;
			$old_sub_id = (int)($cms_product['subcategory_id'] ?? 0);
			if ($new_sub_id > 0 && $old_sub_id !== $new_sub_id){
				$cms_product['subcategory_id'] = $new_sub_id;
				$changed = true;
			}
		} else {
			// Shopify has no subcategory-suffix collection — clear CMS subcategory
			$old_sub_id = (int)($cms_product['subcategory_id'] ?? 0);
			if ($old_sub_id > 0){
				$cms_product['subcategory_id'] = 0;
				$changed = true;
			}
		}

		// Replace product.collections from classified Shopify collections
		$new_collections = [];
		$seen = [];
		if (!empty($class['collections']) && is_array($class['collections'])){
			foreach ($class['collections'] as $c){
				if (empty($c['title'])){
					continue;
				}
				$row = $this->_ensure_collection_for_collection(
						$c['title'],
						$c['heading'] ?? '',
						$c['type'] ?? ''
				);
				if (empty($row['cms_page_panel_id'])){
					continue;
				}
				$cid = (int)$row['cms_page_panel_id'];
				if (isset($seen[$cid])){
					continue;
				}
				$seen[$cid] = true;
				$new_collections[] = ['collection_id' => $cid];
			}
		}

		$old = [];
		if (!empty($cms_product['collections']) && is_array($cms_product['collections'])){
			foreach ($cms_product['collections'] as $row){
				$cid = (int)($row['collection_id'] ?? 0);
				if ($cid > 0){
					$old[] = $cid;
				}
			}
		}
		sort($old);
		$new_ids = array_keys($seen);
		sort($new_ids);
		if ($old !== $new_ids){
			$cms_product['collections'] = $new_collections;
			$changed = true;
		}

		return $changed;

	}

	/**
	 * @deprecated use _assign_organisation_from_shopify
	 */
	function _assign_subcategory_if_empty(&$cms_product){

		return $this->_assign_organisation_from_shopify($cms_product);

	}

	function _purge_delete_product_images($cms_product){

		$this->load->model('cms/cms_image_model');

		if (!empty($cms_product['image']) && is_string($cms_product['image'])){
			$this->cms_image_model->delete_cms_image_by_filename($cms_product['image']);
		}

		if (!empty($cms_product['images']) && is_array($cms_product['images'])){
			foreach ($cms_product['images'] as $row){
				if (!empty($row['image']) && is_string($row['image'])){
					$this->cms_image_model->delete_cms_image_by_filename($row['image']);
				}
			}
		}

	}

	/**
	 * Delete local shop/product panels that:
	 * - have no Shopify id / no longer exist on Shopify, or
	 * - are older duplicates of the same shopify_id (keep highest cms_page_panel_id).
	 * Oldest first. Soft stop after 50s once current item finishes.
	 */
	function purge_missing_products($max_seconds = 50){

		$lock = $this->_purge_lock_path();
		if (file_exists($lock)){
			return [
					'error' => 'busy',
					'text' => 'Wait, purge is running',
					'running' => true,
					'done' => false,
			];
		}

		file_put_contents($lock, (string)time());
		$this->_purge_status_write('Loading products...', false);

		$max_seconds = (int)$max_seconds;
		if ($max_seconds < 1){
			$max_seconds = 50;
		}

		$started = time();
		$stopped = false;

		try {

			$this->load->model('cms/cms_page_panel_model');
			$this->load->model('cms/cms_slug_model');

			$products = $this->cms_page_panel_model->get_cms_page_panels_by([
					'panel_name' => 'shop/product',
			]);

			usort($products, function($a, $b){
				return ((int)($a['cms_page_panel_id'] ?? 0)) <=> ((int)($b['cms_page_panel_id'] ?? 0));
			});

			// Per shopify_id: keep newest panel (max cms_page_panel_id); older copies are duplicates
			$by_sid = [];
			foreach ($products as $cms_product){
				$sid = trim((string)($cms_product['shopify_id'] ?? ''));
				if ($sid === ''){
					continue;
				}
				$id = (int)($cms_product['cms_page_panel_id'] ?? 0);
				if ($id < 1){
					continue;
				}
				if (!isset($by_sid[$sid])){
					$by_sid[$sid] = [];
				}
				$by_sid[$sid][] = $id;
			}

			$duplicate_ids = [];
			foreach ($by_sid as $sid => $ids){
				if (count($ids) < 2){
					continue;
				}
				sort($ids, SORT_NUMERIC);
				$keep_id = (int)array_pop($ids); // highest id kept
				foreach ($ids as $old_id){
					$duplicate_ids[(int)$old_id] = true;
				}
				unset($keep_id);
			}

			$total = count($products);
			$purged = 0;
			$purged_missing = 0;
			$purged_duplicates = 0;
			$kept = 0;

			$this->_purge_status_write(
					$this->_purge_format_status($total, $purged, $kept, $purged_missing, $purged_duplicates),
					false
			);

			// Cache remote existence per shopify_id (one Admin call per unique id this run)
			$remote_ok = [];

			foreach ($products as $cms_product){

				$cms_page_panel_id = (int)($cms_product['cms_page_panel_id'] ?? 0);
				$shopify_id = trim((string)($cms_product['shopify_id'] ?? ''));

				$is_duplicate = !empty($duplicate_ids[$cms_page_panel_id]);
				$missing = false;

				if ($is_duplicate){
					// Older clone of a valid shopify_id — drop without rechecking Shopify
				} else if ($shopify_id === ''){
					$missing = true;
				} else {
					if (!array_key_exists($shopify_id, $remote_ok)){
						$remote = $this->get_product($shopify_id, 1);
						$remote_ok[$shopify_id] = (is_array($remote) && !empty($remote['id'])
								&& empty($remote['_not_found']));
					}
					$missing = empty($remote_ok[$shopify_id]);
				}

				if ($is_duplicate || $missing){
					$this->_purge_delete_product_images($cms_product);
					if ($cms_page_panel_id){
						$this->cms_page_panel_model->delete_cms_page_panel($cms_page_panel_id);
					}
					$purged++;
					if ($is_duplicate){
						$purged_duplicates++;
					} else {
						$purged_missing++;
					}
				} else {
					$kept++;
				}

				$this->_purge_status_write(
						$this->_purge_format_status($total, $purged, $kept, $purged_missing, $purged_duplicates),
						false
				);

				if ((time() - $started) >= $max_seconds){
					$stopped = true;
					break;
				}

			}

			if ($purged > 0){
				$this->cms_slug_model->_regenerate_cache();
				$this->cms_slug_model->_regenerate_sitemap();
			}

			$text = $this->_purge_format_status($total, $purged, $kept, $purged_missing, $purged_duplicates);
			if ($stopped){
				$text .= ' - stopped (50s limit)';
			} else {
				$text .= ' - done';
			}

			$this->_purge_status_write($text, true);

			return [
					'text' => $text,
					'total' => $total,
					'purged' => $purged,
					'purged_missing' => $purged_missing,
					'purged_duplicates' => $purged_duplicates,
					'kept' => $kept,
					'stopped' => $stopped,
					'done' => true,
					'running' => false,
			];

		} finally {

			if (file_exists($lock)){
				unlink($lock);
			}

		}

	}

	function _images_purge_status_path(){

		return $GLOBALS['config']['base_path'].'cache/shopify_images_purge_status.txt';

	}

	function _images_purge_lock_path(){

		return $GLOBALS['config']['base_path'].'cache/shopify_images_purge.lock';

	}

	function images_purge_status_read(){

		$path = $this->_images_purge_status_path();
		clearstatcache(true, $path);
		clearstatcache(true, $this->_images_purge_lock_path());

		$raw = '';
		if (file_exists($path)){
			$raw = (string)@file_get_contents($path);
		}

		$lines = preg_split("/\r\n|\n|\r/", trim($raw));
		$display = $lines[0] ?? '';
		$running = file_exists($this->_images_purge_lock_path());
		$has_done_marker = false;
		foreach ($lines as $line){
			if (trim($line) === 'done'){
				$has_done_marker = true;
				break;
			}
		}

		return [
				'text' => $display,
				'done' => !$running && $has_done_marker,
				'running' => $running,
		];

	}

	function _images_purge_status_write($text, $done = false){

		$content = $text;
		if ($done){
			$content .= "\ndone";
		}

		file_put_contents($this->_images_purge_status_path(), $content, LOCK_EX);
		clearstatcache(true, $this->_images_purge_status_path());

	}

	function _images_purge_format_status($found, $needed, $orphans, $moved){

		return 'Found '.$found.', needed '.$needed.', orphans '.$orphans.', moved '.$moved;

	}

	/**
	 * Load cms_image rows that look like Shopify scrapes (category and/or path prefix).
	 */
	function _list_shopify_cms_images(){

		$sql = "select cms_image_id, filename, category, meta from cms_image ".
				"where category = 'shopify' or filename regexp '(^|/)shopify_' ".
				"order by cms_image_id asc ";
		$query = $this->db->query($sql);

		return $query->result_array();

	}

	/**
	 * True if $filename is used as a panel param value (product image fields, page settings, etc.).
	 * Page covers live in params too — there is no cms_page.image column.
	 */
	function _filename_is_referenced($filename){

		if (empty($filename) || !is_string($filename)){
			return false;
		}

		$sql = 'select cms_page_panel_param_id from cms_page_panel_param where value = ? limit 1 ';
		$query = $this->db->query($sql, [$filename]);

		return $query->num_rows() > 0;

	}

	function _shopify_filename_referenced_cached($filename, &$ref_cache){

		if (array_key_exists($filename, $ref_cache)){
			return $ref_cache[$filename];
		}

		$ref_cache[$filename] = $this->_filename_is_referenced($filename);

		return $ref_cache[$filename];

	}

	/**
	 * Crop-child filenames for a parent cms_image row (path pattern + meta.child_ids).
	 */
	function _shopify_image_child_filenames($parent_row){

		$out = [];
		$parent_fn = $parent_row['filename'] ?? '';
		if ($parent_fn === ''){
			return $out;
		}

		$name_a = pathinfo($parent_fn);
		$dir = isset($name_a['dirname']) ? str_replace('\\', '/', $name_a['dirname']) : '';
		$base = $name_a['filename'] ?? '';
		$ext = $name_a['extension'] ?? '';
		if ($base !== '' && $ext !== ''){
			$like = ($dir === '.' || $dir === '' ? '' : $dir.'/').$base.'_v%.'.$ext;
			$sql = 'select filename from cms_image where filename like ? ';
			$query = $this->db->query($sql, [$like]);
			foreach ($query->result_array() as $row){
				if (!empty($row['filename'])){
					$out[$row['filename']] = true;
				}
			}
		}

		$meta = [];
		if (!empty($parent_row['meta']) && is_string($parent_row['meta'])){
			$meta = json_decode($parent_row['meta'], true) ?: [];
		}
		if (!empty($meta['child_ids']) && is_array($meta['child_ids'])){
			$ids = array_values(array_filter(array_map('intval', $meta['child_ids'])));
			if (!empty($ids)){
				$placeholders = implode(',', array_fill(0, count($ids), '?'));
				$sql = 'select filename from cms_image where cms_image_id in ('.$placeholders.') ';
				$query = $this->db->query($sql, $ids);
				foreach ($query->result_array() as $row){
					if (!empty($row['filename'])){
						$out[$row['filename']] = true;
					}
				}
			}
		}

		return array_keys($out);

	}

	/**
	 * Keep this Shopify image if it (or its parent/child family) is still referenced.
	 */
	function _shopify_image_is_needed($row, &$ref_cache){

		$filename = $row['filename'] ?? '';
		if ($filename === ''){
			return false;
		}

		if ($this->_shopify_filename_referenced_cached($filename, $ref_cache)){
			return true;
		}

		$meta = [];
		if (!empty($row['meta']) && is_string($row['meta'])){
			$meta = json_decode($row['meta'], true) ?: [];
		}

		// Child: keep if parent or any sibling is still used
		$parent_fn = $meta['parent_filename'] ?? '';
		if ($parent_fn !== '' && is_string($parent_fn)){

			if ($this->_shopify_filename_referenced_cached($parent_fn, $ref_cache)){
				return true;
			}

			$parent_row = [
					'filename' => $parent_fn,
					'cms_image_id' => (int)($meta['parent_cms_image_id'] ?? 0),
					'meta' => '',
			];
			if (!empty($meta['parent_cms_image_id'])){
				$sql = 'select cms_image_id, filename, meta from cms_image where cms_image_id = ? limit 1 ';
				$query = $this->db->query($sql, [(int)$meta['parent_cms_image_id']]);
				if ($query->num_rows()){
					$parent_row = $query->row_array();
				}
			}

			foreach ($this->_shopify_image_child_filenames($parent_row) as $sibling_fn){
				if ($sibling_fn === $filename){
					continue;
				}
				if ($this->_shopify_filename_referenced_cached($sibling_fn, $ref_cache)){
					return true;
				}
			}

			return false;

		}

		// Parent: keep if any crop child is still used
		foreach ($this->_shopify_image_child_filenames($row) as $child_fn){
			if ($this->_shopify_filename_referenced_cached($child_fn, $ref_cache)){
				return true;
			}
		}

		return false;

	}

	/**
	 * Move orphan Shopify cms_image files (and resized copies) to cache/tmp/img/.
	 * One-by-one: check reference, then move if orphan (no bulk pre-scan).
	 * Soft-stops after $max_seconds. Click again to continue.
	 */
	function purge_orphan_shopify_images($max_seconds = 100){

		$lock = $this->_images_purge_lock_path();
		if (file_exists($lock)){
			return [
					'error' => 'busy',
					'text' => 'Wait, image purge is running',
					'running' => true,
					'done' => false,
			];
		}

		file_put_contents($lock, (string)time());
		$this->_images_purge_status_write('Loading images...', false);

		$max_seconds = (int)$max_seconds;
		if ($max_seconds < 1){
			$max_seconds = 100;
		}

		$started = time();
		$stopped = false;

		try {

			$this->load->model('cms/cms_image_model');

			$rows = $this->_list_shopify_cms_images();
			$found = count($rows);
			$needed = 0;
			$orphans = 0;
			$moved = 0;
			$ref_cache = [];

			$this->_images_purge_status_write(
					$this->_images_purge_format_status($found, $needed, $orphans, $moved),
					false
			);

			foreach ($rows as $row){

				$filename = $row['filename'] ?? '';
				if ($filename === ''){
					continue;
				}

				if ($this->_shopify_image_is_needed($row, $ref_cache)){
					$needed++;
				} else {
					$orphans++;
					if ($this->cms_image_model->move_cms_image_to_tmp($filename)){
						$moved++;
					}
				}

				$this->_images_purge_status_write(
						$this->_images_purge_format_status($found, $needed, $orphans, $moved),
						false
				);

				if ((time() - $started) >= $max_seconds){
					$stopped = true;
					break;
				}

			}

			$text = $this->_images_purge_format_status($found, $needed, $orphans, $moved);
			if ($stopped){
				$text .= ' - stopped (100s limit)';
			} else {
				$text .= ' - done';
			}

			$this->_images_purge_status_write($text, true);

			return [
					'text' => $text,
					'found' => $found,
					'needed' => $needed,
					'orphans' => $orphans,
					'moved' => $moved,
					'stopped' => $stopped,
					'done' => true,
					'running' => false,
			];

		} finally {

			if (file_exists($lock)){
				unlink($lock);
			}

		}

	}

	/**
	 * Storefront GraphQL (public token) — used for cartCreate at checkout handoff only.
	 */
	function storefront_graphql($query, $variables = []){

		$domain = $GLOBALS['config']['shopify_store_domain'] ?? 'tim-sanders.myshopify.com';
		$token = $GLOBALS['config']['shopify_storefront_token'] ?? '';
		$version = $GLOBALS['config']['shopify_storefront_api_version'] ?? '2026-10';

		if ($token === ''){
			return ['errors' => [['message' => 'Missing shopify_storefront_token in site config']]];
		}

		$url = 'https://'.$domain.'/api/'.$version.'/graphql.json';
		$body = json_encode([
				'query' => $query,
				'variables' => $variables,
		]);

		$ch = curl_init($url);
		curl_setopt_array($ch, [
				CURLOPT_POST => true,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_HTTPHEADER => [
						'Content-Type: application/json',
						'X-Shopify-Storefront-Access-Token: '.$token,
				],
				CURLOPT_POSTFIELDS => $body,
				CURLOPT_TIMEOUT => 30,
		]);
		$raw = curl_exec($ch);
		$errno = curl_errno($ch);
		$error = curl_error($ch);
		curl_close($ch);

		if ($errno){
			return ['errors' => [['message' => 'Storefront request failed: '.$error]]];
		}

		$decoded = json_decode($raw, true);
		if (!is_array($decoded)){
			return ['errors' => [['message' => 'Invalid Storefront response']]];
		}

		return $decoded;

	}

	/**
	 * Build Storefront CartLineInput[] from local order lines (site → Shopify only).
	 * Never reads remote lines into CMS.
	 */
	function _local_lines_to_cart_inputs($lines){

		$cart_lines = [];
		foreach($lines as $line){

			$merchandise = $line['merchandise_id'] ?? '';
			$variant = $line['shopify_variant_id'] ?? '';
			if ($merchandise === '' && $variant === ''){
				continue;
			}
			if ($merchandise === ''){
				$merchandise = (strpos((string)$variant, 'gid://') === 0)
						? $variant
						: 'gid://shopify/ProductVariant/'.$variant;
			}

			$qty = max(1, (int)($line['qty'] ?? $line['quantity'] ?? 1));
			$entry = [
					'merchandiseId' => $merchandise,
					'quantity' => $qty,
			];

			$attrs = [];
			if (!empty($line['attributes']) && is_array($line['attributes'])){
				foreach($line['attributes'] as $k => $v){
					if (is_array($v) && isset($v['key'])){
						$attrs[] = [
								'key' => (string)$v['key'],
								'value' => (string)($v['value'] ?? ''),
						];
					} else if (!is_array($v)){
						$attrs[] = [
								'key' => (string)$k,
								'value' => (string)$v,
						];
					}
				}
			}
			if ($attrs){
				$entry['attributes'] = $attrs;
			}

			$cart_lines[] = $entry;

		}

		return $cart_lines;

	}

	function _storefront_cart_query($cart_id, $with_lines = false){

		if ($cart_id === '' || $cart_id === null){
			return null;
		}

		if ($with_lines){
			$query = '
				query GetCart($id: ID!) {
					cart(id: $id) {
						id
						checkoutUrl
						totalQuantity
						lines(first: 100) {
							edges {
								node { id }
							}
						}
					}
				}
			';
		} else {
			$query = '
				query GetCart($id: ID!) {
					cart(id: $id) {
						id
						checkoutUrl
						totalQuantity
					}
				}
			';
		}

		$response = $this->storefront_graphql($query, ['id' => $cart_id]);
		if (!empty($response['errors'])){
			// Treat API errors as dead cart for status purposes when id is invalid
			return null;
		}

		return $response['data']['cart'] ?? null;

	}

	function _save_shopify_cart_on_order($order_id, $cart_id, $fingerprint, $checkout_url){

		$this->load->model('cms/cms_page_panel_model');
		$this->cms_page_panel_model->update_cms_page_panel($order_id, [
				'shopify_cart_id' => $cart_id,
				'shopify_lines_fingerprint' => $fingerprint,
				'shopify_checkout_url' => $checkout_url,
		]);
		$_SESSION['shopify']['shopify_cart_id'] = $cart_id;
		$_SESSION['shopify']['checkout_url'] = $checkout_url;

	}

	function _cart_create_with_lines($cart_lines){

		$mutation = '
			mutation CartCreate($lines: [CartLineInput!]!) {
				cartCreate(input: { lines: $lines }) {
					cart {
						id
						checkoutUrl
						totalQuantity
					}
					userErrors {
						field
						message
					}
				}
			}
		';

		$response = $this->storefront_graphql($mutation, ['lines' => $cart_lines]);
		if (!empty($response['errors'])){
			return ['ok' => 0, 'error' => $response['errors'][0]['message'] ?? 'Storefront error'];
		}
		$payload = $response['data']['cartCreate'] ?? null;
		if (empty($payload)){
			return ['ok' => 0, 'error' => 'Cart create failed'];
		}
		if (!empty($payload['userErrors'])){
			return ['ok' => 0, 'error' => $payload['userErrors'][0]['message'] ?? 'Cart create rejected'];
		}
		$cart = $payload['cart'] ?? [];
		if (empty($cart['id']) || empty($cart['checkoutUrl'])){
			return ['ok' => 0, 'error' => 'No checkout URL returned'];
		}
		return [
				'ok' => 1,
				'cart' => $cart,
		];

	}

	/**
	 * Replace all merchandise lines on an existing Storefront cart from local inputs only.
	 */
	function _cart_replace_lines($cart_id, $cart_lines){

		$remote = $this->_storefront_cart_query($cart_id, true);
		if (empty($remote['id'])){
			return ['ok' => 0, 'error' => 'Remote cart gone', 'dead' => 1];
		}

		$line_ids = [];
		foreach(($remote['lines']['edges'] ?? []) as $edge){
			if (!empty($edge['node']['id'])){
				$line_ids[] = $edge['node']['id'];
			}
		}

		if ($line_ids){
			$remove_mut = '
				mutation CartLinesRemove($cartId: ID!, $lineIds: [ID!]!) {
					cartLinesRemove(cartId: $cartId, lineIds: $lineIds) {
						cart { id checkoutUrl totalQuantity }
						userErrors { field message }
					}
				}
			';
			$remove_res = $this->storefront_graphql($remove_mut, [
					'cartId' => $cart_id,
					'lineIds' => $line_ids,
			]);
			if (!empty($remove_res['errors'])){
				return ['ok' => 0, 'error' => $remove_res['errors'][0]['message'] ?? 'cartLinesRemove failed'];
			}
			if (!empty($remove_res['data']['cartLinesRemove']['userErrors'])){
				$ue = $remove_res['data']['cartLinesRemove']['userErrors'][0]['message'] ?? 'cartLinesRemove rejected';
				return ['ok' => 0, 'error' => $ue];
			}
		}

		if ($cart_lines){
			$add_mut = '
				mutation CartLinesAdd($cartId: ID!, $lines: [CartLineInput!]!) {
					cartLinesAdd(cartId: $cartId, lines: $lines) {
						cart { id checkoutUrl totalQuantity }
						userErrors { field message }
					}
				}
			';
			$add_res = $this->storefront_graphql($add_mut, [
					'cartId' => $cart_id,
					'lines' => $cart_lines,
			]);
			if (!empty($add_res['errors'])){
				return ['ok' => 0, 'error' => $add_res['errors'][0]['message'] ?? 'cartLinesAdd failed'];
			}
			$payload = $add_res['data']['cartLinesAdd'] ?? null;
			if (!empty($payload['userErrors'])){
				return ['ok' => 0, 'error' => $payload['userErrors'][0]['message'] ?? 'cartLinesAdd rejected'];
			}
			$cart = $payload['cart'] ?? null;
		} else {
			$cart = $this->_storefront_cart_query($cart_id, false);
		}

		if (empty($cart['id']) || empty($cart['checkoutUrl'])){
			return ['ok' => 0, 'error' => 'No checkout URL after line replace'];
		}

		return ['ok' => 1, 'cart' => $cart];

	}

	/**
	 * Push local order lines to Shopify (site → remote only). Reuse cart id when open
	 * so addresses/vouchers survive; replace lines when local fingerprint changed.
	 */
	function materialise_checkout_from_order($order_id){

		$this->load->model('shop/shop_model');
		$this->load->model('cms/cms_page_panel_model');

		$order = $this->cms_page_panel_model->get_cms_page_panel($order_id);
		if (empty($order['cms_page_panel_id'])){
			return ['ok' => 0, 'error' => 'Order not found'];
		}

		$lines = $this->shop_model->get_order_lines($order_id);
		if (empty($lines)){
			return ['ok' => 0, 'error' => 'Cart is empty'];
		}

		$cart_lines = $this->_local_lines_to_cart_inputs($lines);
		if (empty($cart_lines)){
			return ['ok' => 0, 'error' => 'No Shopify products in cart'];
		}

		$fingerprint = $this->shop_model->calculate_order_lines_fingerprint($lines);
		$existing_id = $order['shopify_cart_id'] ?? '';

		// Reuse open remote cart
		if ($existing_id !== ''){
			$remote = $this->_storefront_cart_query($existing_id, false);

			if (empty($remote['id'])){
				// Dead / completed checkout — end site cart (do not create a new remote cart with same lines)
				$this->shop_model->close_cart_order($order_id, 'paid');
				return [
						'ok' => 0,
						'error' => 'Previous checkout completed. Your cart was cleared — add items again.',
						'changed' => 1,
						'quantity' => 0,
						'closed' => 1,
				];
			} else if (($order['shopify_lines_fingerprint'] ?? '') === $fingerprint){
				// Lines already pushed — keep buyer state on Shopify
				$checkout_url = $remote['checkoutUrl'] ?? ($order['shopify_checkout_url'] ?? '');
				if ($checkout_url === ''){
					return ['ok' => 0, 'error' => 'No checkout URL on remote cart'];
				}
				$this->_save_shopify_cart_on_order($order_id, $existing_id, $fingerprint, $checkout_url);
				return [
						'ok' => 1,
						'redirect' => $checkout_url,
						'shopify_cart_id' => $existing_id,
						'quantity' => (int)($remote['totalQuantity'] ?? 0),
						'reused' => 1,
				];
			} else {
				// Local cart changed — overwrite remote lines from site only
				$replaced = $this->_cart_replace_lines($existing_id, $cart_lines);
				if (!empty($replaced['dead'])){
					$this->shop_model->close_cart_order($order_id, 'paid');
					return [
							'ok' => 0,
							'error' => 'Previous checkout completed. Your cart was cleared — add items again.',
							'changed' => 1,
							'quantity' => 0,
							'closed' => 1,
					];
				}
				if (empty($replaced['ok'])){
					return $replaced;
				}
				$cart = $replaced['cart'];
				$this->_save_shopify_cart_on_order(
						$order_id,
						$cart['id'],
						$fingerprint,
						$cart['checkoutUrl']
				);
				return [
						'ok' => 1,
						'redirect' => $cart['checkoutUrl'],
						'shopify_cart_id' => $cart['id'],
						'quantity' => (int)($cart['totalQuantity'] ?? 0),
						'replaced' => 1,
				];
			}
		}

		// New Storefront cart
		$created = $this->_cart_create_with_lines($cart_lines);
		if (empty($created['ok'])){
			return $created;
		}
		$cart = $created['cart'];
		$this->_save_shopify_cart_on_order($order_id, $cart['id'], $fingerprint, $cart['checkoutUrl']);

		return [
				'ok' => 1,
				'redirect' => $cart['checkoutUrl'],
				'shopify_cart_id' => $cart['id'],
				'quantity' => (int)($cart['totalQuantity'] ?? 0),
				'created' => 1,
		];

	}

	/**
	 * Status-only check: if Storefront cart is gone (typically after successful checkout),
	 * close the CMS order and clear the site cart cookie. Never syncs lines Shopify → site.
	 */
	function reconcile_checkout_status($order){

		$this->load->model('shop/shop_model');

		if (empty($order['cms_page_panel_id'])){
			return ['ok' => 1, 'changed' => 0];
		}

		$cart_id = $order['shopify_cart_id'] ?? '';
		if ($cart_id === ''){
			return ['ok' => 1, 'changed' => 0];
		}

		// Throttle: once per 45s per order
		$throttle_key = 'shopify_reconcile_'.$order['cms_page_panel_id'];
		$now = time();
		if (!empty($_SESSION[$throttle_key]) && ($now - (int)$_SESSION[$throttle_key]) < 45){
			return ['ok' => 1, 'changed' => 0, 'throttled' => 1];
		}
		$_SESSION[$throttle_key] = $now;

		$remote = $this->_storefront_cart_query($cart_id, false);

		if (!empty($remote['id'])){
			// Still open (user may have edited lines on Shopify — site cart unchanged)
			return ['ok' => 1, 'changed' => 0, 'open' => 1];
		}

		// Dead cart → treat as completed checkout; empty site cart session
		$this->shop_model->close_cart_order($order['cms_page_panel_id'], 'paid');

		return [
				'ok' => 1,
				'changed' => 1,
				'quantity' => 0,
				'closed' => 1,
		];

	}

}

