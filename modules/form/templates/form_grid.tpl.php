<div class="cms_toolbar">
	<div class="admin_tool_text admin_title_text">Form grid</div>
</div>
<div class="form_admin_content">

	<?php _panel('cms/cms_input_grid', [
			'name' => 'form_data',
			'label' => 'Form data',
			'base_id' => $cms_page_panel_id,
			'ds' => 'subscribers',
			'operations' => 'SL',
			'fields' => [
					[
							'type' => 'form/form_grid_operations',
							'name' => 'form_grid_operations',
							'label' => '',
							'width' => '10',
							'order' => '90',
							'align' => 'left',
					]
			],
	]) ?>

</div>
