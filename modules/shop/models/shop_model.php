<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class shop_model extends Model {

	function get_product_variations($product_id, $params = []){
		
		$this->load->model('cms/cms_page_panel_model');
		
		$product = $this->cms_page_panel_model->get_cms_page_panel($product_id);
		if (!empty($product['product_stock_id'])){
			$product_stock_group = $this->cms_page_panel_model->get_cms_page_panel($product['product_stock_id']);
		}
		
		$exlude_items = [];
			
		// if stock control limited
		if (!empty($product_stock_group['stock_control']) && $product_stock_group['stock_control'] == 'individual'){
			$product_items = $this->cms_page_panel_model->get_list('shop/product_item', ['product_id' => $product_id, 'order_id' => [0, '']]);
			if (!empty($params['exclude_order_id'])){
				$exclude_lines = $this->cms_page_panel_model->get_list('shop/order_line', ['order_id' => $params['exclude_order_id']]);
				foreach($exclude_lines as $line){
					
					$exlude_items[] = $line['ref_id'];
					
				}
			}
		} else {
			$product_items = $this->cms_page_panel_model->get_list('shop/product_item', ['product_id' => $product_id]);
		}
		
		// filter out if limited to certain dimension value
		if (!empty($params['dimension_name'])){
			
			$params['dimensions'] = [
					$params['dimension_name'] => $params['dimension_value'],
			];
			
		}
		
		if (!empty($params['dimensions'])){

			foreach($product_items as $item_key => $item){

				$found = 0;

				foreach($params['dimensions'] as $dim_name => $dim_value){
					foreach($item['dimensions'] as $variation){
						if ($variation['value'] == ($dim_name . '=' . $dim_value)){
							$found += 1;
						}
					}
				}

				if ($found < count($params['dimensions'])){
					unset($product_items[$item_key]);
				}
			
			}
			
		}

		$return = [];
		foreach($product_items as $item){
			
			if (!in_array($item['cms_page_panel_id'], $exlude_items)){
			
				foreach($item['dimensions'] as $variation){
					
					list($dimension_name, $dimension_value_id) = explode('=', $variation['value']);
					
					if (empty($return[$dimension_name])){
						$return[$dimension_name] = [];
					}
					
					if (empty($return[$dimension_name][$dimension_value_id])){
						$return[$dimension_name][$dimension_value_id] = [
								'data' => $this->get_dimension_value_data($dimension_name, $dimension_value_id),
								'count' => 0,
								'items' => [],
						];
					}
					
					$return[$dimension_name][$dimension_value_id]['count'] += 1;
					$return[$dimension_name][$dimension_value_id]['items'][$item['cms_page_panel_id']] = 
							!empty($item['price']) ? $item['price'] : $product['price'];
					
					// fill in availability, go again over variations
					if (empty($return[$dimension_name][$dimension_value_id]['availability'])){
						$return[$dimension_name][$dimension_value_id]['availability'] = [];
					}
					foreach($item['dimensions'] as $avail_var){
						
						if ($avail_var['value'] != $variation['value']){
							list($avail_var_name, $avail_var_value_id) = explode('=', $avail_var['value']);
							$return[$dimension_name][$dimension_value_id]['availability'][$avail_var_name][$avail_var_value_id] = true;
						}
					
					}
					
				}
			
			}
			
		}
		
		foreach($return as &$dimension_data){
			ksort($dimension_data);
		}

		return $return;

	}

	function get_dimension_value_data($dimension_name, $dimension_value_id){
		
		$this->load->model('cms/cms_page_panel_model');
		
		$dimensions = $this->cms_page_panel_model->get_list('shop/product_dimension', ['id' => $dimension_name]);
		
		$return = ['label' => '[no value]', 'description' => ''];
		foreach($dimensions as $dimension){
			foreach($dimension['values'] as $value){
				if ($value['id'] == $dimension_value_id){
					$return = ['label' => $value['label'], 'description' => $value['description']];
				}
			}
		}
		
		return $return;
		
	}
	
	function get_dimension_label($dimension_name){
		
		$this->load->model('cms/cms_page_panel_model');
		
		$dimensions = $this->cms_page_panel_model->get_list('shop/product_dimension', ['id' => $dimension_name]);
		
		$return = '[no value]';
		foreach($dimensions as $dimension){
			$return = $dimension['heading'];
		}
	
		return $return;
		
	}
	
	/**
	 * Long-lived cookie identity for anonymous carts (not sequential order id).
	 */
	function get_cart_cookie_name(){
		return 'shop_cart';
	}

	function get_cart_cookie_days(){
		return 60;
	}

	function get_cart_key_from_cookie(){
		$name = $this->get_cart_cookie_name();
		$key = $_COOKIE[$name] ?? '';
		if (!is_string($key) || !preg_match('/^[a-f0-9]{32,64}$/', $key)){
			return '';
		}
		return $key;
	}

	function set_cart_cookie($cart_key){
		include_once($GLOBALS['config']['base_path'].'system/helpers/cookie_helper.php');
		cms_cookie_create($this->get_cart_cookie_name(), $cart_key, $this->get_cart_cookie_days());
	}

	function clear_cart_cookie(){
		include_once($GLOBALS['config']['base_path'].'system/helpers/cookie_helper.php');
		// Expire immediately
		cms_cookie_create($this->get_cart_cookie_name(), '', -1);
		unset($_COOKIE[$this->get_cart_cookie_name()]);
	}

	/**
	 * End draft cart session after remote checkout completes (or force close).
	 * Order is no longer status '' so it will not be reused as a basket.
	 */
	function close_cart_order($order_id, $status = 'paid'){

		$this->load->model('cms/cms_page_panel_model');

		$order = $this->cms_page_panel_model->get_cms_page_panel($order_id);
		if (empty($order['cms_page_panel_id'])){
			return false;
		}

		$this->cms_page_panel_model->update_cms_page_panel($order_id, [
				'status' => $status,
				'paid_time' => !empty($order['paid_time']) ? $order['paid_time'] : time(),
		]);

		$cookie_key = $this->get_cart_key_from_cookie();
		if ($cookie_key !== '' && !empty($order['cart_key']) && $cookie_key === $order['cart_key']){
			$this->clear_cart_cookie();
		}

		if (!empty($_SESSION['order_id']) && (int)$_SESSION['order_id'] === (int)$order_id){
			unset($_SESSION['order_id']);
		}
		if (!empty($_SESSION['cart_key']) && !empty($order['cart_key']) && $_SESSION['cart_key'] === $order['cart_key']){
			unset($_SESSION['cart_key']);
		}

		// Invalidate shop settings cache not needed; clear order session shopify side keys
		if (!empty($_SESSION['shopify'])){
			unset($_SESSION['shopify']['shopify_cart_id'], $_SESSION['shopify']['checkout_url']);
		}

		return true;

	}

	/**
	 * Stable hash of lines for site → provider push comparison (not for remote pull).
	 */
	function calculate_order_lines_fingerprint($lines){

		$parts = [];
		foreach($lines as $line){
			$merchandise = $line['merchandise_id'] ?? '';
			$variant = $line['shopify_variant_id'] ?? '';
			if ($merchandise === '' && $variant === ''){
				// Include local-only lines so fingerprint changes if they appear/disappear
				$merchandise = 'local:'.($line['ref_id'] ?? $line['cms_page_panel_id'] ?? '');
			}
			$qty = (int)($line['qty'] ?? $line['quantity'] ?? 1);
			$attrs = $line['attributes'] ?? [];
			if (is_array($attrs)){
				ksort($attrs);
				$attr_s = json_encode($attrs);
			} else {
				$attr_s = (string)$attrs;
			}
			$parts[] = $merchandise.'|'.$variant.'|'.$qty.'|'.$attr_s;
		}
		sort($parts);
		return md5(implode("\n", $parts));

	}

	function ensure_order_cart_key($order){
		if (empty($order['cms_page_panel_id'])){
			return $order;
		}
		if (empty($order['cart_key']) || !preg_match('/^[a-f0-9]{32,64}$/', $order['cart_key'])){
			$cart_key = bin2hex(random_bytes(16));
			$this->cms_page_panel_model->update_cms_page_panel($order['cms_page_panel_id'], ['cart_key' => $cart_key]);
			$order['cart_key'] = $cart_key;
		}
		$this->set_cart_cookie($order['cart_key']);
		$_SESSION['order_id'] = $order['cms_page_panel_id'];
		$_SESSION['cart_key'] = $order['cart_key'];
		return $order;
	}

	function get_order_by_cart_key($cart_key){
		if ($cart_key === ''){
			return null;
		}
		$orders = $this->cms_page_panel_model->get_list('shop/order', [
				'cart_key' => $cart_key,
				'status' => '',
		]);
		$order = reset($orders);
		if (empty($order) || empty($order['cms_page_panel_id'])){
			return null;
		}
		return $order;
	}

	/**
	 * Resolve active draft order: user account, durable cookie cart_key, then session cache.
	 */
	function get_current_order($user){
		
		$this->load->model('cms/cms_page_panel_model');

		$order = null;
		$cookie_key = $this->get_cart_key_from_cookie();
		$cookie_order = $this->get_order_by_cart_key($cookie_key);
		
		if(!empty($user['user_id'])){
			
			$orders = $this->cms_page_panel_model->get_list('shop/order', [
					'user_id' => $user['user_id'], 
					'status' => '', 
			]);
			$order = reset($orders);
			if (empty($order)){
				$order = null;
			}

		}

		// Anonymous identity from long-lived cookie (preferred over session alone)
		$guest_order_id = 0;
		if (!empty($cookie_order['cms_page_panel_id'])){
			$guest_order_id = (int)$cookie_order['cms_page_panel_id'];
		} else if (!empty($_SESSION['order_id'])){
			$guest_order_id = (int)$_SESSION['order_id'];
		}
		
		// Merge guest cart into logged-in user order
		if (!empty($order) && $guest_order_id && (int)$order['cms_page_panel_id'] != $guest_order_id){
			
			$account_refs = [];
			
			$account_order_lines = $this->cms_page_panel_model->get_list('shop/order_line', ['order_id' => $order['cms_page_panel_id']]);
			
			foreach($account_order_lines as $line){
			
				if(!empty($line['ref_id'])){
					$account_refs[] = $line['ref_id'];
				}
			
			}

			$guest_order_lines = $this->cms_page_panel_model->get_list('shop/order_line', ['order_id' => $guest_order_id]);
				
			foreach($guest_order_lines as $line){
					
				// Move freeform / shopify lines always; product_item by ref uniqueness
				$is_ref_line = !empty($line['ref_id']);
				if (!$is_ref_line || !in_array($line['ref_id'], $account_refs)){
					$this->cms_page_panel_model->update_cms_page_panel($line['cms_page_panel_id'], ['order_id' => $order['cms_page_panel_id']]);
				}
					
			}
			
			$this->delete_order($guest_order_id);

		}

		// Assign full anonymous order to user
		if(empty($order) && $guest_order_id && !empty($user['user_id'])){
			
			$this->cms_page_panel_model->update_cms_page_panel($guest_order_id, ['user_id' => $user['user_id']]);
			
			$order = $this->cms_page_panel_model->get_cms_page_panel($guest_order_id);
			
			if (empty($order) || ($order['status'] ?? '') != ''){
				$order = null;
			}

		}
		
		// Guest: use cookie/session draft if not owned by another user
		if(empty($order) && $guest_order_id){
			$order = $this->cms_page_panel_model->get_cms_page_panel($guest_order_id);
			if (empty($order) || !empty($order['user_id']) || ($order['status'] ?? '') != ''){
				$order = null;
			}
		}
		
		if (empty($order) || ($order['status'] ?? '') != ''){

			$cart_key = $cookie_key !== '' ? $cookie_key : bin2hex(random_bytes(16));
		
			$order = [
					'panel_name' => 'shop/order',
					'show' => 1,
					'sort' => 'first',
					'number' => '',
					'status' => '',
					'paid_time' => '',
					'last_result' => '',
					'cart_key' => $cart_key,
					'user_id' => !empty($user['user_id']) ? $user['user_id'] : '',
			];
				
			$order_id = $this->cms_page_panel_model->create_cms_page_panel($order);
		
			$order_number = substr(md5($order_id), 0, 8);
		
			$this->cms_page_panel_model->update_cms_page_panel($order_id, ['number' => $order_number]);
		
			$order = $this->cms_page_panel_model->get_cms_page_panel($order_id);
		
		}

		return $this->ensure_order_cart_key($order);
		
	}

	/**
	 * Optional: do not create a draft order when cart is empty (badge shell).
	 */
	function get_current_order_if_any($user = []){

		$this->load->model('cms/cms_page_panel_model');

		if (!empty($user['user_id'])){
			$orders = $this->cms_page_panel_model->get_list('shop/order', [
					'user_id' => $user['user_id'],
					'status' => '',
			]);
			$order = reset($orders);
			if (!empty($order['cms_page_panel_id']) && ($order['status'] ?? '') === ''){
				return $this->ensure_order_cart_key($order);
			}
		}

		$cookie_key = $this->get_cart_key_from_cookie();
		$order = $this->get_order_by_cart_key($cookie_key);
		if (!empty($order)){
			if (!empty($order['user_id']) && empty($user['user_id'])){
				return null;
			}
			return $this->ensure_order_cart_key($order);
		}

		if (!empty($_SESSION['order_id'])){
			$order = $this->cms_page_panel_model->get_cms_page_panel($_SESSION['order_id']);
			if (!empty($order['cms_page_panel_id']) && ($order['status'] ?? '') === ''){
				if (!empty($order['user_id']) && empty($user['user_id'])){
					return null;
				}
				return $this->ensure_order_cart_key($order);
			}
		}

		return null;

	}

	function get_order_lines($order_id){

		$this->load->model('cms/cms_page_panel_model');
		if (empty($order_id)){
			return [];
		}
		return $this->cms_page_panel_model->get_list('shop/order_line', ['order_id' => $order_id]);

	}

	function get_order_quantity($order_id){

		$qty = 0;
		foreach($this->get_order_lines($order_id) as $line){
			$qty += (int)($line['qty'] ?? $line['quantity'] ?? 1);
		}
		return $qty;

	}

	/**
	 * All panels that provide a service (config provides registry).
	 * @return array panel_name => ['panel','module','service','label']
	 */
	function get_provides($service){

		$providers = $GLOBALS['config']['provides'][$service] ?? [];
		if (!is_array($providers) || empty($providers)){
			return [];
		}
		// Legacy single entry
		if (isset($providers['panel']) && is_string($providers['panel'])){
			$panel = $providers['panel'];
			return [
					$panel => [
							'panel' => $panel,
							'module' => $providers['module'] ?? '',
							'service' => $service,
							'label' => $providers['label'] ?? $panel,
					],
			];
		}
		return $providers;

	}

	function get_shop_settings(){

		if (!empty($GLOBALS['shop_settings_cache']) && is_array($GLOBALS['shop_settings_cache'])){
			return $GLOBALS['shop_settings_cache'];
		}

		$this->load->model('cms/cms_page_panel_model');
		$settings = $this->cms_page_panel_model->get_cms_page_panel_settings('shop/shop');
		if (!is_array($settings)){
			$settings = [];
		}

		$GLOBALS['shop_settings_cache'] = $settings;
		return $settings;

	}

	/**
	 * Selected checkout panel from shop settings (panel name, e.g. shopify/checkout).
	 * Empty string if not configured.
	 */
	function get_checkout_panel(){

		$settings = $this->get_shop_settings();
		$panel = $settings['shop_checkout'] ?? '';
		if (!is_string($panel)){
			return '';
		}
		return trim($panel);

	}
	
	function create_order_line($order_id, $params){
// _print_r($params);		
		$this->load->model('cms/cms_page_panel_model');
		
		$ref_id = 0;
		$description = '';
		$product = [];
		$product_item = [];
		
		if(!empty($params['product_item_id'])){
			
			$product_item = $this->cms_page_panel_model->get_cms_page_panel($params['product_item_id']);
			if (!empty($product_item['panel_name']) && $product_item['panel_name'] == 'shop/product_item'){
				$product = $this->cms_page_panel_model->get_cms_page_panel($product_item['product_id']);
			}
			
			$ref_id = $params['product_item_id'];
			
			if (!empty($product_item['dimensions'])){
				foreach($product_item['dimensions'] as $dimension){
					list($did, $dval) = explode('=', $dimension['value']);
					$description .= $this->get_dimension_label($did) .': '.$this->get_dimension_value_data($did, $dval)['label'].' ';
				}
			}
			
		}

		// Shopify / freeform catalogue line (variant on product, not product_item stock row)
		if (!empty($params['shopify_variant_id']) || !empty($params['merchandise_id'])){

			$product_id = (int)($params['product_id'] ?? 0);
			if ($product_id){
				$product = $this->cms_page_panel_model->get_cms_page_panel($product_id);
			}

			$variant_id = $params['shopify_variant_id'] ?? '';
			$merchandise_id = $params['merchandise_id'] ?? '';
			if ($merchandise_id === '' && $variant_id !== ''){
				if (strpos((string)$variant_id, 'gid://') === 0){
					$merchandise_id = $variant_id;
					if (preg_match('/ProductVariant\/(\d+)/', $variant_id, $m)){
						$variant_id = $m[1];
					}
				} else {
					$merchandise_id = 'gid://shopify/ProductVariant/'.$variant_id;
				}
			}

			$qty = max(1, (int)($params['qty'] ?? $params['quantity'] ?? 1));
			$price = $params['price'] ?? $params['expected_price'] ?? ($product['price'] ?? 0);
			$attributes = $params['attributes'] ?? [];
			if (is_string($attributes) && $attributes !== ''){
				$decoded = json_decode($attributes, true);
				if (is_array($decoded)){
					$attributes = $decoded;
				} else {
					$attributes = [];
				}
			}

			$attr_bits = [];
			if (is_array($attributes)){
				foreach($attributes as $k => $v){
					if (is_array($v) && isset($v['key'])){
						$attr_bits[] = ($v['key'] ?? '').': '.($v['value'] ?? '');
					} else if (!is_array($v)){
						$attr_bits[] = $k.': '.$v;
					}
				}
			}
			if ($attr_bits){
				$description = implode("\n", $attr_bits);
			}

			$order_line = [
					'panel_name' => 'shop/order_line',
					'show' => 1,
					'sort' => 'first',
					'ref_id' => $ref_id,
					'product_id' => $product_id,
					'shopify_variant_id' => (string)$variant_id,
					'merchandise_id' => $merchandise_id,
					'line_type' => 'shopify_variant',
					'qty' => $qty,
					'quantity' => $qty,
					'price' => $price,
					'expected_price' => $params['expected_price'] ?? $price,
					'description' => $description,
					'item' => $params['item'] ?? ($product['heading'] ?? ''),
					'image' => $params['image'] ?? ($product['image'] ?? ''),
					'attributes' => is_array($attributes) ? $attributes : [],
					'order_id' => $order_id,
			];

			return $this->cms_page_panel_model->create_cms_page_panel($order_line);

		}
		
		$order_line = [
				'panel_name' => 'shop/order_line',
				'show' => 1,
				'sort' => 'first',
				'ref_id' => $ref_id,
				'qty' => 1,
				'price' => !empty($product_item['price']) ? $product_item['price'] : (!empty($product['price']) ? $product['price'] : 0),
				'description' => $description,
				'item' => !empty($product['heading']) ? $product['heading'] : ($product_item['heading'] ?? ''),
				'order_id' => $order_id,
		];
		
		return $this->cms_page_panel_model->create_cms_page_panel($order_line);
		
	}
	
	function delete_order($order_id){
		
		$this->load->model('cms/cms_page_panel_model');
		
		$this->cms_page_panel_model->delete_cms_page_panel($order_id);
		
		$lines = $this->cms_page_panel_model->get_list('shop/order_line', ['order_id' => $order_id]);
		foreach($lines as $line){
			$this->cms_page_panel_model->delete_cms_page_panel($line['cms_page_panel_id']);
		}
		
	}
	
	function delete_order_line($order_id, $order_line_id){
		
		$this->load->model('cms/cms_page_panel_model');
		
		$line = $this->cms_page_panel_model->get_cms_page_panel($order_line_id);
		
		if ($line['order_id'] == $order_id){
			
			$this->cms_page_panel_model->delete_cms_page_panel($order_line_id);
			
			// check if only delivery remaining
			$lines = $this->cms_page_panel_model->get_list('shop/order_line', ['order_id' => $order_id]);
			if (count($lines) == 1){
				
				$line_to_check = reset($lines);
				
				if (!empty($line_to_check['ref_id'])){
					$ref_item = $this->cms_page_panel_model->get_cms_page_panel($line_to_check['ref_id']);
					if ($ref_item['panel_name'] == 'shop/delivery'){
						$this->cms_page_panel_model->delete_cms_page_panel($line_to_check['cms_page_panel_id']);
					}
				}

			}
			
		}

	}
	
	function calculate_product_item_hash($ref){
		
		$rvs = [];
		foreach($ref['dimensions'] as $rd){
			$rvs[] = $rd['value'];
		}
		sort($rvs);
		$ref_dimension = implode('|', $rvs);
		
		return md5($ref_dimension);
		
	}
	
	function set_order_paid($order_id, $wp_data = []){
		
		$this->load->model('cms/cms_page_panel_model');
		
		$this->cms_page_panel_model->update_cms_page_panel($order_id, [
				'status' => 'paid',
				'last_result' => 'authorized',
				'paid_time' => time(),
				'meta' => json_encode(['worldpay' => $wp_data], JSON_PRETTY_PRINT),
		]);
		
		$lines = $this->cms_page_panel_model->get_list('shop/order_line', ['order_id' => $order_id]);
		
		foreach($lines as $line){
			
			if (!empty($line['ref_id'])){
				
				$ref = $this->cms_page_panel_model->get_cms_page_panel($line['ref_id']);
				
				if ($ref['panel_name'] == 'shop/product_item'){
					
					$this->cms_page_panel_model->update_cms_page_panel($ref['cms_page_panel_id'], ['order_id' => $order_id]);
					
					// check if another ref with same properties is available?
					$ref_hash = $this->calculate_product_item_hash($ref);
					
					$replacement_id = 0;
					
					$refs = $this->cms_page_panel_model->get_list('shop/product_item', ['product_id' => $ref['product_id'], 'order_id' => 0]);
					foreach($refs as $ref_item){
						$ref_item_hash = $this->calculate_product_item_hash($ref_item);
						if ($ref_hash == $ref_item_hash){
							$replacement_id = $ref_item['cms_page_panel_id'];
						}
					}

					// replace or remove product item in all other baskets
					$order_lines = $this->cms_page_panel_model->get_list('shop/order_line', ['ref_id' => $ref['cms_page_panel_id']]);
					foreach($order_lines as $order_line){
						if ($order_line['order_id'] != $order_id){
							if (!empty($replacement_id)){
								$this->cms_page_panel_model->update_cms_page_panel($order_line['cms_page_panel_id'], ['ref_id' => $replacement_id]);
							} else {
								$this->cms_page_panel_model->update_cms_page_panel($order_line['cms_page_panel_id'], ['show' => 0]);
							}
						}
					}
					
				}
				
			}
			
		}
		
		// recipients
		$shop_config = $this->cms_page_panel_model->get_cms_page_panel_settings('shop/shop');
		
		$mailto = [];
		foreach($shop_config['emails'] as $email){
			$mailto[] = $email['email'];
		}
		
		$this->load->model('mail/mail_model');
		$this->mail_model->send_mail([
				'panel' => 'shop/mail_order',
				'order_id' => $order_id,
				'mail_to' => $mailto,
		]);

	}
	
	function is_product_in_basket($product_id, $order_id){
		
		$return = false;
	
		$this->load->model('cms/cms_page_panel_model');
	
		$items = [];
		$lines = $this->cms_page_panel_model->get_list('shop/order_line', ['order_id' => $order_id]);
		foreach($lines as $line){
			
			$product_item = $this->cms_page_panel_model->get_cms_page_panel($line['ref_id']);

			if(!empty($product_item['product_id']) && $product_item['product_id'] == $product_id){
				$return = true;
			}
		}
	
		return $return;
	
	}
	
}
