function cms_input_transform_default_value(n){

	n = parseInt(n, 10) || 5
	if (n < 2){
		n = 5
	}
	var cells = n - 1
	var data = []
	var i, t, row

	// top
	row = []
	for (i = 0; i < n; i++){
		t = (i / cells) * 100
		row.push([cms_input_transform_round(t), 0])
	}
	data.push(row)

	// mid rows: left + right only
	for (i = 1; i < cells; i++){
		t = (i / cells) * 100
		data.push([
			[0, cms_input_transform_round(t)],
			[100, cms_input_transform_round(t)]
		])
	}

	// bottom
	row = []
	for (i = 0; i < n; i++){
		t = (i / cells) * 100
		row.push([cms_input_transform_round(t), 100])
	}
	data.push(row)

	return {
		width: cells,
		height: cells,
		maxx: 100,
		maxy: 100,
		units: 'percent',
		data: data
	}

}

function cms_input_transform_round(v){
	return Math.round(v * 100) / 100
}

function cms_input_transform_parse($container){

	var raw = $('.cms_input_transform_value', $container).val()
	if (!raw){
		return null
	}
	try {
		var v = JSON.parse(raw)
		if (v && v.data && v.data.length){
			return v
		}
	} catch (e){}
	return null

}

function cms_input_transform_perimeter(value){

	// Build closed ring of points [x%, y%] clockwise from TL
	if (!value || !value.data || !value.data.length){
		return []
	}
	var data = value.data
	var top = data[0] || []
	var bottom = data[data.length - 1] || []
	var mid = data.slice(1, -1)
	var pts = []
	var i

	for (i = 0; i < top.length; i++){
		pts.push(top[i])
	}
	for (i = 0; i < mid.length; i++){
		if (mid[i] && mid[i].length >= 2){
			pts.push(mid[i][mid[i].length - 1]) // right
		}
	}
	for (i = bottom.length - 1; i >= 0; i--){
		pts.push(bottom[i])
	}
	for (i = mid.length - 1; i >= 0; i--){
		if (mid[i] && mid[i].length >= 1){
			pts.push(mid[i][0]) // left
		}
	}
	return pts

}

function cms_input_transform_display($container){

	var $svg = $('.cms_input_transform_preview_svg', $container)
	if (!$svg.length){
		return
	}
	$svg.empty()

	var value = cms_input_transform_parse($container)
	if (!value){
		return
	}

	var ring = cms_input_transform_perimeter(value)
	if (ring.length < 3){
		return
	}

	var points_attr = ring.map(function(p){
		return p[0] + ',' + p[1]
	}).join(' ')
	// close ring
	points_attr += ' ' + ring[0][0] + ',' + ring[0][1]

	var poly = document.createElementNS('http://www.w3.org/2000/svg', 'polyline')
	poly.setAttribute('points', points_attr)
	$svg[0].appendChild(poly)

	ring.forEach(function(p){
		var c = document.createElementNS('http://www.w3.org/2000/svg', 'circle')
		c.setAttribute('cx', p[0])
		c.setAttribute('cy', p[1])
		c.setAttribute('r', 1.2)
		$svg[0].appendChild(c)
	})

}

function cms_input_transform_layout($root){

	var $scope = $root ? $root.find('.cms_input_transform_container') : $('.cms_input_transform_container')

	$scope.each(function(){

		var $container = $(this)
		var $inner = $('.cms_input_transform_image_inner', $container)

		if ($inner.data('h')){
			var ratio = $inner.data('w') / $inner.data('h')
			var cratio = $inner.parent().innerWidth() / $inner.parent().innerHeight()
			if (ratio > cratio){
				$inner.css({ width: $inner.parent().innerWidth(), height: $inner.parent().innerWidth() / ratio })
			} else {
				$inner.css({ height: $inner.parent().innerHeight(), width: $inner.parent().innerHeight() * ratio })
			}
		}

		cms_input_transform_display($container)

	})

}

function cms_input_transform_init($root){

	cms_input_transform_layout($root)

	var $scope = $root ? $root.find('.cms_input_transform_container') : $('.cms_input_transform_container')

	$scope.not('.cms_input_transform_ok').each(function(){

		var $container = $(this)
		$container.addClass('cms_input_transform_ok')

		$('.cms_input_transform_set_button', $container).on('click.cms', function(){
			$container.addClass('cms_input_transform_active')
			cms_input_transform_open_picker($container)
		})

		$('.cms_input_transform_clear', $container).on('click.cms', function(){
			$('.cms_input_transform_value', $container).val('')
			cms_input_transform_display($container)
		})

	})

}

function cms_input_transform_open_picker($input){

	var points = $input.data('points') || 5
	var value = $('.cms_input_transform_value', $input).val() || ''

	get_ajax_panel('imagemaker/transform_picker', {
		image: $input.data('target_image') || '',
		value: value,
		points: points
	}, function(data){

		panels_display_popup(data.result._html, {
			select: function(){

				var $picker = $('.cms_transform_picker_container')
				if (typeof transform_picker_get_value === 'function'){
					var v = transform_picker_get_value($picker)
					$('.cms_input_transform_value', $('.cms_input_transform_active')).val(JSON.stringify(v))
					cms_input_transform_display($('.cms_input_transform_active'))
				}

				$('.cms_input_transform_active').removeClass('cms_input_transform_active')
				$('.cms_popup_container').remove()
			},
			cancel: function(){
				$('.cms_input_transform_active').removeClass('cms_input_transform_active')
				$('.cms_popup_container').remove()
			}
		})

	})

}

function cms_input_transform_resize(){
	cms_input_transform_layout()
}

function cms_input_transform_scroll(){

}

$(document).ready(function(){
	$(window).on('resize.cms', cms_input_transform_resize)
	$(window).on('scroll.cms', cms_input_transform_scroll)
	cms_input_transform_init()
	cms_input_transform_resize()
	cms_input_transform_scroll()
})
