<div class="cms_repeater_block" style="<?= (!empty($height) ? ' min-height: '.$height.'rem; ' : '') ?>">

	<div class="cms_repeater_block_content" <?php ($readonly ?? '0') ? false : _ib('cms/cms_drag.png', 12) ?>>
	
		<div class="cms_repeater_block_toolbar">
			<?php if(empty($readonly)): ?>
				<div class="cms_repeater_block_delete">Remove</div>
			<?php endif ?>
		</div>
		
		<?= print_fields($fields, $repeater_data, $name, $repeater_key) ?>

	</div>
	
</div>