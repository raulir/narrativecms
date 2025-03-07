function generate_init(){
	
	$('.generate_button').on('click.cms', function(event){
		$('.cms_page_panel_container').css({'opacity':'0.5'})
		get_ajax_panel('download/generate', {
			'do': 'generate',
			'download_id': $(this).data('download_id')
		}, function(data){
			// location.reload()
			console.log('ready!')
		})
	})

}

function generate_resize(){
		
}

function generate_scroll(){
	
}

$(document).ready(function() {
	
	$(window).on('resize.cms', generate_resize)
	$(window).on('scroll.cms', generate_scroll)
	
	generate_init()
	generate_resize()
	generate_scroll()
	
})
