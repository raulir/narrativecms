
function cms_search_init(){
	
	$('.cms_search_term').on('keyup.cms', function(){
		
		var term = $(this).val();
		
		if (term.length >= 3){
			
			get_ajax('cms_search_operations', {
				'do':'cms_search',
				'term': term,
				'success': function(data){
					
					$('.cms_search_result_pages').html('');
					
					// real defined pages
					if (data.result.result.pages.real){
						$('.cms_search_result_pages').append('<div class="cms_column_header">Static pages</div>');
						$.each(data.result.result.pages.real, function(key, value){
							$('.cms_search_result_pages').append('<div class="cms_search_item cms_search_' + value.show + '"><div class="cms_search_title">' + value.title + '</div><a class="cms_search_edit" href="' + config_url + 'admin/' + value.edit_url + '">edit</a>' + 
									(value.slug || value.page_id == 1 ? '<a target="_blank" class="cms_search_view" href="' + config_url + (value.slug != '' ? (value.slug + '/') : '') + '">view</a>' : '') + '</div>');
						});
					}
					
					// list item pages
					if (data.result.result.pages.lists){
						$('.cms_search_result_pages').append('<div class="cms_column_header">List pages and partials</div>');
						$.each(data.result.result.pages.lists, function(key, value){
							$('.cms_search_result_pages').append('<div class="cms_search_item cms_search_' + value.show + '"><div class="cms_search_title">' + value.title + '</div><a class="cms_search_edit" href="' + config_url + 'admin/' + value.edit_url + '">edit</a>' + 
									(value.slug || value.page_id == 1 ? '<a target="_blank" class="cms_search_view" href="' + config_url + (value.slug != '' ? (value.slug + '/') : '') + '">view</a>' : '') + '</div>');
						});
					}
					
					$('.cms_search_result_panels').html('');
					
					// page panels
					if (data.result.result.page_panels.pages){
						$('.cms_search_result_panels').append('<div class="cms_column_header">Static page panels</div>');
						$.each(data.result.result.page_panels.pages, function(key, value){
							$('.cms_search_result_panels').append('<div class="cms_search_item cms_search_' + value.show + '"><div class="cms_search_title">' + value.title + '</div>' +
									'<a class="cms_search_edit" href="' + config_url + 'admin/' + value.edit_url + '">edit</a></div>');
						});
					}
					
					// list panels
					if (data.result.result.page_panels.lists){
						$('.cms_search_result_panels').append('<div class="cms_column_header">List panels</div>');
						$.each(data.result.result.page_panels.lists, function(key, value){
							$('.cms_search_result_panels').append('<div class="cms_search_item cms_search_' + value.show + '"><div class="cms_search_title">' + value.title + '</div>' +
									'<a class="cms_search_edit" href="' + config_url + 'admin/' + value.edit_url + '">edit</a></div>');
						});
					}
					
					// settings panels
					if (data.result.result.page_panels.settings){
						$('.cms_search_result_panels').append('<div class="cms_column_header">Other panels</div>');
						$.each(data.result.result.page_panels.settings, function(key, value){
							$('.cms_search_result_panels').append('<div class="cms_search_item cms_search_' + value.show + '"><div class="cms_search_title">' + value.title + '</div>' +
									( value.edit_url ? '<a class="cms_search_edit" href="' + config_url + 'admin/' + value.edit_url + '">edit</a>' : '') + 
									'</div>');
						});
					}
					
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
