function get_ajax_positions(url, params, action_on_success) {

	params._url = url
	params._ajax = 1

	$.ajax({
		type: 'POST',
		url: params._url,
		data: params,
		dataType: 'json',
		success: function(returned_data) {

			if (returned_data.redirect) {
				get_ajax_positions(returned_data.redirect, params, action_on_success)
				return
			}

			if (returned_data.error && returned_data.error.message === 'access_denied') {
				cms_access_denied_popup(returned_data.error)
				return
			}

			returned_data._final_url = params._url
			action_on_success(returned_data)

		},
		error: function() {
			$('.cms_position_main').css({'opacity':''})
		}
	})

}

function cms_position_link_init($root){

	var $scope = $root ? $root.find('a[data-_pl="1"]') : $('a[data-_pl="1"]');

	$scope.not('.cms_position_link_ok').each(function(){

		var $link = $(this);

		$link.addClass('cms_position_link_ok');
			
			$link.on('click.cms', function(){
			
				var data = {}

				var $this = $(this)
				
				// default before
				if (!$._data($this.get(0), 'events')['before']){
					$this.on('before', function(){
					
						return new Promise(resolve => {
							$('.cms_position_main').css({'opacity':'0.5'})
							setTimeout(() => resolve($(this)), 300)
						})
					
					})
				}
				
				// default after
				if (!$._data($this.get(0), 'events')['after']){
					$this.on('after', function(){ 				

						return new Promise(resolve => {
							setTimeout(() => $('.cms_position_main').css({'opacity':''}), 300)
							resolve($(this))
						})
						
					})
				}
				
				var download_page = new Promise( resolve => {
						
					let positions = {}
					$('.cms_position').each(function(){
						var $this = $(this)
						positions[$this.data('position')] = $this.data('cms_page_id')
					})

					get_ajax_positions($this.attr('href'), {'cms_positions':positions}, function(result){
						resolve(result)
					})

				})
				
				var update_page = before_result => new Promise(resolve => {

					let $backup_this = before_result[0].clone(true, true)
					var result = before_result[1]

					var apply_positions = function() {

						var needs_cache_hydrate = false

						$.each(result.positions, function(i, posdata){
							$('.cms_position_' + i).html(posdata._html).data('cms_page_id', posdata.cms_page_id)
							if (posdata.has_deferred) {
								needs_cache_hydrate = true
							}
						})

						if (needs_cache_hydrate && typeof cms_cache_hydrate === 'function') {
							cms_cache_hydrate()
						}

						change_url(result._final_url || $this.attr('href'))
						document.title = result.title

						if (typeof gtag != 'undefined'){

							let final_url = result._final_url || $this.attr('href')
							let $a = $('<a href="' + final_url + '"></a>')
							let page = $a[0].pathname + $a[0].hash

							gtag('event', 'page_view', {
								page_title: result.title,
								page_path: page
							})

						}

						setTimeout(() => {
							resolve($backup_this)
						}, 100)

					}

					if (typeof cms_apply_panel_css === 'function') {
						cms_apply_panel_css(result, apply_positions)
					} else {
						apply_positions()
					}

				})
				
				Promise
					.all([$this.triggerHandler('before'), download_page])
					.then(update_page)
					.then($bu => $bu.triggerHandler('after'))
					.then($bu => $bu.remove())
					.then(() => {
						if (typeof cms_position_link_after != 'undefined'){
							cms_position_link_after.forEach((element) => element())
						}
					})

				return false;
				
			});

	});

}

/*
// save gmap
if ($('.akdn_map').length && !$('.akdn_map_backup').length){
	$('.akdn_map').addClass('akdn_map_backup').detach().appendTo('body');
}
*/

function cms_position_link_resize(){

}

$(document).ready(function() {
	
	$(window).on('resize.cms', function(){
		cms_position_link_resize();
	});
	
	cms_position_link_init();

	cms_position_link_resize();
	
});
