<div class="language_container">
	<div class="language_content">
	
		<div class="language_label"><?= $select_label ?></div>
		
		<div class="language_button" <?php !empty($languages[$language['language_id']]['icon']) ? _ib($languages[$language['language_id']]['icon'], 20) : '' ?>>

			<div class="language_button_label"
					<?php !empty($languages[$language['language_id']]) ? _ib($languages[$language['language_id']]['icon'], 30) : '' ?>>
			
				<?= $language['label'] ?>
			
			</div>

			<div class="language_languages">
				<?php foreach($language['languages'] as $language_id => $language_label): ?>
					<div class="language_language" data-language_id="<?= $language_id ?>" 
							<?php !empty($settings[$language_id]) ? _ib($settings[$language_id]['icon'], 30) : '' ?>>
						<?= !empty($settings[$language_id]['label']) ? $settings[$language_id]['label'] : $language_label ?>
					</div>
				<?php endforeach ?>
			</div>

		</div>
		
	</div>
</div>
