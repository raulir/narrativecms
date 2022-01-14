<div class="basic_pageshare_container" data-fb_app_id="<?= $fb_app_id ?>">

	<div class="basic_pageshare_content">

		<?php foreach($channels as $channel): ?>
			<a class="basic_pageshare_icon" <?php _ib($channel['image'], 40) ?>
					data-content="<?= $content ?>" 
					data-type="<?= $channel['type'] ?>" 
					data-hashtags="<?= trim((!empty($hashtags) ? str_replace([' ', '#', ' #'], ',', $hashtags) : ''), '# ,') ?>"
					<?php if(!empty($url)): ?> data-url="<?= $channel['url'] ?? $url ?>" <?php endif ?>
					<?php if(!empty($channel['url_key'])): ?> data-url_key="<?= $channel['url_key'] ?>" <?php endif ?>>
					
				<div class="basic_pageshare_text"><?= $channel['text'] ?></div>
			
			</a>
		<?php endforeach ?>

	</div>

</div>
