<?php if(!empty($help)): ?>
	<div class="cms_help">
		<div class="cms_help_icon" <?php _ib('cms/cms_help.png', 12) ?>></div>
		<div class="cms_help_text"><?= str_replace(['|','{','}','[',']'], ['<br>','<i>','</i>','<b>','</b>'], $help) ?></div>
	</div>
<?php endif ?>
