<?php if(empty($_COOKIE['gdpr'])): ?>

	<div class="gdpr_popup_container gdpr_popup_type_<?= $type ?>">
		<div class="gdpr_popup_content">
		
			<div class="gdpr_popup_upper">
			
				<div class="gdpr_popup_heading"><?= $heading ?></div>
				<div class="gdpr_popup_cta"><?= $read_more ?></div>
				<div class="gdpr_popup_close"><div class="gdpr_popup_close_text"><?= $close ?></div></div>
				
			</div>
			
			<div class="gdpr_popup_lower">
			
				<div class="gdpr_popup_text"><?= $text ?></div>
			
				<?php foreach($links as $link): ?>
			
					<a class="gdpr_popup_link" <?php _lh($link['link']) ?>><div class="gdpr_popup_link_inner"><?= $link['text'] ?></div></a>
				
				<?php endforeach ?>
			
			</div>		
		
		</div>
	</div>

<?php endif ?>
