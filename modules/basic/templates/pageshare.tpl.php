<div class="basic_pageshare_container">

	<div class="basic_pageshare_content">

		<?php foreach($channels as $icon): ?>
			<a class="basic_pageshare_icon" <?php _ib($icon['image'], 40) ?>
					data-content="<?= $content ?>" data-type="<?= $icon['type'] ?>" data-body="<?= $body ?>" 
					data-hashtags="<?= trim((!empty($hashtags) ? str_replace(array(' ', '#', ' #'), ',', $hashtags) : ''), '# ') ?>"
					<?php if(!empty($url)): ?>data-url="<?= $url ?>"<?php endif ?>>
					
				<div class="basic_pageshare_text"><?= $icon['text'] ?></div>
			
			</a>
		<?php endforeach ?>

	</div>

</div>
