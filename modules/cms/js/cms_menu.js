
function cms_menu_open_direct($el){
	// Open only the direct child flyout (L2 or L3), not nested descendants
	$el.children('.cms_menu_children').addClass('cms_menu_children_hover')
}

function cms_menu_close_direct($el){
	$el.children('.cms_menu_children').removeClass('cms_menu_children_hover')
}

function cms_menu_close_l3_siblings($parent_row){
	$parent_row.siblings('.cms_menu_child_parent').each(function(){
		cms_menu_close_direct($(this))
	})
}

function cms_menu_init($root){

	var $scope = $root ? $root.find('.cms_menu_container') : $('.cms_menu_container')

	$scope.not('.cms_menu_ok').each(function(){

		var $container = $(this)

		$container.addClass('cms_menu_ok')

		// L1 parents — flyout under top bar
		$('.cms_menu_item.cms_menu_parent', $container).hover(
			function(){
				cms_menu_open_direct($(this))
			},
			function(){
				cms_menu_close_direct($(this))
				// L3 closes with L2
			}
		)

		$('.cms_menu_item.cms_menu_parent', $container).on('click.cms', function(e){
			// Do not hijack clicks on real menu links (L2 or L3)
			if ($(e.target).closest('a.cms_menu_link[href]').length){
				var href = $(e.target).closest('a.cms_menu_link').attr('href') || ''
				if (href && href !== 'javascript:void(0)' && href !== '#'){
					return
				}
			}
			// reopen L2 (Safari / touch)
			$('.cms_menu_children_hover', $container).removeClass('cms_menu_children_hover')
			cms_menu_open_direct($(this))
		})

		// L2 parents — flyout to the right
		$('.cms_menu_child_parent', $container).hover(
			function(){
				var $row = $(this)
				cms_menu_close_l3_siblings($row)
				cms_menu_open_direct($row)
			},
			function(){
				cms_menu_close_direct($(this))
			}
		)

		$('.cms_menu_child_parent', $container).on('click.cms', function(e){
			// Clicks on L3 (or other nested) real links must navigate
			var $link = $(e.target).closest('a.cms_menu_link')
			if ($link.length && $link.closest('.cms_menu_children_l3').length){
				var href = $link.attr('href') || ''
				if (href && href !== 'javascript:void(0)' && href !== '#'){
					return
				}
			}

			var $row = $(this)
			var $label = $row.children('.cms_menu_child_label')
			// No real navigation when L2 parent label is void only
			if (!$label.attr('href') || $label.attr('href') === 'javascript:void(0)'){
				e.preventDefault()
				e.stopPropagation()
			}
			cms_menu_close_l3_siblings($row)
			cms_menu_open_direct($row)
		})

	})

}

$(document).ready(function() {

	cms_menu_init()

})
