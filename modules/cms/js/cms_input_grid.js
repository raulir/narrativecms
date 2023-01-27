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
	
	$('.cms_grid_delete').on('click.cms', function(){

		var $this = $(this)

		get_ajax_panel('cms/cms_popup_yes_no', {'text':'Are you sure?'}, function(data){
			panels_display_popup(data.result._html, {
				'yes': function(){
					get_ajax('cms/cms_input_grid', {
						'do':'delete_row',
						'ds': $this.data('ds'),
						'id': $this.data('line_id'),
						'base_name': $this.data('base_name'),
						'success': function(data){
							if (data.result.web){
								$this.closest('.cms_grid_row').remove()
							}
						}
					})
				}
			})
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
