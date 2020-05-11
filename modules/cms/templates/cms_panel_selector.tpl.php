<div class="cms_popup_container cms_popup_panel_selector cms_panel_selector_container cms_panel_selector_target_<?= $target_type ?>">

	<div class="cms_popup_table cms_panel_selector_table">
		<div class="cms_popup_cell cms_panel_selector_cell">

			<div class="cms_panel_selector_content">

				<div class="cms_panel_selector_toolbar cms_toolbar">

					<div class="cms_tool_text">Select page panel</div>

					<a
						class="cms_panel_selector_select cms_tool_button admin_right popup_select cms_panel_selector_select_disabled">Select</a>

					<a
						class="cms_panel_selector_cancel cms_tool_button admin_right popup_cancel">Cancel</a>

				</div>

				<div class="cms_panel_selector_area">

					<div class="cms_panel_selector_preview">
						
						<?php foreach ($panel_types as $panel_type => $panel_type_data): ?>

							<div class="cms_panel_selector_preview_item cms_panel_selector_preview_item_<?= md5($panel_type) ?>">
							
								<div class="cms_panel_selector_preview_heading">
									<div class="cms_panel_selector_preview_heading_inner"><?= $panel_type_data['label'] ?></div>
								</div>
							
								<?php if(!empty($panel_type_data['image'])): ?>
									<div class="cms_panel_selector_preview_image" <?php _ib($panel_type_data['image'], 600) ?>></div>
								<?php else: ?>
									<div class="cms_panel_selector_preview_image">
										<div class="cms_panel_selector_preview_missing">-- no preview image --</div>
									</div>
								<?php endif ?>
								
								<?php if(!empty($panel_type_data['description'])): ?>
									<div class="cms_panel_selector_preview_description"><?= $panel_type_data['description'] ?></div>
								<?php endif ?>
								
							</div>
						
						<?php endforeach ?>
						
					</div>
					
					<div class="cms_panel_selector_list_heading">Select new panel</div>

					<div class="cms_panel_selector_list">

						<?php foreach ($panel_types as $panel_type => $panel_type_data): ?>
							<div class="cms_panel_selector_item cms_panel_selector_item_hidden cms_panel_selector_item_<?= $panel_type_data['module'] ?>"
									data-panel_name="<?= $panel_type ?>" data-hash="<?= md5($panel_type) ?>">
									
								<?= $panel_type_data['label'] ?>
							
							</div>
						<?php endforeach ?>

					</div>
					
					<div class="cms_panel_selector_filter">
						<div class="cms_panel_selector_filter_label">Filter by module</div>
						<select class="cms_panel_selector_filter_select">
						
							<option class="cms_panel_selector_filter_option" value="">-- select module --</option>
							<?php foreach($modules as $module): ?>
								<option class="cms_panel_selector_filter_option" value="<?= $module ?>" <?= $main_module == $module ? ' selected ' : '' ?>>
									<?= $module ?>
								</option>
							<?php endforeach ?>
						
						</select>
					</div>

				</div>
				
				<?php if($target_type == 'page'): ?>
					<div class="cms_panel_selector_shortcut">

						<div class="cms_panel_selector_shortcut_label">Or create shortcut</div>
						<select class="cms_panel_selector_shortcut_select">
							<option class="cms_panel_selector_shortcut_option" value="">-- existing page panel --</option>
							<?php foreach ($shortcuts as $key => $value): ?>
								<option class="cms_panel_selector_shortcut_option" value="<?= $key ?>"><?= $value ?></option>
							<?php endforeach ?>
						</select>

					</div>
				<?php endif ?>

			</div>

		</div>
	</div>

</div>
