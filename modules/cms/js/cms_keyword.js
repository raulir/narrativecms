
function cms_keyword_init(){

	$('.cms_keyword_save').on('click.r', function(e){
		
		e.stopPropagation();
		$('.admin_form').submit();
		return false;
		
	});

	$('.cms_keyword_delete').on('click.r', function(e){
		
		e.stopPropagation();
		
		get_ajax('cms_keyword', {
			'do': 'cms_keyword_delete',
			'cms_keyword_id': $('#cms_keyword_id').val(),
			'success': function(){
				window.location.href = config_url + 'admin/keywords/';
			}
		})
		

		
	});

}

$(document).ready(function() {
	
	cms_keyword_init();
	
});