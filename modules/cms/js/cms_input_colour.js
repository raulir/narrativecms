function cms_input_colour_init(){
	
	$('.cms_input_colour').each(function(){
		
		var $this = $(this)
		
		if ($this.hasClass('cms_input_colour_ok')){
			return
		}
		
		$this.addClass('cms_input_colour_ok')

		$('.cms_input_colour_default', $this).on('click.cms', function(){
			$('.cms_input_colour_input', $this).val($(this).data('value'))
			cms_input_colour_update($this)
		})
		
		$('.cms_input_colour_helper').on('change.cms', function(){
			$('.cms_input_colour_input', $(this).closest('.cms_input_colour')).val($(this).val())
			cms_input_colour_update($this)
		})
		
		$('.cms_input_colour_input').on('change.cms keyup.cms', function(){
			cms_input_colour_update($this)
		})
		
		cms_input_colour_update($this)
		
	})

}

function cms_input_colour_update($input){
	
	var standardize_color = function(str){
	    var ctx = document.createElement("canvas").getContext("2d");
	    ctx.fillStyle = str;
	    return ctx.fillStyle;
	}
	
	var rgb_hex = function(color){
        const rgba = color.replace(/^rgba?\(|\s+|\)$/g, '').split(',')
        const hex = `#${((1 << 24) + (parseInt(rgba[0]) << 16) + (parseInt(rgba[1]) << 8) + parseInt(rgba[2])).toString(16).slice(1)}`
        return hex
    }
	
	$('.cms_input_colour_helper', $input).css({'opacity': ''})
	$('.cms_input_colour_input', $input).css({'background-color': ''})
	
	$('.cms_input_colour_input', $input).val($('.cms_input_colour_input', $input).val().trim())

	var value = $('.cms_input_colour_input', $input).val()
	
	var std_value = standardize_color(value)
	
	if (CSS.supports('color', value) || value.length == 0){
		
		if (/^#[0-9A-F]{6}$/i.test(value)){
		
			$('.cms_input_colour_helper', $input).val(value)
			
		} else if (['inherit', 'transparent', 'unset', 'initial', ''].includes(value)) {
			
			$('.cms_input_colour_helper', $input).css({'opacity': '0'})
			
		} else if (/^#[0-9A-F]{6}$/i.test(std_value)){
			
			$('.cms_input_colour_helper', $input).val(std_value)
			
		} else if (/^#[0-9A-F]{6}$/i.test(rgb_hex(std_value))){
			
			$('.cms_input_colour_helper', $input).val(rgb_hex(std_value))
			
		}

	} else {

		$('.cms_input_colour_input', $input).css({'background-color': 'rgba(192,127,127,0.1)'})

	}
	
}

function cms_input_colour_resize(){
	
}

function cms_input_colour_scroll(){
	
}

$(document).ready(function() {

	$(window).on('resize.cms', cms_input_colour_resize)
	$(window).on('scroll.cms', cms_input_colour_scroll)
	
	cms_input_colour_init()
	cms_input_colour_resize()
	cms_input_colour_scroll()

})
