<div class="cms_popup_container cms_popup_xy_picker cms_xy_picker_container" data-x="<?= $x ?>" data-y="<?= $y ?>">

	<div class="cms_popup_table">
		<div class="cms_popup_cell">

			<div class="cms_xy_picker_content">

				<div class="cms_toolbar">

					<div class="cms_tool_text">Click at the point</div>
					
					<a class="cms_xy_picker_select cms_tool_button cms_right popup_select">Select</a>

					<a class="cms_xy_picker_cancel cms_tool_button cms_right popup_cancel">Cancel</a>

					<div class="cms_tool_text cms_right cms_xy_picker_current">Selected: <div class="cms_xy_picker_current_value"></div></div>
					<div class="cms_tool_text cms_right cms_xy_picker_cursor">Cursor: <div class="cms_xy_picker_cursor_value"></div></div>

				</div>

				<div class="cms_xy_picker_area">
					<div class="cms_xy_picker_image" <?php _ib('cms/cms_opacity.png', 40) ?>>
						<div class="cms_xy_picker_image_inner" <?php $i = _ib($image, 960) ?> data-w="<?= $i['width'] ?>" data-h="<?= $i['height'] ?>">
							<div class="cms_xy_picker_pointer"></div>
						</div>
					</div>
				</div>

		</div>
	</div>

</div>
