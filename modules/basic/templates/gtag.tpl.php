<?php if(!empty($GLOBALS['config']['analytics']) && !empty($ids) && is_array($ids) && count($ids)): ?>
<?php // Consent is checked in the browser (gtag.js basic_tracking_allowed): this html may come from the page cache ?>
<div class="basic_gtag_container" data-delay="<?= $delay ?>" data-consent="<?= in_array('gdpr', $GLOBALS['config']['modules'] ?? [], true) ? 1 : 0 ?>">
	<?php foreach($ids as $id): ?>
		<div class="basic_gtag_item" data-id="<?= $id['gtag_id'] ?>" <?php if(!empty($id['linked'])): ?>data-domains="<?= $id['linked'] ?>"<?php endif ?>></div>
	<?php endforeach ?>
</div>
<?php endif ?>
