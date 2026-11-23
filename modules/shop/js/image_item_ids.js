function image_item_ids_sync($box){

	var ids = []
	$box.find('.image_item_ids_check:checked').each(function(){
		ids.push($(this).val())
	})
	$box.find('.image_item_ids_value').val(ids.join(','))

}

function image_item_ids_init($root){

	var $scope = $root ? $($root).find('.image_item_ids_container') : $('.image_item_ids_container')
	if ($root && $($root).hasClass('image_item_ids_container')){
		$scope = $scope.add($root)
	}

	$scope.not('.image_item_ids_ok').addClass('image_item_ids_ok').each(function(){

		var $box = $(this)
		$box.on('change.cms', '.image_item_ids_check', function(){
			image_item_ids_sync($box)
		})

	})

}


$(document).ready(function(){

	image_item_ids_init()

})
