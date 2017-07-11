<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

if ( !function_exists('_i')) {

	/**
	 * prints image url and return image data
	 */
	function _i($image, $params = array()){
		 
		if (is_numeric($params)){
			$params = array('width' => $params, );
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
		$ci->load->model('cms_image_model');
		$image_db_data = $ci->cms_image_model->get_cms_image_by_filename($image);
		$return['alt'] = empty($return['alt']) && !empty($image_db_data['description']) ? $image_db_data['description'] : $return['alt'];

		$return['description'] = !empty($image_db_data['description']) ? $image_db_data['description'] : '';
		$return['author'] = !empty($image_db_data['author']) ? $image_db_data['author'] : '';
		$return['copyright'] = !empty($image_db_data['copyright']) ? $image_db_data['copyright'] : '';

		return $return;

	}
	
	/**
	 * returns link to resized image
	 */
	function _iw($image, $params){

		$width = !empty($params['width']) ? $params['width'] : 0;
		$height = !empty($params['height']) ? $params['height'] : 0;

		if (file_exists($GLOBALS['config']['upload_path'].$image)){
				
			$name_a = pathinfo($image);
				
			// create resized image
			if ($name_a['extension'] == 'jpg' || $name_a['extension'] == 'png' || $name_a['extension'] == 'ico'){

				list($original_width, $original_height) = getimagesize($GLOBALS['config']['upload_path'].$image);

				if (!$width && $height){
					$width = round($original_width * $height / $original_height);
				} else if ($width && !$height){
					$height = round($original_height * $width / $original_width);
				} else {
					$width = $original_width;
					$height = $original_height;
				}

				// do not stretch image bigger
				if ($width > $original_width || $height > $original_height){
					$width = $original_width;
					$height = $original_height;
				}

				if (!empty($params['output']) && in_array($params['output'], array('jpg', 'png', 'ico', ))){
					$extension = $params['output'];
				} else {
					$extension = $name_a['extension'];
				}

				$new_image = $name_a['dirname'].'/_'.$name_a['filename'].'.'.$width.'.'.$extension;

				if (file_exists($GLOBALS['config']['upload_path'].$new_image)){
						
					$image = $new_image;

				} else {

					/* input */
					$needed = (4 * $original_width * $original_height + 4 * $width * $height) * 3.5 + 10000000;
						
					$limit = str_replace(array('G', 'M', 'K', ), array('000000000', '000000', '000', ), ini_get('memory_limit'));
					if($limit > 0 && $limit < $needed) ini_set('memory_limit', $needed);
						
					// check again for memory limit and give up if not enough
					$limit = str_replace(array('G', 'M', 'K', ), array('000000000', '000000', '000', ), ini_get('memory_limit'));
						
					if ($needed > $limit){

						trigger_error('Not enough memory to compress image: needed='.$needed.' memory_limit='.$limit, E_USER_NOTICE);

					} else {

						if ($name_a['extension'] == 'jpg'){
								
							$src = imagecreatefromjpeg($GLOBALS['config']['upload_path'].$image);
								
							$tmp = imagecreatetruecolor($width, $height);
							imagecopyresampled($tmp, $src, 0, 0, 0, 0, $width, $height, $original_width, $original_height);
								
						} else if ($name_a['extension'] == 'png'){

							$src = imagecreatefrompng($GLOBALS['config']['upload_path'].$image);
								
							$tmp = imagecreatetruecolor($width, $height);
							imagesavealpha($tmp, true);
							imagealphablending($tmp, false);
								
							$background = imagecolorallocatealpha($tmp , 0, 0, 0, 127);
							imagefill($tmp , 0, 0, $background);
								
							imagealphablending($tmp, false); // to preserve transparencies
							imagecopyresampled($tmp, $src, 0, 0, 0, 0, $width, $height, $original_width, $original_height);
								
						}

						/* output */

						imageinterlace($tmp, true);

						if ($extension == 'jpg'){

							imagejpeg($tmp, $GLOBALS['config']['upload_path'].$new_image, !empty($GLOBALS['config']['images_quality']) ? $GLOBALS['config']['images_quality'] : 85);

						} else if ($extension == 'png'){
								
							imagesavealpha($tmp, true);
							imagepng($tmp, $GLOBALS['config']['upload_path'].$new_image);
							
							// optimise on linux
							if(!empty($GLOBALS['config']['images_pngquant'])){
								
								$temp_name = $GLOBALS['config']['base_path'].'cache/'.md5($new_image).'.png';
								
								rename($GLOBALS['config']['upload_path'].$new_image, $temp_name);
								
								$cmd = (empty($GLOBALS['config']['images_pngquant_executable']) ? $GLOBALS['config']['base_path'].'application/libraries/pngquant/bin/pngquant.bin' : $GLOBALS['config']['images_pngquant_executable'])
										.' '.$temp_name.' --strip --speed 1 --quality=0-'.(!empty($GLOBALS['config']['images_quality']) ? $GLOBALS['config']['images_quality'] : 85).' -o '.$GLOBALS['config']['upload_path'].$new_image;

								shell_exec($cmd);
								
							}

							if(!empty($GLOBALS['config']['images_zopflipng'])){
								
								$temp_name = $GLOBALS['config']['base_path'].'cache/'.md5($new_image).'.png';
								
								rename($GLOBALS['config']['upload_path'].$new_image, $temp_name);
								
								$cmd = (empty($GLOBALS['config']['images_zopflipng_executable']) ? $GLOBALS['config']['base_path'].'application/libraries/zopflipng/bin/zopflipng.bin' : $GLOBALS['config']['images_zopflipng_executable'])
										.' -y '.$temp_name.' '.$GLOBALS['config']['upload_path'].$new_image;

								shell_exec($cmd);
								
							}
						
						} else if ($extension == 'ico'){
								
							// bmp data part
							$pixel_data = array();
								
							$opacity_data = array();
							$current_opacity_val = 0;
								
							for ( $y = $height - 1; $y >= 0; $y-- ) {
								for ( $x = 0; $x < $width; $x++ ) {
									$color = imagecolorat( $tmp, $x, $y );
										
									$alpha = ( $color & 0x7F000000 ) >> 24;
									$alpha = ( 1 - ( $alpha / 127 ) ) * 255;
										
									$color &= 0xFFFFFF;
									$color |= 0xFF000000 & ( $alpha << 24 );
										
									$pixel_data[] = $color;
										
										
									$opacity = ( $alpha <= 127 ) ? 1 : 0;
										
									$current_opacity_val = ( $current_opacity_val << 1 ) | $opacity;
										
									if ( ( ( $x + 1 ) % 32 ) == 0 ) {
										$opacity_data[] = $current_opacity_val;
										$current_opacity_val = 0;
									}
								}

								if ( ( $x % 32 ) > 0 ) {
									while ( ( $x++ % 32 ) > 0 )
										$current_opacity_val = $current_opacity_val << 1;
											
										$opacity_data[] = $current_opacity_val;
										$current_opacity_val = 0;
								}
							}
								
							$image_header_size = 40;
							$color_mask_size = $width * $height * 4;
							$opacity_mask_size = ( ceil( $width / 32 ) * 4 ) * $height;
								
							$data = pack( 'VVVvvVVVVVV', 40, $width, ( $height * 2 ), 1, 32, 0, 0, 0, 0, 0, 0 );
								
							foreach ( $pixel_data as $color )
								$data .= pack( 'V', $color );
									
								foreach ( $opacity_data as $opacity )
									$data .= pack( 'N', $opacity );
										
										
									$image = array(
											'width'                => $width,
											'height'               => $height,
											'color_palette_colors' => 0,
											'bits_per_pixel'       => 32,
											'size'                 => $image_header_size + $color_mask_size + $opacity_mask_size,
											'data'                 => $data,
									);
										
									// saving part
									$data = pack( 'vvv', 0, 1, 1 );
									$pixel_data = '';

									$data .= pack( 'CCCCvvVV', $width, $height, 0, 0, 1, 32, $image_header_size + $color_mask_size + $opacity_mask_size, 22 );
									$pixel_data .= $image['data'];

									$data .= $pixel_data;
									unset( $pixel_data );

									file_put_contents($GLOBALS['config']['upload_path'].$new_image, $data);

						}

						imagedestroy($tmp);
						imagedestroy($src);

						$image = $new_image;

					}

				}

			}
				
		}
			
		return array('image' => $image, 'width' => $width, 'height' => $height, );
		 
	}


	/**
	 * prints out bg image style parameter with image
	 */
	function _ib($image, $params = array()){
		 
		if (!is_array($params)){
			$params = array('width' => (int)$params);
		}
		
		if (empty($params['css'])){
			$params['css'] = '';
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
		
		// get data about image from db
		$ci =& get_instance();
		$ci->load->model('cms_image_model');
		$image_db_data = $ci->cms_image_model->get_cms_image_by_filename($image);
		
		// if no resizing
		if(empty($params['width']) && empty($params['height'])){
			
			$fileurl = $GLOBALS['config']['upload_url'].$image;
			$image_data['width'] = $image_db_data['original_width'];
			$image_data['height'] = $image_db_data['original_height'];
				
		} else {
 		
			// get image target width (mainly for filename)
			if (empty($params['width']) && !empty($params['height'])){
				$params['width'] = round($image_db_data['original_width'] * $params['height'] / $image_db_data['original_height']);
			} 
			
			if ($params['width'] > $image_db_data['original_width']){
				$params['width'] = $image_db_data['original_width'];
			}
		
			// get image target output format
			if (empty($params['output'])){
				$params['output'] = pathinfo($image, PATHINFO_EXTENSION);
			}
			
			// if file exists
			$image_dir = pathinfo($image, PATHINFO_DIRNAME);
			$filename = $GLOBALS['config']['upload_path'].$image_dir.'/_'.$image_db_data['name'].'.'.$params['width'].'.'.$params['output'];
			$filename_lq = $GLOBALS['config']['upload_path'].$image_dir.'/_'.$image_db_data['name'].'.'.round($params['width']/2).'.'.$params['output'];
			$fileurl = $GLOBALS['config']['upload_url'].$image_dir.'/_'.$image_db_data['name'].'.'.$params['width'].'.'.$params['output'];
			$fileurl_lq = $GLOBALS['config']['upload_url'].$image_dir.'/_'.$image_db_data['name'].'.'.round($params['width']/2).'.'.$params['output'];
				
			if (!file_exists($filename)){
				$needs_lazy_loading = true;
			}
			
			if (!file_exists($filename_lq) && empty($GLOBALS['config']['images_no_imageset'])){
				$needs_lazy_loading = true;
			}
		
			if (empty($image_data)){
				$image_data['width'] = $params['width'];
				$image_data['height'] = !empty($params['height']) ? $params['height'] : round($image_db_data['original_height'] * $params['width'] / $image_db_data['original_width']);
			}
			
		}
		
		if (!empty($needs_lazy_loading)){

			$GLOBALS['_panel_js'][] = 'modules/cms/js/cms_images_lazy.js';
			
			print(' style="background-image: url('.$GLOBALS['config']['base_url'].'modules/cms/img/cms_image_loading.png); '.$params['css'].
					(!empty($params['maxwidth']) && !empty($image_db_data['original_width']) ? ' max-width: '.$image_db_data['original_width'].'px; ' : '').
					'" data-cms_images_lazy="'.$image.'" data-width="'.$image_data['width'].'" data-width_lq="'.round($image_data['width']/2).'"
					data-output="'.$params['output'].'" data-height="'.$image_data['height'].'" ');
		
		} else if (!empty($GLOBALS['config']['images_no_imageset']) || empty($filename_lq)){
			
			print(' style="background-image: url('.$fileurl.'); '.$params['css'].
					(!empty($params['maxwidth']) && !empty($image_db_data['original_width']) ? ' max-width: '.$image_db_data['original_width'].'px; ' : '').' " '.
					(!empty($params['dataprops']) ? ' data-width="'.$image_data['width'].'" data-height="'.$image_data['height'].'" ' : ''));
			
		} else {
			
			print(
					' style="background-image: url('.$fileurl.'); '.
					'background-image: -webkit-image-set( url('.$fileurl_lq.') 1x, url('.$fileurl.') 2x ); '.
					'background-image: image-set( url('.$fileurl_lq.') 1x, url('.$fileurl.') 2x ); '.
					$params['css'].
					(!empty($params['maxwidth']) && !empty($image_db_data['original_width']) ? ' max-width: '.$image_db_data['original_width'].'px; ' : '').' " '.
					(!empty($params['dataprops']) ? 'data-width="'.$image_data['width'].'" data-height="'.$image_data['height'].'" ' : ''));
			
		}

		return $image_data;

	}

	/**
	 * image inline - for svg
	 */
	function _ii($image){
		 
		if (file_exists($GLOBALS['config']['upload_path'].$image)){
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
