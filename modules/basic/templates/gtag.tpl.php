<?php if(!empty($GLOBALS['config']['analytics']) && !empty($ids) && is_array($ids) && count($ids)): ?>
<script>

	window.dataLayer = window.dataLayer || [];
	function gtag(){dataLayer.push(arguments);}
	gtag('js', new Date());
	
	<?php foreach($ids as $id): ?>
	
		gtag('config', '<?= $id['gtag_id'] ?>');

	<?php endforeach ?>

	setTimeout(function(){
		
		injectScript('https://www.googletagmanager.com/gtag/js?id=<?= $id['gtag_id'] ?>')
			.then(

				() => {
					$('a[target=_blank]').on('click.cms',
						function(){
							gtag('event', 'click', {
							    'event_category': 'outbound',
							    'event_label': $(this).attr('href'),
							    'transport_type': 'beacon'
							})
							return true
						}
					)
					$('a[href^="mailto:"]').on('click.cms',
						function(){
							gtag('event', 'click', {
							    'event_category': 'mailto',
							    'event_label': $(this).attr('href'),
							    'transport_type': 'beacon'
							})
							return true
						}
					)
					$('a[href^="tel:"]').on('click.cms',
							function(){
								gtag('event', 'click', {
								    'event_category': 'tel',
								    'event_label': $(this).attr('href'),
								    'transport_type': 'beacon'
								})
								return true
							}
						)
				}

			)

	}, <?= !empty($delay) ? $delay : 0 ?>)

</script>
<?php endif ?>