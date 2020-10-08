function cms_input_grid_init(){
	
	$('.cms_grid_new').on('click.cms', function(){
		
		var $this = $(this)
		
		var data = {
				'do': 'create_row',
				'base_id': $this.data('base_id'),
				'ds': $this.data('ds')
		}

		get_ajax_panel('cms/cms_input_grid', data, function(result){

//			$this.closest('.cms_input_container').html(result.result.html)
			console.log(result)
			
			cms_notification('Row added', 2)
			
			setTimeout(() => { location.reload() }, 500)

		})

	})

}

function cms_input_grid_resize(){
	
}

function cms_input_grid_scroll(){
	
}

$(document).ready(function() {

	$(window).on('resize.cms', cms_input_grid_resize)
	$(window).on('scroll.cms', cms_input_grid_scroll)
	
	cms_input_grid_init()
	cms_input_grid_resize()
	cms_input_grid_scroll()

})
