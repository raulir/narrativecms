<div class="cms_input cms_input_date <?= !empty($extra_class) ? $extra_class : '' ?> <?= !empty($mandatory) ? ' cms_input_mandatory ' : '' ?>"
		<?= !empty($default) ? ' data-default="'.$default.'" ' : '' ?>>
		
	<?php cms_input_label($label, $help ?? '', $name_clean) ?>
	<input id="<?= $name_clean ?>" type="date" class="cms_input_date_input cms_input_date_input_hidden" name="<?= $name ?>" value="<?= $value ?>"
	    	style="background-image: url('<?= $GLOBALS['config']['base_url']; ?>modules/cms/img/cms_calendar.png'); ">
	
	<div class="cms_input_date_clear" style="background-image: url('<?= $GLOBALS['config']['base_url']; ?>modules/cms/img/cms_close.png'); "></div>
	<div class="cms_input_date_today" style="background-image: url('<?= $GLOBALS['config']['base_url']; ?>modules/cms/img/cms_today.png'); "></div>

</div>
