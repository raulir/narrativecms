function init_cms_input_repeater(){

	$('.cms_repeater_button').on('click.r', function(){
		
		var $this = $(this);

		get_ajax_panel('cms/cms_input_repeater_item', {
			
			'fields': JSON.parse(atob(String($this.data('fields')))),
			'repeater_data': '',
			'name': $this.data('name'),
			'repeater_key': $this.closest('.cms_repeater_container').find('.cms_repeater_block').length
			
		}, function(data){
			
			var $area = $this.closest('.cms_repeater_container').find('.cms_repeater_area');

			var $span = $('<span></span>').html(data.result.html)
			var scripts = [];
			
			$span.children('script').each(function(){
				
				if (this.src){
					
					scripts.push(this.src);
					$(this).remove()

				}
			})
			
			$area.append($span.html())

			$.each(scripts, (i, src) => {
				var myScript = document.createElement('script'); 
				myScript.src = src
				document.head.appendChild(myScript);
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
			
			if (typeof cms_page_panel_fields_init === 'function') {
				cms_page_panel_fields_init()
			}
			
			$('body').height('auto');
			
		});
				
	})
	
}

$(() => init_cms_input_repeater())
