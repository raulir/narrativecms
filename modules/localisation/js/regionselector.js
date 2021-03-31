function regionselector_init(){

	$('.localisation_regionselector_region').on('click.cms', function(){
	
		get_ajax('localisation/regionselector', {
			'do':'region_set',
			'region_id': $(this).data('region_id')
		}).then(() => location.reload())
		
	})
	
}

function regionselector_resize(){

}

function regionselector_scroll(){

}

$(document).ready(function() {

	$(window).on('resize.cms', regionselector_resize)
	$(window).on('scroll.cms', regionselector_scroll)
	
	regionselector_init()
	regionselector_resize()
	regionselector_scroll()

});
