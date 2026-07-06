
function cms_keyword_init($root){

	var $scope = $root ? $root.find('.admin_form') : $('.admin_form');

	$scope.not('.cms_keyword_ok').each(function(){

		var $form = $(this);

		if (!$('.cms_keyword_save', $form).length && !$('.cms_keyword_delete', $form).length){
			return;
		}

		$form.addClass('cms_keyword_ok');

		$('.cms_keyword_save', $form).on('click.cms', function(e){

			e.stopPropagation();
			$form.submit();
			return false;

		});

		$('.cms_keyword_delete', $form).on('click.cms', function(e){

			e.stopPropagation();

			get_ajax('cms/cms_keyword', {
				'do': 'cms_keyword_delete',
				'cms_keyword_id': $('#cms_keyword_id').val(),
				'success': function(){
					window.location.href = _cms_base + 'admin/keywords/';
				}
			})

		});

	});

}

$(document).ready(function() {

	cms_keyword_init();

});