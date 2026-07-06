function cms_input_repeater_append_scripts(scripts, callback){

	if (!scripts.length){
		if (typeof callback === 'function'){
			callback()
		}
		return
	}

	var pending = scripts.length

	scripts.forEach(function(src){

		var script = document.createElement('script')
		script.src = src

		var done = function(){
			pending--
			if (pending <= 0 && typeof callback === 'function'){
				callback()
			}
		}

		script.onload = done
		script.onerror = done
		document.head.appendChild(script)

	})

}

function cms_input_repeater_run_init_hooks($container){

	var raw = $container.find('.cms_repeater_button').first().data('init_hooks') || ''

	String(raw).split(',').filter(Boolean).forEach(function(hook){

		if (typeof window[hook] === 'function'){
			window[hook]()
		}

	})

}

function init_cms_input_repeater(){

	$('.cms_repeater_button').on('click.r', function(){
		
		var $this = $(this);

		get_ajax_panel('cms/cms_input_repeater_item', {
			
			'fields': JSON.parse(atob(String($this.data('fields')))),
			'repeater_data': '',
			'name': $this.data('name'),
			'repeater_key': $this.closest('.cms_repeater_container').find('.cms_repeater_block').length
			
		}, function(data){
			
			var $repeater = $this.closest('.cms_repeater_container')
			var $area = $repeater.find('.cms_repeater_area');

			var $span = $('<span></span>').html(data.result._html)
			var scripts = [];
			
			$span.children('script').each(function(){
				
				if (this.src){
					
					scripts.push(this.src);
					$(this).remove()

				}
			})
			
			$area.append($span.html())

			cms_input_repeater_append_scripts(scripts, function(){

				if (typeof cms_page_panel_fields_init === 'function') {
					cms_page_panel_fields_init()
				}

				cms_input_repeater_run_init_hooks($repeater)

				$('body').height('auto');

			})

			setTimeout(() => {
			
				if (typeof cms_input_image_rename == 'function'){
					cms_input_image_rename($this.data('name') + '_image_');
				}

				// if repeater is target for repeater selects, repopulate repeater selects
				if ($this.closest('.cms_repeater_target').length){
					cms_input_repeater_select_reinit();
				}
			
			}, 1000)
			
		});
				
	})
	
}

$(() => init_cms_input_repeater())