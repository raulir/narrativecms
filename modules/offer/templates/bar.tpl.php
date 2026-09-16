<div class="offerbar_container offerbar_endings_<?= $endings ?> offerbar_count_<?= count($offers) ?>"
		style="--offerbar_bg: <?= $background ?>; --offerbar_text: <?= $text_colour ?>; --offerbar_bold: <?= $bold_colour ?>; ">
	<div class="offerbar_content">
		<div class="offerbar_arrow offerbar_arrow_left" <?php _ib($arrow_left, 30) ?>></div>
		<div class="offerbar_emote" <?php _ib($items[0] ?? '', 50) ?>></div>
		<?php $emote = 1 ?>
		<?php foreach($offers as $offer): ?>
			<?php $emote = $emote >= count($items) ? 0 : $emote ?>
			<a class="offerbar_offer" <?php _lh($offer['link']) ?>><?= $offer['heading'] ?></a>
			<div class="offerbar_emote" <?php _ib($items[$emote++] ?? '', 50) ?>></div>
		<?php endforeach ?>
		<div class="offerbar_arrow offerbar_arrow_right" <?php _ib($arrow_right, 30) ?>></div>
	</div>
</div>
