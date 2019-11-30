<div class="cms_input cms_input_subtitle_container cms_input_subtitle_<?= $width ?>"
		data-cms_input_height="2" data-cms_input_width="<?= !empty($width) && $width == 'narrow' ? '1' : '2' ?>">
	
	<div class="cms_input_subtitle_content">

		<div class="cms_input_subtitle_label"><?= $label ?></div>
		
		<?php _panel('cms/cms_help', ['help' => $help, ]); ?>

	</div>

</div>