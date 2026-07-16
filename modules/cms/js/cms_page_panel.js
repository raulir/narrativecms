
function cms_page_panel_check_mandatory(colour){
	
	var $mandatory = $('.cms_input_mandatory');
	
	var ret = [];
	
	$mandatory.each(function(){
		
		var $this = $(this);
		var label_extra = '';
		$('label', $this).css({'color':''});
		
		// check if inside repeater
		if ($this.closest('.cms_repeater_area').length){
			
			var $repeater_area = $this.closest('.cms_repeater_area');
			var repeater_label = $repeater_area.data('label');

			if (!repeater_label){
				repeater_label = $repeater_area.closest('.cms_repeater_container').find('.cms_repeater_label').first().text();
			}

			label_extra = repeater_label + ' &gt; ' + ($this.closest('.cms_repeater_block').prevAll('.cms_repeater_block').length + 1) + ': ';
			
		}
		
		var label = (label_extra + $('label', $this).html()).replace(/ \*$/, '');
		
		if ($this.hasClass('cms_input_text') || $this.hasClass('cms_input_date')){
			
			if (!$('input', $this).val()){
				ret.push(label);
				$('label', $this).css({'color':colour});
			}
			
		} else if ($this.hasClass('cms_input_textarea')){
			
			if (!$('textarea', $this).val()){
				ret.push(label);
				$('label', $this).css({'color':colour});
			}
			
		} else if ($this.hasClass('cms_input_image')){
			
			if (!$('.cms_input_image_input', $this).val()){
				ret.push(label);
				$('label', $this).css({'color':colour});
			}
			
		} else if ($this.hasClass('cms_input_select')){ // includes fk and repeater_select
			
			var val = $('select', $this).val()
			
			if (!val || val === '0'){
				ret.push(label);
				$('label', $this).css({'color':colour});
			}
			
		}
		
	})
	
	return ret;
	
}

function cms_page_panel_format_mandatory(mandatory_result, colour){
	
	var mandatory_extra = '';
	
	if (mandatory_result.length){
		mandatory_extra = '<br><div style="display: inline-block; color: ' + colour + '; font-size: 1.4rem; padding-top: 10px; ">Missing mandatory values:';
		$.each(mandatory_result, function(key, value){
			mandatory_extra = mandatory_extra + '<br>- ' + value;
		});
		mandatory_extra = mandatory_extra + '</div>';
	}
	
	return mandatory_extra;

}

var cms_page_panel_title_preview_timer = null
var cms_page_panel_title_preview_extend = 0
var cms_page_panel_title_preview_last_update = 0
var cms_page_panel_title_preview_request_id = 0
var cms_page_panel_title_preview_min_interval = 3000
var cms_page_panel_title_preview_extend_per_change = 1000

function cms_page_panel_apply_breadcrumb_title(title){

	var $title = $('.cms_page_panel_toolbar_title')

	if (!$title.length){
		return
	}

	$title.html(strip_tags(title))

}

function cms_page_panel_update_breadcrumb_title(text){

	cms_page_panel_apply_breadcrumb_title(text)

}

function cms_page_panel_is_default_language(){

	var $lang = $('.cms_language_select_current')

	if (!$lang.length){
		return true
	}

	return $lang.data('language') === $lang.data('default_language')

}

function cms_page_panel_title_preview_enabled(){

	return $('.cms_page_id').val() == '0'
		&& $('input[name="sort"]').val() != '0'
		&& $('.cms_page_panel_panel_name').val() != ''

}

function cms_page_panel_onpage_title_sync_enabled(){

	return parseInt($('.cms_page_id').val()) > 0

}

function cms_page_panel_fetch_title_preview(){

	if (!cms_page_panel_title_preview_enabled() || !cms_page_panel_is_default_language()){
		return
	}

	if (typeof tinyMCE !== 'undefined'){
		tinyMCE.triggerSave()
	}

	var request_id = ++cms_page_panel_title_preview_request_id
	var data_to_submit = cms_page_panel_save_serialize_form('.admin_form')

	data_to_submit['do'] = 'cms_page_panel_preview_title'
	data_to_submit['language'] = $('.cms_language_select_current').data('language')

	get_ajax('cms/cms_page_panel', $.extend({}, data_to_submit, {
		success: function(data){
			if (request_id != cms_page_panel_title_preview_request_id){
				return
			}
			if (data.result && data.result._title){
				cms_page_panel_apply_breadcrumb_title(data.result._title)
				cms_page_panel_title_preview_last_update = Date.now()
				cms_page_panel_title_preview_extend = 0
			}
		}
	}))

}

