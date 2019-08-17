<?php foreach($positions as $position): ?>
	<div class="cms_page_positions">
	
		<?php _panel('cms/cms_input_select', array(
			'label' => $position['name'], 
			'value' => $position['value'], 
			'values' => $position['values'],
			'name' => $position['id'],
			'name_clean' => 'cms_page_position_'.$position['id'],
			'extra_class' => 'cms_page_positions',
			'help' => '[Page position]||Select an optional panel for the layout position',
		)); ?>

	</div>
<?php endforeach ?>