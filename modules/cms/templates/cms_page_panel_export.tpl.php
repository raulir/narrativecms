<div class="cms_page_panel_export_container">

	<div class="cms_page_panel_export_content">
	
		<div class="cms_page_panel_export_message">Export successful!</div>
		
		<div class="cms_page_panel_export_col1">Export time</div>
		<div class="cms_page_panel_export_col2"></div>
		<div class="cms_page_panel_export_col3"><?= $stats['time']['data'] ?> ms</div>
		
		<div class="cms_page_panel_export_col1">Zip time</div>
		<div class="cms_page_panel_export_col2"></div>
		<div class="cms_page_panel_export_col3"><?= $stats['time']['compress'] ?> ms</div>
		
		<div class="cms_page_panel_export_line"></div>
	
		<div class="cms_page_panel_export_col1">Panels</div>
		<div class="cms_page_panel_export_col2"><?= $stats['panels']['count'] ?></div>
		<div class="cms_page_panel_export_col3"><?= $stats['panels']['size'] ?></div>
		
		<div class="cms_page_panel_export_col1">Images</div>
		<div class="cms_page_panel_export_col2"><?= $stats['images']['count'] ?></div>
		<div class="cms_page_panel_export_col3"><?= $stats['images']['size'] ?></div>
		
		<div class="cms_page_panel_export_col1">Files</div>
		<div class="cms_page_panel_export_col2"><?= $stats['files']['count'] ?></div>
		<div class="cms_page_panel_export_col3"><?= $stats['files']['size'] ?></div>
		
		<div class="cms_page_panel_export_line"></div>
		
		<div class="cms_page_panel_export_col1">Total</div>
		<div class="cms_page_panel_export_col2"></div>
		<div class="cms_page_panel_export_col3"><?= $stats['total']['size'] ?></div>
		
		<div class="cms_page_panel_export_col1">Zipped</div>
		<div class="cms_page_panel_export_col2"></div>
		<div class="cms_page_panel_export_col3"><?= $stats['total']['compressed'] ?></div>
		
		<div class="cms_page_panel_export_buttons">
			<a class="cms_tool_button cms_page_panel_export_download" <?php _lh('/admin/export/'.$filename); ?>>Download</a>
			<div class="cms_tool_button cms_page_panel_export_close">Close</div>
		</div>
	
	</div>
	
</div>
