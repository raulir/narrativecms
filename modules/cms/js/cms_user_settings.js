function cms_user_settings_init($root){

	var $scope = $root ? $root.find('.cms_user_settings_container') : $('.cms_user_settings_container');

	$scope.not('.cms_user_settings_ok').each(function(){

		var $container = $(this);

		$container.addClass('cms_user_settings_ok');

		$('.cms_user_settings_rights_add', $container).on('click.cms', function(){
		var value = $('.cms_user_settings_user_rights_select_' + $(this).data('target')).val();
		if (value != null){
			
			$('.cms_user_settings_user_rights_content_' + $(this).data('target')).append(
					'<div class="cms_user_settings_user_access_item" ' +
						' data-value="' + value + '" ' + 
						' data-text="' + $('.cms_user_settings_user_rights_select_' + $(this).data('target') + ' :selected').text() +'" ' +
						' data-target="' + $(this).data('target') + '">' +
					'<div class="cms_user_settings_user_access_item_x">x</div>' +
					'<input type="hidden" name="rights[]" value="' + value + '">' +
					$('.cms_user_settings_user_rights_select_' + $(this).data('target') + ' :selected').text() +
					'</div>');
			
			$('.cms_user_settings_user_rights_select_' + $(this).data('target') + ' :selected').remove();
			
			cms_user_settings_item_init(); // reinit all items again, as this is easier
			
		}
		});

		cms_user_settings_item_init($container);

		// save / add button
		$('.cms_user_settings_user_save', $container).on('click.cms', function(){
		cms_user_settings_user_save($(this), function(){
			location.reload();
		});
		});

		// delete button
		$('.cms_user_settings_user_delete', $container).on('click.cms', function(){
		cms_user_settings_user_delete($(this), function(){
			location.reload();
		});
		});

	});

}

function cms_user_settings_item_init($root){

	var $items = $root ? $root.find('.cms_user_settings_user_access_item') : $('.cms_user_settings_user_access_item');

	$items.not('.cms_user_settings_item_ok').each(function(){

		var $item = $(this);

		$item.addClass('cms_user_settings_item_ok');

		$item.on('click.cms', function(){
			$('.cms_user_settings_user_rights_select_' + $item.data('target')).append('<option value="' + $item.data('value') + '">' + $item.data('text') + '</option>');
			$item.remove();
		});

	});

}

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
	
	get_ajax_panel('cms/cms_user_operations', {
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
	get_ajax_panel('cms/cms_popup_yes_no', {}, function(data){
		panels_display_popup(data.result._html, {
			'yes': function(){
				get_ajax_panel('cms/cms_user_operations', {
					'do': 'cms_user_delete',
					'cms_user_id': cms_user_id
				}, function(data){
					after();
				});
			}
		}); 
	});

}

function cms_user_settings_resize(){
	
}

function cms_user_settings_scroll(){
	
}

$(document).ready(function() {

	$(window).on('resize.cms', function(){
		cms_user_settings_resize();
	});
	
	$(window).on('scroll.cms', function(){
		cms_user_settings_scroll();
	});
	
	cms_user_settings_init();

	cms_user_settings_resize();
	
	cms_user_settings_scroll();

});
