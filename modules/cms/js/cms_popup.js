function cms_popup_init(){

	// remove popup
	$('.cms_popup_cancel').off('click.cms').on('click.cms', function(){
		
		var $container = $(this).closest('.cms_popup_container');
		$container.css({'opacity':''});
		
		setTimeout(function(){
			
			$container.remove(); // css({'display':'none'});
			
		}, 300);

	});
	
}

function cms_popup_resize(){

}

function cms_popup_run(name, after){
	
	var $container = $('.cms_popup_' + name);

	// move element to top level
	$(document.body).append( $container.detach() );
	
	$container.css({'display':'table'});
	setTimeout(function(){
		$container.css({'opacity':'1'});
	}, 50);
	
	after();
	
}

$(document).ready(function() {
	
	$(window).on('resize.cms', function(){
		cms_popup_resize();
	});
	
	cms_popup_init();

	cms_popup_resize();
	
});
