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
		$tmp_dir = $GLOBALS['config']['base_path'].'/cache/';
		
		Shopify\Context::initialize(
				apiKey: $api_key,
				apiSecretKey: $api_secret,
				scopes: ['read_products', 'read_product_listings', 'read_orders', 'read_product_feeds'],
				hostName: 'tim-sanders.myshopify.com',
				sessionStorage: new FileSessionStorage($tmp_dir),
				apiVersion: '2025-07',
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
		
		$filename = $GLOBALS['config']['base_path'].'/cache/shopify_'.substr(md5($endpoint.json_encode($params)), 0, 16).'.json';
		
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

				if (stristr($endpoint, '/') && count($request_data) == 1){
					
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
		if (!empty($product['errors']) && $product['errors'] == 'Not Found'){
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

		if (empty($shopify_product)){
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
			
		} else if (empty($cms_product['image_update']) || 
				($cms_product['image_name_hash'] ?? '') != md5(parse_url($shopify_product['image']['src'], PHP_URL_PATH)) ||
				$cms_product['image_update'] != strtotime($shopify_product['image']['updated_at']) ||
				!file_exists($GLOBALS['config']['upload_path'].$cms_product['image'])){
			
			$image = $this->cms_image_model->scrape_image($shopify_product['image']['src'], 'shopify', 'shopify');
			
			$cms_product['image'] = $image;
			$cms_product['image_update'] = strtotime($shopify_product['image']['updated_at']);
			$cms_product['image_name_hash'] = md5(parse_url($shopify_product['image']['src'], PHP_URL_PATH));
			$needs_update = true;
			
		}
		
		$found_images = [];
		foreach($shopify_product['images'] as $shopify_image){
				
			$i_current = -1;
			if (empty($cms_product['images'])){
				$cms_product['images'] = [];
			}
			foreach($cms_product['images'] as $key => $image){
				if ($image['shopify_id'] == $shopify_image['id']){
					$i_current = $key;
		
					if ($image['image_update'] != strtotime($shopify_image['updated_at']) ||	!file_exists($GLOBALS['config']['upload_path'].$image['image'])){
		
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
			if (!in_array($image['shopify_id'], $found_images)){
				unset($cms_product['images'][$key]);
			} else {
				unset($found_images[array_search($image['shopify_id'], $found_images)]);
			}
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

}
