function cms_languages_id_init($root){

	var $scope = $root ? $root.find('.cms_languages_id_container') : $('.cms_languages_id_container');

	$scope.not('.cms_languages_id_ok').each(function(){

		var $container = $(this);

		$container.addClass('cms_languages_id_ok');

		$container.find('.cms_languages_id_input').on('focus.cms', function(){
			$(this).data('old_value', $(this).val())
		})

		$container.find('.cms_languages_id_input').on('blur.cms', function(){

			var $this = $(this)

			if ($this.val() == $this.data('old_value')){
				return
			}

			var data = {
					'do': 'update_field',
					'item_id': $this.data('item_id'),
					'name': 'language_id',
					'value': $this.val()
			}

			if ($this.data('base_id')){
				data.base_id = $this.data('base_id')
			}

			if ($this.data('ds')){
				data.ds = $this.data('ds')
			}

			get_ajax_panel('cms/cms_languages_id', data, function(result){

				$this.closest('.cms_grid_field_inner').html(result.result._html)
				cms_notification('Language ID updated', 2)

			})

		})

	})

}

$(document).ready(function() {
	cms_languages_id_init()
})