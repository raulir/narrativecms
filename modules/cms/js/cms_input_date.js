function cms_input_date_init(){
	
	$('.cms_input_date_input').each(function(){
		
		var $this = $(this);
		
		if (!$this.hasClass('cms_input_date_input_hidden')){
			return;
		}
		
		var $parent = $this.closest('.cms_input_date');

		$this.removeClass('cms_input_date_input_hidden');
		
		var config = {};
		
		var val = $this.val();
		var initial = $parent.data('default'); 
		
		if (val){
			config.defaultDate = val;
		} else if (initial){
			config.defaultDate = initial;
		}

		$this.data('picker', $this.flatpickr(config));
		
		$('.cms_input_date_clear', $parent).on('click.cms', function(){
			
			if (initial){
				$this.data('picker').setDate(initial);
			} else {
				$this.data('picker').clear();
			}
			
		});
		
		$('.cms_input_date_today', $parent).on('click.cms', function(){
			
			var today = new Date();

			var dd = today.getDate();
			if (dd < 10) {
				dd = '0' + dd;
			}
			
			var mm = today.getMonth() + 1; //January is 0!
			if (mm < 10) {
				mm = '0' + mm;
			} 

			var yyyy = today.getFullYear();
			
			$this.data('picker').setDate(yyyy + '-' + mm + '-' + dd);
		
		});

	});

}

function cms_input_date_resize(){
	
}

function cms_input_date_scroll(){
	
}

$(document).ready(function() {

	$(window).on('resize.cms', function(){
		cms_input_date_resize();
	});
	
	$(window).on('scroll.cms', function(){
		cms_input_date_scroll();
	});
	
	cms_input_date_init();

	cms_input_date_resize();
	
	cms_input_date_scroll();

});
