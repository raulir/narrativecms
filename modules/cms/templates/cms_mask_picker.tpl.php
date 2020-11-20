<div class="cms_popup_container cms_popup_mask_picker cms_mask_picker_container" data-definition="<?= $definition ?>"
		data-value="<?= $value ?>" data-image="<?= $image ?>">

	<div class="cms_popup_table">
		<div class="cms_popup_cell">

			<div class="cms_mask_picker_content">

				<div class="cms_toolbar">

					<div class="cms_tool_text">Mark the squares</div>
					
					<a class="cms_mask_picker_select cms_tool_button admin_right popup_select">Select</a>

					<a class="cms_mask_picker_cancel cms_tool_button admin_right popup_cancel">Cancel</a>

					<a class="cms_mask_picker_clear cms_tool_button admin_right">Clear</a>

					<a class="cms_mask_picker_erase cms_tool_button admin_right">Erase</a>
					<a class="cms_mask_picker_mark cms_tool_button admin_right">Mark</a>

				</div>

				<div class="cms_mask_picker_area">
					<div class="cms_mask_picker_image" <?php _ib('cms/cms_opacity.png', 40) ?>>
						<div class="cms_mask_picker_image_inner" <?php $i = _ib($image, 960) ?> data-w="<?= $i['width'] ?>" data-h="<?= $i['height'] ?>">
						</div>
					</div>
				</div>
				
			</div>

		</div>
	</div>

</div>
