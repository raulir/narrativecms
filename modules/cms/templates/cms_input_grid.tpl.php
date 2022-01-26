<div class="cms_input cms_grid_container" data-cms_input_height="<?= !empty($lines) ? $lines + 4 : count($data) + 4 ?>" data-cms_input_width="2" 
		data-cms_page_panel_id="<?= $base_id ?>">

	<div class="cms_grid_label"><?= $label ?></div>
	<div class="cms_grid_line cms_grid_line_top"></div>
	
	<?php if(!empty($fields)): ?>

		<div class="cms_grid_area cms_grid_area_<?= $name ?>">
			
			<div class="cms_grid_header">
				<?php foreach($fields as $field): ?>
					<div class="cms_grid_field cms_grid_field_<?= !empty($field['align']) ? $field['align'] : 'center' ?>" 
							style="width: <?= $field['width'] ?>%; ">
							
						<div class="cms_grid_field_inner"><?= $field['label'] ?></div>
					
					</div>
				<?php endforeach ?>
			</div>

			<?php if (!empty($data)): ?>
				<?php foreach($data as $key => $line_data): ?>
					<div class="cms_grid_row">
						<?php foreach($fields as $field): ?>
						
							<div class="cms_grid_field cms_grid_field_<?= !empty($field['align']) ? $field['align'] : 'center' ?>" 
									style="width: <?= $field['width'] ?>%; ">
									
								<div class="cms_grid_field_inner">
									<?php if($field['type'] == 'text'): ?>
										<?= $line_data[$field['name']] ?>
									<?php elseif($field['type'] == 'id'): ?>
										<a class="cms_grid_id" href="<?= $GLOBALS['config']['base_url'] . 
												'admin/cms_page_panel/'.$line_data[$field['name']].'/' ?>"><?= $line_data[$field['name']] ?></a>
									<?php elseif($field['type'] == 'ids'): ?>
										<div class="cms_grid_ids">
											<?php foreach($line_data[$field['name']] as $id): ?>
												<a class="cms_grid_id" href="<?= $GLOBALS['config']['base_url'] . 
														'admin/cms_page_panel/'.$id.'/' ?>"><?= $id ?></a>
											<?php endforeach ?>
										</div>
									<?php elseif(stristr($field['type'],'/')): ?>
										<?php _panel($field['type'], $field + ['base_id' => $base_id, 'item_id' => $line_data['item_id']]) ?>
									<?php endif ?>
								</div>
								
							</div>
						
						<?php endforeach ?>
					</div>
				<?php endforeach ?>
			<?php endif ?>
			
			<?php // _print_r($data) ?>
	
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

<?php // _print_r($_params) ?>