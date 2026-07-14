/**
 * In-page hash URL updates only (scroll-to / virtual page anchors).
 * Does not handle inter-page SPA navigation — that is cms_position_link.js.
 */

var cms_change_hash_busy = false

/**
 * Set only the #fragment of the current path+query (replaceState, no scroll jump).
 * @param {string} new_hash  "section", "#section", or "" / "_top" to clear
 */
function cms_change_hash(new_hash) {

	cms_change_hash_busy = true

	var fragment = (new_hash === undefined || new_hash === null) ? '' : String(new_hash)
	if (fragment === '_top') {
		fragment = ''
	}
	fragment = fragment.replace(/^#/, '')

	var path = window.location.pathname + window.location.search
	var target = fragment ? (path + '#' + fragment) : path

	if (history && history.replaceState) {
		history.replaceState(history.state || {}, '', target)
	} else {
		var scrollV = document.documentElement.scrollTop
		var scrollH = document.documentElement.scrollLeft
		location.hash = fragment ? ('#' + fragment) : ''
		document.documentElement.scrollTop = scrollV
		document.documentElement.scrollLeft = scrollH
	}

	setTimeout(function() {
		cms_change_hash_busy = false
	}, 20)

}

function change_hash(new_hash) {
	cms_change_hash(new_hash)
}
