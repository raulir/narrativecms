<?php
	$search_label = trim((string)($search ?? ''));
	$search_title = $search_label !== '' ? $search_label : 'Search';
	$no_label_class = $search_label === '' ? ' button_no_label' : '';
?>
<div class="button_container">
	<div class="button_content">

		<div class="button_trigger<?= $no_label_class ?>" title="<?= $search_title ?>">
			<?php if ($search_label !== ''): ?>
				<div class="button_label"><?= $search_label ?></div>
			<?php endif ?>
			<div class="button_icon" <?php _ib($search_icon, 75) ?>></div>
		</div>

		<div class="button_modal">
			<div class="button_modal_body"></div>
		</div>

	</div>
</div>
