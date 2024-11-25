function search_init(){

	$('.search_input').on('keyup.cms', function(){
		
		var term = $('.search_input').val()
		
		if (term !== $('.search_container').data('lastterm')){
		
			if (!$('.search_container').data('blocked')){
				
				$('.search_container').data('blocked', true)
				
				get_ajax_panel('search/searchajax', {
					
					'term' : $('.search_input').val()
					
				}, function(data){
					
					$('.search_container').data('lastterm', term)
					$('.search_container').data('blocked', false)
					$('.search_results').html(data.result.html)
// console.log(data.result.html)					
					$('.search_results').addClass('search_results_active')
				
				})

			} else {
				
				setTimeout(() => $('.search_input').keyup(), 100)
				
			}

		}
		
		if (term == ''){
			$('.search_results').removeClass('search_results_active').html('')
		}
		
	});
	
}

function search_resize(){

}

function search_scroll(){
	
}

$(document).ready(function() {

	$(window).on('resize.cms', function(){
		search_resize();
	});
	
	$(window).on('scroll.cms', function(){
		search_scroll();
	});
	
	search_init();

	search_resize();
	
	search_scroll();

});
