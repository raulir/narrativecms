<?php foreach($cms_images as $cms_image): ?>
	<div class="cms_images_image <?php print($cms_image['filename'] == $filename ? ' cms_images_selected ' : ''); 
			?>" data-filename="<?php print($cms_image['filename']); ?>">
		
		<div class="cms_images_image_cell" style="background-image: url('<?php print($GLOBALS['config']['base_url']); ?>modules/cms/img/opacity.png'); ">
			
			<div class="cms_images_image_img" <?php _ib($cms_image['filename'], ['width' => 600, 'maxwidth' => true, ]); ?>></div>
			
			<div class="cms_images_hover cms_images_image_delete">Delete</div>
			<div class="cms_images_hover cms_images_image_edit" style="background-image: url('<?php print($GLOBALS['config']['base_url']); ?>modules/cms/img/cms_edit.png'); "></div>
			<div class="cms_images_hover cms_images_image_replace" style="background-image: url('<?php print($GLOBALS['config']['base_url']); ?>modules/cms/img/cms_replace.png'); "></div>
			<div class="cms_images_hover cms_images_image_usage"><?php print($cms_image['number']); ?></div>
		
		</div>
	</div>
<?php endforeach ?>