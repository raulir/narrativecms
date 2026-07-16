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

/**
 * Sortable for repeater blocks — sized helper/placeholder so absolute field layout keeps shape.
 * Drag handle: upper toolbar only (four-way arrow cursor via CSS).
 */
function cms_input_repeater_sortable_init($root){

	var $areas = $root && $root.length
		? ($root.hasClass('cms_repeater_area') ? $root : $root.find('.cms_repeater_area'))
		: $('.cms_repeater_area')

	$areas.each(function(){

		var $area = $(this)

		if ($area.closest('.cms_repeater_container_readonly').length){
			return
		}

		if ($area.hasClass('ui-sortable')){
			try {
				$area.sortable('destroy')
			} catch (e) {
				// ignore if already destroyed
			}
		}

		$area.sortable({
			items: '> .cms_repeater_block',
			handle: '.cms_repeater_block_toolbar',
			cancel: 'input, textarea, select, button, a, .cms_repeater_block_delete',
			tolerance: 'pointer',
			opacity: 0.92,
			forcePlaceholderSize: true,
			forceHelperSize: true,
			placeholder: 'cms_repeater_block cms_repeater_block_placeholder',
			helper: function(e, $item){

				var $h = $item.clone()
				// Match .cms_repeater_block width (49rem) — do not use full-row outerWidth quirks
				var content_h = $item.children('.cms_repeater_block_content').outerHeight()
				var block_h = $item.outerHeight()

				$h.css({
					'width': '49.0rem',
					'max-width': '49.0rem',
					'height': block_h,
					'box-sizing': 'border-box',
					'z-index': 10000,
					'display': 'inline-block',
					'vertical-align': 'top'
				})
				$h.children('.cms_repeater_block_content').css({
					'height': content_h,
					'box-sizing': 'border-box'
				})

				return $h

			},
			start: function(e, ui){

				var content_h = ui.item.children('.cms_repeater_block_content').outerHeight()
				var block_h = ui.item.outerHeight()

				// Same footprint as a real block so two still fit on a row
				ui.placeholder.css({
					'visibility': 'visible',
					'display': 'inline-block',
					'vertical-align': 'top',
					'box-sizing': 'border-box',
					'width': '49.0rem',
					'max-width': '49.0rem',
					'height': block_h,
					'margin': 0
				})
				if (!ui.placeholder.children('.cms_repeater_block_content').length){
					ui.placeholder.html(
						'<div class="cms_repeater_block_content cms_repeater_block_placeholder_inner"></div>'
					)
				}
				ui.placeholder.children('.cms_repeater_block_content').css({
					'height': content_h,
					'min-height': content_h,
					'box-sizing': 'border-box',
					'width': '100%'
				})

			},
			stop: function(){

				if (typeof cms_page_panel_fields_init === 'function'){
					cms_page_panel_fields_init()
				}

			}
		})

	})

}

function init_cms_input_repeater(){

	// Initial sortable (page panel may also call after its own init)
	cms_input_repeater_sortable_init()

	$('.cms_repeater_button').off('click.r').on('click.r', function(){
		
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

			// Bind delete on new block
			if (typeof init_cms_repeater_block_delete === 'function'){
				init_cms_repeater_block_delete()
			}

			cms_input_repeater_append_scripts(scripts, function(){

				if (typeof cms_page_panel_fields_init === 'function') {
					cms_page_panel_fields_init()
				}

				// Refresh sortable so freshly added item keeps shape when dragged
				cms_input_repeater_sortable_init($area)

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
