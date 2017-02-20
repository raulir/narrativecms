<?php if(!empty($help)): ?>
	<div class="cms_help">
		<div class="cms_help_icon" style="background-image: url('<?= $GLOBALS['config']['base_url'] ?>modules/cms/img/help.png'); "></div>
		<div class="cms_help_text"><?= str_replace('|', '<br>', $help) ?></div>
	</div>
<?php endif ?>
