<div class="cms_page_panel_targets_container">

	<div class="cms_page_panel_targets_content">
	
		<?php if(!empty($message)): ?>
			<div class="cms_page_panel_targets_message"><?= $message ?></div>
		<?php endif ?>
		
		<div class="cms_page_panel_targets_list">
		
			<?php foreach($groups as $group): ?>
			
				<div class="cms_page_panel_targets_row">
					<div class="cms_page_panel_targets_label"><?= $group['heading'] ?> (<?= $group['strategy'] ?>)</div>
					<div class="cms_page_panel_targets_field">
					
						<select class="cms_page_panel_targets_select" data-group="<?= $group['heading'] ?>">
							<option value=""><b>all</b></option>
							<?php foreach($group['values'] as $value): ?>
								<option value="<?= $value['id'] ?>" <?= $group['selected'] == $value['id'] ? ' selected="selected" ' : '' ?>><?= $value['label'] ?></option>
							<?php endforeach ?>
						</select>
		
					</div>
				</div>
			
			<?php endforeach ?>
	
		</div>
	
	</div>
	
</div>