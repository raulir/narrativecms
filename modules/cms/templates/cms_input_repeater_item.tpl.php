<div class="cms_repeater_block" style="<?= (!empty($height) ? ' min-height: '.$height.'rem; ' : '') ?>">

	<div class="cms_repeater_block_content">
	
		<?php // Toolbar is the sortable drag handle (four-way arrow cursor on hover) ?>
		<div class="cms_repeater_block_toolbar">
			<?php if(empty($readonly)): ?>
				<div class="cms_repeater_block_delete">Remove</div>
			<?php endif ?>
		</div>
		
		<?= print_fields($fields, $repeater_data, $name, $repeater_key) ?>

	</div>
	
</div>