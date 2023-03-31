<div class="cms_toolbar">
	<div class="admin_tool_text admin_title_text">Forms collected data</div>
</div>
<div class="form_admin_content">

	<?php foreach($forms as $form ): ?>
		<div class="admin_small_button admin_form_data" data-id="<?= $form['cms_page_panel_id'] ?>">
			CSV: <?php _p(substr(!empty($form['title']) ? $form['title'] : '[no name]', 0, 40)); ?>
		</div>
		<a class="admin_small_button admin_form_data" <?php _lh('form/grid/'.$form['cms_page_panel_id']) ?>>
			Grid: <?php _p(substr(!empty($form['title']) ? $form['title'] : '[no name]', 0, 40)); ?>
		</a>
	<?php endforeach ?>

</div>
