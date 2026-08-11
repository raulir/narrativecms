<?php
$image = $image ?? '';
$value = $value ?? '';
$points = (int)($points ?? 5);
$value_attr = htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
?>
<div class="cms_popup_container cms_popup_transform_picker cms_transform_picker_container"
		data-points="<?= $points ?>"
		data-value="<?= $value_attr ?>"
		data-image="<?= htmlspecialchars($image, ENT_QUOTES, 'UTF-8') ?>"
		data-zoom="1"
		data-pan_x="0"
		data-pan_y="0">

	<div class="cms_popup_table">
		<div class="cms_popup_cell">

			<div class="cms_transform_picker_content">

				<div class="cms_transform_picker_left">
					<div class="cms_transform_picker_area" <?php _ib('cms/cms_opacity.png', 40) ?>>
						<?php if (!empty($image)): ?>
							<div class="cms_transform_picker_pan">
								<div class="cms_transform_picker_stage" <?php $i = _ib($image, 960) ?>
										data-w="<?= $i['width'] ?>" data-h="<?= $i['height'] ?>">
									<div class="cms_transform_picker_image_bg"></div>
									<svg class="cms_transform_picker_svg" viewBox="0 0 100 100" preserveAspectRatio="none"></svg>
									<div class="cms_transform_picker_handles"></div>
								</div>
							</div>
						<?php else: ?>
							<div class="cms_transform_picker_empty">
								No target image — set print background first
							</div>
						<?php endif ?>
					</div>
				</div>

				<div class="cms_transform_picker_right">

					<div class="cms_transform_picker_toolbar">
						<div class="cms_transform_picker_title cms_tool_text">Transform</div>
						<div class="cms_transform_picker_toolbar_buttons">
							<a class="cms_transform_picker_select cms_tool_button popup_select">Select</a>
							<a class="cms_transform_picker_cancel cms_tool_button popup_cancel">Cancel</a>
						</div>
					</div>

					<div class="cms_transform_picker_tools">

						<div class="cms_transform_picker_tool_block">
							<div class="cms_transform_picker_tool_label">Zoom</div>
							<div class="cms_transform_picker_zoom_row">
								<input type="text" class="cms_transform_picker_zoom_input" value="1.0">
								<div class="cms_transform_picker_zoom_slider">
									<div class="cms_transform_picker_zoom_slider_track">
										<div class="cms_transform_picker_zoom_slider_inner">
											<div class="cms_transform_picker_zoom_slider_line"></div>
											<?php foreach ([0.5, 1.0, 2.0, 4.0, 8.0] as $tick_index => $tick_value): ?>
												<div class="cms_transform_picker_zoom_slider_tick" style="left: <?= ($tick_index / 4) * 100 ?>%">
													<span class="cms_transform_picker_zoom_slider_tick_label"><?= number_format($tick_value, 1, '.', '') ?></span>
												</div>
											<?php endforeach ?>
											<div class="cms_transform_picker_zoom_slider_handle"></div>
										</div>
									</div>
								</div>
							</div>
						</div>

						<div class="cms_transform_picker_tool_block">
							<div class="cms_transform_picker_tool_label">Edge linearisation</div>
							<div class="cms_transform_picker_edge_buttons">
								<a class="cms_transform_picker_edge cms_input_button" data-edge="up" title="Linearize top edge"></a>
								<a class="cms_transform_picker_edge cms_input_button" data-edge="right" title="Linearize right edge"></a>
								<a class="cms_transform_picker_edge cms_input_button" data-edge="down" title="Linearize bottom edge"></a>
								<a class="cms_transform_picker_edge cms_input_button" data-edge="left" title="Linearize left edge"></a>
							</div>
						</div>

						<div class="cms_transform_picker_tool_block">
							<div class="cms_transform_picker_tool_label">View</div>
							<div class="cms_transform_picker_view_buttons">
								<a class="cms_transform_picker_zoom_reset cms_input_button">Reset view</a>
								<a class="cms_transform_picker_reset cms_input_button">Reset points</a>
							</div>
						</div>

					</div>

				</div>

			</div>

		</div>
	</div>

</div>
