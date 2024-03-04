<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class emailer extends CI_Controller{
	
	function panel_params($params){
		
		$this->load->model('cms/cms_image_model');

		if (!empty($params['strapline'])){
			
			$font_hartree = $GLOBALS['config']['base_path'].'modules/hartree/css/hartree/hartree.ttf';
			
			$result = imagecreatetruecolor(1280, 76);
			
			imagesavealpha($result, true);
			imagealphablending($result, false);
			
			$background = imagecolorallocatealpha($result, 0, 0, 0, 127);
			$black = imagecolorallocate($result, 0, 0, 0);
				
			imagefill($result, 0, 0, $background);
			
			imagealphablending($result, false); // to preserve transparencies
			
			$strapline = trim($params['strapline']);
				
			$strapline_lh = 76;
			$strapline_fz = 60;
			
			$strapline_x = 640;
			$strapline_y = 0;

			$textbox_size = imagettfbbox( $strapline_fz, 0, $font_hartree, $strapline);
			$textbox_width = (int)($textbox_size[4] - $textbox_size[6]);

			imagettftext($result, $strapline_fz, 0, $strapline_x - (int)($textbox_width/2), $strapline_y + $strapline_lh, $black, $font_hartree, $strapline);
			
			imagesavealpha($result, true);
			
			$filename = $GLOBALS['config']['base_path'].'cache/emailer_'.md5($strapline).'.png';
			imagepng($result, $filename, 9);
			
			$params['strapline_image'] = $this->cms_image_model->scrape_image($filename, 'emailer', 'emailer', 'png');
				
		}
		
		$params['lead'] = str_replace("\n", '<br>', trim($params['lead']));
		
		// hartree lead image
		if (!empty($params['lead_extra'])){
			$result = imagecreatetruecolor(1600, 194);
				
			imagesavealpha($result, true);
			imagealphablending($result, false);
				
			$background = imagecolorallocatealpha($result, 0, 0, 0, 127);
			$black = imagecolorallocate($result, 0, 0, 0);
			
			imagefill($result, 0, 0, $background);
				
			imagealphablending($result, false); // to preserve transparencies
				
			$image_text = trim($params['lead_extra']);
			
			$strapline_lh = 100;
			$strapline_fz = 85;
				
			$strapline_x = 800;
			$strapline_y = 0;
			
			$textbox_size = imagettfbbox( $strapline_fz, 0, $font_hartree, $image_text);
			$textbox_width = (int)($textbox_size[4] - $textbox_size[6]);
			
			imagettftext($result, $strapline_fz, 0, $strapline_x - (int)($textbox_width/2), $strapline_y + $strapline_lh, $black, $font_hartree, $image_text);
				
			imagesavealpha($result, true);
				
			$filename = $GLOBALS['config']['base_path'].'cache/emailer_'.md5($image_text).'.png';
			imagepng($result, $filename, 9);
				
			$params['lead_extra_image'] = $this->cms_image_model->scrape_image($filename, 'emailer', 'emailer', 'png');
		}
		
		// events heading image
		$image_text = trim($params['events_heading']);
		$linebreaks = substr_count($image_text, "\n" );
			
		$strapline_lh = 85;
		$strapline_fz = 85;
			
		$strapline_x = 800;
		$strapline_y = 65;
			
		$result = imagecreatetruecolor(1600, ($linebreaks + 2)*$strapline_lh - 18);
			
		imagesavealpha($result, true);
		imagealphablending($result, false);
			
		$background = imagecolorallocatealpha($result, 0, 0, 0, 127);
		$black = imagecolorallocate($result, 0, 0, 0);
			
		imagefill($result, 0, 0, $background);
			
		imagealphablending($result, false); // to preserve transparencies
		
		$texts = explode("\n", $image_text);
		
		foreach($texts as $tkey => $ttext){
		
			$textbox_size = imagettfbbox( $strapline_fz, 0, $font_hartree, trim($ttext));
			$textbox_width = (int)($textbox_size[4] - $textbox_size[6]);
			
			imagettftext($result, $strapline_fz, 0, $strapline_x - (int)($textbox_width/2),
					(int)($strapline_y + ((1 + $tkey) * $strapline_lh)), $black, $font_hartree, trim($ttext));
		
		}
			
		imagesavealpha($result, true);
			
		$filename = $GLOBALS['config']['base_path'].'cache/emailer_'.md5($image_text).'.png';
		imagepng($result, $filename, 9);
			
		$params['events_heading_image'] = $this->cms_image_model->scrape_image($filename, 'emailer', 'emailer', 'png');
		
		// upcoming heading image
		$image_text = trim($params['upcoming_heading']);
		$linebreaks = substr_count($image_text, "\n" );
			
		$strapline_lh = 85;
		$strapline_fz = 85;
			
		$strapline_x = 800;
		$strapline_y = 0;
			
		$result = imagecreatetruecolor(1600, ($linebreaks + 2)*$strapline_lh - 18);
			
		imagesavealpha($result, true);
		imagealphablending($result, false);
			
		$background = imagecolorallocatealpha($result, 0, 0, 0, 127);
		$black = imagecolorallocate($result, 0, 0, 0);
			
		imagefill($result, 0, 0, $background);
			
		imagealphablending($result, false); // to preserve transparencies
		
		$texts = explode("\n", $image_text);
		
		foreach($texts as $tkey => $ttext){
		
			$textbox_size = imagettfbbox( $strapline_fz, 0, $font_hartree, trim($ttext));
			$textbox_width = (int)($textbox_size[4] - $textbox_size[6]);
				
			imagettftext($result, $strapline_fz, 0, $strapline_x - (int)($textbox_width/2),
					(int)($strapline_y + ((1 + $tkey) * $strapline_lh)), $black, $font_hartree, trim($ttext));
		
		}
			
		imagesavealpha($result, true);
			
		$filename = $GLOBALS['config']['base_path'].'cache/emailer_'.md5($image_text).'.png';
		imagepng($result, $filename, 9);
			
		$params['upcoming_heading_image'] = $this->cms_image_model->scrape_image($filename, 'emailer', 'emailer', 'png');
		
		foreach($params['items'] as $ikey => $item){
			
			$params['items'][$ikey]['text'] = str_replace("\n", '<br>', trim($item['text']));
			
			// block title
			if (!empty($item['heading'])){
			
				$image_text = trim($item['heading']);
				$linebreaks = substr_count($image_text, "\n" );
					
				$strapline_lh = 38;
				$strapline_fz = 38;
			
				$strapline_x = 50;
				$strapline_y = -5;
					
				$result = imagecreatetruecolor(400, ($linebreaks + 2)*$strapline_lh - 18);
					
				imagesavealpha($result, true);
				imagealphablending($result, false);
					
				$background = imagecolorallocatealpha($result, 0, 0, 0, 127);
				$black = imagecolorallocate($result, 0, 0, 0);
					
				imagefill($result, 0, 0, $background);
					
				imagealphablending($result, false); // to preserve transparencies
				
				$texts = explode("\n", $image_text);
				
				foreach($texts as $tkey => $ttext){
				
					imagettftext($result, $strapline_fz, 0, $strapline_x,
							(int)($strapline_y + ((1 + $tkey) * $strapline_lh)), $black, $font_hartree, trim($ttext));
						
				}
			
				imagesavealpha($result, true);
			
				$filename = $GLOBALS['config']['base_path'].'cache/emailer_'.md5($image_text).'.png';
				imagepng($result, $filename, 9);
			
				$params['items'][$ikey]['heading_image'] = $this->cms_image_model->scrape_image($filename, 'emailer', 'emailer', 'png');
			}

		}
		
		$params['link'] = _l('hartree/emailer='.$params['cms_page_panel_id'], false);
		

		return $params;
		
	}

}
