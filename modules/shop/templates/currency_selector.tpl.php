<div class="currency_selector_container" data-value="<?= $selected ?>">

	<input type="hidden" id="currency_selector_value" class="currency_selector_value"
			name="currency_selector_value" value="<?= $selected ?>">

	<div class="currency_selector_button">
		<div class="currency_selector_button_label"><?= $selected_label ?></div>
		<div class="currency_selector_list">
			<?php if ($add_empty): ?>
				<div class="currency_selector_option" data-currency_id="">—</div>
			<?php endif ?>
			<?php foreach ($options as $copt): ?>
				<div class="currency_selector_option"
						data-currency_id="<?= $copt['cms_page_panel_id'] ?>"><?= $copt['heading'] ?></div>
			<?php endforeach ?>
		</div>
	</div>

</div>
