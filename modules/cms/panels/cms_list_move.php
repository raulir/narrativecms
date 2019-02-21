<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_list_move extends CI_Controller {

	function __construct(){

		parent::__construct();

		// check if user
		if(empty($_SESSION['cms_user']['cms_user_id'])){
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}

	}

	function panel_action(){

		$do = $this->input->post('do');
		if ($do == 'cms_list_move'){
			 
			$block_id = $this->input->post('block_id');
			$target = $this->input->post('target');
			$start = $this->input->post('start');
			$limit = $this->input->post('limit');
			 
			$this->load->model('cms_page_panel_model');
			 
			// get block panel name
			$block = $this->cms_page_panel_model->get_cms_page_panel($block_id);

			if ($target == 'first'){

				$this->cms_page_panel_model->move_first($block_id);
					
			} elseif ($target == 'previous'){

				// add to the end of previous page
				if ($start >= $limit){

					$filters = $this->input->post('filters');
					$list_order = $this->input->post('list_order');

					$filter = array('panel_name' => $block['panel_name'], 'cms_page_id' => [999999,0], );
					if (is_array($filters)){
						$filter = array_merge($filter, $filters);
					}
						
					// get previous on this position
					$old_block_a = $this->cms_page_panel_model->get_cms_page_panels_by(array_merge($filter, array('_start' => $start - 1, '_limit' => 1, )));

					// get reusable sorts and updated list sort
					$previous_sort = array(); // free sorts
					$new_list_sort = array($old_block_a[0]['cms_page_panel_id']); // what has to be placed there
					foreach($list_order as $list_sort => $cms_page_panel_id){
						$panel = $this->cms_page_panel_model->get_cms_page_panel($cms_page_panel_id);
						$previous_sort[] = $panel['sort'];
						if($cms_page_panel_id != $block_id){
							$new_list_sort[] = $cms_page_panel_id;
						}
					}
					 
					// update moving block to prev page last sort
					$this->cms_page_panel_model->update_cms_page_panel($block_id, array('sort' => $old_block_a[0]['sort'], ), true);

					// resort current page
					sort($previous_sort);
					 
					// update panels referencing sorted previous sorts
					foreach($new_list_sort as $index => $block_id){
						$this->cms_page_panel_model->update_cms_page_panel($block_id, array('sort' => $previous_sort[$index], ), true);
					}

				}

			} elseif ($target == 'next'){

				// check if next page exists
				$filters = $this->input->post('filters');
				$list_order = $this->input->post('list_order');

				$filter = array('panel_name' => $block['panel_name'], 'cms_page_id' => [999999,0], );
				if (is_array($filters)){
					$filter = array_merge($filter, $filters);
				}

				// get previous on this position
				$old_block_a = $this->cms_page_panel_model->get_cms_page_panels_by(
						array_merge($filter, array('_start' => $start + $limit, '_limit' => 1, )));

				if (!empty($old_block_a[0])){
						
					// get reusable sorts and updated list sort
					$previous_sort = array(); // free sorts
					$new_list_sort = array(); // what has to be placed there
					foreach($list_order as $list_sort => $cms_page_panel_id){
						$panel = $this->cms_page_panel_model->get_cms_page_panel($cms_page_panel_id);
						$previous_sort[] = $panel['sort'];
						if($cms_page_panel_id != $block_id){
							$new_list_sort[] = $cms_page_panel_id;
						}
					}
					$new_list_sort[] = $old_block_a[0]['cms_page_panel_id']; // old block goes to the end of page
					 
					// update moving block to next page first sort found before
					$this->cms_page_panel_model->update_cms_page_panel($block_id, array('sort' => $old_block_a[0]['sort'], ), true);

					// resort current page
					sort($previous_sort);
					 
					// update panels referencing sorted previous sorts
					foreach($new_list_sort as $index => $block_id){
						$this->cms_page_panel_model->update_cms_page_panel($block_id, array('sort' => $previous_sort[$index], ), true);
					}

				}

			} elseif ($target == 'last'){

				// get max sort and count
				$sort_stats = $this->cms_page_panel_model->get_sort_stats($block['panel_name']);
				$this->cms_page_panel_model->update_cms_page_panel($block_id, array('sort' => $sort_stats['max_sort'] + 1, ), true);

			}

		}

	}

}
