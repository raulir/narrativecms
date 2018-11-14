<div class="cms_toolbar cms_page_panel_toolbar">

	<div class="cms_page_panel_toolbar_breadcrumb">

		<?php foreach($breadcrumb as $element): ?>
			
			<<?= !empty($element['url']) ? 'a' : 'div' ?> class="admin_tool_text cms_page_panel_toolbar_text" 
					<?php _lh($element['url']); ?> <?php print(!empty($element['field']) ? 'data-title_field="'.$element['field'].'"' : ''); ?>>
				
				<?= $element['text'] ?>
			
			</<?= !empty($element['url']) ? 'a' : 'div' ?>>
			
			<div class="admin_tool_text cms_page_panel_toolbar_gt">&nbsp; &gt; &nbsp;</div>
		
		<?php endforeach ?>

	</div>

	<div class="cms_toolbar_buttons">
	
		<?php if(!empty($hidden_section)): ?>
			<div class="admin_tool_button admin_right cms_toolbar_buttons_hidden" style="background-image: url('<?= $GLOBALS['config']['base_url']; ?>modules/cms/img/cms_settings.png'); ">
				<div class="cms_toolbar_buttons_hidden_arrow" style="background-image: url('<?= $GLOBALS['config']['base_url']; ?>modules/cms/img/cms_down.png'); "></div>
				<div class="cms_toolbar_buttons_hidden_container">
				
					<?php foreach($buttons as $button): ?>
					
						<?php if($button['position'] == 'hidden'): ?>
							<?php _panel($button['name'], array('cms_page_panel_id' => $cms_page_panel_id, )); ?>
						<?php endif ?>
					
					<?php endforeach ?>
				
				</div>
			</div>
		<?php endif ?>

		<?php if(!empty($buttons)): ?>
			<?php foreach($buttons as $button): ?>
			
				<?php if($button['position'] == 'visible'): ?>
					<?php _panel($button['name'], array('cms_page_panel_id' => $cms_page_panel_id, )); ?>
				<?php endif ?>
			
			<?php endforeach ?>
		<?php endif ?>

	</div>

</div>
