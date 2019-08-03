
<div class="cms_toolbar">
	<a class="admin_tool_text" <?php _lh('admin/keywords/'); ?>>Keywords</a>
	<div class="admin_tool_text">
		&nbsp; &gt; &nbsp; <?php print($cms_keyword_id ? $cms_keyword_id : 'New keyword'); ?>
	</div>
	<a class="cms_keyword_save cms_tool_button admin_right">Save</a>
	<a class="cms_keyword_delete cms_tool_button admin_right">Delete</a>
</div>

<div>

	<form method="post" class="admin_form" style="display: inline; ">
	
		<input type="hidden" name="do" value="cms_keyword_save">
		<input type="hidden" id="cms_keyword_id" name="cms_keyword_id" value="<?php print($cms_keyword_id); ?>">
		
		<div class="admin_block">
			<div class="admin_column admin_column_left">
				
				<?php _panel('cms_input_text', [
						'name' => 'keyword',
						'value' => $cms_keyword_id,
						'name_clean' => 'keyword',
						'label' => 'Keyword',
				]); ?>

			</div>
			<div class="admin_column admin_column_right">

			</div>
			<div style="clear: both; "></div>
		</div>
	
	</form>
	
</div>
