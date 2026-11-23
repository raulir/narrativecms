function cms_input_text_init($root){

	var $scope = $root ? $root.find('.cms_input_text') : $('.cms_input_text');

	$scope.not('.cms_input_text_ok').each(function(){

		var $this = $(this);

		if ($this.closest('.cms_repeater_target').length){

			$('input', $this).on('focus.cms', function(){
				$this.data('old_value', $(this).val());
			});

			$('input', $this).on('change.cms', function(){
				cms_input_repeater_select_reinit();
			});

		}

		$('.cms_input_text_default', $this).on('click.cms', function(){
			$('.cms_input_text_input', $this).val($(this).data('value'))
		})

		$this.addClass('cms_input_text_ok');

	})

}


$(document).ready(function() {

	cms_input_text_init();

});
