var search_button_loading = false

function search_button_close_cart_if_open(){

	if ($('.cart_container').hasClass('cart_visible')){
		$('.cart_container').removeClass('cart_visible')
		get_ajax_panel('shop/cart', {'do': 'set_visible', 'value': '0'})
	}

}

function search_button_modal_top_px(){

	var top = 0
	var menu = document.querySelector('.menu_container')
	if (menu){
		top = menu.getBoundingClientRect().bottom
	}

	var cat = document.querySelector('.category_menu')
	if (cat){
		var cat_bottom = cat.getBoundingClientRect().bottom
		if (cat_bottom > top){
			top = cat_bottom
		}
	}

	return Math.max(0, Math.round(top))

}

function search_button_position_modal(){

	var $modal = $('.button_modal')
	if (!$modal.length){
		return
	}

	// CSS subtracts 0.01rem from --search_modal_top to close the menu/modal gap
	var top = search_button_modal_top_px()
	$modal.css({
		'--search_modal_top': top + 'px'
	})

}

function search_button_is_open(){

	return $('.button_container').hasClass('button_open')

}

function search_button_focus_input(){

	var $input = $('.button_modal_body .search_input').first()
	if ($input.length){
		$input.trigger('focus')
	}

}

function search_button_load_search(done){

	var $body = $('.button_modal_body')
	if (!$body.length){
		if (typeof done === 'function'){
			done()
		}
		return
	}

	// Already loaded
	if ($body.children().length){
		if (typeof done === 'function'){
			done()
		}
		return
	}

	if (search_button_loading){
		return
	}
	search_button_loading = true

	get_ajax_panel('search/search', {}, function(result){

		search_button_loading = false
		var html = result && result.result && (result.result._html || result.result.html)
		if (html){
			$body.html(html)
			if (typeof cursor_init === 'function'){
				cursor_init()
			}
		}
		if (typeof done === 'function'){
			done()
		}

	})

}

function search_button_open(){

	search_button_close_cart_if_open()
	$('.menu_container').removeClass('menu_items_active')

	search_button_position_modal()
	$('.button_container').addClass('button_open')
	$('.button_modal').attr('aria-hidden', 'false')
	$('.menu_search').addClass('menu_search_open')

	search_button_load_search(function(){
		search_button_focus_input()
	})

}

function search_button_close(){

	$('.button_container').removeClass('button_open')
	$('.button_modal').attr('aria-hidden', 'true')
	$('.menu_search').removeClass('menu_search_open')

}

function button_init(){

	$('.button_trigger').off('click.cms_search_button').on('click.cms_search_button', function(e){

		e.preventDefault()
		e.stopPropagation()

		if (search_button_is_open()){
			search_button_close()
			return
		}

		search_button_open()

	})

	$(document).off('click.cms_search_button').on('click.cms_search_button', function(e){
		if (!search_button_is_open()){
			return
		}
		if ($(e.target).closest('.button_modal, .button_trigger, .menu_search').length){
			return
		}
		search_button_close()
	})

	$(document).off('keydown.cms_search_button').on('keydown.cms_search_button', function(e){
		if (e.key === 'Escape' || e.keyCode === 27){
			search_button_close()
		}
	})

}

function button_resize(){

	if (search_button_is_open()){
		search_button_position_modal()
	}

}

function button_scroll(){

	if (search_button_is_open()){
		search_button_position_modal()
	}

}

$(document).ready(function(){

	$(window).on('resize.cms', button_resize)
	$(window).on('scroll.cms', button_scroll)

	button_init()
	button_resize()
	button_scroll()

})
