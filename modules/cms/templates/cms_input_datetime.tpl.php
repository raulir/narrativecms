<div class="cms_input cms_input_datetime cms_input_datetime_hidden <?= !empty($extra_class) ? $extra_class : '' ?> 
		<?= !empty($mandatory) ? ' cms_input_mandatory ' : '' ?>" <?= !empty($default) ? ' data-default="'.$default.'" ' : '' ?>>
		
	<label for="<?= $name_clean ?>"><?= $label ?></label>
	<?php if (!empty($help)) _panel('cms_help', ['help' => $help, ]); ?>
	
	<input id="<?= $name_clean ?>" type="hidden" name="<?= $name ?>" value="<?= $value ?>" class="cms_input_datetime_value">
	
	<input type="date" class="cms_input_datetime_date" style="background-image: url('<?= $GLOBALS['config']['base_url'] ?>modules/cms/img/cms_calendar.png'); ">
	
	<div class="cms_input_datetime_time" style="background-image: url('<?= $GLOBALS['config']['base_url']; ?>modules/cms/img/cms_clock.png'); ">
		<select class="cms_input_datetime_hour">
			<?php for($i = 0; $i < 24; $i++): ?>
				<option value="<?= sprintf('%02d', $i) ?>"><?= sprintf('%02d', $i) ?></option>
			<?php endfor ?>
		</select>
		<select class="cms_input_datetime_minute">
			<?php for($i = 0; $i < 60; $i++): ?>
				<option value="<?= sprintf('%02d', $i) ?>"><?= sprintf('%02d', $i) ?></option>
			<?php endfor ?>
		</select>
	</div>
	
	
	<div class="cms_input_datetime_clear" style="background-image: url('<?= $GLOBALS['config']['base_url'] ?>modules/cms/img/cms_close.png'); "></div>
	<div class="cms_input_datetime_today" style="background-image: url('<?= $GLOBALS['config']['base_url'] ?>modules/cms/img/cms_today.png'); "></div>

</div>
