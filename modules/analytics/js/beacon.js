var analytics_beacon_pageview_token = ''
var analytics_beacon_timers = []
var analytics_beacon_max_scroll = 0
var analytics_beacon_engagement = false
var analytics_beacon_heartbeat_seconds = [5, 10, 20, 30, 60, 120, 180, 240, 300]
var analytics_beacon_last_page = ''
var analytics_beacon_last_page_at = 0
var analytics_beacon_record_promise = null
var analytics_beacon_record_promise_page = ''

function analytics_beacon_get_container() {

	return $('.analytics_beacon_container').filter(function() {
		return $(this).data('js_tracking') == 1
	}).first()

}

function analytics_beacon_js_enabled() {

	return analytics_beacon_get_container().length > 0

}

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

	if (!analytics_beacon_js_enabled()) {
		return
	}

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

	if (!analytics_beacon_js_enabled() || !analytics_beacon_pageview_token) {
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

function analytics_beacon_reset_page_dedup() {

	analytics_beacon_last_page = ''
	analytics_beacon_last_page_at = 0
	analytics_beacon_record_promise = null
	analytics_beacon_record_promise_page = ''

}

function analytics_beacon_record_pageview(page) {

	if (!analytics_beacon_js_enabled()) {
		return Promise.resolve()
	}

	page = page || (window.location.pathname + window.location.hash)
	var now = Date.now()

	if (analytics_beacon_record_promise && analytics_beacon_record_promise_page === page) {
		return analytics_beacon_record_promise
	}

	if (page === analytics_beacon_last_page && analytics_beacon_pageview_token && (now - analytics_beacon_last_page_at) < 2000) {
		return Promise.resolve()
	}

	analytics_beacon_clear_timers()
	analytics_beacon_pageview_token = ''
	analytics_beacon_max_scroll = analytics_beacon_get_scroll_pct()
	analytics_beacon_last_page = page
	analytics_beacon_last_page_at = now

	var params = {
		do: 'hit',
		page: page,
		viewport_w: window.innerWidth || 0,
		viewport_h: window.innerHeight || 0,
	}

	var $beacon_container = analytics_beacon_get_container()
	if ($beacon_container.length && $beacon_container.data('beacon_id')) {
		params.beacon_id = $beacon_container.data('beacon_id')
	}

	analytics_beacon_record_promise_page = page
	analytics_beacon_record_promise = fetch(_cms_get_base() + 'analytics/beacon', {
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
		.finally(() => {
			if (analytics_beacon_record_promise_page === page) {
				analytics_beacon_record_promise = null
				analytics_beacon_record_promise_page = ''
			}
		})

	return analytics_beacon_record_promise

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

	var $container = analytics_beacon_get_container()
	if (!$container.length) {
		return
	}

	analytics_beacon_engagement = $container.data('collect_engagement') == 1
	var delay = parseInt($container.data('delay'), 10) || 0

	$(window).off('scroll.analytics_beacon').on('scroll.analytics_beacon', analytics_beacon_on_scroll)

	if (!window.cms_position_link_after) {
		window.cms_position_link_after = []
	}
	if (!window.analytics_beacon_position_hook_ok) {
		window.analytics_beacon_position_hook_ok = true
		window.cms_position_link_after.push(() => {
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