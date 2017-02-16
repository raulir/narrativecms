
$(document).ready(function() {
	
	$( '#pages_list' ).sortable({
		'stop':function(event, ui){
			// save order
			var page_orders = {};
			$('#pages_list .page_id').each(function(index, value){
				page_orders[$(this).val()] = index + 1;
			});
			get_ajax('admin_save_page_order', {'do': 'admin_save_page_order', 'page_orders': page_orders});
		},
	}).disableSelection();
	
});
