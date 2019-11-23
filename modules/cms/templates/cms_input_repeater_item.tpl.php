<div class="cms_repeater_block ui-sortable-handle"
		<?php _ib('cms/cms_drag.png', ['height' => 12, 'css' => (!empty($height) ? ' min-height: '.$height.'rem; ' : ''),]) ?>>
		
	<div class="cms_repeater_block_content" <?= !empty($height) ? ' style="min-height: '.($height - 2).'rem; "' : '' ?>>
	
		<div class="cms_repeater_block_toolbar">
			<div class="cms_repeater_block_delete">Remove</div>
		</div>
		
		<?= print_fields($fields, $repeater_data, $name, $repeater_key) ?>

	</div>
	
</div>