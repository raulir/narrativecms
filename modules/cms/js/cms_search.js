
function cms_search_init($root){

	var $scope = $root ? $root.find('.cms_search_top') : $('.cms_search_top');

	$scope.not('.cms_search_ok').each(function(){

		var $container = $(this);

		$container.addClass('cms_search_ok');

		$('.cms_search_term', $container).on('keyup.cms', function(){

			var term = $(this).val();

			if (term.length >= 3){

				get_ajax('cms/cms_search_operations', {
					'do':'cms_search',
					'term': term,
					'success': function(data){

						var payload = (data && data.result && data.result.result) ? data.result.result : null
						$('.cms_search_result_pages').html('')
						$('.cms_search_result_panels').html('')
						if (!payload){
							return
						}

						var pages = payload.pages || {}
						var page_panels = payload.page_panels || {}

						// real defined pages
						if (pages.real && pages.real.length){
							$('.cms_search_result_pages').append('<div class="cms_column_header">Static pages</div>');
							$.each(pages.real, function(key, value){
								$('.cms_search_result_pages').append('<div class="cms_search_item cms_search_' + value.show + '"><div class="cms_search_title">' + value.title + '</div><a class="cms_search_edit" href="' + _cms_base + 'admin/' + value.edit_url + '">edit</a>' +
										(value.slug || value.page_id == 1 ? '<a target="_blank" class="cms_search_view" href="' + _cms_base + (value.slug != '' ? (value.slug + '/') : '') + '">view</a>' : '') + '</div>');
							});
						}

						// list item pages
						if (pages.lists && pages.lists.length){
							$('.cms_search_result_pages').append('<div class="cms_column_header">List pages and partials</div>');
							$.each(pages.lists, function(key, value){
								$('.cms_search_result_pages').append('<div class="cms_search_item cms_search_' + value.show + '"><div class="cms_search_title">' + value.title + '</div><a class="cms_search_edit" href="' + _cms_base + 'admin/' + value.edit_url + '">edit</a>' +
										(value.slug || value.page_id == 1 ? '<a target="_blank" class="cms_search_view" href="' + _cms_base + (value.slug != '' ? (value.slug + '/') : '') + '">view</a>' : '') + '</div>');
							});
						}

						// page panels
						if (page_panels.pages && page_panels.pages.length){
							$('.cms_search_result_panels').append('<div class="cms_column_header">Static page panels</div>');
							$.each(page_panels.pages, function(key, value){
								$('.cms_search_result_panels').append('<div class="cms_search_item cms_search_' + value.show + '"><div class="cms_search_title">' + value.title + '</div>' +
										'<a class="cms_search_edit" href="' + _cms_base + 'admin/' + value.edit_url + '">edit</a></div>');
							});
						}

						// list panels
						if (page_panels.lists && page_panels.lists.length){
							$('.cms_search_result_panels').append('<div class="cms_column_header">List panels</div>');
							$.each(page_panels.lists, function(key, value){
								$('.cms_search_result_panels').append('<div class="cms_search_item cms_search_' + value.show + '"><div class="cms_search_title">' + value.title + '</div>' +
										'<a class="cms_search_edit" href="' + _cms_base + 'admin/' + value.edit_url + '">edit</a></div>');
							});
						}

						// settings panels
						if (page_panels.settings && page_panels.settings.length){
							$('.cms_search_result_panels').append('<div class="cms_column_header">Other panels</div>');
							$.each(page_panels.settings, function(key, value){
								$('.cms_search_result_panels').append('<div class="cms_search_item cms_search_' + value.show + '"><div class="cms_search_title">' + value.title + '</div>' +
										( value.edit_url ? '<a class="cms_search_edit" href="' + _cms_base + 'admin/' + value.edit_url + '">edit</a>' : '') +
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

	});

}

$(document).ready(function() {

	cms_search_init();

});