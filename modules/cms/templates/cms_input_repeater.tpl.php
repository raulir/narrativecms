<div class="cms_repeater_container">

	<div class="cms_repeater_label"><?= $label ?></div>
	<div class="cms_repeater_line cms_repeater_line_top"></div>
	<div class="cms_repeater_area cms_repeater_area_<?= $name ?>">

		<?php if (!empty($data)): ?>
			<?php foreach($data as $repeater_key => $repeater_data): ?>
			
				<?php _panel('cms/cms_input_repeater_item', [
						'fields' => $fields,
						'repeater_data' => $repeater_data,
						'name' => $name,
						'repeater_key' => $repeater_key,
						'height' => $height,
				]) ?>
			
			<?php endforeach ?>
		<?php endif ?>

	</div>

	<div class="admin_small_button cms_repeater_button" data-name="<?= $name ?>" data-fields="<?= base64_encode(json_encode($fields)) ?>">
		Add element
	</div>
	
	<div class="cms_repeater_line cms_repeater_line_bottom"></div>

</div>
