function cms_input_datetime_init(){
	
	$('.cms_input_datetime').each(function(){
		
		var $this = $(this);
		
		if (!$this.hasClass('cms_input_datetime_hidden')){
			return;
		}

		var value = $('.cms_input_datetime_value', $this).val();
		
		if ($this.data('format') == 'timestamp'){

			var value = new Date(value * 1000).toISOString()
				.replace(/T/, ' ')
				.replace(/\..+/, '')

		}
		
		var initial = $this.data('default'); 
		
		// init datepicker
		var config = {
			'onChange': update_hidden
		};
		
		if (value){
			config.defaultDate = value.substring(0, 10);
		} else if (initial){
			config.defaultDate = initial.substring(0, 10);
		}
		
		$this.data('picker', $('.cms_input_datetime_date', $this).flatpickr(config));
		
		// init hour and minute
		if (value){
			$('.cms_input_datetime_hour', $this).val(value.substring(11, 13));
			$('.cms_input_datetime_minute', $this).val(value.substring(14, 16));
		} else if (initial && initial !== ''){
			$('.cms_input_datetime_hour', $this).val(initial.substring(11, 13));
			$('.cms_input_datetime_minute', $this).val(initial.substring(14, 16));
		} else {
			$('.cms_input_datetime_hour', $this).val('');
			$('.cms_input_datetime_minute', $this).val('');
		}

		$this.removeClass('cms_input_datetime_hidden');

		// write changes to selects back to hidden field
		function update_hidden(){
			
			if ($('.cms_input_datetime_date', $this).val() && $('.cms_input_datetime_date', $this).val() != '' && 
					(!$('.cms_input_datetime_hour', $this).val() || $('.cms_input_datetime_hour', $this).val() == '') ){
				
				$('.cms_input_datetime_hour', $this).val('00')
				
			}
			
			if ($('.cms_input_datetime_date', $this).val() && $('.cms_input_datetime_date', $this).val() != '' && 
					(!$('.cms_input_datetime_minute', $this).val() || $('.cms_input_datetime_minute', $this).val() == '') ){
				
				$('.cms_input_datetime_minute', $this).val('00')
				
			}

			if (!$('.cms_input_datetime_date', $this).val() || $('.cms_input_datetime_minute', $this).val() === null || 
					$('.cms_input_datetime_hour', $this).val() === null){
				$('.cms_input_datetime_value', $this).val('');
			} else {
				if ($this.data('format') != 'timestamp'){
					
					$('.cms_input_datetime_value', $this).val($('.cms_input_datetime_date', $this).val() + ' ' + 
							$('.cms_input_datetime_hour', $this).val() + ':' + $('.cms_input_datetime_minute', $this).val());
					
				} else {
					
					var date = new Date($('.cms_input_datetime_date', $this).val() + ' ' + 
							$('.cms_input_datetime_hour', $this).val() + ':' + $('.cms_input_datetime_minute', $this).val() + ':00.000Z')
					
					$('.cms_input_datetime_value', $this).val(Math.round(date.getTime()/1000))

				}
			}
			
		}
		
		$('.cms_input_datetime_hour', $this).on('change.cms', update_hidden);
		$('.cms_input_datetime_minute', $this).on('change.cms', update_hidden);
		
		// clear
		$('.cms_input_datetime_clear', $this).on('click.cms', function(){
			
			if (initial){
				$this.data('picker').setDate(initial.substring(0, 10));
				$('.cms_input_datetime_hour', $this).val(initial.substring(11, 13));
				$('.cms_input_datetime_minute', $this).val(initial.substring(14, 16));
			} else {
				$this.data('picker').clear();
				$('.cms_input_datetime_hour', $this).val('');
				$('.cms_input_datetime_minute', $this).val('');
			}
			
			update_hidden();
			
		});
		
		// today
		$('.cms_input_datetime_today', $this).on('click.cms', function(){
			
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
			
			var hh = today.getHours();
			if (hh < 10) {
				hh = '0' + hh;
			}
			$('.cms_input_datetime_hour', $this).val(hh);
		
			var ii = today.getMinutes();
			if (ii < 10) {
				ii = '0' + ii;
			}
			$('.cms_input_datetime_minute', $this).val(ii);
			
			update_hidden();
		
		});

	});

}

function cms_input_datetime_resize(){
	
}

function cms_input_datetime_scroll(){
	
}

$(document).ready(function() {

	$(window).on('resize.cms', function(){
		cms_input_datetime_resize();
	});
	
	$(window).on('scroll.cms', function(){
		cms_input_datetime_scroll();
	});
	
	cms_input_datetime_init();

	cms_input_datetime_resize();
	
	cms_input_datetime_scroll();

});