function cms_page_panel_schedule_title_preview(){

	if (!cms_page_panel_title_preview_enabled() || !cms_page_panel_is_default_language()){
		return
	}

	clearTimeout(cms_page_panel_title_preview_timer)

	var now = Date.now()
	var elapsed = now - cms_page_panel_title_preview_last_update

	if (elapsed >= cms_page_panel_title_preview_min_interval){
		cms_page_panel_title_preview_extend = 0
		cms_page_panel_title_preview_timer = setTimeout(cms_page_panel_fetch_title_preview, 0)
		return
	}

	cms_page_panel_title_preview_extend += cms_page_panel_title_preview_extend_per_change
	var delay = (cms_page_panel_title_preview_min_interval - elapsed) + cms_page_panel_title_preview_extend

	cms_page_panel_title_preview_timer = setTimeout(function(){
		cms_page_panel_title_preview_extend = 0
		cms_page_panel_fetch_title_preview()
	}, delay)

}

function cms_page_panel_title_preview_on_save(data){

	if (data.result && data.result._title && cms_page_panel_is_default_language()){
		cms_page_panel_apply_breadcrumb_title(data.result._title)
		cms_page_panel_title_preview_last_update = Date.now()
		cms_page_panel_title_preview_extend = 0
	} else if (cms_page_panel_onpage_title_sync_enabled()){
		cms_page_panel_update_breadcrumb_title($('input', '.cms_page_panel_title').val())
	}

}

function cms_page_panel_title_preview_init($root){

	var $scope = $root ? $root.find('.cms_page_panel_container') : $('.cms_page_panel_container');

	$scope.not('.cms_page_panel_title_preview_ok').each(function(){

		var $container = $(this);

		$container.addClass('cms_page_panel_title_preview_ok');

		if (cms_page_panel_title_preview_enabled()){

			$('.admin_form', $container).off('input.title_preview change.title_preview')
				.on('input.title_preview change.title_preview', 'input, textarea, select', function(){
					cms_page_panel_schedule_title_preview()
				})

		}

		if (cms_page_panel_onpage_title_sync_enabled()){

			$('input', '.cms_page_panel_title', $container).off('input.title_sync').on('input.title_sync', function(){
				cms_page_panel_update_breadcrumb_title($(this).val())
			})

		}

	});

}

function cms_page_panel_init($root){

	var $scope = $root ? $root.find('.cms_page_panel_container') : $('.cms_page_panel_container');

	$scope.not('.cms_page_panel_ok').each(function(){

		var $container = $(this);

		$container.addClass('cms_page_panel_ok');

		var $title = $('input', '.cms_page_panel_title', $container);
		if ($title.val() == 'New block'){
			$title.data('new_block', true);
		}

		$('.cms_repeater_area', $container).each(function(){

			if ($(this).parent().hasClass('cms_repeater_container_readonly')){

			} else {
				$(this).sortable().disableSelection()
			}

		})

		cms_page_panel_title_preview_init($container)

		// limit length of text inputs
		$('.admin_max_chars', $container).each(function(){
			var $this = $(this);
			$this.on('keyup.cms', function(){
				var $that = $(this);
				if ($that.val().length > parseInt($that.data('max_chars'))){
					$that.addClass('admin_input_error');
				} else {
					$that.removeClass('admin_input_error');
				}
			})
		});

	});

	// Field grid layout (no *_ok guard — re-runs on repeater add/remove)
	cms_page_panel_fields_init($root)

}

/**
 * Absolute-position layout for definition fields inside .cms_page_panel_fields.
 * No *_ok guard — callers (repeaters, grid) re-run after DOM changes.
 */
function cms_page_panel_fields_init($root){

	var $fields = $root ? $root.find('.cms_page_panel_fields') : $('.cms_page_panel_fields');

	var left = 0;
	var right = 0;

	var top_extra = 0;

	$fields.find('> div').each(function(){
		
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
			
			var input_height = h * 3.5 + 'rem'
			
			if (w == 1){
				
				$input.css({
					'position': 'absolute',
					'height': input_height,
					'top': t * 3.5 + top_extra + 'rem',
					'left': pos + '%',
					'width': '50%'
				})
				
			} else if ($this.hasClass('cms_input_container_full')) {
				
				$this.css({
					'display': 'block',
					'position': 'absolute',
					'top': t * 3.5 + top_extra + 'rem',
					'left': '0',
					'width': '100%',
					'height': input_height
				})
				
			} else {
				
				$input.css({
					'position': 'absolute',
					'height': input_height,
					'top': t * 3.5 + top_extra + 'rem',
					'left': pos + '%',
					'width': '100%'
				})
				
			}
		
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
	
	$fields.css({'height': Math.max(left, right)*3.5 + 'rem'}).removeClass('cms_page_panel_fields_hidden')
	
}

$(document).ready(function() {
	
	cms_page_panel_init();

});
