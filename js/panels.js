
function get_ajax(name, params){

	var ext_params = $.extend({'no_html': '1', 'success': function(){} }, params)
	var action_on_success = ext_params.success;
	delete ext_params.success;
	get_ajax_panel(name, ext_params, action_on_success);

}

var _cms_test_localstorage = function() {
	
	var test = 'test';
    try {
        localStorage.setItem(test, test);
        localStorage.removeItem(test);
        return true;
    } catch(e) {
        return false;
    }

};

function get_ajax_panel(name, params, action_on_success){
	
	var data = false;
	
	var cache = 0;
	if (typeof params.cache != 'undefined'){
		cache = parseInt(params.cache);
	}
	params.cache = '';
	
	// try to read from storage
	if (_cms_test_localstorage() && cache > 0 && !admin_logged_in){
		var key = hex_md5(config_url + name + JSON.stringify(params));
		var local_data = localStorage.getItem(key);
		if (local_data){
			data = $.parseJSON(local_data);
			if (data.storage_timestamp > +new Date() - (cache * 1000)){
				action_on_success(data);
// console.log('cache hit age: ' + (+new Date() - data.storage_timestamp))
			} else {
				data = false;
				localStorage.removeItem(key);
// console.log('cache old')
			}
		}
	}

	if (!data){
		params.panel_id = name;
		$.ajax({
			type: 'POST',
		  	url: config_url + 'ajax_api/get_panel/',
		  	data: params,
		  	dataType: 'json',
		  	context: this,
		  	success: function( returned_data ) {
		  		
		  		// save to local storage
		  		if (_cms_test_localstorage() && cache > 0){
		  			returned_data.storage_timestamp = new Date().getTime();
		  			localStorage.setItem(key, JSON.stringify(returned_data));
// console.log('cache new ' + key)
		  		}
		  		
		  		action_on_success(returned_data);
				
			}
		});
	}
		
}

function get_ajax_page(url, params, action_on_success){
	
	params = $.extend({ '_positions': ['main'] }, params)

	var data = false;
/*	
	var cache = 0;
	if (typeof params.cache != 'undefined'){
		cache = parseInt(params.cache);
	}
	params.cache = '';

	// try to read from storage
	if (_cms_test_localstorage() && cache > 0 && !admin_logged_in){
		var key = hex_md5(config_url + name + JSON.stringify(params));
		var local_data = localStorage.getItem(key);
		if (local_data){
			data = $.parseJSON(local_data);
			if (data.storage_timestamp > +new Date() - (cache * 1000)){
				action_on_success(data);
			} else {
				data = false;
				localStorage.removeItem(key);
			}
		}
	}
*/
	if (!data){
		params._url = url;
		params._ajax = 1;
		$.ajax({
			type: 'POST',
		  	url: params._url,
		  	data: params,
		  	dataType: 'json',
		  	context: this,
		  	success: function( returned_data ) {

		  		/*
		  		// save to local storage
		  		if (_cms_test_localstorage() && cache > 0){
		  			returned_data.storage_timestamp = new Date().getTime();
		  			localStorage.setItem(key, JSON.stringify(returned_data));
		  		}
		  		*/
		  		
		  		action_on_success(returned_data);

		  	}
		});
	}
	
}

function panels_display_popup(html, params){
	
	$('body').append(html);
	
	params = $.extend({
		'yes': function(after){
			after();
		},
		'select': function(after){
			after();
		},
		'cancel': function(after){
			after();
		},
		'pre_close': function(after){
			after();
		},
		'clean_up': function(){
			console.log('clean_up');
			$('.popup_container,.popup_overlay').css({'opacity':'0'});
			setTimeout(function(){
				$('.popup_container,.popup_overlay').remove();
			}, 300);
		}
	}, params);
	
	$('.popup_yes').on('click.r', function(){
		console.log('yes clicked');
		params.pre_close(function(){
			console.log('pre_close finished');
			params.yes(function(){
				console.log('yes finished');
				params.clean_up();
			});
		});
	});

	$('.popup_cancel').on('click.r', function(){
		
		console.log('cancel clicked');
		params.pre_close(function(){
		
			console.log('pre_close finished');
			params.cancel(function(){
			
				console.log('cancel finished');
				params.clean_up();
			
			});
			
		});
		
	});

	$('.popup_select').on('click.r', function(){
		console.log('select clicked');
		params.pre_close(function(){
			console.log('pre_close finished');
			params.select(function(){
				console.log('select finished');
				params.clean_up();
			});
		});
	});
	
}

function cms_load_css(filename, force_download){
	
	if (filename.indexOf('?') !== -1){
		var clean_filename = filename.substr(0, filename.indexOf('?'));
	} else {
		var clean_filename = filename;
	}
	
	var found = false;
	
	$('link[type="text/css"]').each(function(){
		if (this.href.indexOf(clean_filename) !== -1){
			found = true;
		}
	});

	if(!found){
		
		if (force_download){
			filename = clean_filename + '?v=' + Math.round(Math.random() * 10000000);
		}
		
		$('head').append('<link rel="stylesheet" type="text/css" href="' + filename + '"/>');
	
	}
	
}
