function cms_page_panel_fields_init(){
	
	var left = 0;
	var right = 0;
	
	var top_extra = 0;

	$('.cms_page_panel_fields > div').each(function(){
		
		var $this = $(this);
		var t = 0
		
		if ($this.hasClass('cms_input_container')){
			
			var $input = $('.cms_input', $this)
		
			var h = $input.data('cms_input_height')
			if (!h) h = 1
			
			var w = $input.data('cms_input_width')
			if (!w) w = 1
	
			var pos = 0
			
			if (w == 1){
			
				if (left <= right){
					t = left
					left = left + h
					pos = 0
				} else {
					t = right
					right = right + h
					pos = 50
				}
				
			} else {
				
				t = Math.max(left, right)
				left = t + h
				right = t + h
				pos = 0
				
			}
			
			$input.css({
				'height':h * 3.5 + 'rem', 
				'top': t * 3.5 + top_extra + 'rem', 
				'left': pos + '%',
				'width': 50 * w + '%'
			})
		
		} else if ($this.hasClass('cms_repeater_container')){
			
			var hi = 1
			
			var item_i = 1
			
			// calculate item height
			var $items = $('.cms_repeater_block', $this)
			
			if ($items.length){
				
				var $item = $items.first()
				
				var gr_heights = {}
				var gr_starts = {}
				
				// init groups
				$('.cms_input_container_groups', $item).each(function(){
					
					var $grs = $(this) // the input whitch belongs to a group
					var grs = $grs.data('groups').split(',')
					
					$.each(grs, function(i, val){
						
						$grs.addClass('cms_input_container_groups_group_' + val)
						
						if (!gr_heights[val]) gr_heights[val] = 0;
						var grs_h = $grs.children('.cms_input').data('cms_input_height')
						if (!grs_h) grs_h = 1
						gr_heights[val] = gr_heights[val] + grs_h
						
					})
					
				})
				
				$('.cms_input', $item).each(function(){
					
					item_i = item_i + 1;
					
					var $input = $(this)
					
					$input.data('item_i', item_i)
					
					if ($input.parent().hasClass('cms_input_container_groups')){
						return
					}
					
					var ih = $input.data('cms_input_height')
					if (!ih) ih = 1

					// if this is group header
					if ($input.children('.cms_input_groups').length){
						
						var max = 0
						$('.cms_input_groups_value', $input).each(function(){
							
							gr_starts[$(this).data('value')] = hi + ih

							var test = gr_heights[$(this).data('value')]
							if (test > max) max = test
							
						})
						
						ih = ih + max
						
					}
					
					
					$('.cms_repeater_block_content > .cms_input_container:nth-child(' + item_i + ') .cms_input', $this)
							.css({'top': hi * 3.5 + 'rem', 'height':ih * 3.5 + 'rem'})

					hi = hi + ih
					
				})

				// inputs belonging to groups
				$('.cms_input_container_groups .cms_input', $item).each(function(){
					
					var $input = $(this)
					
					var ih = $input.data('cms_input_height')
					if (!ih) ih = 1
					
					$('.cms_repeater_block_content > .cms_input_container_groups:nth-child(' + $input.data('item_i') + ') .cms_input', $this)
							.css({'top': gr_starts[$input.parent().data('groups')] * 3.5 + 'rem', 'height':ih * 3.5 + 'rem'})
							
					gr_starts[$input.parent().data('groups')] = gr_starts[$input.parent().data('groups')] + ih;
					
				})
				
			}
			
			$items.children('.cms_repeater_block_content').css({'height': hi * 3.5 + 1.0 + 'rem'})
			
			var h = (hi + (3.0/3.5)) * Math.ceil($items.length/2) // 2/3.5 = extra per line of blocks
			
			t = Math.max(left, right)
			left = t + h + 3
			right = t + h + 3
			pos = 0
			
			$this.css({'top': t * 3.5 + top_extra + 'rem', 'left': pos + '%'})
			$this.children('.cms_repeater_area').css({'height':h * 3.5 + 'rem'})

		}
		
	})
	
	$('.cms_page_panel_fields').css({'height': Math.max(left, right)*3.5 + 'rem'}).removeClass('cms_page_panel_fields_hidden')
	
}

function cms_page_panel_fields_resize(){
	
}

function cms_page_panel_fields_scroll(){
	
}

$(function() {

	$(window).on('resize.cms', cms_page_panel_fields_resize);
	
	$(window).on('scroll.cms', cms_page_panel_fields_scroll);
	
	cms_page_panel_fields_init();

	cms_page_panel_fields_resize();
	
	cms_page_panel_fields_scroll();

});
