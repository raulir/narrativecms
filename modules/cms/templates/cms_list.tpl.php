<div class="cms_toolbar">
	
	<div class="cms_tool_text"><?php print($title); ?></div>
	
	<?php if(empty($hide_new)): ?>

		<a class="cms_tool_button admin_right" href="<?php print($GLOBALS['config']['base_url'].$edit_base.str_replace('/', '__', $new_panel_name).'/'); ?>">New</a>
		
		<?php if($GLOBALS['config']['environment'] == 'NOP'): ?>
		
			<div class="admin_tool_button admin_right cms_list_import">Import</div>
			<?php _panel('cms_popup', ['heading' => 'Import', 'name' => 'import', ]) ?>
			 
		<?php endif ?>
		
	<?php endif ?>

	<?php if(!empty($extra_buttons)): ?>
		<?php foreach($extra_buttons as $extra_button): ?>
			<?php _panel($extra_button); ?>
		<?php endforeach ?>
	<?php endif ?>
	
	<div class="cms_tool_button admin_right cms_paging_last" <?php _ib('cms/cms_paging_last.png', ['height' => 12]) ?>></div>
	<div class="cms_tool_button admin_right cms_paging_next" <?php _ib('cms/cms_paging_next.png', ['height' => 12]) ?>></div>

	<div class="cms_tool_text admin_right">
		<span class="admin_paging_current">&nbsp;&nbsp;</span>/<span class="admin_paging_total">&nbsp;&nbsp;</span>
	</div>

	<div class="cms_tool_button admin_right cms_paging_previous" <?php _ib('cms/cms_paging_previous.png', ['height' => 12]) ?>></div>
	<div class="cms_tool_button admin_right cms_paging_first" <?php _ib('cms/cms_paging_first.png', ['height' => 12]) ?>></div>
	
	<?php if(!empty($filter_fields)) foreach($filter_fields as $filter_field => $filter_field_label): ?>
		<select class="admin_tool_select admin_right admin_tool_filter" data-field="<?php print($filter_field); ?>">
			<option value="_empty_"><?php print($filter_field_label); ?></option>
			<?php foreach($filter_fields_values[$filter_field] as $key => $val): ?>
				<option value="<?php print($key); ?>"><?php print($val); ?></option>
			<?php endforeach ?>
		</select>
	<?php endforeach ?>
	
</div>

<div class="admin_list_container <?php print(!empty($extra_class) ? $extra_class : ''); ?> cms_list_container" 
		data-edit_base="<?php print($GLOBALS['config']['base_url'].$edit_base); ?>"
		<?php if(!empty($filter['panel_name'])): ?>
			data-panel_name="<?php print($filter['panel_name']); ?>" 
		<?php else: ?>
			data-source="<?php print($source['model'].'|'.$source['method']); ?>" 
		<?php endif ?>
		
		<?php if(!empty($title_field)): ?>
			data-title_field="<?php print($title_field); ?>"
		<?php elseif (!empty($title_panel)): ?>
			data-title_panel="<?php print($title_panel); ?>" 
		<?php endif ?>
		
		<?php if(!empty($id_field)): ?>
			data-id_field="<?php print($id_field); ?>"	
		<?php else: ?>
			data-id_field="block_id" 
		<?php endif ?>
		<?php if(!empty($no_sort)): ?>
			data-no_sort="no_sort"	
		<?php endif ?>
		data-limit="<?php print(!empty($limit) ? $limit : 20); ?>"	
		>

</div>
