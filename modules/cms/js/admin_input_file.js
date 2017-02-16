function admin_input_file_rename(old_name){
	
	// TODO: check if such name exists on page
	
	var new_name = ('0000'+Math.random().toString(36).replace('.', '')).substr(-5);
	
	$('.admin_input_file_container_' + old_name + ' label').attr({'for':'admin_input_file_' + new_name});
	$('.admin_input_file_container_' + old_name + ' .admin_file_content')
			.removeClass('admin_input_file_content_' + old_name).addClass('admin_input_file_content_' + new_name);
	$('.admin_input_file_container_' + old_name + ' .admin_input_button').data('name', new_name);
	$('.admin_input_file_container_' + old_name + ' input').removeClass('cms_file_input_' + old_name).addClass('cms_file_input_' + new_name);
	
	$('.admin_input_file_container_' + old_name).removeClass('admin_input_file_container_' + old_name).addClass('admin_input_file_container_' + new_name);
	
}

function cms_file_upload(params){
	
	var data = new FormData( $('.new_file_form').get(0) );
	data.append('panel_id', 'cms_file_upload');
	
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
	    	$(params.name_selector).parent().parent().siblings('.admin_input_cms_file_id').val(data.result.cms_file_id);
	    	$(params.name_selector).parent().parent().siblings('.admin_input_name').val(data.result.name);
	    	$(params.name_selector).parent().parent().siblings('.admin_input_date_posted').val(data.result.date_posted);
	    	if ($(params.name_selector).parent().parent().siblings('.admin_input').children('.admin_input_title').val() == ''){
		    	$(params.name_selector).parent().parent().siblings('.admin_input').children('.admin_input_title').val(data.result.name);
	    	}
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

function admin_input_file_init(){

	$('.new_file_form').remove();
	$('.admin_content').append('<form class="new_file_form" method="post" enctype="multipart/form-data" style="display: none; ">' +
			'<input type="hidden" name="do" value="cms_file_upload"><input type="file" name="new_file" class="new_file"></form>');
	
	$('.new_file').on('change.r', function(){
		var input_name = $(this).data('name');
		cms_file_upload({
			'input_selector': '.cms_file_input_' + input_name,
			'name_selector': '.admin_input_file_content_' + input_name
		});
	});
	
	$('.admin_input_file_button').off('click.r').on('click.r', function(event){
		
		var $this = $(this);
	
		if($this.data('accept')){
			$('.new_file').attr('accept', $this.data('accept'))
		} else {
			$('.new_file').attr('accept', '');
		}
		
		$('.new_file').data('name', $this.data('name')).click();			
	
	});
	
	$('.admin_input_file_clear').off('click.r').on('click.r', function(event){
		
		var input_name = $(this).data('name');
		$('.cms_file_input_' + input_name + '').val('');
		$('.admin_input_file_content_' + input_name).html('-- no file --');
		
	});

}

$(document).ready(function() {
	
	admin_input_file_init();

});
