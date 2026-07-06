function cms_languages_local_label_init($root){

	var $scope = $root ? $root.find('.cms_languages_local_label_container') : $('.cms_languages_local_label_container');

	$scope.not('.cms_languages_local_label_ok').each(function(){

		var $container = $(this);

		$container.addClass('cms_languages_local_label_ok');

		$container.find('.cms_languages_local_label_input').on('focus.cms', function(){
			$(this).data('old_value', $(this).val())
		})

		$container.find('.cms_languages_local_label_input').on('blur.cms', function(){

			var $this = $(this)

			if ($this.val() == $this.data('old_value')){
				return
			}

			var data = {
					'do': 'update_field',
					'item_id': $this.data('item_id'),
					'name': 'local_label',
					'value': $this.val()
			}

			if ($this.data('base_id')){
				data.base_id = $this.data('base_id')
			}

			if ($this.data('ds')){
				data.ds = $this.data('ds')
			}

			if ($('.cms_language_select_current').length){
				data.cms_language = $('.cms_language_select_current').data('language')
			}

			get_ajax_panel('cms/cms_languages_local_label', data, function(result){

				$this.closest('.cms_grid_field_inner').html(result.result._html)
				cms_notification('Name updated', 2)

			})

		})

	})

}

$(document).ready(function() {
	cms_languages_local_label_init()
})