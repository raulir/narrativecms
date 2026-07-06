function cms_popup_close($popup){

	if (!$popup || !$popup.length){
		return
	}

	if (typeof _panels_popup_remove_fragment === 'function'){
		_panels_popup_remove_fragment($popup)
		return
	}

	$popup.css({'opacity': '0', 'pointer-events': 'none'})

	setTimeout(function(){
		$popup.remove()
	}, 300)

}

function cms_popup_bind_cancel($popup){

	$popup.find('.cms_popup_cancel').off('click.cms_popup').on('click.cms_popup', function(e){
		e.preventDefault()
		e.stopPropagation()
		cms_popup_close($(this).closest('.cms_popup_container'))
	})

}

function cms_popup_open_ajax(name, after){

	get_ajax_panel('cms/cms_popup_shell', {
		'name': name,
		'_no_css': '1',
	}, function(data){

		var $appended = $($.parseHTML(data.result._html || '', document, true))
		$('body').append($appended)

		var $popup = $appended.filter('.cms_popup_container').add($appended.find('.cms_popup_container')).first()

		if (!$popup.length){
			return
		}

		if (typeof _panels_popup_store_fragment === 'function'){
			_panels_popup_store_fragment($popup, $appended)
		}

		$popup.css({'display': 'table'})

		setTimeout(function(){
			$popup.css({'opacity': '1'})
		}, 50)

		cms_popup_bind_cancel($popup)

		if (typeof after === 'function'){
			after($popup)
		}

	})

}

function cms_popup_run(name, after){

	var $container = $('.cms_popup_' + name).not('[data-cms_popup_ajax]').first()

	if ($container.length){

		if (!$container.parent().is('body')){
			$(document.body).append($container.detach())
		}

		$container.css({'display': 'table'})

		setTimeout(function(){
			$container.css({'opacity': '1'})
		}, 50)

		cms_popup_bind_cancel($container)

		if (typeof after === 'function'){
			after($container)
		}

		return

	}

	cms_popup_open_ajax(name, after)

}

function cms_popup_init($root){

	var $scope = $root ? $root.find('.cms_popup_container') : $('.cms_popup_container');

	$scope.not('.cms_popup_ok').each(function(){
		$(this).addClass('cms_popup_ok');
	});

}

function cms_popup_resize(){

}

$(document).ready(function() {

	$(window).on('resize.cms', function(){
		cms_popup_resize()
	})

	cms_popup_init()
	cms_popup_resize()

})