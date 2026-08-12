// shop/products — filter bar + ajax grid reload

var products_filter = {
	category_id: 0,
	subcategory_id: 0,
	collection_id: 0
}

function products_init($root){

	var $scope = $root ? $root.find('.products_container') : $('.products_container')

	$scope.not('.products_ok').each(function(){

		var $el = $(this)
		$el.addClass('products_ok')

		products_filter.category_id = parseInt($el.data('category_id'), 10) || 0
		products_filter.subcategory_id = parseInt($el.data('subcategory_id'), 10) || 0
		products_filter.collection_id = parseInt($el.data('collection_id'), 10) || 0

		// Category dropdown toggle
		$el.on('click.cms', '.products_menu_category_toggle', function(e){

			e.preventDefault()
			e.stopPropagation()

			var $wrap = $(this).closest('.products_menu_category')
			var $coll = $el.find('.products_menu_collection')

			$coll.removeClass('products_menu_collection_open')

			// If a collection is selected, toggle does not clear category — only open/close
			$wrap.toggleClass('products_menu_category_open')

		})

		// Category option
		$el.on('click.cms', '.products_menu_category_option', function(e){

			e.preventDefault()
			e.stopPropagation()

			var $opt = $(this)
			var cat_id = parseInt($opt.data('category_id'), 10) || 0

			products_filter.category_id = cat_id
			// Changing category clears subcategory and collection
			products_filter.subcategory_id = 0
			products_filter.collection_id = 0

			$el.find('.products_menu_category_open').removeClass('products_menu_category_open')
			products_reload($el)

		})

		// Subcategory pill
		$el.on('click.cms', '.products_menu_item[data-filter="subcategory"]', function(e){

			e.preventDefault()

			var $this = $(this)
			products_filter.subcategory_id = parseInt($this.data('subcategory_id'), 10) || 0

			// Local active state until menu reloads
			$el.find('.products_menu_item_all_subs, .products_menu_item_subcategory')
					.removeClass('products_menu_item_active')
			$this.addClass('products_menu_item_active')

			products_reload($el)

		})

		// Collection dropdown toggle / clear when selected
		$el.on('click.cms', '.products_menu_collection_toggle', function(e){

			e.preventDefault()
			e.stopPropagation()

			var $wrap = $(this).closest('.products_menu_collection')
			$el.find('.products_menu_category').removeClass('products_menu_category_open')

			if ($wrap.hasClass('products_menu_collection_selected')){
				// Selected → clear collection
				products_filter.collection_id = 0
				products_reload($el)
				return
			}

			$wrap.toggleClass('products_menu_collection_open')

		})

		// Collection option
		$el.on('click.cms', '.products_menu_collection_option', function(e){

			e.preventDefault()
			e.stopPropagation()

			var $opt = $(this)
			products_filter.collection_id = parseInt($opt.data('collection_id'), 10) || 0

			$el.find('.products_menu_collection_open').removeClass('products_menu_collection_open')
			products_reload($el)

		})

		// Click outside closes dropdowns
		$(document).off('click.cms_products_dd').on('click.cms_products_dd', function(e){
			if (!$(e.target).closest('.products_menu_category, .products_menu_collection').length){
				$('.products_menu_category_open').removeClass('products_menu_category_open')
				$('.products_menu_collection_open').removeClass('products_menu_collection_open')
			}
		})

	})

}

/**
 * Reload menu (pills / collection list) and grid for current filter state.
 */
function products_reload($el){

	if (!$el || !$el.length){
		$el = $('.products_container').first()
	}
	if (!$el.length){
		return
	}

	var params = {
		category_id: products_filter.category_id,
		subcategory_id: products_filter.subcategory_id,
		collection_id: products_filter.collection_id
	}

	$el.attr('data-category_id', params.category_id)
	$el.attr('data-subcategory_id', params.subcategory_id)
	$el.attr('data-collection_id', params.collection_id)
	$el.data('category_id', params.category_id)
	$el.data('subcategory_id', params.subcategory_id)
	$el.data('collection_id', params.collection_id)

	var $menu_area = $el.find('.products_menu_area')
	var $grid_area = $el.find('.products_grid_area')

	get_ajax_panel('shop/products_menu', params, function(result){
		var html = result && result.result && (result.result.html || result.result._html)
		if (html && $menu_area.length){
			$menu_area.html(html)
		}
	})

	get_ajax_panel('shop/products_grid', params, function(result){
		var html = result && result.result && (result.result.html || result.result._html)
		if (html && $grid_area.length){
			$grid_area.html(html)
		}
	})

}

function products_resize(){
}

function products_scroll(){
}

$(document).ready(function(){

	products_init()
	products_resize()
	products_scroll()

	$(window).on('resize.cms', products_resize)
	$(window).on('scroll.cms', products_scroll)

})
