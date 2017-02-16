
<?php
	if (empty($name_clean)) $name_clean = $name;
?>

<div class="admin_input admin_input_file admin_input_file_container_<?php print($name_clean); ?> <?php print(!empty($extra_class) ? $extra_class : ''); ?>">
	
	<label for="admin_input_file_<?php print($name_clean); ?>"><?php print($label); ?></label> 
	
	<div class="admin_file_container">
	
		<div class="admin_file_content admin_input_file_content_<?php print($name_clean); ?>">
			<?php if(!empty($value)): ?>
				<?php $temp = explode('/', $value); print(array_pop($temp)); ?>
			<?php else: ?>
				-- no file --
			<?php endif ?>
		</div>
		<div class="admin_input_file_button admin_input_button" data-name="<?php print($name_clean); ?>" <?= !empty($accept) ? ' data-accept="'.$accept.'" ' : '' ?>>
			Upload
		</div>
		<div class="admin_input_file_clear admin_input_button" data-name="<?php print($name_clean); ?>">
			Clear
		</div>
				
	</div>
		
	<input type="hidden" class="cms_file_input_<?php print($name_clean); ?>" name="<?php print($name); ?>" value="<?php print($value); ?>">
	
</div>
