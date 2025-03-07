<div class="faqs_container">
	<div class="faqs_content">
	
		<?php foreach($faqs as $faq): ?>
			<div class="faqs_faq">
				<div class="faqs_faq_heading">
					<div class="faqs_faq_arrow" <?php _ib($arrow, 30) ?>></div>
					<?= $faq['heading'] ?>
				</div>
				<div class="faqs_faq_text">
					<div class="faqs_faq_text_inner"><?= $faq['text'] ?></div>
				</div>
			</div>
		<?php endforeach ?>

	</div>
</div>