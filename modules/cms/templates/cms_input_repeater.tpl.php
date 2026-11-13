<?php if ($nested): ?>
	<?php _html_error('Repeater "'.$label.'" cannot be inside a repeater row (cms_input.md)') ?>
<?php else: ?>
<div class="cms_repeater_container<?= $readonly ? ' cms_repeater_container_readonly' : '' ?>">

	<div class="cms_repeater_label"><?= $label ?></div>
	<div class="cms_repeater_line cms_repeater_line_top"></div>
	<div class="cms_repeater_area cms_repeater_area_<?= $name ?>" data-label="<?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>"
			data-next_key="<?= $next_key ?>">

		<?php if (!empty($data)): ?>
			<?php foreach($data as $repeater_key => $repeater_data): ?>

				<?php _panel('cms/cms_input_repeater_item', [
						'fields' => $fields,
						'repeater_data' => $repeater_data,
						'name' => $name,
						'repeater_key' => $repeater_key,
						'height' => $height,
						'readonly' => $readonly ?? 0,
				]) ?>
			
			<?php endforeach ?>
		<?php endif ?>

	</div>

	<?php if(!$readonly): ?>
		<?php $init_hooks = cms_repeater_field_init_hooks($fields); ?>
		<div class="admin_small_button cms_repeater_button" data-name="<?= $name ?>" data-block_panel="<?= $block_panel ?>"
				data-section="<?= $section ?>" data-base_id="<?= $base_id ?>" data-init_hooks="<?= implode(',', $init_hooks) ?>">
			Add element
		</div>
	<?php endif ?>
	
	<div class="cms_repeater_line cms_repeater_line_bottom"></div>

</div>
<?php endif ?>
