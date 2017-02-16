function cms_update_init(){
	
	$('.cms_update_button').on('click.cms', function(){
		
		// do update
		$('.cms_update_result').html('Getting list of files ...');

		get_ajax('cms_update', {
			'do': 'cms_update_list',
			'success': function(data){
				
				// print out list of files
				$('.cms_update_result').html('');
				$.each(data.result.result, function(index, value){
					$('.cms_update_result').html($('.cms_update_result').html() + '<div class="cms_update_result_file_' + value.fn_hash + '">' + 
							'<span class="cms_update_tick">(<span style="font-weight: bold; color: #d0d0d0; ">' + value.letter + '</span>)</span>&nbsp;' + 
							value.filename + '</div>');
					data.result.result[index].updated = 0;
				});

				var number_updating = 0;

				// update
				var interval = setInterval(function(){
					
					$.each(data.result.result, function(key, value){
						
						if (value.updated == 0 && number_updating < 7){
							data.result.result[key].updated = 1;
							number_updating = number_updating + 1;
							$('.cms_update_result_file_' + value.fn_hash).children('.cms_update_tick').html('(<span style="color: orange; font-weight: bold; ">' + value.letter + '</span>)');
							
							// TODO: check if in cache already?
							
							get_ajax('cms_update', {
								'do': 'cms_update_file',
								'filename': value.filename,
								'letter': value.letter,
								'success': function(data){
									// console.log(data);
									$('.cms_update_result_file_' + data.result.result.fn_hash).children('.cms_update_tick')
											.html('(<span style="color: green; font-weight: bold; ">' + data.result.result.letter + '</span>)');
									number_updating = number_updating - 1;
								}
							});
						}
						
					});
					
					if (number_updating == 0){
						
						clearInterval(interval);
						
						// copy over
						get_ajax('cms_update', {
							'do': 'cms_update_copy',
							'success': function(data){
								cms_notification('CMS updated', 5);
							}
						});
						
					}

				}, 300);

			}
		});
		
	});

}

function cms_update_resize(){
		
}

$(document).ready(function() {
	
	$(window).on('resize.cms', function(){
		cms_update_resize();
	});

	cms_update_init();

	cms_update_resize();
	
});
