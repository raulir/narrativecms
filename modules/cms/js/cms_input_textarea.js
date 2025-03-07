function cms_input_textarea_srcconverter(url, node, on_save, name) {

    if (name === 'src'){
    	
    	if (!url.startsWith('img/')){
    	
    		url = 'img/' + url;
        
    	}
    	
    }

    return url;
	
}

function cms_input_textarea_init(){

	$('.cms_input_textarea').each(function(){
		
		var $this = $(this);
		
		if ($this.hasClass('cms_input_textarea_ok')){
			return;
		}
		
		if ($this.closest('.cms_repeater_target').length){

			$this.addClass('cms_input_textarea_ok');
			
			$('textarea', $this).on('focus.cms', function(){
				$this.data('old_value', $(this).val());
			});
			
			$('textarea', $this).on('change.cms', function(){
				cms_input_repeater_select_reinit();
			});
			
		}
		
	});
	
	setTimeout(function(){
		
		// go over all tinymce elements
		var i = $('.cms_tinymce_formatted').length;
		
		$('.cms_tinymce').each(function(){
			
			var $this = $(this);

			if (!$this.hasClass('cms_tinymce_formatted')){

				$this.addClass('cms_tinymce_formatted');

				$this.addClass('cms_tinymce_' + i);
				
				// get buttons
				var buttons = '' + $this.data('html');
				
				var toolbar = 'undo redo code | ';
				var valid_elements = 'br';
				var plugins = '';
				var setup = [];
				var extra_init = {formats:{}};
	
				if (buttons.indexOf('B') > -1){
					toolbar = toolbar + 'bold ';
					valid_elements = valid_elements + ',b/strong';
				}
				
				if (buttons.indexOf('I') > -1){
					toolbar = toolbar + 'italic ';
					valid_elements = valid_elements + ',i/em';
				}
				
				if (buttons.indexOf('U') > -1){
					toolbar = toolbar + 'underline ';
					valid_elements = valid_elements + ',u/underline';
					extra_init.formats = $.extend(true, extra_init.formats, {
		                underline: {inline: 'u', exact: true},
		            });
				}
				
				if (buttons.indexOf('C') > -1){
					toolbar = toolbar + 'forecolor ';
					valid_elements = valid_elements + ',span[style]';
					plugins = plugins + ' textcolor';
					extra_init.textcolor_map = [
				        "edff00", "Yellow",
				        "00ff85", "Green",
				        "00ffd9", "Blue"
				    ];
				}
				
				if (buttons.indexOf('A') > -1){
					toolbar = toolbar + 'link unlink ';
					valid_elements = valid_elements + ',a[href|target|class]';
					plugins = plugins + ' link';
				}
				
				if (buttons.indexOf('L') > -1){
					toolbar = toolbar + 'bullist ';
					valid_elements = valid_elements + ',ul,li';
					plugins = plugins + ' lists';
				}
	
				if (buttons.indexOf('H') > -1){
					toolbar = toolbar + 'h2 ';
					valid_elements = valid_elements + ',h2';
					setup.push(function(ed){
						ed.ui.registry.addButton('h2', {
			    			title : 'Subheader',
			    			image : _cms_base + 'modules/cms/img/tinymce_h_icon.png',
			    			onclick : function() {
			    				ed.execCommand('FormatBlock', false, 'h2');
			    			}
			    		});					
					})
				}
				
				if (buttons.indexOf('Q') > -1){
					toolbar = toolbar + 'blockquote ';
					valid_elements = valid_elements + ',q,blockquote';
				}

				if (buttons.indexOf('P') > -1){
					valid_elements = valid_elements + ',p';
					extra_init.force_br_newlines = false;
					extra_init.force_p_newlines = true;
				}
				
				// media selector
				if (buttons.indexOf('M') > -1){
					
					toolbar = toolbar + '| image ';
					valid_elements = valid_elements + ',img[style|align|src]';
					plugins = plugins + ' image';
					extra_init.urlconverter_callback = 'cms_input_textarea_srcconverter';
					
					toolbar = toolbar + 'styleselect ';
					if ($this.data('styles')){
						
						extra_init.style_formats = [];
						
						var style_data = JSON.parse($this.data('styles').replace(/~/g, '"'));
						 
						console.log(style_data);
						
						style_data.forEach(function(value){
							
							extra_init.style_formats.push({
						    	title: value.name,
							    selector: 'img',
							    styles: value.style
						    });

						});

					}

					extra_init.file_browser_callback = cms_input_textarea_media_browser;

				}
				
				tinymce.init($.extend({
					selector: '.cms_tinymce_' + i, 
					valid_elements: valid_elements, 
					toolbar: toolbar,
					mode : 'textareas',
					theme: 'silver',
					content_css: _cms_base + $this.data('html_css'),
					body_class: $this.data('html_class') + ' cms_tinymce_body',
				    forced_root_block : '',
				    menubar: false,
				    statusbar: false,
				    plugins: 'code paste' + plugins,
			    	paste_text_sticky : true,
			    	remove_linebreaks : false,
			    	convert_urls : false,
			    	height: 'calc(100% - 2.5rem)',
			    	setup : function(ed) {
			    		ed.on('init', function(ed) {
			    			ed.pasteAsPlainText = true;
			    	    });
			    		ed.on('change', function () {
			                ed.save();
			            });
			    		$.each(setup, function(key, value){
			    			value(ed);
			    		});
			    	}
				}, extra_init));

				i++;
				
			}

		});

	}, 200);
	
	setTimeout(() => {
		$('.cms_input_textarea textarea').each(function(){

			$this = $(this);
			if (!$this.closest('.cms_page_panel_fields').length){
				$this.css({'height':parseInt($this.css('line-height')) * parseInt($this.data('lines')) + 7 + 'px'});
			}
		});
	}, 30)

}

function cms_input_textarea_resize(){
		
}

function cms_input_textarea_media_browser(field_name, url, type, win){
	
	// console.log("Field_Name: " + field_name + "nURL: " + url + "nType: " + type + "nWin: " + win);
	
	$('.mce-widget,.mce-floatpanel,.mce-reset').hide();
	cms_input_image_load_images({
		'input_selector': '#' + field_name,
		'path_type': 'root',
		'after': function(params){
			
			$('.mce-widget,.mce-floatpanel,.mce-reset').show();
			
			// resize image
			get_api('cms/image_resize', {
				'do':'resize',
				'name': params.name,
				'success': function(data){
					
					$('#' + field_name).val('img/' + data.result.filename);
					
				}
			})

		}
	});

}

$(document).ready(function() {
	
	$(window).on('resize.cms', function(){
		cms_input_textarea_resize();
	});

	cms_input_textarea_init();

	cms_input_textarea_resize();
	
});




