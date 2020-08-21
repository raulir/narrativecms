<div class="cms_input user_input_loginlink loginlink_container" data-cms_input_height="1">
	
	<label><?= $label ?></label>

	<?php if (!empty($help)) _panel('cms/cms_help', ['help' => $help, ]) ?>

	<div class="loginlink_content">
	
		<div class="cms_input_button loginlink_button_create">Create</div>
	
		<input type="text" class="cms_input_text_input loginlink_input" name="<?= $name ?>" value="<?= $value ?>">

	</div>

</div>
