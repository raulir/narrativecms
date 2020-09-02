<div class="language_container">
	<div class="language_content">
	
		<div class="language_label"><?= $select_label ?></div>
		
		<div class="language_button" <?php !empty($languages[$active_language]['icon']) ? _ib($languages[$active_language]['icon'], 30) : '' ?>>

			<div class="language_button_label"><?= $languages[$active_language]['label'] ?></div>

			<div class="language_languages">
				<?php foreach($languages as $language_id => $language_data): ?>
					<div class="language_language" data-language_id="<?= $language_id ?>" <?php _ib($language_data['icon'], 30) ?>>
						<?= $language_data['label'] ?>
					</div>
				<?php endforeach ?>
			</div>

		</div>
		
	</div>
</div>
