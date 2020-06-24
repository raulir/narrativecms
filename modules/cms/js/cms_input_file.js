
function cms_input_file_rename(input_name){
	
	var $content = $('.cms_input_file_content_' + input_name);
	
	$content.append('<div class="cms_input_file_rename_container">' + 
			'<input type="text" class="cms_input_file_rename_input"><div class="cms_input_file_rename_extension"></div>' + 
			'<div class="cms_input_file_rename_ok">v</div><div class="cms_input_file_rename_cancel">x</div></div>');
	
	// data into box
	var value = $('.cms_file_input_' + input_name).val();
	var full_filename = value.split('/').pop();
	var extension = '.' + (full_filename.split('.').pop());
	var filename = full_filename.slice(0, -(extension.length));
	
	$('.cms_input_file_rename_input', $content).val(filename).focus();
	$('.cms_input_file_rename_extension', $content).html(extension);
	
	// if cancel is clicked
	$('.cms_input_file_rename_cancel', $content).on('click.cms', function(){
		$('.cms_input_file_rename_container', $content).remove();
	});
	
	// if ok is clicked
	$('.cms_input_file_rename_ok', $content).on('click.cms', function(){
		
		get_ajax('cms/cms_input_file', {
			'do': 'cms_file_rename', 
			'old_name': $('.cms_file_input_' + input_name).val(),
			'new_name': $('.cms_input_file_rename_input', $content).val(),
			success: function(data){
				
				$('.cms_input_file_rename_container', $content).remove();
				
				// update fields
				$content.html(data.result.new_short_name);
				$('.cms_file_input_' + input_name).val(data.result.new_filename)
				
		    	// update download button
		    	$('.cms_input_file_download', $content.closest('.cms_input_file')).attr('href', config_url + 'files/get/' + data.result.new_filename.replace(/\//g, '__'));
				
				cms_notification('File renamed', 3);
				
			}
		});

	});
	
}

function cms_input_file_upload(params){
	
	var data = new FormData( $('.cms_input_file_form').get(0) );
	data.append('panel_id', 'cms/cms_input_file_upload');
	
	$.ajax( {
		url: config_url + 'ajax_api/get_panel',
	    type: 'POST',
	    data: data,
	    processData: false,
	    contentType: false,
	    dataType: 'json',
	    success: function(data){
	    	$(params.input_selector).val(data.result.filename);
	    	$(params.name_selector).html(data.result.name);
	    	$(params.name_selector).parent().parent().siblings('.cms_input_cms_file_id').val(data.result.cms_file_id);
	    	$(params.name_selector).parent().parent().siblings('.cms_input_name').val(data.result.name);
	    	$(params.name_selector).parent().parent().siblings('.cms_input_date_posted').val(data.result.date_posted);
	    	
	    	// update download button
	    	$(params.name_selector).siblings('.cms_input_file_buttons').children('.cms_input_file_download').attr('href', config_url + 'files/get/' + data.result.filename.replace(/\//g, '__'));
	    	
	    	// update rename button
	    	$('.cms_input_file_button_disabled' ,$(params.name_selector).closest('.cms_input_file')).removeClass('cms_input_file_button_disabled');
	    	
	    	// clear hidden upload form
	    	$('.cms_input_file_form_file').val('');
	    	
			cms_notification('File uploaded', 3);
	    	
	    },
	    xhr: function() {
	        var xhr = new window.XMLHttpRequest();

	        xhr.upload.addEventListener('progress', function(evt) {
	        	if (evt.lengthComputable) {
	        	  
	        		var percentComplete = evt.loaded / evt.total;
	        		percentComplete = parseInt(percentComplete * 100);

	        		$(params.name_selector).html('<div style="float: right; background-color: #efefef; height: 100%; width: ' + (100 - percentComplete) 
	        				+ '%; "></div><div style="width: 100%; height: 100%; top: 0px; left: 0px; position: absolute; ">' + percentComplete + '%</div>');

	        	}
	        }, false);

	        return xhr;
	    }
	} );

}

function cms_input_file_init(){

	if(!$('.cms_input_file_form').length){
		$('.cms_admin_content').append('<form class="cms_input_file_form" method="post" enctype="multipart/form-data" style="display: none; ">' +
				'<input type="hidden" name="do" value="cms_input_file_upload"><input type="file" name="new_file" class="cms_input_file_form_file"></form>');
	}
	
	$('.cms_input_file_form_file').off('change.r').on('change.r', function(){
		if ($('.cms_input_file_form_file').val()){
			var input_name = $(this).data('name');
			cms_input_file_upload({
				'input_selector': '.cms_file_input_' + input_name,
				'name_selector': '.cms_input_file_content_' + input_name,
				'name': input_name
			});
		}
	});
	
	// upload
	$('.cms_input_file_button').off('click.r').on('click.r', function(event){

		var $this = $(this);
	
		if($this.data('accept')){
			$('.cms_input_file_form_file').attr('accept', $this.data('accept'))
		} else {
			$('.cms_input_file_form_file').attr('accept', '');
		}
		
		$('.cms_input_file_form_file').data('name', $this.data('name')).click();			
	
	});
	
	// clear
	$('.cms_input_file_clear').off('click.r').on('click.r', function(event){
		
		if ($(this).hasClass('cms_input_file_button_disabled')){
			return;
		}
		
		var input_name = $(this).data('name');
		$('.cms_file_input_' + input_name + '').val('');
		$('.cms_input_file_content_' + input_name).html('-- no file --');
		
    	// update rename button
    	$('.cms_input_file_rename' ,'.cms_input_file_container_' + input_name).addClass('cms_input_file_button_disabled');
    	
    	// update clear button
    	$('.cms_input_file_clear' ,'.cms_input_file_container_' + input_name).addClass('cms_input_file_button_disabled');
		
    	// update download button
    	$('.cms_input_file_download' ,'.cms_input_file_container_' + input_name).addClass('cms_input_file_button_disabled');
    	
	});

	// rename
	$('.cms_input_file_rename').off('click.r').on('click.r', function(event){
		
		if ($(this).hasClass('cms_input_file_button_disabled')){
			return;
		}
		
		var input_name = $(this).data('name');
		cms_input_file_rename(input_name);
    	
	});
	
}

$(document).ready(function() {
	
	cms_input_file_init();

});
