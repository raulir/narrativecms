<?php

namespace cms;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_image extends \Controller {

	function __construct(){

		parent::__construct();

		// check if user
		if(empty($_SESSION['cms_user']['cms_user_id'])){
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}

	}

	function panel_params($params){

		$this->load->model('cms/cms_image_model');

		if (empty($params['filename'])) {
			$params['filename'] = '';
		}

		// get possible categories
		$params['categories'] = $this->cms_image_model->get_cms_image_categories();

		$image = $this->cms_image_model->get_cms_image_by_filename($params['filename']);

		$meta = [];
		if (!empty($image['meta']) && is_string($image['meta'])){
			$meta = json_decode($image['meta'], true) ?: [];
		}

		$params['author'] = !empty($meta['author']) ? $meta['author'] : '';
		$params['copyright'] = !empty($meta['copyright']) ? $meta['copyright'] : '';
		$params['description'] = !empty($meta['description']) ? $meta['description'] : '';

		$params['category'] = !empty($image['category']) ? $image['category'] : '';

		$source_image = $image;
		if (!empty($meta['parent_cms_image_id'])){
			$parent = $this->cms_image_model->get_cms_image_by_id($meta['parent_cms_image_id']);
			if (!empty($parent['cms_image_id'])){
				$source_image = $parent;
			}
		}

		$params['source_filename'] = !empty($source_image['filename']) ? $source_image['filename'] : $params['filename'];
		$params['source_cms_image_id'] = !empty($source_image['cms_image_id']) ? $source_image['cms_image_id'] : 0;

		$default_crop = [
			'x1' => '0.0',
			'y1' => '0.0',
			'x2' => '100.0',
			'y2' => '100.0',
		];

		if (!empty($meta['parent_cms_image_id']) && !empty($meta['crop']) && is_array($meta['crop'])){
			$params['crop'] = [
				'x1' => isset($meta['crop']['x1']) ? $meta['crop']['x1'] : $default_crop['x1'],
				'y1' => isset($meta['crop']['y1']) ? $meta['crop']['y1'] : $default_crop['y1'],
				'x2' => isset($meta['crop']['x2']) ? $meta['crop']['x2'] : $default_crop['x2'],
				'y2' => isset($meta['crop']['y2']) ? $meta['crop']['y2'] : $default_crop['y2'],
			];
		} else {
			$params['crop'] = $default_crop;
		}

		$params['is_child'] = !empty($meta['parent_cms_image_id']);

		$params['zoom'] = '1.0';
		$params['pan_x'] = '0';
		$params['pan_y'] = '0';
		$params['brightness'] = '0.50';
		$params['contrast'] = '0.50';
		$params['overlay_colour'] = '#000000';
		$params['overlay_opacity'] = '0.00';
		$params['rotation'] = '0';
		$params['rotation_fixed'] = '1';

		if (!empty($meta['parent_cms_image_id'])){
			if (isset($meta['zoom'])){
				$params['zoom'] = number_format((float)$meta['zoom'], 1, '.', '');
			}
			if (isset($meta['pan_x'])){
				$params['pan_x'] = (string)(float)$meta['pan_x'];
			}
			if (isset($meta['pan_y'])){
				$params['pan_y'] = (string)(float)$meta['pan_y'];
			}
			if (isset($meta['brightness'])){
				$params['brightness'] = number_format((float)$meta['brightness'], 2, '.', '');
			}
			if (isset($meta['contrast'])){
				$params['contrast'] = number_format((float)$meta['contrast'], 2, '.', '');
			}
			if (!empty($meta['overlay_colour'])){
				$params['overlay_colour'] = $meta['overlay_colour'];
			}
			if (isset($meta['overlay_opacity'])){
				$params['overlay_opacity'] = number_format((float)$meta['overlay_opacity'], 2, '.', '');
			}
			if (isset($meta['rotation'])){
				$params['rotation'] = (string)(int)round(max(-180, min(180, (float)$meta['rotation'])));
			}
			if (isset($meta['rotation_fixed'])){
				$params['rotation_fixed'] = $meta['rotation_fixed'] === '0' ? '0' : '1';
			}
		}

		$params['image'] = $image;
		$params['source_image'] = $source_image;

		$GLOBALS['_panel_js'][] = 'modules/cms/js/cms_media_view.js';

		$params['is_source_video'] = $this->cms_image_model->is_source_video($source_image);
		$params['source_width'] = 0;
		$params['source_height'] = 0;

		if ($params['is_source_video']){
			$dims = $this->cms_image_model->get_video_source_dimensions($params['source_filename']);
			$params['source_width'] = $dims['width'];
			$params['source_height'] = $dims['height'];
		}

		return $params;

	}

}