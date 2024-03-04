<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class button extends Controller{
	
	function panel_params($params){
		
		$font_asap = $GLOBALS['config']['base_path'].'modules/cms/css/asap/asap.ttf';
		
		$this->load->model('cms/cms_image_model');
		
		$image_text = trim($params['heading']);
		$linebreaks = substr_count($image_text, "\n" );
		
		$strapline_lh = 50;
		$strapline_fz = 30;
		
		$strapline_x = 240;
		$strapline_y = 11;
			
		$image_height = ($linebreaks + 2)*$strapline_lh - 8;
			
		$result = imagecreatetruecolor(480, $image_height);
		
		imagesavealpha($result, true);
		imagealphablending($result, false);
		
		$background = imagecolorallocatealpha($result, 0, 0, 0, 127);
		$black = imagecolorallocate($result, 0, 0, 0);
		
		imagefill($result, 0, 0, $background);
		
		imagealphablending($result, false); // to preserve transparencies
			
		imagerectangle($result, 0, 0, 479, $image_height - 1, $black);
		imagerectangle($result, 1, 1, 478, $image_height - 2, $black);
		imagerectangle($result, 2, 2, 477, $image_height - 3, $black);
		imagerectangle($result, 3, 3, 476, $image_height - 4, $black);
		
		$texts = explode("\n", $image_text);
			
		foreach($texts as $tkey => $ttext){
				
			$textbox_size = imagettfbbox( $strapline_fz, 0, $font_asap, trim($ttext));
			$textbox_width = (int)($textbox_size[4] - $textbox_size[6]);
				
			imagettftext($result, $strapline_fz, 0, (int)($strapline_x - ($textbox_width/2)),
					(int)($strapline_y + ((1 + $tkey) * $strapline_lh)), $black, $font_asap, trim($ttext));
				
		}
			
		imagesavealpha($result, true);
		
		$filename = $GLOBALS['config']['base_path'].'cache/emailer_'.md5('event_'.$strapline_fz.$strapline_lh.$image_text).'.png';
		imagepng($result, $filename, 9);
		
		$params['button_label_image'] = $this->cms_image_model->scrape_image($filename, 'emailer', 'emailer', 'png');

		return $params;
		
	}

}
