<div class="form_basic_container form_basic_recaptcha_<?= !empty($recaptcha) ? 'on' : 'off' ?>"
		<?php if (!empty($recaptcha)): ?>data-recaptcha_key="<?= $recaptcha_client_key ?>" <?php endif ?>
		<?php if (!empty($brochure)): ?>data-success_url="<?php _lfd($brochure, false) ?>" <?php endif ?>
		<?php if (!empty($virtual_success_url)): ?>data-virtual_success_url="<?= $virtual_success_url ?>"<?php endif ?>>
		
	<div class="form_basic_content">

		<?php if(!empty($heading)): ?>
			<div class="form_basic_title"><?= $heading ?></div>
		<?php endif ?>
		
		<?php if(!empty($text)): ?>
			<div class="form_basic_text"><?= $text ?></div>
		<?php endif ?>

		<div class="form_basic_form">
			<form style="display: inline; " method="post">
			
				<input type="hidden" name="do" value="send_form">
				<input type="hidden" name="id" value="<?= $cms_page_panel_id ?>">

				<?php foreach($elements as $element): ?>
					<div class="form_basic_input form_basic_input_type_<?= $element['type'] ?> form_basic_input_<?= $element['name'] ?>">

						<?php if (!empty($element['label']) && empty($label_as_placeholder) && $element['type'] != 'radio'): ?>
							<label class="form_basic_input_label" for="form_basic_<?= $element['name'] ?>"><?= $element['label'] ?></label>
						<?php endif ?>

						<?php if ($element['type'] == 'text'): ?>
						
							<input class="form_basic_input_input <?= $element['mandatory'] ? 'form_basic_mandatory' : '' ?>" 
									id="form_basic_<?= $element['name'] ?>" type="text" name="<?= $element['name'] ?>"
									placeholder="<?= empty($label_as_placeholder) ? str_replace('[name]', $element['name'], $placeholder) : $element['label'] ?>"
									<?php _p(!empty($element['limit']) ? ' data-limit="'.$element['limit'].'" ' : '') ?> >
						
						<?php elseif ($element['type'] == 'textarea'): ?>
							
							<textarea class="form_basic_input_input <?= $element['mandatory'] ? 'form_basic_mandatory' : '' ?>"
									id="form_basic_<?= $element['name'] ?>" name="<?= $element['name'] ?>"
									placeholder="<?= empty($label_as_placeholder) ? str_replace('[name]', $element['name'], $placeholder) : $element['label'] ?>"
									<?php _p(!empty($element['limit']) ? ' data-limit="'.$element['limit'].'" ' : '') ?>></textarea>
						
						<?php elseif ($element['type'] == 'select'): ?>
						
							<select class="form_basic_input_input <?php print($element['mandatory'] ? 'form_basic_mandatory' : ''); ?>"
									id="form_basic_<?php print($element['name']); ?>" name="<?php print($element['name']); ?>">
									
								<?php if (!empty($label_as_placeholder)): ?>
									<option value="" disabled="disabled" selected="selected"><?= $element['label'] ?></option>
								<?php endif ?>
						
								<?php if (!empty($values)) foreach($values as $value): ?>
									<?php if ($value['element'] == $element['name']): ?>
										<option value="<?php print($value['value']); ?>"><?php print($value['label']); ?></option>
									<?php endif ?>
								<?php endforeach ?>
						
							</select>
							
						<?php elseif ($element['type'] == 'checkbox'): ?>
						
							<input class="form_basic_input_input <?= $element['mandatory'] ? 'form_basic_mandatory' : '' ?>" 
									id="form_basic_<?= $element['name'] ?>" type="hidden" name="<?= $element['name'] ?>">
						
							<div class="form_basic_input_checkbox" data-target="form_basic_<?= $element['name'] ?>"></div>

						<?php elseif ($element['type'] == 'radio'): ?>
						
							<div class="form_basic_input_radio <?= $element['mandatory'] ? 'form_basic_mandatory' : '' ?>">
								<div class="form_basic_input_radio_label"><?= $element['label'] ?></div>
								<?php foreach($values as $value): ?>
									<?php if ($value['element'] == $element['name']): ?>
									
										<input type="radio" class="form_basic_input_radio_input"
												value="<?= $value['value'] ?>" name="<?= $element['name'] ?>"
												id="form_basic_<?= md5($element['name'].$value['value']) ?>">
									
									
										<label class="form_basic_input_radio_label_area" for="form_basic_<?= md5($element['name'].$value['value']) ?>">
											<div class="form_basic_input_radio_label_heading"><?= $value['label'] ?></div>
											<div class="form_basic_input_radio_label_text"><?= $value['text'] ?></div>
										</label>
										
									<?php endif ?>
								<?php endforeach ?>
							</div>

						<?php elseif ($element['type'] == 'spacer'): ?>
														
						<?php elseif ($element['type'] == 'copy'): ?>
						
							<div class="form_basic_input_copy_area">
								<?php foreach($values as $value): ?>
									<?php if ($value['element'] == $element['name']): ?>
										<div class="form_basic_input_copy_heading"><?= $value['label'] ?></div>
										<div class="form_basic_input_copy_text"><?= $value['text'] ?></div>
									<?php endif ?>
								<?php endforeach ?>
							</div>

						<?php endif ?>

					</div>
				<?php endforeach ?>

				<div class="form_basic_submit">
					<div class="form_basic_submit_label" <?php !empty($submit_icon) ? _ib($submit_icon, 30) : '' ?>><?= $submit_text ?></div>
				</div>
				
				<div class="form_basic_message">
					<div class="form_basic_message_text"><?= $success_message ?></div>
					<div class="form_basic_message_sending"><?= $sending_message ?></div>
				</div>

			</form>
		</div>
		
		<div class="form_basic_close"></div>

	</div>
</div>