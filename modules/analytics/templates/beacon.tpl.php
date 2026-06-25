<?php if (!empty($show)): ?>
<div class="analytics_beacon_container analytics_beacon_content"
	data-delay="<?= (int)($delay ?? 0) ?>"
	data-collect_engagement="<?= !empty($collect_engagement) ? '1' : '0' ?>">
</div>
<?php endif ?>