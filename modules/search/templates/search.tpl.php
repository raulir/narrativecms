<div class="search_container"
		data-debounce_s="<?= $debounce_s ?>"
		data-min_chars="<?= $min_chars ?>">

	<div class="search_header" style="background-color: <?= $header_colour ?>;">
		<div class="search_header_inner">
			<input class="search_input" type="text"
					placeholder="<?= $search_placeholder ?>"
					value=""
					autocomplete="off"
					aria-label="<?= $search ?>" />
		</div>
	</div>

	<div class="search_results"></div>

</div>
