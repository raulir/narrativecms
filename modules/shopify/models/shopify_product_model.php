<?php
require_once 'vendor/autoload.php';

use Shopify\Auth\FileSessionStorage;
use Shopify\Clients\Rest;
use Shopify\Rest\Admin2025_07\Collection;
use Shopify\Utils;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class shopify_product_model extends Model {
	
	function __construct(){
		
		$api_key = $GLOBALS['config']['shopify_api_key'];
		$api_secret = $GLOBALS['config']['shopify_api_secret'];
		$tmp_dir = $GLOBALS['config']['base_path'].'cache/';
		
		Shopify\Context::initialize(
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

		// Defaults when settings not yet saved in admin
		$defaults = [
				'thumb_html_ttl' => 900,
				'shopify_data_ttl' => 3600,
				'product_page_recheck_ttl' => 300,
				'max_refresh_time' => 30,
		];
		foreach ($defaults as $key => $value){
			if (!isset($settings[$key]) || $settings[$key] === '' || $settings[$key] === null){
				$settings[$key] = $value;
			} else {
				$settings[$key] = (int)$settings[$key];
			}
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

			} catch (Exception $e){

				$data = ['_soft_fail' => 1, '_reason' => 'exception', '_message' => $e->getMessage(), ];
				$api_ok = false;

			} catch (Throwable $e){

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
	 * Productthumb display payload path (shopify-owned; not CMS panel HTML cache).
	 */
	function _productthumb_cache_path($cms_page_panel_id){

		return $GLOBALS['config']['base_path'].'cache/productthumb_'.(int)$cms_page_panel_id.'.json';

	}

	/**
	 * Drop productthumb payload so next list/related render rebuilds display fields.
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
	 * Read productthumb payload if within thumb_html_ttl and not older than product update_time.
	 * Returns ['product' => ..., 'sold_out_label' => ...] or null.
	 */
	function get_productthumb_payload_cache($cms_page_panel_id, $update_time = 0){

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

		$payload = cms_json_decode(file_get_contents($filename), $filename);
		if (!is_array($payload) || empty($payload['product'])){
			return null;
		}

		return $payload;

	}

	/**
	 * Write productthumb display payload (price HTML, subcategory label, thumbnail, …).
	 */
	function set_productthumb_payload_cache($cms_page_panel_id, $payload){

		$cms_page_panel_id = (int)$cms_page_panel_id;
		if ($cms_page_panel_id <= 0 || !is_array($payload)){
			return;
		}

		file_put_contents(
				$this->_productthumb_cache_path($cms_page_panel_id),
				json_encode($payload, JSON_PRETTY_PRINT)
		);

	}

	/**
	 * Full productthumb panel_params assembly: cache → CMS/Shopify product → display fields.
	 * Parents only pass cms_page_panel_id (+ productthumb settings labels merged by CMS).
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
			return $params;
		}

		$update_time = (int)($params['update_time'] ?? 0);
		$cached = $this->get_productthumb_payload_cache($cms_page_panel_id, $update_time);
		if ($cached !== null){
			$params['product'] = $cached['product'];
			if (array_key_exists('sold_out_label', $cached) && $cached['sold_out_label'] !== null){
				$params['sold_out_label'] = $cached['sold_out_label'];
			}
			return $params;
		}

		// Rebuild: CMS-first, Shopify recheck only when data TTL expired + budget
		$product = $this->get_product_by_id($cms_page_panel_id, 'thumb');

		if (empty($product) || empty($product['cms_page_panel_id'])){
			$params['product'] = [
					'heading' => 'Missing product cms id: '.$cms_page_panel_id,
					'error' => 1,
			];
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

		// Subcategory label when Shopify type is empty
		$product['subcategory_heading'] = '';
		if (!empty($product['subcategory_id'])){
			$subcategory = $this->cms_page_panel_model->get_cms_page_panel($product['subcategory_id']);
			$product['subcategory_heading'] = trim((string)($subcategory['heading'] ?? ''));
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

		$this->set_productthumb_payload_cache($cms_page_panel_id, [
				'product' => $product,
				'sold_out_label' => $params['sold_out_label'] ?? null,
		]);

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
		if ($this->_assign_subcategory_if_empty($cms_product)){
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
		// for checked_at alone — that would thrash productthumb payload invalidation.
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
					// force refresh when images/subcategory/flag need work (even if Shopify updated_at matches)
					'sync_needed' => $this->_product_needs_sync($panel),
			];
		}

		return $map;

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
	 * Sync frontpage collection products into CMS.
	 * New: create + full refresh. Stale: full refresh when Shopify updated_at is newer.
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

	function _purge_format_status($total, $purged, $kept){

		return 'Total '.$total.', purged '.$purged.', kept '.$kept;

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
	 */
	function _product_needs_sync($panel){

		if (!empty($panel['sync_needed']) && $panel['sync_needed'] !== '0' && $panel['sync_needed'] !== 0){
			return true;
		}

		if ($this->_product_needs_image_repair($panel)){
			return true;
		}

		$subcategory_id = $panel['subcategory_id'] ?? '';
		if ($subcategory_id === '' || $subcategory_id === null || $subcategory_id === '0' || $subcategory_id === 0){
			return true;
		}

		return false;

	}

	function _sync_needed_once_path(){

		return $GLOBALS['config']['base_path'].'cache/shopify_sync_needed_v1.done';

	}

	/**
	 * One-shot after deploy: mark every local product sync_needed so stale list includes them.
	 */
	function _mark_all_products_sync_needed_once(){

		$path = $this->_sync_needed_once_path();
		if (file_exists($path)){
			return;
		}

		$this->load->model('cms/cms_page_panel_model');

		$panels = $this->cms_page_panel_model->get_cms_page_panels_by([
				'panel_name' => 'shop/product',
		]);

		foreach ($panels as $panel){
			$id = (int)($panel['cms_page_panel_id'] ?? 0);
			if (!$id){
				continue;
			}
			if (!empty($panel['sync_needed']) && $panel['sync_needed'] !== '0' && $panel['sync_needed'] !== 0){
				continue;
			}
			$this->cms_page_panel_model->update_cms_page_panel($id, ['sync_needed' => 1], true);
		}

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
			$map[(string)$col['id']] = [
					'id' => $col['id'],
					'title' => $col['title'] ?? '',
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
	 * Prefer a range collection; ignore Shopify frontpage/Main.
	 * If several remain, pick title A–Z for stability.
	 */
	function _pick_range_collection($collections){

		if (empty($collections) || !is_array($collections)){
			return null;
		}

		$candidates = [];
		foreach ($collections as $col){
			$handle = strtolower(trim((string)($col['handle'] ?? '')));
			$title = trim((string)($col['title'] ?? ''));
			if ($handle === 'frontpage' || strtolower($title) === 'main'){
				continue;
			}
			if ($title === ''){
				continue;
			}
			$candidates[] = $col;
		}

		if (empty($candidates)){
			return null;
		}

		usort($candidates, function($a, $b){
			return strcasecmp((string)($a['title'] ?? ''), (string)($b['title'] ?? ''));
		});

		return $candidates[0];

	}

	function _get_cards_category_id(){

		if (isset($this->_cards_category_id)){
			return $this->_cards_category_id;
		}

		$this->load->model('cms/cms_page_panel_model');

		$rows = $this->cms_page_panel_model->get_cms_page_panels_by([
				'panel_name' => 'shop/category',
				'heading' => 'Cards',
		]);

		if (empty($rows[0]['cms_page_panel_id'])){
			_html_error('Shopify sync: shop/category "Cards" not found — cannot create subcategories');
			$this->_cards_category_id = 0;
			return 0;
		}

		$this->_cards_category_id = (int)$rows[0]['cms_page_panel_id'];
		return $this->_cards_category_id;

	}

	/**
	 * Find subcategory by shopify_collection; fall back to heading match and backfill the field.
	 */
	function _find_subcategory_by_shopify_collection($collection_title){

		$collection_title = trim((string)$collection_title);
		if ($collection_title === ''){
			return null;
		}

		$this->load->model('cms/cms_page_panel_model');

		$subs = $this->cms_page_panel_model->get_cms_page_panels_by([
				'panel_name' => 'shop/subcategory',
		]);

		$heading_match = null;

		foreach ($subs as $sub){
			$sc = trim((string)($sub['shopify_collection'] ?? ''));
			if ($sc !== '' && $sc === $collection_title){
				return $sub;
			}
			if ($heading_match === null && trim((string)($sub['heading'] ?? '')) === $collection_title){
				$heading_match = $sub;
			}
		}

		// Harden: reuse existing row with same heading and set shopify_collection
		if ($heading_match !== null){
			if (trim((string)($heading_match['shopify_collection'] ?? '')) === ''){
				$this->cms_page_panel_model->update_cms_page_panel(
						(int)$heading_match['cms_page_panel_id'],
						['shopify_collection' => $collection_title],
						true
				);
				$heading_match['shopify_collection'] = $collection_title;
			}
			return $heading_match;
		}

		return null;

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

	function _ensure_subcategory_for_collection($collection_title){

		$collection_title = trim((string)$collection_title);
		if ($collection_title === ''){
			return null;
		}

		$existing = $this->_find_subcategory_by_shopify_collection($collection_title);
		if (!empty($existing['cms_page_panel_id'])){
			// Backfill slug for subcategories created before slug support
			$this->_ensure_subcategory_slug(
					(int)$existing['cms_page_panel_id'],
					$collection_title,
					$existing['show'] ?? 1
			);
			return $existing;
		}

		$cards_id = $this->_get_cards_category_id();
		if (empty($cards_id)){
			return null;
		}

		$this->load->model('cms/cms_page_panel_model');

		$new_id = $this->cms_page_panel_model->create_cms_page_panel([
				'panel_name' => 'shop/subcategory',
				'show' => 1,
				'sort' => 'first',
				'title' => $collection_title,
				'heading' => $collection_title,
				'shopify_collection' => $collection_title,
				'category_id' => $cards_id,
		]);

		if (empty($new_id)){
			return null;
		}

		$this->_ensure_subcategory_slug((int)$new_id, $collection_title, 1);

		return $this->cms_page_panel_model->get_cms_page_panel($new_id);

	}

	/**
	 * When product has no subcategory, assign from Shopify range collection.
	 * @return bool true if $cms_product was modified
	 */
	function _assign_subcategory_if_empty(&$cms_product){

		$subcategory_id = $cms_product['subcategory_id'] ?? '';
		if ($subcategory_id !== '' && $subcategory_id !== null && $subcategory_id !== '0' && $subcategory_id !== 0){
			return false;
		}

		$shopify_id = $cms_product['shopify_id'] ?? '';
		if ($shopify_id === '' || $shopify_id === null){
			return false;
		}

		$collections = $this->_get_product_collections($shopify_id);
		$range = $this->_pick_range_collection($collections);
		if (empty($range['title'])){
			return false;
		}

		$sub = $this->_ensure_subcategory_for_collection($range['title']);
		if (empty($sub['cms_page_panel_id'])){
			return false;
		}

		$cms_product['subcategory_id'] = (int)$sub['cms_page_panel_id'];
		return true;

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
	 * Delete local shop/product panels that no longer exist on Shopify.
	 * Oldest first (cms_page_panel_id ASC). Soft stop after 50s once current item finishes.
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

			$total = count($products);
			$purged = 0;
			$kept = 0;

			$this->_purge_status_write($this->_purge_format_status($total, $purged, $kept), false);

			foreach ($products as $cms_product){

				$cms_page_panel_id = (int)($cms_product['cms_page_panel_id'] ?? 0);
				$shopify_id = $cms_product['shopify_id'] ?? '';

				$missing = false;
				if ($shopify_id === '' || $shopify_id === null){
					$missing = true;
				} else {
					$remote = $this->get_product($shopify_id, 1);
					if (!is_array($remote) || empty($remote['id'])){
						$missing = true;
					}
				}

				if ($missing){
					$this->_purge_delete_product_images($cms_product);
					if ($cms_page_panel_id){
						$this->cms_page_panel_model->delete_cms_page_panel($cms_page_panel_id);
					}
					$purged++;
				} else {
					$kept++;
				}

				$this->_purge_status_write($this->_purge_format_status($total, $purged, $kept), false);

				if ((time() - $started) >= $max_seconds){
					$stopped = true;
					break;
				}

			}

			if ($purged > 0){
				$this->cms_slug_model->_regenerate_cache();
				$this->cms_slug_model->_regenerate_sitemap();
			}

			$text = $this->_purge_format_status($total, $purged, $kept);
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

