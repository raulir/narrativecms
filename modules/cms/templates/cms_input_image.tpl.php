<div class="cms_input_container <?= !empty($params['groups']) ? ' cms_input_container_groups ' : '' ?>"
		<?= !empty($params['groups']) ? ' data-groups="'.implode(',', $params['groups']).'" ' : '' ?>>

	<div class="cms_input cms_input_image cms_input_image_container_<?= $name_clean ?> <?= !empty($extra_class) ? $extra_class : '' ?> 
			<?= !empty($mandatory_class) ? $mandatory_class : '' ?>" <?= !empty($extra_data) ? $extra_data : '' ?> data-value="<?= $value ?>">
	
		<label for="cms_input_image_<?php print($name_clean); ?>"><?= $label ?></label>
		
		<?php _panel('cms_help', ['help' => $help]) ?>
		
		<div class="admin_image_container" <?php _ib('cms/cms_opacity.png', 40) ?>>
			<div class="admin_image_content cms_input_image_content_<?= $name_clean ?>">
				<?php if(!empty($error)): ?>
					<div class="cms_input_image_error"><?= $error ?></div>
				<?php elseif(!empty($value)): ?>
					<div class="cms_input_image_image" <?php $i = _ib($value, ['width' => 300, 'maxwidth' => true, ]); ?>></div>
				<?php else: ?>
					-- no image --
				<?php endif ?>
			</div>
		</div>
		
		<div class="cms_input_image_button cms_input_button" data-name="<?= $name_clean ?>" data-category="<?= $category ?>">
			Select
		</div>
		
		<div class="cms_input_image_clear cms_input_button" data-name="<?= $name_clean ?>">
			Clear
		</div>
		
		<input type="hidden" class="cms_input_image_input cms_image_input_<?= $name_clean ?> <?= $name ?>"
				name="<?= $name ?>" value="<?= $value ?>">
		
		<div style="clear: both; "></div>
	
	</div>

</div>
