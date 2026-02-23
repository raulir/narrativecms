<div class="cms_schema_container">
	<div class="cms_schema_content">
		
		<div class="cms_toolbar">
			<div class="cms_tool_text">Database schema</div>
			<div class="cms_toolbar_buttons">
				<!-- intentionally empty – no global fix button -->
			</div>
		</div>
		
		<?php if (!$has_errors): ?>
			
			<div class="cms_schema_status cms_schema_status_ok">
				All database tables match the schema definition files
			</div>
			
		<?php else: ?>
			
			<?php foreach ($grouped_errors as $module => $items): ?>
			
				<div class="cms_schema_module">
				
					<div class="cms_schema_module_header">
						<div class="cms_schema_module_title">
							<?= $module ?>
						</div>
						<div class="cms_schema_module_fix">
							<div class="cms_schema_fix cms_small_button"
							     data-key="<?= $module ?>">
								fix module
							</div>
						</div>
					</div>
					
					<div class="cms_schema_items">
						
						<?php foreach ($items as $item): ?>
							<div class="cms_schema_item_row">
								<div class="cms_schema_location">
									<?= $item['location'] ?>
								</div>
								<div class="cms_schema_description">
									<?= $item['description'] ?>
								</div>
								<div class="cms_schema_action">
									<?php if ($item['enabled']): ?>
										<div class="cms_schema_fix cms_small_button"
										     data-key="<?= $item['key'] ?>">
											fix
										</div>
									<?php endif ?>
								</div>
							</div>
						<?php endforeach ?>
						
					</div>
				
				</div>
						
			<?php endforeach ?>
			
		<?php endif ?>
		
	</div>
</div>