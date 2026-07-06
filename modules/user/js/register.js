var register_submitting = false

function register_init(){
	
	$('.register_submit').on('click.cms', function(){
		
		if (register_submitting){
			return
		}
		
		register_hide_error()
		
		var data = {
				'do': 'register',
				'fields': [],
		}

		var cms_page_panel_id = $('.register_container').data('cms_page_panel_id')
		if (cms_page_panel_id){
			data.cms_page_panel_id = cms_page_panel_id
		}
		
		var error_fields = []
		
		$('.register_input').each(function(){
			
			var $this = $(this)

			data.fields.push({
				id: $this.data('field_id'), 
				value: $this.val(), 
				label: $this.data('label'),
			})
			
			if (!$this.val() && $this.hasClass('register_field_mandatory')){
				$this.addClass('register_input_error')
				error_fields.push($this.data('label'))
			}
			
		})
		
		if (error_fields.length){
			
			if (error_fields.length == 1){
				register_show_error('mandatory', error_fields[0])
			} else {
				register_show_error('mandatories', error_fields.join($('.register_fieldname').data('glue')))
			}
			
			return
			
		}
		
		register_submitting = true
		$('.register_submit').addClass('register_submit_disabled')
		
		data.success = function(result){

			register_submitting = false
			$('.register_submit').removeClass('register_submit_disabled')

			if (result.result.success || result.result.redirect_url){
				
				register_show_success(result.result.redirect_url)
				
			} else if (result.result.errors){
				
				result.result.errors.forEach(error => register_show_error(error))

			} else {
				
				register_show_success()
				
			}
		}
		
		get_ajax('user/register', data).catch(function(){
			register_submitting = false
			$('.register_submit').removeClass('register_submit_disabled')
		});

	})
	
	$('.register_errors').on('click.cms', register_hide_error)

}

function register_show_error(error, fieldname = false){
	
	if (fieldname){
		$('.register_fieldname').html(fieldname)
	}
	
	$('.register_error_' + error).addClass('register_error_active')
	
	$('.register_errors').addClass('register_errors_active')
	
}

function register_hide_error(){
	
	$('.register_error_active').removeClass('register_error_active')
	$('.register_errors_active').removeClass('register_errors_active')
	$('.register_input_error').removeClass('register_input_error')
	$('.register_success_active').removeClass('register_success_active')

}

function register_show_success(redirect_url){
	
	register_hide_error()
	
	var url = redirect_url || $('.register_container').data('success')
	
	if (url){
		window.location.href = url
	} else {
		$('.register_form').hide()
		$('.register_success').addClass('register_success_active')
	}
	
}

function register_resize(){
	
}

function register_scroll(){
	
}

$(document).ready(function() {

	$(window).on('resize.cms', function(){
		register_resize();
	});
	
	$(window).on('scroll.cms', function(){
		register_scroll();
	});
	
	register_init();

	register_resize();
	
	register_scroll();

});
