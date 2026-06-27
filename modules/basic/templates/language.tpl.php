<div class="language_container">
	<div class="language_content">
	
		<div class="language_label"><?= $select_label ?></div>
		
		<div class="language_button">

			<div class="language_button_label"><?= $languages[$active_language]['label'] ?></div>

			<div class="language_languages">
				<?php foreach($languages as $language_id => $language_data): ?>
					<div class="language_language" data-language_id="<?= $language_id ?>">
						<?= $language_data['label'] ?>
					</div>
				<?php endforeach ?>
			</div>

		</div>
		
	</div>
</div>