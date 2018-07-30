<?php if(empty($_COOKIE['gdpr'])): ?>

	<div class="popup_container">
		<div class="popup_content">
		
			<div class="popup_upper">
			
				<div class="popup_heading"><?= $heading ?></div>
				<div class="popup_cta"><?= $read_more ?></div>
				<div class="popup_close"><div class="popup_close_text"><?= $close ?></div></div>
				
			</div>
			
			<div class="popup_lower">
			
				<div class="popup_text"><?= $text ?></div>
			
				<?php foreach($links as $link): ?>
			
					<a class="popup_link" <?php _lh($link['link']) ?>><div class="popup_link_inner"><?= $link['text'] ?></div></a>
				
				<?php endforeach ?>
			
			</div>		
		
		</div>
	</div>

<?php endif ?>
