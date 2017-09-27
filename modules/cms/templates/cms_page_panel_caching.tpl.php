<div class="popup_container cms_page_panel_caching_container">

	<div class="cms_page_panel_caching_table">
		<div class="cms_page_panel_caching_cell">

			<div class="cms_page_panel_caching_content">

				<div class="cms_page_panel_caching_toolbar cms_toolbar">
					
					<div class="admin_tool_text">Panel cache settings</div>
					
					<a class="cms_page_panel_caching_save admin_tool_button admin_right popup_yes">Save</a>
					
					<a class="cms_page_panel_caching_cancel admin_tool_button admin_right popup_cancel">Cancel</a>
					
				</div>
				
				<div class="cms_page_panel_caching_area">
				
					<div class="cms_page_panel_caching_input cms_input cms_input_select">
						<label class="cms_page_panel_caching_label">Time</label>
						<select class="cms_page_panel_caching_select">
							<?php foreach($caching_options as $option_value => $option_name): ?>
								<option value="<?php print($option_value); ?>" <?php print($option_value == $cache ? 'selected="selected"' : ''); ?>><?php print($option_name); ?></option>
							<?php endforeach ?>
						</select>
					</div>
					
					<div class="cms_page_panel_caching_input cms_input">
						<label class="cms_page_panel_caching_related_label">Related lists</label>
						<div class="cms_page_panel_caching_values">
							<?php foreach($lists as $list): ?>
								<?php if(in_array($list, $no_cache_lists)): ?>
									<div class="cms_page_panel_caching_item" data-value="<?php print($list); ?>">
										<?php print($list); ?>
										<div class="cms_page_panel_caching_item_close">x</div>
									</div>
								<?php endif ?>
							<?php endforeach ?>
						</div>
						<div class="cms_page_panel_caching_add cms_input_button">add</div>
						<select class="cms_page_panel_caching_lists_select">
							<?php foreach($lists as $list): ?>
								<?php if(!in_array($list, $no_cache_lists)): ?>
									<option class="cms_page_panel_caching_lists_option_<?php print($list); ?>" value="<?php print($list); ?>"><?php print($list); ?></option>
								<?php endif ?>
							<?php endforeach ?>
						</select>
					</div>
					
					<input type="hidden" class="cms_page_panel_caching_target_id" value="<?php print($target_id); ?>">
				
					<!-- div style="text-align: center; font-size: 8px; "><pre><?php print_r($params); ?></pre></div -->
					
				</div>
			
			</div>
			
		</div>
	</div>

</div>

<div class="popup_overlay cms_page_panel_caching_overlay"></div>
