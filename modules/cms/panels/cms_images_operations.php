<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_images_operations extends CI_Controller {

	function __construct(){

		parent::__construct();

		// check if user
		if(empty($_SESSION['cms_user']['cms_user_id'])){
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}

	}

	function panel_params($params){

		add_css('modules/cms/css/cms_video_view.scss');
		$GLOBALS['_panel_js'][] = 'modules/cms/js/dash/dash.min.js';
		$GLOBALS['_panel_js'][] = 'modules/cms/js/cms_media_view.js';
		$GLOBALS['_panel_js'][] = 'modules/cms/js/cms_video.js';

		return $params;

	}

	function panel_action($params){

		$this->load->model('cms/cms_image_model');

		$do = $this->input->post('do');
		if ($do == 'cms_images_delete_by_filename'){
			 
			// collect data
			$filename = $this->input->post('filename');
			 
			$this->cms_image_model->delete_cms_image_by_filename($filename);
			 
			$return = array();
			 
		}
		if ($do == 'cms_images_check_by_filename'){
			 
			$filename = $this->input->post('filename');
			 
			$image = $this->cms_image_model->get_cms_image_by_filename($filename);
			 
			$return['filename'] = !empty($image['filename']) ? $image['filename'] : '';
			 
			if (!empty($image['meta'])){
				$return = array_merge($return, json_decode($image['meta'], true));
			}

		}
		if ($do == 'cms_images_save'){
			 
			$filename = $this->input->post('filename');
			$category = $this->input->post('category');
			$return = array();

			$existing = $this->cms_image_model->get_cms_image_by_filename($filename);
			$existing_meta = [];
			if (!empty($existing['meta']) && is_string($existing['meta'])){
				$existing_meta = json_decode($existing['meta'], true) ?: [];
			}

			$meta = array_merge($existing_meta, array(
					'author' => $this->input->post('author'),
					'copyright' => $this->input->post('copyright'),
					'description' => $this->input->post('description'),
			));

			if (empty($existing_meta['parent_cms_image_id'])){
				unset($meta['crop']);
			}
			 
			$this->cms_image_model->update_cms_image($filename, array(
					'category' => empty($category) ? '' : $category,
					'meta' => json_encode($meta),
			));

			$source_cms_image_id = (int)$this->input->post('source_cms_image_id');
			if ($source_cms_image_id){
				$child_filename = $this->cms_image_model->save_cms_image_child($source_cms_image_id, array(
					'x1' => $this->input->post('crop_x1'),
					'y1' => $this->input->post('crop_y1'),
					'x2' => $this->input->post('crop_x2'),
					'y2' => $this->input->post('crop_y2'),
				), $filename, array(
					'author' => $this->input->post('author'),
					'copyright' => $this->input->post('copyright'),
					'description' => $this->input->post('description'),
				), array(
					'zoom' => $this->input->post('zoom'),
					'pan_x' => $this->input->post('pan_x'),
					'pan_y' => $this->input->post('pan_y'),
					'brightness' => $this->input->post('brightness'),
					'contrast' => $this->input->post('contrast'),
					'overlay_colour' => $this->input->post('overlay_colour'),
					'overlay_opacity' => $this->input->post('overlay_opacity'),
					'rotation' => $this->input->post('rotation'),
					'rotation_fixed' => $this->input->post('rotation_fixed'),
				));
				if (!empty($child_filename)){
					$return['child_filename'] = $child_filename;
				}
			}
			 
		}

		return $return;

	}

}
 