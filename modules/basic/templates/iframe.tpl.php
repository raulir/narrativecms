<div class="basic_iframe_container">
	<div class="basic_iframe_content">

		<iframe class="basic_iframe_iframe" 
				<?php if(!empty($id)): ?> id="<?= $id ?>" <?php endif ?>
				src="<?= $address ?>" data-delay="<?= $delay ?? 300 ?>"
				width="100%" style="height:<?= $initial_height ?? 'calc(100vh - 5.0rem);' ?>" 
				frameborder="0" allow="autoplay; fullscreen" allowfullscreen></iframe>

	</div>
</div>
