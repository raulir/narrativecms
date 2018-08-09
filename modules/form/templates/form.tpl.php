<div class="form_container">
	<div class="form_content">
		<div class="form_area">
	
			<?php if(!empty($heading)): ?>
			
				<div class="form_title">
					<?php print($heading); ?>
				</div>
			
			<?php endif ?>
			
			<div class="form_form">
				<form style="display: inline; " method="post">
				
					<input type="hidden" name="do" value="send_form">
					<input type="hidden" name="id" value="<?php print($cms_page_panel_id); ?>">
	
					<?php foreach($elements as $element): ?>
						<div class="form_input form_input_<?= $element['name'] ?>">
	
							<label for="form_<?php print($element['name']); ?>"><?= $element['label'] ?></label>
	
							<?php if ($element['type'] == 'text'): ?>
							
								<input class="form_input_input <?php print($element['mandatory'] ? 'form_mandatory' : ''); ?>" 
										id="form_<?php print($element['name']); ?>" type="text" name="<?php print($element['name']); ?>"
										placeholder="<?= str_replace('###', $element['name'], $placeholder) ?>"
										<?php _p(!empty($element['limit']) ? ' data-limit="'.$element['limit'].'" ' : ''); ?> >
							
							<?php elseif ($element['type'] == 'textarea'): ?>
								
								<textarea class="form_input_input <?php print($element['mandatory'] ? 'form_mandatory' : ''); ?>"
										id="form_<?php print($element['name']); ?>" name="<?php print($element['name']); ?>"
										placeholder="<?= str_replace('###', $element['name'], $placeholder) ?>" 
										<?php _p(!empty($element['limit']) ? ' data-limit="'.$element['limit'].'" ' : ''); ?>></textarea>
							
							<?php elseif ($element['type'] == 'select'): ?>
							
								<select class="form_input_input <?php print($element['mandatory'] ? 'form_mandatory' : ''); ?>"
										id="form_<?php print($element['name']); ?>" name="<?php print($element['name']); ?>">
							
									<?php if (!empty($values)) foreach($values as $value): ?>
										<?php if ($value['element'] == $element['name']): ?>
											<option value="<?php print($value['value']); ?>"><?php print($value['label']); ?></option>
										<?php endif ?>
									<?php endforeach ?>
							
								</select>
	
							<?php elseif ($element['type'] == 'spacer'): ?>
															
							<?php endif ?>
	
						</div>
					<?php endforeach ?>
	
					<div class="form_submit">
						<div class="form_submit_label" <?php _ib($submit_icon, 20) ?>><?php print($submit_text); ?></div>
					</div>
					
					<div class="form_message">
						<div class="form_message_text"><?php print($success_message); ?></div>
						<div class="form_message_sending"><?php print($sending_message); ?></div>
					</div>
	
				</form>
			</div>
			
		</div>
	</div>
</div>