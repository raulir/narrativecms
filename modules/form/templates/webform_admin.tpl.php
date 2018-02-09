<div class="cms_toolbar">
	<div class="admin_tool_text admin_title_text">Webforms collected data</div>
</div>
<div>

	<?php foreach($webforms as $webform ): ?>
		<div class="admin_small_button admin_webform_data" data-id="<?php print($webform['cms_page_panel_id']); ?>">
			<?php _p(substr($webform['title'], 0, 40)); ?>
		</div>
	<?php endforeach ?>

</div>