<div class="cms_page_panel_targets_container">

	<div class="cms_page_panel_targets_content">
	
		<?php if(!empty($message)): ?>
			<div class="cms_page_panel_targets_message"><?= $message ?></div>
		<?php endif ?>
		
		<div class="cms_page_panel_targets_table">
			<div class="cms_page_panel_targets_cell">
			
				<?php foreach($groups as $group): ?>
				
					<div class="cms_page_panel_targets_col1"><?= $group['heading'] ?> (<?= $group['strategy'] ?>)</div>
					<div class="cms_page_panel_targets_col2">
					
						<select class="cms_page_panel_targets_select" data-group="<?= $group['heading'] ?>">
							<option value=""><b>all</b></option>
							<?php foreach($group['values'] as $value): ?>
								<option value="<?= $value ?>" <?= $group['selected'] == $value ? ' selected="selected" ' : '' ?>><?= $value ?></option>
							<?php endforeach ?>
						</select>
		
					</div>
				
				<?php endforeach ?>
		
			</div>
		</div>
		
		<div class="cms_page_panel_targets_buttons">
			<div class="admin_tool_button cms_page_panel_targets_close">Save</div>
		</div>
	
	</div>
	
</div>
