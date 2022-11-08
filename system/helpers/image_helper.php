<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

require_once 'image_optimiser_helper.php';

if ( !function_exists('_i')) {

	/**
	 * prints image url and return image data
	 */
	function _i($image, $params = []){
		 
		if (is_numeric($params)){
			$params = ['width' => $params];
		}

		if (!empty($image)){

			$image_a = pathinfo($image);
			$image_data = array();

			if (!empty($params['width']) && (int)$params['width'] > 0){
				$image_data = _iw($image, $params);
			} else if (!empty($params['height']) && (int)$params['height'] > 0){
				$image_data = _iw($image, $params);
			} else if (!empty($params['output']) && $image_a['extension'] != $params['output']){
				$image_data = _iw($image, $params);
			} else if (!empty($params['data'])){
				list($image_data['width'], $image_data['height']) = getimagesize($GLOBALS['config']['upload_path'].$image);
				$image_data['size'] = filesize($GLOBALS['config']['upload_path'].$image);
				$image_data['image'] = $image;
			} else {
				$image_data['image'] = $image;
			}

			if (empty($params['silent'])){
				if (substr($image_data['image'], 0, 4) != 'http'){
					print($GLOBALS['config']['upload_url'].$image_data['image']);
				} else {
					print($image_data['image']);
				}
			}

			$return = $image_data;

		} else {
			$return = array('image' => '', 'height' => 0, 'width' => 0, );
		}
		 
		$return['alt'] = !empty($params['alt']) ? $params['alt'] : '';
		 
		// check for possible description in db
		$ci =& get_instance();
		$ci->load->model('cms/cms_image_model');
		$image_db_data = $ci->cms_image_model->get_cms_image_by_filename($image);
		$return['alt'] = empty($return['alt']) && !empty($image_db_data['description']) ? $image_db_data['description'] : $return['alt'];

		$return['description'] = !empty($image_db_data['description']) ? $image_db_data['description'] : '';
		$return['author'] = !empty($image_db_data['author']) ? $image_db_data['author'] : '';
		$return['copyright'] = !empty($image_db_data['copyright']) ? $image_db_data['copyright'] : '';

		return $return;

	}
	
	/**
	 * prints out bg image style parameter with image
	 */
	function _ib($image, $params = []){

		if (!is_array($params)){
			$params = ['width' => (int)$params];
		}
		
		if (empty($params['css'])){
			$params['css'] = '';
		}

		// if image is array
		if (is_array($image)) {
			$image = end($image);
		}

		// if no image, print only image when needed
		if (empty($image)){
			
			if (!empty($params['css'])){
				print('style="'.$params['css'].'"');
			}
			
			$return = array('image' => '', 'height' => 0, 'width' => 0, );
			$return['alt'] = !empty($params['alt']) ? $params['alt'] : '';
			
			return $return;
			
		}
		
		// if image from external source
		if (!(substr($image, 0, 4) != 'http' && substr($image, 0, 1) != '/')){
		
			if (substr($image, 0, 4) == 'http'){
			
				print('style="background-image:  url('.$image.'); '.$params['css'].'"');
			
			} else {
			
				print('style="background-image:  url('.$GLOBALS['config']['base_url'].trim($image, '/').'); '.$params['css'].'"');
			
			}
			
			return ['image' => $image, 'height' => 0, 'width' => 0, ];
			
		}
		
		$extension = pathinfo($image, PATHINFO_EXTENSION);
		
		// if gif or svg
		if (($extension == 'gif' || $extension == 'svg') && file_exists($GLOBALS['config']['upload_path'].$image) && 
				!is_dir($GLOBALS['config']['upload_path'].$image)){
			
			print('style="background-image:  url('.$GLOBALS['config']['upload_url'].trim($image, '/').'); '.$params['css'].'"');
			return ['image' => $image, 'height' => 0, 'width' => 0, ];
			
		}
		
		// if from module
		if (!empty($params['module']) || substr_count($image, '/') == 1){
			
			if (empty($params['module'])){
				list($params['module'], $image) = explode('/', $image);
			}
		
			$fileurl = $GLOBALS['config']['base_url'].'modules/'.$params['module'].'/img/'.$image;
			
			$filepath = $GLOBALS['config']['upload_path'].$params['module'].'/'.$image;
			
			// if not in uploads, copy over
			if (!file_exists($filepath)){
				
				$original_path = ($GLOBALS['config']['base_site']??'').$GLOBALS['config']['base_path'].'modules/'.$params['module'].'/img/'.$image;
				
				if (!file_exists($GLOBALS['config']['upload_path'].$params['module'].'/')){
					mkdir($GLOBALS['config']['upload_path'].$params['module'].'/');
				}
				
				if (file_exists($original_path)){
					copy($original_path, $filepath);
				}

				// add to db
				$ci =& get_instance();
				$ci->load->model('cms/cms_image_model');
				
				if (file_exists($original_path)){
					$ci->cms_image_model->create_cms_image($params['module'].'/', $image, $params['module']);
				}
				
			}
			
			$image = $params['module'].'/'.$image;

		} 
		
		// get data about image from db
		$ci =& get_instance();
		$ci->load->model('cms/cms_image_model');
		$image_db_data = $ci->cms_image_model->get_cms_image_by_filename($image);

		if (!(file_exists($GLOBALS['config']['upload_path'].$image) && !is_dir($GLOBALS['config']['upload_path'].$image)) 
				|| empty($image_db_data['original_width'])){
			
			$fileurl = $GLOBALS['config']['base_url'].'modules/cms/img/cms_no_image.png';
			$image_data['width'] = 2800;
			$image_data['height'] = 2800;
			
			$fileurl_hq = $fileurl;
			$fileurl_lq = $fileurl;
			$fileurl_mobile = $fileurl;
				
		} else {
		
			// if no resizing
			if((empty($params['width']) && empty($params['height'])) || empty($image_db_data['name'])){
				
				$fileurl = $GLOBALS['config']['upload_url'].$image;
				$image_data['width'] = $image_db_data['original_width'];
				$image_data['height'] = $image_db_data['original_height'];
				
				$fileurl_hq = $fileurl;
				$fileurl_lq = $fileurl;
				$fileurl_mobile = $fileurl;
					
			} else {
	 		
				// get image target width (mainly for filename)
				if (empty($params['width']) && !empty($params['height'])){
					$params['width'] = $image_db_data['original_width'] * $params['height'] / $image_db_data['original_height'];
				} 
				
				if ($params['width'] > $image_db_data['original_width']){
					$params['width'] = $image_db_data['original_width'];
				}
				
				if (empty($GLOBALS['config']['images_2x'])){
					$GLOBALS['config']['images_2x'] = 1;
				}
				
				$params['width_hq'] = round($GLOBALS['config']['images_2x'] * $params['width']);
				if ($params['width_hq'] > $image_db_data['original_width']){
					$params['width_hq'] = $image_db_data['original_width'];
				}
				
				if (empty($GLOBALS['config']['images_1x'])){
					$GLOBALS['config']['images_1x'] = 1;
				}
				
				$params['width_lq'] = round($GLOBALS['config']['images_1x'] * $params['width']);
				if ($params['width_lq'] > $image_db_data['original_width']){
					$params['width_lq'] = $image_db_data['original_width'];
				}

				$params['width_mobile'] = round((!empty($GLOBALS['config']['images_mobile']) ? $GLOBALS['config']['images_mobile'] : 1) * $params['width']);
				if ($params['width_mobile'] > $image_db_data['original_width']){
					$params['width_mobile'] = $image_db_data['original_width'];
				}
				
				// get image target output format
				if (empty($params['output'])){
					$params['output'] = $extension;
				}
				
				// webp
				if ($_SESSION['webp'] && !empty($GLOBALS['config']['images_webp']) && in_array($params['output'], ['png', 'jpg'])){
					$params['output'] = 'webp';
				}
				
				// if file exists
				$image_dir = pathinfo($image, PATHINFO_DIRNAME);
				$filename_hq = $GLOBALS['config']['upload_path'].$image_dir.'/_'.$image_db_data['name'].'.'.$params['width_hq'].'.'.$params['output'];
				$filename_lq = $GLOBALS['config']['upload_path'].$image_dir.'/_'.$image_db_data['name'].'.'.$params['width_lq'].'.'.$params['output'];
				$filename_mobile = $GLOBALS['config']['upload_path'].$image_dir.'/_'.$image_db_data['name'].'.'.$params['width_mobile'].'.'.$params['output'];
				$fileurl_hq = $GLOBALS['config']['upload_url'].$image_dir.'/_'.$image_db_data['name'].'.'.$params['width_hq'].'.'.$params['output'];
				$fileurl_lq = $GLOBALS['config']['upload_url'].$image_dir.'/_'.$image_db_data['name'].'.'.$params['width_lq'].'.'.$params['output'];
				$fileurl_mobile = $GLOBALS['config']['upload_url'].$image_dir.'/_'.$image_db_data['name'].'.'.$params['width_mobile'].'.'.$params['output'];
				
				// if mobile
				if ($_SESSION['mobile']){
					
					if (!file_exists($filename_mobile)){
						$needs_lazy_loading = true;
						$params['width_1'] = $params['width_mobile'];
					}
					
					$fileurl_a = $fileurl_mobile;
					$fileurl_b = $fileurl_mobile;
					
				} else {
					
					if (!file_exists($filename_hq) || !file_exists($filename_lq)){
						$needs_lazy_loading = true;
						$params['width_1'] = $params['width_hq'];
						$params['width_2'] = $params['width_lq'];
					}

					$fileurl_a = $fileurl_hq;
					$fileurl_b = $fileurl_lq;
					
				}
				
				if (empty($image_data)){
					$image_data['width'] = $params['width_hq'];
					$image_data['height'] = !empty($params['height'])
							? (round($GLOBALS['config']['images_2x'] * $params['height']))
							: round($image_db_data['original_height'] * $params['width_hq'] / $image_db_data['original_width']);
				}
				
			}
			
		}
		
		if (!empty($params['maxwidth']) && !empty($image_db_data['original_width'])){
			$params['css'] .= ' max-width: '.$image_db_data['original_width'].'px; ';
		}
		
		if (!empty($params['dataprops'])){
			$dataprops = ' data-width="'.$image_data['width'].'" data-height="'.$image_data['height'].'" ';
		} else {
			$dataprops = '';
		}
		
		if (!empty($params['pb'])){
			$params['css'] .= ' padding-bottom: '.round($image_data['height']/$image_data['width']*100, 2).'%; ';
		}
		
		if (!empty($GLOBALS['config']['base_site'])){
			$fileurl_lq = trim($GLOBALS['config']['base_site'],'/').$fileurl_lq;
			$fileurl_hq = trim($GLOBALS['config']['base_site'],'/').$fileurl_hq;
		}
		
		if (!empty($needs_lazy_loading)){
			
			$GLOBALS['_panel_js'][] = 'modules/cms/js/cms_images_lazy.js';
			
			print(' style="background-image: url('.trim(($GLOBALS['config']['base_site']??''),'/').$GLOBALS['config']['upload_url'].$image.'); '.
					$params['css'].'" data-cms_images_lazy="'.$image.'" data-output="'.$params['output'].'" '.
					' data-w1="'.$params['width_1'].'" '.(!empty($params['width_2']) ? ' data-w2="'.$params['width_2'].'" ' : '').$dataprops);
		
		} elseif ($_SESSION['mobile']){
			
			print(' style="background-image: url('.$fileurl_mobile.'); '.$params['css'].' " '.$dataprops);
			
		} elseif (!$_SESSION['imageset']){
			
			print(' style="background-image: url('.$fileurl_hq.'); '.$params['css'].' " '.$dataprops);
			
		} else {
			
			print(
					' style="background-image: url('.$fileurl_hq.'); '.
					( $fileurl_lq != $fileurl_hq ? 'background-image: -webkit-image-set( url('.$fileurl_lq.') 1x, url('.$fileurl_hq.') 2x ); '.
							'background-image: image-set(url('.$fileurl_lq.') 1x, url('.$fileurl_hq.') 2x ); '	: '').$params['css'].' " '.$dataprops);
			
		}

		return $image_data;

	}

	/**
	 * image inline - for svg
	 */
	function _ii($image){
		 
		if (!empty($image) && file_exists($GLOBALS['config']['upload_path'].$image)){
			$name_a = pathinfo($image);
			if ($name_a['extension'] == 'svg'){
				print(file_get_contents($GLOBALS['config']['upload_path'].$image));
			}
		}
		 
	}
	
	/**
	 * gif image loop fixer, prints image full url
	 */
	function _ig($image){
		
		$image_a = pathinfo($image);
		
		$new_filename = $GLOBALS['config']['upload_path'].$image_a['dirname'].'/_'.$image_a['filename'].'.'.$image_a['extension'];
		$new_url = $GLOBALS['config']['upload_url'].$image_a['dirname'].'/_'.$image_a['filename'].'.'.$image_a['extension'];
		
		if ($image_a['extension'] == 'gif'){
		
			if (!file_exists($new_filename)){
			
				// load file contents
				$data = file_get_contents($GLOBALS['config']['upload_path'].$image);
				
				if (!strstr($data, 'NETSCAPE2.0')){
					
					// gif colours byte
					$colours_byte = $data[10];
					
					// extract binary string
					$bin = decbin(ord($colours_byte));
					$bin = str_pad($bin, 8, 0, STR_PAD_LEFT);
					
					// calculate colour table length
					if ($bin[0] == 0){
						$colours_length = 0;
					} else {
						$colours_length = 3 * pow(2, (bindec(substr($bin, 1, 3)) + 1)); 
					}

					// put netscape string after 13 + colours table length
					$start = substr($data, 0, 13 + $colours_length);
					$end = substr($data, 13 + $colours_length);
					
					file_put_contents($new_filename, $start . chr(0x21) . chr(0xFF) . chr(0x0B) . 'NETSCAPE2.0' . chr(0x03) . chr(0x01) . chr(0x00) . chr(0x00) . chr(0x00) . $end);
				
				} else {
					
					file_put_contents($new_filename, $data);
				
				}
				
			}
		
			print($new_url);
				
		} else {
			
			print($GLOBALS['config']['upload_url'].$image);
			
		}

	}

}
