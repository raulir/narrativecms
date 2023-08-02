function _cms_loader(panel_name, panel_params, target, config_url){

	panel_params.panel_id = panel_name
	
	_cms_url = config_url
	
	_cms_load_jquery().then(_cms_load_main).then(() => {
		
		if (panel_name.includes('/')){
	
		    $.ajax({
				type: 'POST',
			  	url: _cms_url + 'ajax_api/get_panel/',
			  	data: panel_params,
			  	dataType: 'json',
			  	context: this,
			  	success: function( returned_data ) {
			  		$(target).append(returned_data.result._html)
			  		session_form_init()
				}
			})
		
		} else {
			
			_cms_load_panel(panel_name, target, panel_params)
			
		}

	})

}

function _cms_load_jquery(){
	return new Promise((resolve,reject) => {
		if (window.jQuery) 
			resolve()
		else {
			let script = document.createElement('script')
		    document.head.appendChild(script)
		    script.type = 'text/javascript'
		    script.addEventListener('load',resolve)
		    script.src = '//ajax.googleapis.com/ajax/libs/jquery/3.7.0/jquery.min.js'
	    }
	})
}

function _cms_load_main(){
	return new Promise((resolve,reject) => {
		if (typeof get_ajax == 'function') 
			resolve()
		else {
			let script = document.createElement('script')
		    document.head.appendChild(script)
		    script.type = 'text/javascript'
		    script.addEventListener('load',resolve)
		    script.src = _cms_url + 'modules/cms/js/cms_site_main.js'
		}
	})
}

function _cms_load_panel(anchor, target, panel_params){
	if ($(target).length){
		
		var url_params = {}
		for(const [key, value] of (new URL(window.location)).searchParams.entries()) { url_params[key] = value }

		_cms_panel_by_anchor(anchor, {...url_params, ...panel_params}).then(data => {
			if (!data){
				$(target).html('Panel loading error!')
			} else {
				$(target).html(data.result.html)
			}
			setTimeout(() => {
				$(window).trigger('resize.cms')
				$(target).get(0).removeAttribute('style')
				setTimeout(() => {
					$(window).trigger('resize.cms')
				}, 500)
			}, 150)
			
		})
	}
}

function _cms_panel_by_anchor(anchor, params){

	return new Promise ((resolve, reject) => {
		
		if (!params){
			params = {}
		}
		
		params.anchor = anchor

		$.ajax({
			type: 'POST',
		  	url: _cms_url + 'ajax_api/get_panel_anchor/',
		  	data: params,
		  	dataType: 'json',
		  	context: this,
		  	success: function( returned_data ) {

				resolve(returned_data)
				
			},
			error: function( return_handler ) {
				
				var data = {}
				data.result = {'html':return_handler.responseText}
				resolve(data)
				
			}
		})
		
	})
	
}
