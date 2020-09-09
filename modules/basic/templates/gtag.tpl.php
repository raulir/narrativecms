<?php if(!empty($GLOBALS['config']['analytics']) && !empty($ids) && is_array($ids) && count($ids) && 
				(!in_array($GLOBALS['config']['modules']) || (!empty($_COOKIE['gdpr']) && empty($_COOKIE['gdpr_notrack'])))): ?>): ?>
<div class="basic_gtag_container" data-delay="<?= $delay ?>">
	<?php foreach($ids as $id): ?>
		<div class="basic_gtag_item" data-id="<?= $id['gtag_id'] ?>" <?php if(!empty($id['linked'])): ?>data-domains="<?= $id['linked'] ?>"<?php endif ?>></div>
	<?php endforeach ?>
</div>
<?php endif ?>