<?php
$target = $target ?? 'print_background';
$points = (int)($points ?? 5);
$value_esc = htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
$target_image = $target_image ?? '';
$name = $name ?? 'transform';
$label = $label ?? 'Transform';
?>
<div class="cms_input cms_input_transform_container cms_input_transform_target_<?= htmlspecialchars($target, ENT_QUOTES, 'UTF-8') ?>"
		data-cms_input_height="4"
		data-target="<?= htmlspecialchars($target, ENT_QUOTES, 'UTF-8') ?>"
		data-target_image="<?= htmlspecialchars($target_image, ENT_QUOTES, 'UTF-8') ?>"
		data-name="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>"
		data-points="<?= $points ?>">

	<div class="cms_input_transform_content">

		<label class="cms_input_label"><?= $label ?></label>
		<?php _panel('cms/cms_help', ['help' => !empty($help) ? $help : '', ]); ?>

		<div class="cms_input_button cms_input_transform_set cms_input_transform_set_button">Edit</div>
		<div class="cms_input_button cms_input_transform_clear">Clear</div>

		<textarea class="cms_input_transform_value" style="display: none; " name="<?= $name ?>"><?= $value_esc ?></textarea>

		<div class="cms_input_transform_image cms_input_transform_set_button" <?php _ib('cms/cms_opacity.png', 40) ?>>

			<?php if (!empty($target_image)): ?>
				<div class="cms_input_transform_image_inner" <?php $i = _ib($target_image, 300) ?>
						data-w="<?= $i['width'] ?>" data-h="<?= $i['height'] ?>">
					<svg class="cms_input_transform_preview_svg" viewBox="0 0 100 100" preserveAspectRatio="none"></svg>
				</div>
			<?php else: ?>
				<div class="cms_input_transform_image_inner cms_input_transform_empty">
					-- empty target --
				</div>
			<?php endif ?>

		</div>

	</div>

</div>
