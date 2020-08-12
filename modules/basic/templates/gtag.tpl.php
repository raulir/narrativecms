<?php if(!empty($GLOBALS['config']['analytics']) && !empty($ids) && is_array($ids) && count($ids)): ?>
<div class="basic_gtag_container" data-delay="<?= $delay ?>">
	<?php foreach($ids as $id): ?>
		<div class="basic_gtag_item" data-id="<?= $id['gtag_id'] ?>" <?php if(!empty($id['linked'])): ?>data-domains="<?= $id['linked'] ?>"<?php endif ?>></div>
	<?php endforeach ?>
</div>
<?php endif ?>