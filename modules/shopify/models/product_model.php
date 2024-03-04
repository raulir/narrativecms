<?php
require_once 'vendor/autoload.php';

use Shopify\Auth\FileSessionStorage;
use Shopify\Clients\Rest;
use Shopify\Rest\Admin2023_10\Collection;
use Shopify\Utils;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');


class product_model extends Model {
	
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
				apiVersion: '2023-10',
		);
		
		$this->client = new Rest('tim-sanders.myshopify.com', $GLOBALS['config']['shopify_api_token']);

	}
	
	function call($endpoint, $params = []){
		
		$filename = $GLOBALS['config']['base_path'].'/cache/shopify_'.substr(md5($endpoint.json_encode($params)), 0, 16).'.json';
		
		if (!file_exists($filename) || (time() - filemtime($filename)) > 300 ){
				
			if (empty($params)){
				$response = $this->client->get(path: $endpoint);
			} else {
				$response = $this->client->get(path: $endpoint, query: $params);
//				_print_r($response);
			}
			
			$data = $response->getDecodedBody();

			file_put_contents($filename, json_encode($data, JSON_PRETTY_PRINT));
				
		} else {
				
			$data = json_decode(file_get_contents($filename), true);
				
		}
		
		if (stristr($endpoint, '/')){
			$endpoint_a = explode('/', $endpoint);
			$endpoint_key = end($endpoint_a);
		} else {
			$endpoint_key = $endpoint;
		}
		
		if (!empty($data[$endpoint_key])){
			return $data[$endpoint_key];
		} else {
			return $data;
		}

	}

	function get_products($collection_handle = ''){
		
		$collections = $this->call('custom_collections');
		
		foreach($collections as $collection){
			if ($collection['handle'] == $collection_handle){
				$collection_id = $collection['id'];
			}
		}
		
		$products = $this->call('collections/'.$collection_id.'/products'); //, ['collection_id' => $collection_id, ]);
		
//		_print_r($products);
		
		$return = [];
		
		foreach($products as $product){
			
			$product_data = $this->get_product($product['id']);
			
//			_print_r($product_data);
				
			$min_price = 0;
			$max_price = 0;
			$available = 0;
		
			foreach($product_data['variants'] as $variant){
				if (empty($min_price) || $min_price > $variant['price']) $min_price = $variant['price'];
				if (empty($max_price) || $max_price < $variant['price']) $max_price = $variant['price'];
				$available += $variant['inventory_quantity'];
			}
		
			$return[] = [
					'shopify_id' => $product['id'],
					'heading' => $product_data['title'],
					'image' => $product_data['image']['src'] ?? '',
					'category' => $product_data['product_type'],
					'min_price' => $min_price,
					'max_price' => $max_price,
					'available' => $available,
			];
		}

		return $return;
	
	}
	
	function get_product($product_shopify_id){
		
		$product = $this->call('products/'.$product_shopify_id)['product'];
		
		return $product;
		
	}
	
}
