<div class="admin_input admin_input_link admin_input_link_container_<?php print($name_clean); ?> <?php print(!empty($extra_class) ? $extra_class : ''); ?>">
	<label for="admin_input_link_<?php print($name_clean); ?>"><?php print($label); ?></label>
	<div class="cms_input_link_container">

		<select class="cms_input_link_target cms_input_link_dropdown" name="<?php print($name); ?>[target]<?php print($name_extra); ?>">
			<?php foreach($targets as $key => $target): ?>
				<option value="<?php print($key); ?>" <?php print($key == $value['target'] ? ' selected="selected"' : ''); ?>><?php print($target); ?></option>
			<?php endforeach ?>
		</select>
		
		<div class="cms_input_link_select_placeholder"></div>

		<select class="cms_input_link_select cms_input_link_select_page cms_input_link_dropdown" name="<?php print($name); ?>[cms_page_id]<?php print($name_extra); ?>">
			<?php foreach($pages as $page): ?>

				<option value="<?php print($page['cms_page_id']) ?>" data-url="<?php print($page['slug'].'/'); ?>" 
						<?php print(!empty($value['cms_page_id']) && $page['cms_page_id'] == $value['cms_page_id'] ? ' selected="selected"' : ''); ?>
						><?php print(!empty($page['title']) ? $page['title'] : '[ no title ]'); ?></option>
			
			<?php endforeach ?>
		</select>
		
		<?php if (!empty($lists)) foreach($lists as $list_name => $list): ?>
		
			<select class="cms_input_link_select cms_input_link_select_<?php print($list_name); ?> cms_input_link_dropdown" 
					name="<?php print($name); ?>[<?php print($list_name); ?>_id]<?php print($name_extra); ?>">
				
				<?php foreach($list as $item_id => $item_title): ?>
					
					<option value="<?php print($list_name.'='.$item_id) ?>" 
							<?php print(!empty($value[$list_name.'_id']) && ($list_name.'='.$item_id) == $value[$list_name.'_id'] ? ' selected="selected"' : ''); ?>
							data-slug="<?php print($slugs[$list_name][$item_id]); ?>"
							data-target_id="<?php print($item_id); ?>"
							><?php print($item_title); ?></option>
				
				<?php endforeach ?>
			
			</select>

		<?php endforeach ?>
		
		<div class="cms_input_link_select_spacer"></div>

		<input type="text" class="cms_input_link_url_display" value="<?php print(!empty($value['url']) ? $value['url'] : ''); ?>" placeholder="Link URL">
		<input type="text" class="cms_input_link_text_display" value="<?php print(!empty($value['text']) ? $value['text'] : ''); ?>" placeholder="Link text">

		<input type="hidden" class="cms_input_link_url" name="<?php print($name); ?>[url]<?php print($name_extra); ?>" 
				value="<?php print(!empty($value['url']) ? $value['url'] : ''); ?>">
				
		<input type="hidden" class="cms_input_link_text" name="<?php print($name); ?>[text]<?php print($name_extra); ?>" 
				value="<?php print(!empty($value['text']) ? $value['text'] : ''); ?>">

		<input type="hidden" class="cms_input_link_target_id" name="<?php print($name); ?>[target_id]<?php print($name_extra); ?>" 
				value="<?php print(!empty($value['target_id']) ? $value['target_id'] : ''); ?>">
	
	</div>
</div>
