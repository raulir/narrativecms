<?php if(!empty($GLOBALS['config']['analytics']) && !empty($ids) && is_array($ids) && count($ids)): ?>
	<?php foreach($ids as $id): ?>
		<div class="analytics_id" data-analytics_id="<?= $id['analytics_id'] ?>" data-delay="<?= !empty($delay) ? $delay : 0 ?>"></div>
	<?php endforeach ?>
<?php endif ?>