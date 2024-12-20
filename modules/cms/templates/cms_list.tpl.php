<div class="cms_toolbar">
	
	<div class="cms_tool_text"><?php print($title); ?></div>
	
	<?php if(empty($hide_new)): ?>
	
		<div class="cms_list_infinity cms_tool_button cms_right">
			<div class="cms_tool_button_inner" <?php _ib('cms/cms_infinity.png', 34) ?>></div>
		</div>

		<a class="cms_tool_button cms_right" href="<?php print($GLOBALS['config']['base_url'].$edit_base.str_replace('/', '__', $new_panel_name).'/'); ?>">New</a>
		
		<?php if($GLOBALS['config']['environment'] == 'NOP'): ?>
		
			<div class="admin_tool_button cms_right cms_list_import">Import</div>
			<?php _panel('cms/cms_popup', ['heading' => 'Import', 'name' => 'import', ]) ?>
			 
		<?php endif ?>
		
	<?php endif ?>

	<?php if(!empty($extra_buttons)): ?>
		<?php foreach($extra_buttons as $extra_button): ?>
			<?php _panel($extra_button['name'], $extra_button) ?>
		<?php endforeach ?>
	<?php endif ?>
	
	<div class="cms_tool_button cms_right cms_paging_button cms_paging_last" <?php _ib('cms/cms_paging_last.png', ['height' => 12]) ?>></div>
	<div class="cms_tool_button cms_right cms_paging_button cms_paging_next" <?php _ib('cms/cms_paging_next.png', ['height' => 12]) ?>></div>

	<div class="cms_tool_text cms_right">
		<span class="admin_paging_current">&nbsp;&nbsp;</span>/<span class="admin_paging_total">&nbsp;&nbsp;</span>
	</div>

	<div class="cms_tool_button cms_right cms_paging_button cms_paging_previous" <?php _ib('cms/cms_paging_previous.png', ['height' => 12]) ?>></div>
	<div class="cms_tool_button cms_right cms_paging_button cms_paging_first" <?php _ib('cms/cms_paging_first.png', ['height' => 12]) ?>></div>
	
	<?php if(!empty($filter_fields)) foreach($filter_fields as $filter_field => $filter_field_label): ?>
		<select class="admin_tool_select cms_right admin_tool_filter cms_list_filter" data-field="<?= $filter_field ?>">
			<option value="_empty_"><?= $filter_field_label ?></option>
			<?php foreach($filter_fields_values[$filter_field] as $key => $val): ?>
				<option value="<?= $key ?>"><?= $val ?></option>
			<?php endforeach ?>
		</select>
	<?php endforeach ?>
	
</div>

<div class="<?php print(!empty($extra_class) ? $extra_class : ''); ?> cms_list_container" 
		data-edit_base="<?php print($GLOBALS['config']['base_url'].$edit_base); ?>"
		<?php if(!empty($filter['panel_name'])): ?>
			data-panel_name="<?php print($filter['panel_name']); ?>" 
		<?php else: ?>
			data-source="<?php print($source['model'].'|'.$source['method']); ?>" 
		<?php endif ?>
		
		<?php if(!empty($id_field)): ?>
			data-id_field="<?php print($id_field); ?>"	
		<?php else: ?>
			data-id_field="block_id" 
		<?php endif ?>
		<?php if(!empty($no_sort)): ?>
			data-no_sort="no_sort"	
		<?php endif ?>
		data-limit="<?= $limit ?? 20 ?>"
		data-orig_limit="1">

</div>
