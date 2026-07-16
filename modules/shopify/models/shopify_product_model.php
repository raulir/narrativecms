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
	
	function call($endpoint, $params = []){
		
		$caching = $params['force'] ?? 0;
		// -1 - never update if cache available
		// 0 - 300s caching
		// 1 - always update cache
		
		if (isset($params['force'])){
			unset($params['force']);
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

		if (!file_exists($filename) || ((time() - filemtime($filename)) > 300 && $caching != -1) || $caching == 1){
			
			$more = true;
			$data = [];
			
			while ($more){
		
				if (empty($params)){
					$response = $this->client->get(path: $endpoint);
				} else {
					$response = $this->client->get(path: $endpoint, query: $params);
				}
				
				$request_data = $response->getDecodedBody();

				if (!empty($request_data['errors'])){
					
					$data = $request_data;
					$more = false;
					
				} else if (stristr($endpoint, '/') && count($request_data) == 1){
					
					$data = reset($request_data);
					$more = false;

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
				
				}

			}

			file_put_contents($filename, json_encode($data, JSON_PRETTY_PRINT));
				
		} else {
				
			$data = json_decode(file_get_contents($filename), true);
			
			if (!is_array($data)){
				$data = [];
			}
				
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

	function get_product($product_shopify_id, $force = 0){
		
		$product = $this->call('products/'.$product_shopify_id, ['force' => $force, ]);
// _print_r($product);		
		if (!is_array($product) || !empty($product['errors'])){
			return [];
		}
		
		return $product;
		
	}
	
	function get_product_by_id($product_id){
		
		$product = $this->refresh_product($product_id);

		return $product;
		
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
			
			$cms_product = $this->cms_page_panel_model->get_cms_page_panels_by(['panel_name' => 'shopify/product', 'shopify_id' => $product['id']]);
				
// _print_r($cms_product);
				
			if (empty($cms_product)){

				$new_cms_product = [
						'panel_name' => 'shopify/product',
						'show' => 1,
						'sort' => 'first',
						'shopify_id' => $product['id'],
						'heading' => $product['title'],
						'type' => $product['product_type'],
						'colour' => 'white',
				];
					
				$shopify_products[$product_key]['cms_page_panel_id'] = $this->cms_page_panel_model->create_cms_page_panel($new_cms_product);

				// update slug
				$slug = $this->cms_slug_model->generate_list_item_slug('_/product='.$shopify_products[$product_key]['cms_page_panel_id'], $product['title']);
		
				$this->cms_slug_model->set_page_slug('_/product='.$shopify_products[$product_key]['cms_page_panel_id'], $slug, '0');
				
				$slugs_updated = true;
		
			}
				
		}
		
		if ($slugs_updated){
			$this->cms_slug_model->_regenerate_cache();
			$this->cms_slug_model->_regenerate_sitemap();
		}
		
		return $shopify_products;
		
	}
	
	function refresh_product($cms_product_id, $force = 0){
		
		$this->load->model('cms/cms_page_panel_model');
		$this->load->model('cms/cms_slug_model');
		$this->load->model('cms/cms_image_model');
		
		$cms_product = $this->cms_page_panel_model->get_cms_page_panel($cms_product_id);
		
		if (empty($cms_product['cms_page_panel_id'])){
			return [];
		}
		
		if (empty($cms_product['shopify_id'])){
			$this->cms_page_panel_model->update_cms_page_panel($cms_product_id, ['show' => 0, ]);
			return [];
		}
		
		$shopify_product = $this->get_product($cms_product['shopify_id'], $force);

		if (!is_array($shopify_product) || empty($shopify_product['id'])){
			$this->cms_page_panel_model->update_cms_page_panel($cms_product_id, ['show' => 0, ]);
			return [];
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

		// Clear force-refresh flag after a full pass
		if (!empty($cms_product['sync_needed'])){
			$cms_product['sync_needed'] = 0;
			$needs_update = true;
		}

		if ($needs_update){
			$cms_product['update_time'] = time();
			$cms_product['last_update'] = time();
			$this->cms_page_panel_model->update_cms_page_panel($cms_product_id, $cms_product, true);
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
				'panel_name' => 'shopify/product',
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
						'panel_name' => 'shopify/product',
						'show' => 1,
						'sort' => 'first',
						'shopify_id' => $product['id'],
						'heading' => $product['title'] ?? '',
						'type' => $product['product_type'] ?? '',
						'colour' => 'white',
				];

				$cms_page_panel_id = $this->cms_page_panel_model->create_cms_page_panel($new_cms_product);

				$slug = $this->cms_slug_model->generate_list_item_slug(
						'_/product='.$cms_page_panel_id,
						$product['title'] ?? ('product-'.$product['id'])
				);
				$this->cms_slug_model->set_page_slug('_/product='.$cms_page_panel_id, $slug, '0');
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
				'panel_name' => 'shopify/product',
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
				'panel_name' => 'stock/category',
				'heading' => 'Cards',
		]);

		if (empty($rows[0]['cms_page_panel_id'])){
			_html_error('Shopify sync: stock/category "Cards" not found — cannot create subcategories');
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
				'panel_name' => 'stock/subcategory',
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
	 * Ensure list-item slug for stock/subcategory={id} (link_target panels need this for frontend URLs).
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

		$target = 'stock/subcategory='.$cms_page_panel_id;
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
				'panel_name' => 'stock/subcategory',
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
	 * Delete local shopify/product panels that no longer exist on Shopify.
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
					'panel_name' => 'shopify/product',
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

}
