function basic_popup_init(){

	// remove popup
	$('.basic_popup_cancel').off('click.cms').on('click.cms', function(){
		
		var $container = $(this).closest('.basic_popup_container');
		$container.css({'opacity':''});
		
		setTimeout(function(){
			
			$container.css({'display':'none'});
			
			if (typeof cms_scroll_unlock != 'undefined'){
				cms_scroll_unlock()
			}
			
			if ($container.closest('.basic_popup_wrapper').length){
				$container.closest('.basic_popup_wrapper').remove()
			}
			
		}, 300);

	});
	
	basic_popup_run()

}

function basic_popup_resize(){

}

function basic_popup_run(popup_id, wrapper_class = false){
	
	var $container = $('.basic_popup_container')
	
	if ($container.length){
		
		$container.css({'display':'block'});
		setTimeout(function(){
			$container.css({'opacity':'1'});
		}, 50)
		
		if (typeof cms_scroll_lock != 'undefined'){
			cms_scroll_lock()
		}

		return
		
	}
	
	return new Promise ((resolve, reject) => {
		
		// load popup panel
		get_ajax_panel('basic/popup', {'popup_id': popup_id}).then((data) => {
			
			if (wrapper_class){
				$(document.body).append('<span class="basic_popup_wrapper ' + wrapper_class + '">' + data.result._html + '</div>')
			} else {
				$(document.body).append(data.result._html)
			}
			
			var $container = $('.basic_popup_' + popup_id);
	
			$container.css({'display':'block'});
			setTimeout(function(){
				$container.css({'opacity':'1'});
			}, 50)
			
			resolve()
	
		})
	})

}

$(document).ready(function() {
	
	$(window).on('resize.cms', function(){
		basic_popup_resize();
	});
	
	basic_popup_init();

	basic_popup_resize();
	
});
