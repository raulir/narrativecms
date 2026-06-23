<div class="cms_images_page_container" style="min-height: calc(<?= $GLOBALS['config']['images_rows'] ?> * 15.8rem); ">
	<?php foreach($cms_images as $cms_image): ?>
		<div class="cms_images_image <?= $cms_image['filename'] == $filename ? ' cms_images_selected ' : '' ?>" 
				data-filename="<?= $cms_image['filename'] ?>" data-category="<?= $cms_image['category'] ?>">
			
			<div class="cms_images_image_cell" <?php _ib('cms/cms_opacity.png', 40) ?>>
				
				<div class="cms_images_image_img" <?php _ib($cms_image['filename'], 150); ?>></div>
				
				<div class="cms_images_hover cms_images_image_delete">Delete</div>
				<div class="cms_images_hover cms_images_image_edit" <?php _ib('cms/cms_edit.png', 16) ?>></div>
				<?php
					$usage_self = (int)$cms_image['number'];
					$usage_children = (int)($cms_image['children_number'] ?? 0);
					$usage_total = $usage_self + $usage_children;
				?>
				<div class="cms_images_hover cms_images_image_usage"
						data-usage_self="<?= $usage_self ?>"
						data-usage_children="<?= $usage_children ?>"><?= $cms_image['usage_label'] ?? $usage_self ?></div>
			
			</div>
		</div>
	<?php endforeach ?>
</div>