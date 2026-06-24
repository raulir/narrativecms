function cms_access_denied_init($container){

	if (!$container || !$container.length){
		return
	}

	var dismiss = function(){
		$container.remove()
		$(document).off('keyup.cms_access_denied_dismiss')
	}

	$container.find('.cms_access_denied_close, .cms_access_denied_overlay').off('click.cms').on('click.cms', dismiss)

	$(document).off('keyup.cms_access_denied_dismiss').on('keyup.cms_access_denied_dismiss', function(e){

		if (e.which !== 27){
			return
		}

		if (!$container.closest('body').length){
			return
		}

		e.preventDefault()
		e.stopImmediatePropagation()
		dismiss()

	})

}

function cms_access_denied_boot(){

	var $container = $('.cms_access_denied_container').not('[data-cms_access_denied_init]').last()

	if (!$container.length){
		return
	}

	$container.attr('data-cms_access_denied_init', '1')
	cms_access_denied_init($container)

}

cms_access_denied_boot()