function cms_cssjs_settings_resize(){
	
}

function cms_cssjs_settings_scroll(){
	
}

function cms_cssjs_settings_init(){
	
	$('.cms_cssjs_settings_css_add').on('click.cms', function(){
		
		var $select = $('.cms_cssjs_settings_css_select');
		var value = $select.val();
		if (value != null){
			$('.cms_cssjs_settings_csss').append('<div class="cms_list_sortable_item cms_cssjs_settings_csss_item ui-sortable-handle" style="' + $('.cms_cssjs_settings_csss').data('bg') + '"' +
					' data-value="' + value + '" data-text="' + $(':selected', $select).text() + '">' + $(':selected', $select).text() +
					'<div class="cms_cssjs_settings_csss_item_delete cms_list_item_button">remove</div>' +
					'</div>');
			
			$(':selected', $select).remove();
			
			cms_cssjs_settings_item_init(); // reinit all items again, as this is easier
			
		}
	
	});
	
	cms_cssjs_settings_item_init();
	
	$('.cms_cssjs_settings_save').on('click.cms', function(){
		
		// collect data
		var panels = [];
		$('.cms_cssjs_settings_csss_item').each(function(){
			panels.push($(this).data('value'));
		});
		
		// send to server
		get_ajax_panel('cms_cssjs_operations', {
			'do': 'cms_cssjs_save',
			'panels': panels
		}, function(data){
			cms_notification('Css settings saved!', 3);
			location.reload();
		});

	});
	
}

function cms_cssjs_settings_item_init(){
	
	$('.cms_cssjs_settings_csss_item_delete').off('click.cms').on('click.cms', function(){
		$('.cms_cssjs_settings_css_select').append('<option value="' + $(this).parent().data('value') + '">' + $(this).parent().data('text') + '</option>');
		$(this).parent().remove();
	});

}

$(document).ready(function() {

	$(window).on('resize.cms', function(){
		cms_cssjs_settings_resize();
	});
	
	$(window).on('scroll.cms', function(){
		cms_cssjs_settings_scroll();
	});
	
	cms_cssjs_settings_init();

	cms_cssjs_settings_resize();
	
	cms_cssjs_settings_scroll();

});

/*

function cms_user_settings_user_save($this, after){
	
	if (!after){
		after = function(){};
	}
	
	// find form area
	var $parent = $this.closest('.cms_user_settings_user');
	
	// collect data
	var rights = [];
	$('.cms_user_settings_user_access_item', $parent).each(function(){
		rights.push($(this).data('value'));
	});
	
	var cms_user_id = $('.cms_user_settings_cms_user_id', $parent).val();
	var username = $('.cms_user_settings_username', $parent).val();
	var password = $('.cms_user_settings_password', $parent).val();
	var name = $('.cms_user_settings_name', $parent).val();
	var email = $('.cms_user_settings_email', $parent).val();
	var sort = $('.cms_user_settings_sort', $parent).val();
	
	// check username and password
	if (username.length < 3){
		cms_notification('Username has to have at least 3 characters', 3, 'error');
		return;
	} else if (password.length > 0 && password.length < 6){
		cms_notification('Password has to have at least 6 characters', 3, 'error');
		return;
	} else if (/[^a-z0-9_]+/.test(username)){
		cms_notification('Username can contain only lowercase letters and numbers', 3, 'error');
		return;
	}
	
	get_ajax_panel('cms_user_operations', {
		'do': 'cms_user_save',
		'rights': rights,
		'cms_user_id': cms_user_id,
		'username': username,
		'password': password,
		'name': name,
		'email': email
	}, function(data){
		after();
	});
	
}

function cms_user_settings_user_delete($this, after){
	
	if (!after){
		after = function(){};
	}
	
	// find form area
	var $parent = $this.closest('.cms_user_settings_user');
	
	var cms_user_id = $('.cms_user_settings_cms_user_id', $parent).val();
	
	// ask are you sure
	get_ajax_panel('cms_popup_yes_no', {}, function(data){
		panels_display_popup(data.result.html, {
			'yes': function(){
				get_ajax_panel('cms_user_operations', {
					'do': 'cms_user_delete',
					'cms_user_id': cms_user_id
				}, function(data){
					after();
				});
			}
		}); 
	});

}
*/
