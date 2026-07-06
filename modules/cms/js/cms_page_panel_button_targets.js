function cms_page_panel_button_targets_bind_save($popup, cms_page_panel_id){

	$popup.find('.cms_page_panel_targets_close').off('click.cms').on('click.cms', function(e){
		e.preventDefault()

		var data = {}
		$popup.find('.cms_page_panel_targets_select').each(function(){

			var $select = $(this)
			data[$select.data('group')] = $select.val()

		})

		get_ajax('cms/cms_page_panel_targets', {
			'targets_id': cms_page_panel_id,
			'do': 'cms_page_panel_targets',
			'data': data,
			'success': function(response){
				if (response.result && response.result._title){
					cms_page_panel_apply_breadcrumb_title(response.result._title)
				}
				cms_popup_close($popup)
				cms_notification('Visitor target group visibility saved', 3)
			}
		})

	})

}

function cms_page_panel_button_targets_init($root){

	var $scope = $root ? $root.find('.cms_page_panel_button_targets') : $('.cms_page_panel_button_targets');

	$scope.not('.cms_page_panel_button_targets_ok').each(function(){

		var $button = $(this);

		$button.addClass('cms_page_panel_button_targets_ok');

		$button.on('click.cms', function(e){

		e.preventDefault()
		e.stopPropagation()

		var cms_page_panel_id = $(this).data('cms_page_panel_id')

		cms_popup_open_ajax('targets', function($popup){

			$popup.find('.cms_popup_content').html('loading ...')

			get_ajax_panel('cms/cms_page_panel_targets', {
				'targets_id': cms_page_panel_id,
				'do': 'cms_page_panel_targets'
			}, function(data){

				$popup.find('.cms_popup_content').html(data.result._html)
				cms_popup_bind_cancel($popup)
				cms_page_panel_button_targets_bind_save($popup, cms_page_panel_id)

			})

		})

		})

	});

}

function cms_page_panel_button_targets_resize(){

}

function cms_page_panel_button_targets_scroll(){

}

$(document).ready(function() {

	$(window).on('resize.cms', function(){
		cms_page_panel_button_targets_resize()
	})

	$(window).on('scroll.cms', function(){
		cms_page_panel_button_targets_scroll()
	})

	cms_page_panel_button_targets_init()
	cms_page_panel_button_targets_resize()
	cms_page_panel_button_targets_scroll()

})