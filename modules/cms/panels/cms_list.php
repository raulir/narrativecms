<?php

namespace cms;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_list extends \Controller {

	function __construct(){

		parent::__construct();

		// check if user
		if(empty($_SESSION['cms_user']['cms_user_id'])){
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}

		$GLOBALS['_panel_js'][] = array('script' => 'modules/cms/js/jquery/jquery-ui.min.js', 'no_pack' => 1, );
		$GLOBALS['_panel_js'][] = array('script' => 'modules/cms/js/cms_cookie.js', );
		$GLOBALS['_panel_js'][] = array('script' => 'modules/cms/js/cms_page_panel_button_show.js', );
		
	}

	function panel_action($params){

		$do = $this->input->post('do');

		if ($do === 'cms_list_set'){

			$cms_page_panel_id = $this->input->post('id');
			$field = $this->input->post('field');
			$value = $this->input->post('value');

			$this->load->model('cms/cms_page_panel_model');
			$this->cms_page_panel_model->update_cms_page_panel($cms_page_panel_id, [
					$field => $value,
			]);

		} else if ($do === 'cms_list_save_order'){

			$list_order = $this->input->post('list_order');

			$this->load->model('cms/cms_page_panel_model');

			$previous_sort = [];
			foreach ($list_order as $list_sort => $cms_page_panel_id){
				$panel = $this->cms_page_panel_model->get_cms_page_panel($cms_page_panel_id);
				$previous_sort[] = $panel['sort'];
			}

			sort($previous_sort);

			foreach ($list_order as $list_sort => $cms_page_panel_id){
				$this->cms_page_panel_model->update_cms_page_panel($cms_page_panel_id, [
						'sort' => $previous_sort[$list_sort],
				]);
			}

		} else if ($do === 'cms_list_move'){

			$block_id = $this->input->post('block_id');
			$target = $this->input->post('target');
			$start = $this->input->post('start');
			$limit = $this->input->post('limit');

			$this->load->model('cms/cms_page_panel_model');

			$block = $this->cms_page_panel_model->get_cms_page_panel($block_id);

			if ($target == 'first'){

				$this->cms_page_panel_model->move_first($block_id);

			} else if ($target == 'previous'){

				if ($start >= $limit){

					$filters = $this->input->post('filters');
					$list_order = $this->input->post('list_order');

					$filter = [
							'panel_name' => $block['panel_name'],
							'cms_page_id' => 0,
							'sort!' => '0',
					];
					if (is_array($filters)){
						$filter = array_merge($filter, $filters);
					}

					$old_block_a = $this->cms_page_panel_model->get_cms_page_panels_list_by(
							array_merge($filter, ['_start' => $start - 1, '_limit' => 1, ])
					);

					$previous_sort = [];
					$new_list_sort = [$old_block_a[0]['cms_page_panel_id']];
					foreach ($list_order as $list_sort => $cms_page_panel_id){
						$panel = $this->cms_page_panel_model->get_cms_page_panel($cms_page_panel_id);
						$previous_sort[] = $panel['sort'];
						if ($cms_page_panel_id != $block_id){
							$new_list_sort[] = $cms_page_panel_id;
						}
					}

					$this->cms_page_panel_model->update_cms_page_panel($block_id, [
							'sort' => $old_block_a[0]['sort'],
					]);

					sort($previous_sort);

					foreach ($new_list_sort as $index => $block_id){
						$this->cms_page_panel_model->update_cms_page_panel($block_id, [
								'sort' => $previous_sort[$index],
						]);
					}

				}

			} else if ($target == 'next'){

				$filters = $this->input->post('filters');
				$list_order = $this->input->post('list_order');

				$filter = [
						'panel_name' => $block['panel_name'],
						'cms_page_id' => 0,
						'sort!' => '0',
				];
				if (is_array($filters)){
					$filter = array_merge($filter, $filters);
				}

				$old_block_a = $this->cms_page_panel_model->get_cms_page_panels_list_by(
						array_merge($filter, ['_start' => $start + $limit, '_limit' => 1, ])
				);

				if (!empty($old_block_a[0])){

					$previous_sort = [];
					$new_list_sort = [];
					foreach ($list_order as $list_sort => $cms_page_panel_id){
						$panel = $this->cms_page_panel_model->get_cms_page_panel($cms_page_panel_id);
						$previous_sort[] = $panel['sort'];
						if ($cms_page_panel_id != $block_id){
							$new_list_sort[] = $cms_page_panel_id;
						}
					}
					$new_list_sort[] = $old_block_a[0]['cms_page_panel_id'];

					$this->cms_page_panel_model->update_cms_page_panel($block_id, [
							'sort' => $old_block_a[0]['sort'],
					]);

					sort($previous_sort);

					foreach ($new_list_sort as $index => $block_id){
						$this->cms_page_panel_model->update_cms_page_panel($block_id, [
								'sort' => $previous_sort[$index],
						]);
					}

				}

			} else if ($target == 'last'){

				$sort_stats = $this->cms_page_panel_model->get_sort_stats($block['panel_name']);
				$this->cms_page_panel_model->update_cms_page_panel($block_id, [
						'sort' => $sort_stats['max_sort'] + 1,
				]);

			}

		}

		return $params;

	}

