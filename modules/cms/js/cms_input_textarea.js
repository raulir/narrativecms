function cms_input_textarea_init(){

	$('.admin_input_textarea textarea').each(function(){
		$this = $(this);
		$this.css({'height':parseInt($this.css('line-height')) * parseInt($this.data('lines')) + 3 + 'px'});
	});
	
	setTimeout(function(){
		
		// go over all tinymce elements
		var i = $('.cms_tinymce_formatted').length;
		
		$('.admin_tinymce').each(function(){
			
			var $this = $(this);
			
			if (!$this.hasClass('cms_tinymce_formatted')){

				$this.addClass('cms_tinymce_formatted');
				
				$this.addClass('admin_tinymce_' + i);
				
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
					valid_elements = valid_elements + ',a[href|target]';
					plugins = plugins + ' link';
				}
				
				if (buttons.indexOf('L') > -1){
					toolbar = toolbar + 'bullist ';
					valid_elements = valid_elements + ',ul,li';
				}
	
				if (buttons.indexOf('H') > -1){
					toolbar = toolbar + 'h2 ';
					valid_elements = valid_elements + ',h2';
					setup.push(function(ed){
						ed.addButton('h2', {
			    			title : 'Subheader',
			    			image : config_url + 'modules/cms/img/tinymce_h_icon.png',
			    			onclick : function() {
			    				ed.execCommand('FormatBlock', false, 'h2');
			    			}
			    		});					
					})
				}
				
				if (buttons.indexOf('P') > -1){
					valid_elements = valid_elements + ',p';
					extra_init.force_br_newlines = false;
					extra_init.force_p_newlines = true;
				}
				
				tinymce.init($.extend({
					selector: '.admin_tinymce_' + i, 
					valid_elements: valid_elements, 
					toolbar: toolbar,
					mode : 'textareas',
					theme: 'modern',
					content_css: config_url + $this.data('html_css'),
					body_class: $this.data('html_class') + ' admin_tinymce_body',
				    forced_root_block : '',
				    menubar: false,
				    statusbar: false,
				    plugins: 'code paste' + plugins,
			    	paste_text_sticky : true,
			    	remove_linebreaks : false,
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

}

function cms_input_textarea_resize(){
		
}

function cms_tinymce_img(field_name, url, type, win){
	
	// alert("Field_Name: " + field_name + "nURL: " + url + "nType: " + type + "nWin: " + win);
	$('.mce-widget,.mce-container,.mce-reset').hide();
	cms_input_image_load_images({
		'input_selector': '#' + field_name,
		'path_type': 'root',
		'after': function(){
			$('.mce-widget,.mce-container,.mce-reset').show();
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




