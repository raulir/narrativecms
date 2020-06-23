<div class="cms_input cms_input_file cms_input_file_container_<?php print($name_clean); ?> <?php print(!empty($extra_class) ? $extra_class : ''); ?> 
		<?= !empty($mandatory_class) ? $mandatory_class : '' ?>" data-cms_input_height="2">
	
	<label for="cms_input_file_<?php print($name_clean); ?>"><?php print($label); ?></label>

	<?php if (!empty($help)) _panel('cms/cms_help', ['help' => $help, ]); ?>

	<div class="cms_file_container">
	
		<div class="cms_input_file_content cms_input_file_content_<?php print($name_clean); ?>">
			<?php if(!empty($value)): ?>
				<?php $temp = explode('/', $value); print(array_pop($temp)); ?>
			<?php else: ?>
				-- no file --
			<?php endif ?>
		</div>
		
		<div class="cms_input_file_buttons">
		
			<div class="cms_input_file_button cms_input_button" data-name="<?= $name_clean ?>" <?= !empty($accept) ? ' data-accept="'.$accept.'" ' : '' ?>>
				Upload
			</div>
			
			<div class="cms_input_file_clear cms_input_button <?= empty($value) ? ' cms_input_file_button_disabled ' : '' ?>" data-name="<?= $name_clean ?>">
				Clear
			</div>
					
			<a class="cms_input_file_download cms_input_button <?= empty($value) ? ' cms_input_file_button_disabled ' : '' ?>" <?php _lf($value) ?>>Download</a>

			<div class=" cms_input_button cms_input_file_rename <?= empty($value) ? ' cms_input_file_button_disabled ' : '' ?>" data-name="<?= $name_clean ?>">
				Rename
			</div>
			
		</div>

	</div>
		
	<input type="hidden" class="cms_file_input_<?php print($name_clean); ?>" name="<?php print($name); ?>" value="<?php print($value); ?>">
	
</div>
