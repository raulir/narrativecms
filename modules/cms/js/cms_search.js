
function cms_search_init(){
	
	$('.cms_search_term').on('keyup.cms', function(){
		
		var term = $(this).val();
		
		if (term.length >= 3){
			
			get_ajax('cms_search_operations', {
				'do':'cms_search',
				'term': term,
				'success': function(data){
					
					$('.cms_search_result_pages').html('<div>PAGES</div>');
					$.each(data.result.result.pages, function(key, value){
						$('.cms_search_result_pages').append('<div>' + value.title + ' | ' + value.page_id + ' | ' + value.score + ' | <a href="' + config_url + 'admin/' + value.edit_url + '">edit</a>' + 
								(value.slug || value.page_id == 1 ? ' | <a target="_blank" href="' + config_url + (value.slug != '' ? (value.slug + '/') : '') + '">view</a>' : '') + '</div>');
					});
					
					$('.cms_search_result_panels').html('<div>PANELS</div>');
					$.each(data.result.result.page_panels, function(key, value){
						$('.cms_search_result_panels').append('<div>' + value.title + ' | ' + value.cms_page_panel_id + ' | ' + value.score + ' | <a href="' + config_url + 'admin/' + value.edit_url + '">edit</a></div>');
					});
					
				}
			});
			
		} else {
			$('.cms_search_result_pages').html('');
			$('.cms_search_result_panels').html('');
		}
		
		
	});
	
}

$(document).ready(function() {
	
	cms_search_init();
	
});
