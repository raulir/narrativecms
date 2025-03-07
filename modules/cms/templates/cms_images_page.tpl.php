<div class="cms_images_page_container" style="min-height: calc(<?= $GLOBALS['config']['images_rows'] ?> * 15.8rem); ">
	<?php foreach($cms_images as $cms_image): ?>
		<div class="cms_images_image <?= $cms_image['filename'] == $filename ? ' cms_images_selected ' : '' ?>" 
				data-filename="<?= $cms_image['filename'] ?>" data-category="<?= $cms_image['category'] ?>">
			
			<div class="cms_images_image_cell" <?php _ib('cms/cms_opacity.png', 40) ?>>
				
				<div class="cms_images_image_img" <?php _ib($cms_image['filename'], 145); ?>></div>
				
				<div class="cms_images_hover cms_images_image_delete">Delete</div>
				<div class="cms_images_hover cms_images_image_edit" <?php _ib('cms/cms_edit.png', 16) ?>></div>
				<div class="cms_images_hover cms_images_image_usage"><?= $cms_image['number'] ?></div>
			
			</div>
		</div>
	<?php endforeach ?>
</div>