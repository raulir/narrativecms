/**
 * Currency selector: writes selection to container data-value and #currency_selector_value.
 * Parents listen to change on .currency_selector_value (or read data-value).
 */

function currency_selector_set($container, currency_id, label){

	currency_id = currency_id === undefined || currency_id === null ? '' : String(currency_id)
	label = label === undefined || label === null ? '' : String(label).trim()

	$container.attr('data-value', currency_id)
	var $input = $container.find('.currency_selector_value').first()
	if ($input.length){
		$input.val(currency_id)
		$input.trigger('change')
	}
	$container.find('.currency_selector_button_label').first().text(label)

}

function currency_selector_init($root){

	var $scope = $root ? $root.find('.currency_selector_container') : $('.currency_selector_container')

	$scope.not('.currency_selector_ok').each(function(){

		var $container = $(this)
		$container.addClass('currency_selector_ok')

		$container.find('.currency_selector_option').on('click.cms', function(){

			var cid = $(this).attr('data-currency_id')
			if (cid === undefined || cid === null){
				cid = ''
			}
			cid = String(cid)
			var label = String($(this).text() || '').trim()
			currency_selector_set($container, cid, label)

		})

	})

}

function currency_selector_resize(){
}

function currency_selector_scroll(){
}

$(document).ready(function(){

	currency_selector_init()
	$(window).on('resize.cms', currency_selector_resize)
	$(window).on('scroll.cms', currency_selector_scroll)

})
