
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

function activate_cms_page_panel_show(){
	$('.cms_page_panel_show').off('click.cms').on('click.cms', function(){
		
		var action = function($this){
			var cms_page_panel_id = $this.data('cms_page_panel_id');
			get_ajax_panel('cms_page_panel_operations', {
				'cms_page_panel_id': cms_page_panel_id,
				'do': 'cms_page_panel_show'
			}, function(data){
				if (data.result.show == 1){
					$this.closest('li').removeClass('cms_item_hidden');
					$this.html('hide');
				} else {
					$this.closest('li').addClass('cms_item_hidden');
					$this.html('show');
				}
			});
			
		}
		
		var $this = $(this);

		if ($this.html().trim() == 'show'){
			
			// check if all mandatory is filled in
			if (typeof cms_page_panel_check_mandatory == 'function'){
				var mandatory_result = cms_page_panel_check_mandatory('red');
			} else {
				var mandatory_result = [];
			}
			
			if (mandatory_result.length){

				var mandatory_extra = cms_page_panel_format_mandatory(mandatory_result, 'red');
				cms_notification('Error showing panel' + mandatory_extra, 3, 'error')

			} else {

				// ask are you sure
				get_ajax_panel('cms_popup_yes_no', {}, function(data){
					panels_display_popup(data.result.html, {
						'yes': function(){
							
							// if save button, save 
							if ($('.cms_page_panel_save').length){
								
								cms_page_panel_save({
									'no_mandatory_check': true,
									'success':function(data){
										action($this);
									}
								})
							
							} else {
							
								action($this)
							
							}
							
						}
					}); 
				});

			}
			
		} else {
			action($this);
		}

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

function cms_resize(){
	
	$('body').css({'height': parseInt($(window).innerHeight()) - 0.5 + 'px'});

}

function cms_init(){
	
	activate_cms_page_panel_show();

}

$(document).ready(function() {
	
	$(window).on('resize.cms', cms_resize);
	
	cms_init();
	cms_resize();

});
