<div class="webform_container">
	<div class="webform_content">
		<div class="webform_area">
	
			<div class="webform_title">
				<?php print($heading); ?>
			</div>
			
			<div class="webform_form">
				<form style="display: inline; " method="post">
				
					<input type="hidden" name="do" value="send_form">
					<input type="hidden" name="id" value="<?php print($cms_page_panel_id); ?>">
	
					<?php foreach($elements as $element): ?>
						<div class="webform_input">
	
							<label for="webform_<?php print($element['name']); ?>"></label>
	
							<?php if ($element['type'] == 'text'): ?>
							
								<input class="webform_input_input <?php print($element['mandatory'] ? 'webform_mandatory' : ''); ?>" 
										id="webform_<?php print($element['name']); ?>" type="text" name="<?php print($element['name']); ?>"
										placeholder="<?php print($element['label']); ?>"
										<?php _p(!empty($element['limit']) ? ' data-limit="'.$element['limit'].'" ' : ''); ?> >
							
							<?php elseif ($element['type'] == 'textarea'): ?>
								
								<textarea class="webform_input_input <?php print($element['mandatory'] ? 'webform_mandatory' : ''); ?>"
										id="webform_<?php print($element['name']); ?>" name="<?php print($element['name']); ?>"
										placeholder="<?php print($element['label']); ?>" 
										<?php _p(!empty($element['limit']) ? ' data-limit="'.$element['limit'].'" ' : ''); ?>></textarea>
							
							<?php elseif ($element['type'] == 'select'): ?>
							
								<select class="webform_input_input <?php print($element['mandatory'] ? 'webform_mandatory' : ''); ?>"
										id="webform_<?php print($element['name']); ?>" name="<?php print($element['name']); ?>">
							
									<?php if (!empty($values)) foreach($values as $value): ?>
										<?php if ($value['element'] == $element['name']): ?>
											<option value="<?php print($value['value']); ?>"><?php print($value['label']); ?></option>
										<?php endif ?>
									<?php endforeach ?>
							
								</select>
	
							<?php endif ?>
	
						</div>
					<?php endforeach ?>
	
					<div class="webform_submit" <?php if(!empty($settings['arrow'])) _ib($settings['arrow'], 100); ?>>
						<div class="webform_submit_label"><?php print($submit_text); ?></div>
					</div>
					
					<div class="webform_message">
						<div class="webform_message_text"><?php print($success_message); ?></div>
						<div class="webform_message_sending"><?php print($sending_message); ?></div>
					</div>
	
				</form>
			</div>
			
		</div>
	</div>
</div>