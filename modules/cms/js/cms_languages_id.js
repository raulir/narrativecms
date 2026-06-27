function cms_languages_id_init(){

	$(document).off('focus.cms.cms_languages_id', '.cms_languages_id_input')
	$(document).on('focus.cms.cms_languages_id', '.cms_languages_id_input', function(){
		$(this).data('old_value', $(this).val())
	})

	$(document).off('blur.cms.cms_languages_id', '.cms_languages_id_input')
	$(document).on('blur.cms.cms_languages_id', '.cms_languages_id_input', function(){

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

}

$(document).ready(function() {
	cms_languages_id_init()
})