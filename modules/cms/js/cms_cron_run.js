function cms_cron_run_init($root){

	var $scope = $root ? $root : $('body');

	if ($scope.hasClass('cms_cron_run_ok')){
		return;
	}

	$scope.addClass('cms_cron_run_ok');

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