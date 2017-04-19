<div class="cms_toolbar">

	<div class="admin_tool_text">Global css</div>
	
	<div class="cms_toolbar_buttons">
		<div class="cms_cssjs_settings_save admin_tool_button admin_right">
			Save
			<div class="cms_ctrl_hint">S</div>
		</div>
	</div>

</div>

<div>

	<div class="cms_columns">
		<div class="cms_column">
		
			<div class="cms_cssjs_settings_header">
				css
			</div>
		
			<div class="cms_cssjs_settings_csss ui-sortable" data-bg="background-image: url('<?php print($GLOBALS['config']['base_url']); ?>modules/cms/img/drag.png'); ">
			
				<?php foreach($current_css as $current_css_item): ?>
					
					<div class="cms_list_sortable_item cms_cssjs_settings_csss_item ui-sortable-handle" data-value="<?= $current_css_item ?>" 
							data-text="<?= str_replace(['modules/','css/', '.css','.scss'], '', $current_css_item) ?>"
							style="background-image: url('<?php print($GLOBALS['config']['base_url']); ?>modules/cms/img/drag.png'); ">
						<?= str_replace(['modules/','css/', '.css','.scss'], '', $current_css_item) ?>
						<div class="cms_cssjs_settings_csss_item_delete cms_list_item_button">remove</div>
					</div>

				<?php endforeach ?>
			
			</div>
			
			<div class="cms_cssjs_settings_css_operations">
			
				<div class="cms_cssjs_settings_css_add">add</div>
				
				<select class="cms_cssjs_settings_css_select">
					<?php foreach($css as $key => $css_item): ?>
					
						<?php if(!in_array($key, $current_css)): ?>
							<option value="<?= $key ?>"><?= $css_item ?></option>
						<?php endif ?>
					
					<?php endforeach ?>
				</select>

			</div>
		
		</div>
	</div>

</div>
