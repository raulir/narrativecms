function cms_input_list_link_init($root){

	var $scope = $root ? $root.find('.cms_input_list_link_container') : $('.cms_input_list_link_container');

	$scope.not('.cms_input_list_link_ok').each(function(){

		var $container = $(this);

		$container.addClass('cms_input_list_link_ok');

		get_ajax('cms/cms_input_list_link', {
			'do':'get',
			'item_id': $('.cms_page_panel_id').val(),
			'success': function(data){
				var target = location.protocol + '//' + location.hostname + data.result.link
				$('.cms_input_list_link_link', $container).html(target).attr('href',target)
			}
		})

	});

}

function cms_input_list_link_resize(){

}

$(document).ready(function() {

	$(window).on('resize.cms', function(){
		cms_input_list_link_resize()
	})

	cms_input_list_link_init()

	cms_input_list_link_resize()

});