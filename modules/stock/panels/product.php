<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class product extends CI_Controller{
	
	function panel_heading($params){
		
		if (empty($params['heading'])){
			return '';
		}
		
		$this->load->model('cms/cms_page_panel_model');
		
		$return = $params['heading'];

		if (!empty($params['category_id'])){

			$category = $this->cms_page_panel_model->get_cms_page_panel($params['category_id']);
			if (!empty($category)){
				$return .= ' ('.$category['heading'].')';
			}
			
			if (!empty($params['subcategory_id'])){
				$subcategory = $this->cms_page_panel_model->get_cms_page_panel($params['subcategory_id']);
				$return .= ' - ('.$subcategory['heading'].')';
			}

		}
		
		return $return;
	
	}
	
	function panel_slug($params){
		
		$this->load->model('cms/cms_page_panel_model');
		$this->load->model('cms/cms_slug_model');
		
		$product = $this->cms_page_panel_model->get_cms_page_panel($params['cms_page_panel_id']);

		$return = $this->cms_slug_model->get_cms_slug_by_target($product['panel_name'].'='.$params['cms_page_panel_id']);
		
		return $return;
		
	}
	
	function panel_params($params){
		
		$this->load->model('cms/cms_page_panel_model');

		$params['image'] = '';
		if (!empty($params['images'][0]['image'])){
			$params['image'] = $params['images'][0]['image'];
		}
		
		// add dimension images
		$dimensions = $this->cms_page_panel_model->get_list('stock/product_dimension');
		
		$params['dimension_labels'] = [];
		foreach($dimensions as $dimension){
			foreach($dimension['values'] as $value){
				$params['dimension_labels'][$dimension['id'].'='.$value['id']] = strtolower($dimension['heading'].' - '.$value['label']);
			}
		}
		
		$product_items = $this->cms_page_panel_model->get_list('stock/product_item', ['product_id' => $params['cms_page_panel_id']]);
		
		$params['image_buttons'] = [];
		foreach($product_items as $product_item){
			
			$ids = [];
			$image_texts = [];
			foreach($product_item['dimensions'] as $dimension){
				$ids[] = $dimension['value'];
				list($did, $dval) = explode(' - ', $params['dimension_labels'][$dimension['value']]);
				$image_texts[] = trim($dval);
			}
			sort($ids);
			$id = md5(implode('', $ids));
			
			foreach($product_item['images'] as $image){
				$params['images'][] = [
					'image' => $image['image'],
					'id' => $id,
					'text' => implode(', ', $image_texts),
				];
				$params['image_buttons'][] = [
					'id' => $id,
					'text' => implode("\n", $image_texts),
				];
			}
			
			$image_ids[$id] = $ids;
			
		}		
		
		$params['params'] = $params;
		
		return $params;
		
	}
	
	function ds_product_items($params){
	
		$this->load->model('cms/cms_page_panel_model');
		$this->load->model('shop/shop_model');
		
		// schema
		if ($params['do'] == 'S'){
				
			$product = $this->cms_page_panel_model->get_cms_page_panel($params['id']);
	
			if (empty($return)){
				$return = [];
			}
				
			$return = $params['fields'];
				
			// get dimensions
			$product_stock = $this->cms_page_panel_model->get_cms_page_panel($product['product_stock_id']);
			if (!empty($product_stock['dimensions'])){
				foreach($product_stock['dimensions'] as $key =>	$dim){
		
					$dim_data = $this->cms_page_panel_model->get_cms_page_panel($dim['dimension']);
		
					$return[] = [
							'type' => 'stock/dim_value_select',
							'name' => 'dimension_' . $dim_data['id'],
							'label' => $dim_data['heading'],
							'width' => '10',
							'dimension' => $dim_data['id'],
							'order' => 30 + $key,
					];
		
				}
			}

			if (!empty($product_stock['stock_control']) && $product_stock['stock_control'] == 'count'){
				
				$return[] = [
					'type' => 'cms/cms_grid_editable',
					'name' => 'number',
					'label' => 'Number',
					'width' => '10',
					'align' => 'right',
					'order' => '70',
				];
			
			} else if (!empty($product_stock['stock_control']) && $product_stock['stock_control'] == 'request') {
				
				$return = [];
				
			} else {
				
				$return[] = [
					'type' => 'id',
					'name' => 'order_id',
					'label' => 'Order',
					'width' => '5',
					'align' => 'center',
					'order' => '80',
				];

				$return[] = [
					'type' => 'ids',
					'name' => 'baskets',
					'label' => 'Baskets',
					'width' => '25',
					'align' => 'left',
					'order' => '90',
				];

			}

		// list
		} elseif ($params['do'] == 'L'){
				
			$return = [];
	
			$data = $this->cms_page_panel_model->get_list('stock/product_item', ['product_id' => $params['id']]);
				
			$product = $this->cms_page_panel_model->get_cms_page_panel($params['id']);
	
			foreach($data as $key => $line){
					
				$dimensions = [];
				foreach($line['dimensions'] as $dimension){
					$dimensions[] = $dimension['value'];
				}
	
				$orders = [];
				$order_lines = $this->cms_page_panel_model->get_list('cg/order_line', ['ref_id' => $key]);
				foreach($order_lines as $order_line){
					if (!empty($order_line['order_id']) && $order_line['order_id'] != $line['order_id'] && !in_array($order_line['order_id'], $orders)){
						$orders[] = $order_line['order_id'];
					}
				}
				
				// TODO: status
				
				$return[$key] = [
						'id' => $key,
						'order_id' => !empty($line['order_id']) ? $line['order_id'] : '',
						'price' => !empty($line['price']) ? $line['price'] : (!empty($product['price']) ? ('('.$product['price'].')') : ''),
						'sku' => $line['sku'],
						'dimensions' => implode(',', $dimensions),
						'status' => !empty($line['order_id']) ? 'sold' : 'available',
						'baskets' => $orders,
				];
	
				ksort($return);
					
			}
				
			// create new
		} elseif ($params['do'] == 'C'){
	
			$return = [];
	
			$return['product_item_id'] = $this->create_product_item($params['id']);
				
		}
	
		return $return;
	
	}
	
	function create_product_item($product_id){
	
		$product_item = [
				'panel_name' => 'stock/product_item',
				'show' => 1,
				'sort' => 'first',
				'product_id' => $product_id,
				'order_id' => '',
				'price' => '',
				'sku' => '',
				'dimensions' => [],
				'number' => 1,
		];
			
		$product_item_id = $this->cms_page_panel_model->create_cms_page_panel($product_item);
	
		return $product_item_id;
	
	}

}
