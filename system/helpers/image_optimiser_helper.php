<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

if ( !function_exists('_iw')) {
	
	// if used from api, ci object may not exist
	if ( !function_exists('get_instance')){
		
		include_once($GLOBALS['config']['base_path'] . 'system/core/Common.php');
		include($GLOBALS['config']['base_path'] . 'system/core/controller.php');
		
		function &get_instance(){
			return CI_Controller::get_instance();
		}

	}
	
	function _get_iw_size($image, $params){
		
		$ci =& get_instance();
		if ($ci === null) {
			new CI_Controller();
			$ci =& get_instance();
		}
		
		$ci->load->model('cms/cms_image_model');

		$width = !empty($params['width']) ? $params['width'] : 0;
		$height = !empty($params['height']) ? $params['height'] : 0;
		
		$image_data = $ci->cms_image_model->get_cms_image_by_filename($image);
		
		if (!$width && $height){
			$width = round($image_data['original_width'] * $height / $image_data['original_height']);
		} else if ($width && !$height){
			$height = round($image_data['original_height'] * $width / $image_data['original_width']);
		} else {
			$width = $image_data['original_width'];
			$height = $image_data['original_height'];
		}
		
		// do not stretch image bigger
		if ($width > $image_data['original_width'] || $height > $image_data['original_height']){
			$width = $image_data['original_width'];
			$height = $image_data['original_height'];
		}
		
		return [
				$width,
				$height,
				$image_data['original_width'],
				$image_data['original_height'],
		];
		
	}
	
	function _get_iw_new($image, $width, $output){
		
		if ($output == 'jpeg'){
			$output = 'jpg';
		}
		
		$name_a = pathinfo($image);
		
		$new_image = $name_a['dirname'].'/_'.$name_a['filename'].'.'.$width.'.'.$output;
		
		return $new_image;
	
	}

	/**
	 * returns link to resized image
	 */
	function _iw($image, $params = []){
		
		if (empty($image)){
			return ['image' => '', 'width' => '', 'height' => '', ];
		}
		
		if (is_numeric($params)){
			$params = ['width' => $params]; 
		}

		$name_a = pathinfo($image);
		
		// if image missing or can't resize, return image
		if (!file_exists($GLOBALS['config']['upload_path'].$image) 
				|| (empty($params['width']) && empty($params['height']))
				|| !($name_a['extension'] == 'jpg' || $name_a['extension'] == 'jpeg' || $name_a['extension'] == 'png' || $name_a['extension'] == 'ico')) {
			
			return [
					'image' => $image, 
					'width' => !empty($params['width']) ? $params['width'] : 0, 
					'height' => !empty($params['height']) ? $params['height'] : 0, 
			];
		
		}

		// get needed size
		list($width, $height, $original_width, $original_height) = _get_iw_size($image, $params);
		
		$extension = !empty($params['output']) ? $params['output'] : $name_a['extension'];
		$new_image = _get_iw_new($image, $width, $extension);
		
		// if file aready exists, return
		if (file_exists($GLOBALS['config']['upload_path'].$new_image)) {
			return ['image' => $new_image, 'width' => $width, 'height' => $height, ];
		}
		
		// really needs resizing:
		
		// check memory availability
		$needed = (4 * $original_width * $original_height + 4 * $width * $height) * 3.5 + 10000000;
			
		$limit = str_replace(array('G', 'M', 'K', ), array('000000000', '000000', '000', ), ini_get('memory_limit'));
		if ($limit > 0 && $limit < $needed) ini_set('memory_limit', $needed);
			
		// check again for memory limit and give up if not enough
		$limit = str_replace(array('G', 'M', 'K', ), array('000000000', '000000', '000', ), ini_get('memory_limit'));

		if ($needed > $limit) {

			trigger_error('Not enough memory to compress image: needed='.$needed.' memory_limit='.$limit, E_USER_NOTICE);

		} else {
			
			if (!function_exists('imagecreatetruecolor')){
				print('PHP module gd not present, please enable!');
				die();
			}

			$tmp = imagecreatetruecolor($width, $height);
			
			// detect file format
			$imagetype = exif_imagetype($GLOBALS['config']['upload_path'].$image);
			
			if ($imagetype == IMAGETYPE_JPEG){
				
				$name_a['extension'] = 'jpg';
				
				$src = imagecreatefromjpeg($GLOBALS['config']['upload_path'].$image);

			} else if ($imagetype == IMAGETYPE_PNG){
				
				$name_a['extension'] = 'png';

				@$src = imagecreatefrompng($GLOBALS['config']['upload_path'].$image);
					
				imagesavealpha($tmp, true);
				imagealphablending($tmp, false);
					
				$background = imagecolorallocatealpha($tmp , 0, 0, 0, 127);
				imagefill($tmp , 0, 0, $background);
					
				imagealphablending($tmp, false); // to preserve transparencies
					
			} else {
				
				$src = imagecreatefrompng($GLOBALS['config']['base_path'].'modules/cms/img/cms_no_image.png');
				list($original_width, $original_height) = getimagesize($GLOBALS['config']['base_path'].'modules/cms/img/cms_no_image.png');
				
			}

			imagecopyresampled($tmp, $src, 0, 0, 0, 0, $width, $height, $original_width, $original_height);
			
			/* output */

			imageinterlace($tmp, true);

			if ($extension == 'jpg' || $extension == 'jpeg'){

				imagejpeg($tmp, $GLOBALS['config']['upload_path'].$new_image, !empty($GLOBALS['config']['images_quality']) ? $GLOBALS['config']['images_quality'] : 85);

			} else if ($extension == 'png'){
					
				imagesavealpha($tmp, true);
				imagepng($tmp, $GLOBALS['config']['upload_path'].$new_image);
				
				/*
				// optimise on linux
				if(!empty($GLOBALS['config']['images_pngquant'])){
					
					$temp_name = $GLOBALS['config']['base_path'].'cache/'.md5($new_image).'.png';
					
					rename($GLOBALS['config']['upload_path'].$new_image, $temp_name);
					
					$cmd = (empty($GLOBALS['config']['images_pngquant_executable']) ? $GLOBALS['config']['base_path'].'system/vendor/pngquant/bin/pngquant.bin' : $GLOBALS['config']['images_pngquant_executable'])
							.' '.$temp_name.' --strip --speed 1 --quality=0-'.(!empty($GLOBALS['config']['images_quality']) ? $GLOBALS['config']['images_quality'] : 85).' -o '.$GLOBALS['config']['upload_path'].$new_image;

					shell_exec($cmd);
					
					unlink($temp_name);
					
				}

				if(!empty($GLOBALS['config']['images_zopflipng'])){
					
					$temp_name = $GLOBALS['config']['base_path'].'cache/'.md5($new_image).'.png';
					
					rename($GLOBALS['config']['upload_path'].$new_image, $temp_name);
					
					$cmd = (empty($GLOBALS['config']['images_zopflipng_executable']) ? $GLOBALS['config']['base_path'].'system/vendor/zopflipng/bin/zopflipng.bin' : $GLOBALS['config']['images_zopflipng_executable'])
							.' -y '.$temp_name.' '.$GLOBALS['config']['upload_path'].$new_image;

					shell_exec($cmd);
					
					unlink($temp_name);
				
				}
				*/
				
				// if size is same or bigger, but new filesize is bigger, then use original image instead
				if ($width >= $original_width){
					
					$new_filesize = filesize($GLOBALS['config']['upload_path'].$new_image);
					$old_filesize = filesize($GLOBALS['config']['upload_path'].$image);
					
					if ($new_filesize > $old_filesize){
						
						unlink($GLOBALS['config']['upload_path'].$new_image);
						copy($GLOBALS['config']['upload_path'].$image, $GLOBALS['config']['upload_path'].$new_image);
						
					}
					
				}
			
			} else if ($extension == 'webp'){
				
				// detect if source is png or jpg
				if ($name_a['extension'] == 'png'){
				
					$png_image = str_replace('.png', '.tmp.png', _get_iw_new($image, $width, 'png'));
					
					if (!file_exists($GLOBALS['config']['upload_path'].$png_image)){
						
						imagesavealpha($tmp, true);
						imagepng($tmp, $GLOBALS['config']['upload_path'].$png_image);
						/*
						// optimise on linux
						if(!empty($GLOBALS['config']['images_pngquant'])){
							
							$temp_name = $GLOBALS['config']['base_path'].'cache/'.md5($png_image).'.png';
							
							rename($GLOBALS['config']['upload_path'].$png_image, $temp_name);
							
							$cmd = (empty($GLOBALS['config']['images_pngquant_executable']) ? $GLOBALS['config']['base_path'].'system/vendor/pngquant/bin/pngquant.bin' : $GLOBALS['config']['images_pngquant_executable'])
									.' '.$temp_name.' --strip --speed 1 --quality=0-'.(!empty($GLOBALS['config']['images_quality']) ? $GLOBALS['config']['images_quality'] : 90).' -o '.$GLOBALS['config']['upload_path'].$new_image;
		
							shell_exec($cmd);
							
//							unlink($temp_name);
							
						}
						*/
					}
					
					// convert png to webp
					if ($GLOBALS['config']['images_webp'] == 'gd' || $GLOBALS['config']['images_webp'] == 'cwebp'){
						
						$src = imagecreatefrompng($GLOBALS['config']['upload_path'].$png_image);
						
						imagewebp($tmp, $GLOBALS['config']['upload_path'].$new_image);
						
					} /* else if ($GLOBALS['config']['images_webp'] == 'cwebp'){
						
						$temp_name = $GLOBALS['config']['base_path'].'cache/'.md5($png_image).'.png';
						rename($GLOBALS['config']['upload_path'].$png_image, $temp_name);
						
						$cmd = 'cwebp -z 9 '.$temp_name.' -o '.$GLOBALS['config']['upload_path'].$new_image;

						shell_exec($cmd);
						
						unlink($temp_name);
						
					} */
					
					if (file_exists($GLOBALS['config']['upload_path'].$png_image)){
						unlink($GLOBALS['config']['upload_path'].$png_image);
					}
				
				} else { // if jpg
					
					// convert jpg to webp
					if ($GLOBALS['config']['images_webp'] == 'gd' || $GLOBALS['config']['images_webp'] == 'cwebp'){
					
						imagewebp($tmp, $GLOBALS['config']['upload_path'].$new_image);
					
					} /* else if ($GLOBALS['config']['images_webp'] == 'cwebp'){
					
						$jpg_image = _get_iw_new($image, $width, 'jpg');
						$temp_name = $GLOBALS['config']['base_path'].'cache/'.md5($jpg_image).'.jpg';
						
						imagesavealpha($tmp, false);
						imagejpeg($tmp, $temp_name, 100);

						$cmd = 'cwebp -m 6 -q '.(!empty($GLOBALS['config']['images_quality']) ? ($GLOBALS['config']['images_quality']) : 85).
								' '.$temp_name.' -o '.$GLOBALS['config']['upload_path'].$new_image;
					
						shell_exec($cmd);
						
						unlink($temp_name);

					} */

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

		return ['image' => $image, 'width' => $width, 'height' => $height, 'original_width' => $original_width, 'original_height' => $original_height, ];
		 
	}

}
