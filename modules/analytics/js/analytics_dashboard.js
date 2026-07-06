var analytics_dashboard_detail_open_id = ''
var analytics_dashboard_detail_open_type = ''

var analytics_dashboard_session_fields = [
	['session_id', 'Session ID'],
	['started', 'Started'],
	['last_activity', 'Last activity'],
	['pageviews', 'Pageviews'],
	['total_seconds', 'Total seconds'],
	['language', 'Language'],
	['first_page', 'First page'],
	['last_page', 'Last page'],
	['ip_anonymised', 'IP'],
	['user_agent', 'User agent'],
	['country', 'Country'],
	['region', 'Area'],
	['city', 'City'],
	['geo_resolved', 'Geo resolved'],
]

var analytics_dashboard_pageview_fields = [
	['cms_analytics_pageview_id', 'Pageview ID'],
	['pageview_token', 'Pageview token'],
	['session_id', 'Session ID'],
	['beacon_id', 'Beacon ID'],
	['language', 'Language'],
	['created', 'Created'],
	['updated', 'Updated'],
	['page', 'Page'],
	['ip_anonymised', 'IP'],
	['user_agent', 'User agent'],
	['viewport_w', 'Viewport width'],
	['viewport_h', 'Viewport height'],
	['seconds', 'Seconds'],
	['scroll_pct', 'Scroll %'],
	['bot', 'Bot'],
	['country', 'Country'],
	['region', 'Area'],
	['city', 'City'],
	['geo_resolved', 'Geo resolved'],
]

function analytics_dashboard_escape(text) {

	return String(text === undefined || text === null ? '' : text)
		.replace(/&/g, '&amp;')
		.replace(/</g, '&lt;')
		.replace(/>/g, '&gt;')
		.replace(/"/g, '&quot;')

}

function analytics_dashboard_build_detail_html(row, fields) {

	var html = '<table class="analytics_dashboard_detail_table"><tbody>'

	fields.forEach(function(field) {
		var key = field[0]
		var label = field[1]
		var value = row[key]
		if (value === undefined || value === null) {
			value = ''
		}
		html += '<tr><th>' + analytics_dashboard_escape(label) + '</th>'
		html += '<td class="analytics_dashboard_detail_value">' + analytics_dashboard_escape(value) + '</td></tr>'
	})

	html += '</tbody></table>'

	return html

}

function analytics_dashboard_find_row(type, row_id) {

	if (typeof analytics_dashboard_rows === 'undefined' || !analytics_dashboard_rows) {
		return null
	}

	var list = type === 'session' ? (analytics_dashboard_rows.sessions || []) : (analytics_dashboard_rows.pageviews || [])
	var id_key = type === 'session' ? 'session_id' : 'cms_analytics_pageview_id'

	for (var i = 0; i < list.length; i++) {
		if (String(list[i][id_key]) === String(row_id)) {
			return list[i]
		}
	}

	return null

}

function analytics_dashboard_position_panel($button) {

	var $panel = $('.analytics_dashboard_detail_panel')
	if (!$panel.length || !$button.length) {
		return
	}

	var rect = $button[0].getBoundingClientRect()
	var panel_width = Math.min(520, window.innerWidth - 24)
	var left = Math.max(12, rect.right - panel_width)
	var top = rect.bottom + 6

	if (top + 280 > window.innerHeight) {
		top = Math.max(12, rect.top - 280)
	}

	$panel.css({
		left: left + 'px',
		top: top + 'px',
		width: panel_width + 'px',
	})

}

function analytics_dashboard_close_detail() {

	$('.analytics_dashboard_detail_panel').attr('hidden', 'hidden')
	$('.analytics_dashboard_details_button').removeClass('analytics_dashboard_details_button_active')
	analytics_dashboard_detail_open_id = ''
	analytics_dashboard_detail_open_type = ''

}

function analytics_dashboard_open_detail($button) {

	var type = $button.data('row_type')
	var row_id = String($button.data('row_id') || '')
	var row = analytics_dashboard_find_row(type, row_id)

	if (!row) {
		return
	}

	if (analytics_dashboard_detail_open_type === type && analytics_dashboard_detail_open_id === row_id) {
		analytics_dashboard_close_detail()
		return
	}

	var fields = type === 'session' ? analytics_dashboard_session_fields : analytics_dashboard_pageview_fields
	var title = type === 'session' ? 'Session details' : 'Pageview details'

	$('.analytics_dashboard_details_button').removeClass('analytics_dashboard_details_button_active')
	$button.addClass('analytics_dashboard_details_button_active')

	$('.analytics_dashboard_detail_panel_inner').html(
		'<div class="analytics_dashboard_detail_header">'
		+ '<div class="analytics_dashboard_detail_title">' + analytics_dashboard_escape(title) + '</div>'
		+ '<button type="button" class="analytics_dashboard_details_button analytics_dashboard_detail_delete_button" data-row_type="' + analytics_dashboard_escape(type) + '" data-row_id="' + analytics_dashboard_escape(row_id) + '">Delete</button>'
		+ '</div>'
		+ analytics_dashboard_build_detail_html(row, fields)
	)

	analytics_dashboard_position_panel($button)
	$('.analytics_dashboard_detail_panel').removeAttr('hidden')

	analytics_dashboard_detail_open_type = type
	analytics_dashboard_detail_open_id = row_id

}

function analytics_dashboard_delete_visitor(type, row_id) {

	get_ajax_panel('cms/cms_popup_yes_no', {
		text: 'Delete this visitor session and all related pageviews?',
	}, function(data) {
		panels_display_popup(data.result._html, {
			yes: function() {
				get_ajax_panel('analytics/analytics_dashboard', {
					do: 'delete_visitor',
					row_type: type,
					row_id: row_id,
					no_html: 1,
				}, function(delete_data) {
					if (delete_data.result && delete_data.result.deleted) {
						window.location.reload()
						return
					}
					alert('Delete failed')
				})
			},
		})
	})

}

function analytics_dashboard_init() {

	var $container = $('.analytics_dashboard_container')

	if (!$container.length) {
		return
	}

	$container.off('click.cms', '.analytics_dashboard_detail_delete_button').on('click.cms', '.analytics_dashboard_detail_delete_button', function(e) {
		e.preventDefault()
		e.stopPropagation()
		analytics_dashboard_delete_visitor($(this).data('row_type'), String($(this).data('row_id') || ''))
	})

	$container.off('click.cms', '.analytics_dashboard_details_button').on('click.cms', '.analytics_dashboard_details_button', function(e) {
		e.preventDefault()
		e.stopPropagation()
		analytics_dashboard_open_detail($(this))
	})

	$(document).off('click.cms.analytics_dashboard_detail').on('click.cms.analytics_dashboard_detail', function(e) {
		if ($(e.target).closest('.analytics_dashboard_detail_panel, .analytics_dashboard_details_button').length) {
			return
		}
		analytics_dashboard_close_detail()
	})

	$(window).off('resize.cms.analytics_dashboard_detail').on('resize.cms.analytics_dashboard_detail', function() {
		if (!analytics_dashboard_detail_open_id) {
			return
		}
		var $button = $('.analytics_dashboard_details_button.analytics_dashboard_details_button_active').first()
		if ($button.length) {
			analytics_dashboard_position_panel($button)
		}
	})

}

$(document).ready(function() {

	analytics_dashboard_init()

})