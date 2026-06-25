var analytics_beacon_pageview_token = ''
var analytics_beacon_timers = []
var analytics_beacon_max_scroll = 0
var analytics_beacon_engagement = false
var analytics_beacon_heartbeat_seconds = [5, 10, 20, 30, 60, 120, 180, 240, 300]

function analytics_beacon_get_scroll_pct() {

	var doc = document.documentElement
	var scroll_top = window.pageYOffset || doc.scrollTop || 0
	var scroll_height = Math.max(doc.scrollHeight, doc.offsetHeight, doc.clientHeight) - window.innerHeight
	if (scroll_height <= 0) {
		return 100
	}
	return Math.min(100, Math.round((scroll_top / scroll_height) * 100))

}

function analytics_beacon_on_scroll() {

	var pct = analytics_beacon_get_scroll_pct()
	if (pct > analytics_beacon_max_scroll) {
		analytics_beacon_max_scroll = pct
	}

}

function analytics_beacon_clear_timers() {

	analytics_beacon_timers.forEach(t => clearTimeout(t))
	analytics_beacon_timers = []

}

function analytics_beacon_send_heartbeat(seconds) {

	if (!analytics_beacon_pageview_token) {
		return
	}

	var data = new FormData()
	data.append('do', 'heartbeat')
	data.append('pageview_token', analytics_beacon_pageview_token)
	data.append('seconds', seconds)
	data.append('scroll_pct', analytics_beacon_max_scroll)

	var url = _cms_get_base() + 'analytics/beacon'
	if (navigator.sendBeacon) {
		navigator.sendBeacon(url, data)
	} else {
		fetch(url, { method: 'POST', body: data, keepalive: true })
	}

}

function analytics_beacon_schedule_heartbeats() {

	if (!analytics_beacon_engagement) {
		return
	}

	analytics_beacon_clear_timers()

	analytics_beacon_heartbeat_seconds.forEach(seconds => {
		analytics_beacon_timers.push(setTimeout(() => {
			analytics_beacon_send_heartbeat(seconds)
		}, seconds * 1000))
	})

}

function analytics_beacon_record_pageview(page) {

	analytics_beacon_clear_timers()
	analytics_beacon_pageview_token = ''
	analytics_beacon_max_scroll = analytics_beacon_get_scroll_pct()

	var params = {
		do: 'hit',
		page: page || (window.location.pathname + window.location.hash),
		viewport_w: window.innerWidth || 0,
		viewport_h: window.innerHeight || 0,
	}

	return fetch(_cms_get_base() + 'analytics/beacon', {
		method: 'POST',
		headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
		body: new URLSearchParams(params),
		keepalive: true,
	})
		.then(r => r.json())
		.then(data => {
			if (data && data.result && data.result.pageview_token) {
				analytics_beacon_pageview_token = data.result.pageview_token
				analytics_beacon_schedule_heartbeats()
			}
		})
		.catch(() => {})

}

function beacon_pageview(page) {

	return analytics_beacon_record_pageview(page)

}

function analytics_beacon_on_position_nav(final_url, title) {

	var $a = $('<a href="' + (final_url || window.location.href) + '"></a>')
	var page = $a[0].pathname + $a[0].hash
	analytics_beacon_record_pageview(page)

}

function analytics_beacon_init() {

	var $container = $('.analytics_beacon_container')
	if (!$container.length) {
		return
	}

	analytics_beacon_engagement = $container.data('collect_engagement') == 1
	var delay = parseInt($container.data('delay'), 10) || 0

	$(window).off('scroll.analytics_beacon').on('scroll.analytics_beacon', analytics_beacon_on_scroll)

	if (typeof cms_position_link_after === 'undefined') {
		cms_position_link_after = []
	}
	if (!window.analytics_beacon_position_hook_ok) {
		window.analytics_beacon_position_hook_ok = true
		cms_position_link_after.push(() => {
			analytics_beacon_on_position_nav(window.location.pathname + window.location.hash, document.title)
		})
	}

	setTimeout(() => {
		analytics_beacon_record_pageview(window.location.pathname + window.location.hash)
	}, delay)

}

$(document).ready(function() {

	analytics_beacon_init()

})