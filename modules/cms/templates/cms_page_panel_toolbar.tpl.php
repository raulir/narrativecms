<div class="cms_toolbar cms_page_panel_toolbar">

	<div class="cms_page_panel_toolbar_breadcrumb">

		<?php foreach($breadcrumb as $element): ?>
			
			<<?= !empty($element['url']) ? 'a' : 'div' ?> class="admin_tool_text cms_page_panel_toolbar_text" 
					<?php _lh($element['url']); ?> <?php print(!empty($element['field']) ? 'data-title_field="'.$element['field'].'"' : ''); ?>>
				
				<?php print(str_limit($element['text'], 30)); ?>
			
			</<?= !empty($element['url']) ? 'a' : 'div' ?>>
			
			<div class="admin_tool_text cms_page_panel_toolbar_gt">&nbsp; &gt; &nbsp;</div>
		
		<?php endforeach ?>

	</div>

	<div class="cms_toolbar_buttons">

		<?php if(!empty($buttons)): ?>
			<?php foreach($buttons as $button): ?>
				<?php _panel($button, array('cms_page_panel_id' => $cms_page_panel_id, )); ?>
			<?php endforeach ?>
		<?php endif ?>

	</div>

</div>
