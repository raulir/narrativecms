<?php
	$row_count = !empty($data) ? count($data) : 0;
	$grid_height = 1 + $row_count + 2;
	if (!empty($operations) && stristr($operations, 'C')){
		$grid_height += 1;
	}

	$cms_grid_render_row = function($line_data, $is_template) use ($fields, $operations, $base_id, $base_name, $ds){

		$row_id = $is_template ? '' : ($line_data['id'] ?? '');
		$row_class = $is_template ? 'cms_grid_row cms_grid_row_template' : 'cms_grid_row';
		$row_style = $is_template ? ' style="display:none;"' : '';

		ob_start();
?>
					<div class="<?= $row_class ?>"<?= $row_style ?>>
						<input type="hidden" class="cms_grid_row_id" value="<?= $row_id ?>">
						<?php foreach($fields as $field): ?>
							<?php
								if ($is_template){
									$cell = array_key_exists('default', $field) ? $field['default'] : '';
									if (($field['name'] ?? '') === 'reserved' && $cell === ''){
										$cell = '0';
									}
								} else {
									$cell = $line_data[$field['name']] ?? '';
								}
							?>
							<div class="cms_grid_field cms_grid_field_<?= !empty($field['align']) ? $field['align'] : 'center' ?>"
									style="width: <?= $field['width'] ?>%; ">
								<div class="cms_grid_field_inner">
									<?php if($field['type'] == 'text'): ?>
										<?= $cell ?>
									<?php elseif($field['type'] == 'id'): ?>
										<a class="cms_grid_id" href="<?= $GLOBALS['config']['base_url'] . 
												'admin/cms_page_panel/'.$cell.'/' ?>"><?= $cell ?></a>
									<?php elseif($field['type'] == 'ids'): ?>
										<div class="cms_grid_ids">
											<?php foreach((array)$cell as $id): ?>
												<a class="cms_grid_id" href="<?= $GLOBALS['config']['base_url'] . 
														'admin/cms_page_panel/'.$id.'/' ?>"><?= $id ?></a>
											<?php endforeach ?>
										</div>
									<?php elseif(stristr($field['type'],'/')): ?>
										<?php _panel($field['type'], $field + [
												'base_id' => $base_id ?? 0,
												'base_name' => $base_name ?? '',
												'ds' => $ds ?? '',
												'item_id' => $row_id,
												'id' => $row_id,
												'value' => $cell,
										]) ?>
									<?php endif ?>
								</div>
							</div>
						<?php endforeach ?>
						<?php if(!empty($operations) && stristr($operations, 'D')): ?>
							<div class="cms_grid_field cms_grid_field_delete cms_grid_field_left">
								<div class="cms_small_button cms_grid_delete"
										<?= !empty($base_name) ? ' data-base_name="'.$base_name.'" ' : '' ?>
										<?= !empty($base_id) ? ' data-base_id="'.$base_id.'" ' : '' ?>
										data-ds="<?= $ds ?>" data-line_id="<?= $row_id ?>">
									Delete
								</div>
							</div>
						<?php endif ?>
					</div>
<?php
		return ob_get_clean();
	};
?>
<div class="cms_input cms_grid_container" data-cms_input_height="<?= !empty($lines) ? $lines + 4 : $grid_height ?>" data-cms_input_width="2"
		data-cms_page_panel_id="<?= $base_id ?? 0 ?>" data-ds="<?= $ds ?? '' ?>">

	<div class="cms_grid_label"><?= $label ?></div>
	<div class="cms_grid_line cms_grid_line_top"></div>

	<?php if(!empty($fields)): ?>

		<textarea class="cms_grid_log" name="panel_params[<?= $name ?>_log]" data-grid_name="<?= $name ?>" style="display:none;">[]</textarea>

		<div class="cms_grid_area cms_grid_area_<?= $name ?>">

			<div class="cms_grid_header">
				<?php foreach($fields as $field): ?>
					<div class="cms_grid_field cms_grid_field_<?= !empty($field['align']) ? $field['align'] : 'center' ?>"
							style="width: <?= $field['width'] ?>%; ">
						<div class="cms_grid_field_inner"><?= $field['label'] ?></div>
					</div>
				<?php endforeach ?>
				<?php if(!empty($operations) && stristr($operations, 'D')): ?>
					<div class="cms_grid_field cms_grid_header_delete"></div>
				<?php endif ?>
			</div>

			<?php if (!empty($data)): ?>
				<?php foreach($data as $line_data): ?>
					<?= $cms_grid_render_row($line_data, false) ?>
				<?php endforeach ?>
			<?php endif ?>

			<?= $cms_grid_render_row([], true) ?>

		</div>

		<?php if(!empty($operations) && stristr($operations, 'C') && !empty($base_id)): ?>
			<div class="admin_small_button cms_grid_new" data-ds="<?= $ds ?>" data-base_id="<?= $base_id ?>">
				Add row
			</div>
		<?php endif ?>

	<?php else: ?>

		<div class="cms_grid_not_available">Not available for this object</div>

	<?php endif ?>

	<div class="cms_grid_line cms_grid_line_bottom"></div>

</div>
