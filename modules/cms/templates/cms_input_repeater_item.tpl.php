<div class="cms_repeater_block ui-sortable-handle" style="<?= (!empty($height) ? ' min-height: '.$height.'rem; ' : '') ?>">
		
	<div class="cms_repeater_block_content" <?php _ib('cms/cms_drag.png', 12) ?>>
	
		<div class="cms_repeater_block_toolbar">
			<div class="cms_repeater_block_delete">Remove</div>
		</div>
		
		<?= print_fields($fields, $repeater_data, $name, $repeater_key) ?>

	</div>
	
</div>