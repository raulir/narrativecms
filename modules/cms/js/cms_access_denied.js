function cms_access_denied_init($root){

	var $containers = $root ? $root.find('.cms_access_denied_container') : $('.cms_access_denied_container');

	$containers.not('.cms_access_denied_ok').each(function(){

		var $container = $(this);

		$container.addClass('cms_access_denied_ok');

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

	})

}

function cms_access_denied_boot(){

	cms_access_denied_init()

}

cms_access_denied_boot()