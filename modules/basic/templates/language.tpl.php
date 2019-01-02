<div class="language_container">
	<div class="language_content">
	
		<div class="language_label"><?= $select_label ?></div>
		
		<div class="language_button">

			<div class="language_button_label"><?= $language['label'] ?></div>

			<div class="language_languages">
				<?php foreach($language['languages'] as $language_id => $language_label): ?>
					<div class="language_language" data-language_id="<?= $language_id ?>"><?= $language_label ?></div>
				<?php endforeach ?>
			</div>

		</div>
		
	</div>
</div>
