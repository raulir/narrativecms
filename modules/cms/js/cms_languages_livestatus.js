function cms_languages_livestatus_init($root){

	var $scope = $root ? $root.find('.cms_languages_livestatus_container') : $('.cms_languages_livestatus_container')

	$scope.not('.cms_languages_livestatus_ok').each(function(){

		var $container = $(this)

		$container.addClass('cms_languages_livestatus_ok')

		$container.find('.cms_languages_livestatus_input').on('change.cms', function(){

			var $this = $(this)
			var data = {
				'do': 'update_field',
				'item_id': $this.data('item_id'),
				'name': 'livestatus',
				'value': $this.val()
			}

			if ($this.data('base_id')){
				data.base_id = $this.data('base_id')
			}

			if ($this.data('ds')){
				data.ds = $this.data('ds')
			}

			get_ajax_panel('cms/cms_languages_livestatus', data, function(){
				cms_notification('Status updated', 2)
			})

		})

	})

}

$(document).ready(function(){
	cms_languages_livestatus_init()
})
