<div class="language_container">
	<div class="language_content">
	
		<div class="language_label"><?= $select_label ?></div>
		
		<div class="language_button" <?php _ib($icon, 20) ?>>

			<div class="language_button_label" <?php !empty($settings[$language['language_id']]) ? _ib($settings[$language['language_id']]['icon'], 30) : '' ?>>
				<?= $language['label'] ?>
			</div>

			<div class="language_languages">
				<?php foreach($language['languages'] as $language_id => $language_label): ?>
					<div class="language_language" data-language_id="<?= $language_id ?>" 
							<?php !empty($settings[$language_id]) ? _ib($settings[$language_id]['icon'], 30) : '' ?>><?= $language_label ?></div>
				<?php endforeach ?>
			</div>

		</div>
		
	</div>
</div>
