function cms_cron_run_init(){

	setTimeout(function(){
		$.ajax({
			type: 'POST',
		  	url: _cms_base + 'cms_operations/cron/',
		});
	}, 1000);
	
}

$(document).ready(function() {
		
	cms_cron_run_init();
	
});
