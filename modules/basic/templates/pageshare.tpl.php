<div class="basic_pageshare_container" data-fb_app_id="<?= $fb_app_id ?>">

	<div class="basic_pageshare_content">

		<?php foreach($channels as $icon): ?>
			<a class="basic_pageshare_icon" <?php _ib($icon['image'], 40) ?>
					data-content="<?= $content ?>" 
					data-type="<?= $icon['type'] ?>" 
					data-hashtags="<?= trim((!empty($hashtags) ? str_replace([' ', '#', ' #'], ',', $hashtags) : ''), '# ,') ?>"
					<?php if(!empty($url)): ?>data-url="<?= $url ?>"<?php endif ?>>
					
				<div class="basic_pageshare_text"><?= $icon['text'] ?></div>
			
			</a>
		<?php endforeach ?>

	</div>

</div>
