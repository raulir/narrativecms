<div class="weather_container<?= empty($ok) ? ' weather_has_error' : '' ?>"
		data-selected="<?= htmlspecialchars($selected_date, ENT_QUOTES, 'UTF-8') ?>"
		data-today="<?= htmlspecialchars($today_date, ENT_QUOTES, 'UTF-8') ?>"
		data-label_later="<?= htmlspecialchars($label_later, ENT_QUOTES, 'UTF-8') ?>"
		data-refresh_seconds="180">

	<div class="weather_content">

<?php if (!empty($ok) && !empty($days)): ?>

		<div class="weather_days" role="tablist">
<?php foreach ($days as $i => $day): ?>
			<button type="button"
					class="weather_day<?= ($day['date'] === $selected_date) ? ' weather_day_on' : '' ?>"
					data-date="<?= htmlspecialchars($day['date'], ENT_QUOTES, 'UTF-8') ?>"
					role="tab"
					aria-selected="<?= ($day['date'] === $selected_date) ? 'true' : 'false' ?>">
				<span class="weather_day_label"><?= htmlspecialchars($day['label_chip'], ENT_QUOTES, 'UTF-8') ?></span>
				<span class="weather_day_icon weather_sky" aria-hidden="true"><?php
					// Same stack HTML as weather_model::sky_html / weather_sky_html (JS)
					if (!empty($day['sky_html'])){
						echo $day['sky_html'];
					} else {
						$base = !empty($day['sky_glyph']) ? $day['sky_glyph'] : '☁️';
						$slug = !empty($day['sky_stack'])
							? preg_replace('/[^a-z0-9_]/', '', (string)$day['sky_stack'])
							: 'unknown';
						$stack_cls = 'weather_sky_stack weather_sky_stack_'.$slug;
						if (!empty($day['sky_overlay']) && !empty($day['sky_overlay_glyph'])){
							echo '<span class="'.$stack_cls.' weather_sky_stack_has_overlay">'
								.'<span class="weather_sky_base">'.$base.'</span>'
								.'<span class="weather_sky_overlay">'.$day['sky_overlay_glyph'].'</span>'
								.'</span>';
						} else {
							echo '<span class="'.$stack_cls.'"><span class="weather_sky_base">'.$base.'</span></span>';
						}
					}
				?></span>
				<span class="weather_day_temps">
					<span class="weather_day_max"><?= $day['temp_max'] !== null ? (int)$day['temp_max'].'°' : '–' ?></span>
					<span class="weather_day_min"><?= $day['temp_min'] !== null ? (int)$day['temp_min'].'°' : '–' ?></span>
				</span>
			</button>
<?php endforeach ?>
			<button type="button"
					class="weather_day weather_day_later"
					data-date="later"
					role="tab"
					aria-selected="false">
				<span class="weather_day_label"><?= htmlspecialchars($label_later, ENT_QUOTES, 'UTF-8') ?></span>
				<span class="weather_day_icon weather_sky" aria-hidden="true">···</span>
				<span class="weather_day_temps weather_day_temps_later">
<?php if (!empty($later_days)): ?>
					<span class="weather_day_max"><?= count($later_days) ?>d</span>
					<span class="weather_day_min">more</span>
<?php else: ?>
					<span class="weather_day_max">–</span>
					<span class="weather_day_min"></span>
<?php endif ?>
				</span>
			</button>
		</div>

		<div class="weather_detail">
			<div class="weather_detail_day">
				<div class="weather_temp_band"></div>
				<div class="weather_hour_times"></div>
				<div class="weather_hour_winds"></div>
			</div>
			<div class="weather_detail_later" hidden>
				<canvas class="weather_later_canvas" width="1200" height="280"></canvas>
				<div class="weather_later_hours">
					<!-- filled by JS: 6h slots + daily separators -->
				</div>
			</div>
		</div>

		<div class="weather_attr"><?= htmlspecialchars($attribution, ENT_QUOTES, 'UTF-8') ?></div>

		<script type="application/json" class="weather_days_data"><?= $days_json ?></script>
		<script type="application/json" class="weather_later_data"><?= $later_days_json ?></script>
		<script type="application/json" class="weather_hours_data"><?= $hours_json ?></script>

<?php elseif (!empty($error)): ?>
		<div class="weather_message weather_message_error"><?= htmlspecialchars($label_error, ENT_QUOTES, 'UTF-8') ?></div>
		<div class="weather_message_detail"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
<?php else: ?>
		<div class="weather_message"><?= htmlspecialchars($label_empty, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif ?>

	</div>
</div>
