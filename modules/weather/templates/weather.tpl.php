<div class="weather_container">
	<div class="weather_content" <?php _ib($image, 1600) ?>>
	
		<div class="weather_time" data-time="<?= $time ?>">&nbsp;</div>
		<div class="weather_location"><?= $location ?></div>
		<div class="weather_weather"><?= round($weather['main']['temp'] - 273.15) . '&deg; ' . $weather['weather'][0]['description'] ?></div>
	
	</div>
</div>