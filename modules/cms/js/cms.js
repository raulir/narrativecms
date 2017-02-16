
function strip_tags(input, allowed) {
  allowed = (((allowed || '') + '')
    .toLowerCase()
    .match(/<[a-z][a-z0-9]*>/g) || [])
    .join(''); // making sure the allowed arg is a string containing only tags in lowercase (<a><b><c>)
  var tags = /<\/?([a-z][a-z0-9]*)\b[^>]*>/gi,
    commentsAndPhpTags = /<!--[\s\S]*?-->|<\?(?:php)?[\s\S]*?\?>/gi;
  return input.replace(commentsAndPhpTags, '')
    .replace(tags, function($0, $1) {
      return allowed.indexOf('<' + $1.toLowerCase() + '>') > -1 ? $0 : '';
    });
}

function format_admin_page(){
	
	$('.admin_block,.admin_column').each(function(){
		$this = $(this);
		var label = $this.data('label')
		if (label){
			$this.children('.admin_block_label').remove();
			$this.prepend('<div class="admin_block_label"><div class="admin_block_title">' + $this.data('label') + '</div></div>');
		}
	});
	
}

function activate_cms_page_panel_show(){
	$('.cms_page_panel_show').off('click.cms').on('click.cms', function(){
		
		var action = function($this){
			var cms_page_panel_id = $this.data('cms_page_panel_id');
			get_ajax_panel('cms_page_panel_operations', {
				'cms_page_panel_id': cms_page_panel_id,
				'do': 'cms_page_panel_show'
			}, function(data){
				if (data.result.show == 1){
					$this.closest('li').removeClass('cms_input_page_panel_hidden');
					$this.html('hide');
				} else {
					$this.closest('li').addClass('cms_input_page_panel_hidden');
					$this.html('show');
				}
			});
			
		}
		
		var $this = $(this);
		
		if ($this.html() == 'show'){
			// ask are you sure
			get_ajax_panel('cms_popup_yes_no', {}, function(data){
				panels_display_popup(data.result.html, {
					'yes': function(){
						action($this)
					}
				}); 
			});
		} else {
			action($this);
		}

	});
}

function activate_cms_page_panel_copy(params){
	
	if (typeof params.after !== 'function'){
		params = {
			after: function(){}
		}
	}

	$('.cms_page_panel_copy').off('click.cms').on('click.cms', function(){
		var $this = $(this);
		var cms_page_panel_id = $this.data('cms_page_panel_id');
		get_ajax_panel('cms_page_panel_operations', {
			'cms_page_panel_id': cms_page_panel_id,
			'do': 'cms_page_panel_copy' 
		}, function(data){
			params.after();
		})
	});

}

function cms_notification(text, timer, type){
	
	if (!type){
		type = 'success';
	}
	
	if ($('.cms_notification_container').length == 0){
		$('body').append('<div class="cms_notification_container cms_notification_' + type + '">' + text + '</div>');
		$('.cms_notification_container').css({'left': ($(window).innerWidth() - $('.cms_notification_container').outerWidth())/2 + 'px'});
		setTimeout(function(){
			$('.cms_notification_container').css({'opacity':'1'});
		}, 100);
	}
	
	if (timer){
		setTimeout(function(){
			$('.cms_notification_container').css({'opacity':'0'});
			setTimeout(function(){
				$('.cms_notification_container').remove();
			}, 600);
		}, timer * 1000);
	}
	
}

function cms_error(text, timer){
	cms_notification(text, timer, 'error');
}

$(document).ready(function() {
		
	format_admin_page();
	
	activate_cms_page_panel_show();
	activate_cms_page_panel_copy({});
	
});
