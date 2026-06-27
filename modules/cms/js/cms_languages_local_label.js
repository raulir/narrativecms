function cms_languages_local_label_init(){

	$(document).off('focus.cms.cms_languages_local_label', '.cms_languages_local_label_input')
	$(document).on('focus.cms.cms_languages_local_label', '.cms_languages_local_label_input', function(){
		$(this).data('old_value', $(this).val())
	})

	$(document).off('blur.cms.cms_languages_local_label', '.cms_languages_local_label_input')
	$(document).on('blur.cms.cms_languages_local_label', '.cms_languages_local_label_input', function(){

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

}

$(document).ready(function() {
	cms_languages_local_label_init()
})