	function panel_params($params){

		$params['filter_fields_values'] = array();

		if (!empty($params['filter_fields'])){

			$this->load->model('cms/cms_panel_model');
			$this->load->model('cms/cms_page_panel_model');
				
			// load definition
			$panel_definition = $this->cms_panel_model->get_cms_panel_definition($params['filter']['panel_name']);
			
			// for show
			$panel_definition[] = array(
					'type' => 'select',
					'name' => 'show',
					'label' => 'Show',
					'values' => array('No', 'Yes', ),
			);

			foreach($params['filter_fields'] as $filter_field => $filter_field_label){
				// get values
				foreach($panel_definition as $panel_field){
						
					// if select
					if (!empty($panel_field['name']) && $panel_field['type'] == 'select' && $panel_field['name'] == $filter_field){
						$params['filter_fields_values'][$filter_field] = $panel_field['values'];
					}
						
					// if fk
					if (!empty($panel_field['name']) && $panel_field['type'] == 'fk' && $panel_field['name'] == $filter_field && 
							!empty($panel_field['target']) && $panel_field['target'] == 'block'){

						$panel_name = str_replace('_id', '', $panel_field['name']);

						$target_a = $this->cms_page_panel_model->get_list($panel_name, ['show' => [0,1]]);

						if (count($target_a)){
								
							$params['filter_fields_values'][$filter_field] = array();
							foreach($target_a as $row){

								$params['filter_fields_values'][$filter_field][$row['cms_page_panel_id']] = !empty($row['heading']) ? $row['heading'] : $row['cms_page_panel_id'];
									
							}
								
						}

					} else if (!empty($panel_field['name']) && $panel_field['type'] == 'fk' && $panel_field['name'] == $filter_field){
						
						$target_a = $this->cms_page_panel_model->get_list($panel_field['list'], ['show' => [0,1]]);
						
						if (count($target_a)){
						
							$params['filter_fields_values'][$filter_field] = [];
							foreach($target_a as $key => $row){
								
								$params['filter_fields_values'][$filter_field][$key] = $row['title'];
									
							}
						
						}
						
					} else if (!empty($panel_field['name']) && !empty($panel_field['table']) && $panel_field['table'] == '1' && $panel_field['name'] == $filter_field) {

						$panel_name = $params['filter']['panel_name'];
						$table = $this->cms_page_panel_model->get_panel_table_name($panel_name);
						$params['filter_fields_values'][$filter_field] = [];

						if ($table && $this->cms_page_panel_model->panel_table_exists($panel_name)) {
							$sql = "select distinct `".$panel_field['name']."` as val from `{$table}` where `".$panel_field['name']."` != '' order by `".$panel_field['name']."` ";
							$query = $this->db->query($sql);
							if ($query->num_rows()) {
								foreach ($query->result_array() as $row) {
									$params['filter_fields_values'][$filter_field][$row['val']] = $row['val'];
								}
							}
						}

					}
						
				}
			}

		}
		
		if (is_array($params['filter']['panel_name'])){
			$params['filter']['panel_name'] = $params['filter']['panel_name'][0];
		}
		
		$params['new_panel_name'] = $params['filter']['panel_name'];
		if (stristr($params['filter']['panel_name'], '|')){
			
			$panel_names = explode('|', $params['filter']['panel_name']);
			
			$params['new_panel_name'] = $panel_names[0];
			
			// if array, get first with /
			foreach($panel_names as $_cms_panel){
				if (stristr($_cms_panel, '/')){
					$params['new_panel_name'] = $_cms_panel;
					break;
				}
			}
				
		}

		return $params;

	}

}
