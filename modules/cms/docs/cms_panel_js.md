# Panel JavaScript contracts

Every panel with client behaviour ships `modules/<module>/js/<panel>.js`. The public entry point is always `<panel>_init`. Optional cleanup is `<panel>_destroy`.

No jQuery-object-per-panel pattern — plain functions, namespaced events, class-based init guards.

## Naming

| Piece | Rule | Example |
|-------|------|---------|
| JS file | `modules/<module>/js/<panel>.js` | `cms_input_link.js` |
| Init | `<panel>_init($root)` | `cms_input_link_init` |
| Guard class | `<panel>_ok` on panel root DOM node | `.cms_input_link_ok` |
| Destroy | `<panel>_destroy($root)` — optional | `cms_input_image_destroy` |
| Repeater hook | panel name → `<panel>_init` via [`cms_repeater_panel_init_hook()`](../helpers/cms_fields_helper.php) | `cms_input_textarea_init` |

## Init contract

```javascript
function cms_input_link_init($root){

	var $scope = $root ? $root.find('.cms_input_link_container') : $('.cms_input_link_container')

	$scope.not('.cms_input_link_ok').each(function(){

		var $el = $(this)
		$el.addClass('cms_input_link_ok')

		$('.cms_input_link_target', $el).on('change.cms', function(){
			cms_input_link_target($el)
		})

	})

}
```

### Rules

- **Guard = CSS class only** — do not use `.data('*_init')`, `.data('cms_initiated')`, or `.data('init_ok')` for init state.
- **Root element** — the class matching the panel container in the template (e.g. `.cms_input_link_container`, `.cms_input_textarea`, `.exercise_params_container`).
- **Skip initialised** — `.not('.panel_ok')` on the scope query, or `if ($el.hasClass('panel_ok')) return` inside `.each()`.
- **Add `panel_ok` before binding** handlers.
- **`$root` optional** — when omitted, scan the whole document (default). When passed, only `$root.find(selector)`. Used for repeater-row scoping when wired; repeater currently calls global init.
- **Events** — bind with a namespace: `.on('click.cms', ...)`. Use `.off('click.cms').on('click.cms', ...)` when intentional re-bind is needed; the `*_ok` guard prevents double-bind in normal flow.
- **Sub-inits** — secondary entry points (e.g. `cms_input_textarea_md_init`) get their own `*_ok` on their own root (`.cms_input_textarea_md` → `cms_input_textarea_md_ok`).
- **Sub-state** — feature flags inside an already-initialised panel may use other classes (e.g. `cms_tinymce_formatted` for TinyMCE on a textarea).

### Debugging

In DevTools, uninitialised panel roots lack `*_ok`. After init the class is present. After destroy it is removed and the panel can be re-inited.

## Destroy contract (optional, only where needed)

Garbage collection for panels that attach handlers to `document`/`window`, open overlays, or hold player state. Do not add empty `*_destroy` stubs on every panel.

```javascript
function cms_input_image_destroy($root){

	var $scope = $root ? $root.find('.cms_input_image_container') : $('.cms_input_image_container')

	$scope.filter('.cms_input_image_ok').each(function(){

		var $el = $(this)
		$el.removeClass('cms_input_image_ok')
		$el.off('.cms')

	})

}
```

Call destroy when DOM is removed (repeater row delete, popup close) so handlers and state do not leak. Repeater wiring for `$root` / destroy is not automatic yet — see [`todo.md`](todo.md) § Panel JavaScript.

Panels with destroy in CMS module: `cms_input_image`, `cms_image`, `cms_video`, `cms_images` popup.

## Repeater integration

When a repeater adds a row, [`cms_input_repeater.js`](../js/cms_input_repeater.js) preloads field panel JS and calls hooks from `data-init_hooks` on the Add button. PHP derives them from the field types with the same type→panel resolver as `cms_input()` (`cms_input_panel_name()`, `cms_fields_helper.php`). Each hook is a global `<panel>_init` function. The row itself is `cms/cms_input_repeater_item` (ajax), which renders its fields with `print_fields()`. The request names the panel, definition section and repeater, the row key (from the area's `data-next_key`) and the block id; the server takes the fields from the definition ([`cms_input.md`](cms_input.md) § repeater).

## jQuery plugins

Some panels delegate to `$.fn.*` plugins (`cms_images_lazy`, `cms_images_hq`). The plugin applies element-level `*_ok` on each target; the panel `*_init` still follows the scope/guard pattern at the entry point.

## What not to use

- `.data()` keys for init guards
- Ticket #24 jQuery-object-per-panel pattern
- Unnamespaced `.on('click', ...)` on selectors that may be re-inited

## document.ready

Panel files call `<panel>_init()` from `$(document).ready()` (or `$(() => ...)`). Re-init is triggered by repeaters, `cms_input_repeater_select_reinit`, or other CMS code calling the same init function again — safe because of `*_ok`.

## Resize / scroll

Only a panel with layout work on window resize or scroll has **`<panel>_resize`** / **`<panel>_scroll`**, each with a body. A panel without such work has neither: no empty stubs, no bindings, no calls. Empty stubs were the rule until 2026-11-19 and were removed from every module then.

When a panel has one, on `$(document).ready()`:

- bind `$(window).off('resize.<file>').on('resize.<file>', <panel>_resize)` (and the same for `scroll`), `<file>` being the script's file name (`resize.cms_list`, `resize.cms_video`)
- call `<panel>_init()`, then `<panel>_resize()` (and `<panel>_scroll()`) once

Nothing in the CMS calls these by name; a caller that does checks first (`typeof window[type + '_resize'] === 'function'` in the music engine).

## A script that runs again in the same window

A script can run more than once in one window. The music app installs a start page into the same window (footer Homepage, `app_install_html`), and its page scripts run again. A panel script loaded through `cms_inapp_load_scripts` also runs again when its panel comes back after that, because its `<script>` tag left with the old page. jQuery and jQuery UI do not run again: the app shell keeps the window's first jQuery (`app_script_loaded_library`). So whatever a script binds to `window` or `document`, or adds to a window list, must not add a second copy:

- **`window` / `document` handlers:** `.off()` and `.on()` with the same namespace, the script's file name (`resize.cms_list`, `click.userheader`). A namespace shared by several files would let one file's `.off()` remove another's handler. `.off('resize', fn)` does not work here: the script defines a new `fn` each time it runs. `cms_loader.js` triggers plain `resize` after it loads a panel, which reaches every resize handler, as a real resize does.
- **Native `addEventListener` on `window`, `document` or a media query:** add it once per window behind a `window` flag. Make the listener call the current function by name (`function(){ cms_mobile_update() }`), not a reference kept from the first run. The `rem` layout's inline script does this (`window._cms_mobile_bound`).
- **`window.cms_position_link_after`:** push once per window behind a flag (`window.<file>_position_hook_ok`), with a hook that calls the current function by name (`cms_cache.js`, `units.js`, `beacon.js`).
- **Check:** `ai/tmp/homepage_listeners_check.js` (music project) counts the native listeners, jQuery's handlers and the hooks around three Homepage taps.

## Exceptions

- **`cms_cron_run_init($root)`** — no panel DOM; guard `cms_cron_run_ok` on `body`.
- **Feature state on `.data()`** — e.g. `cms_edit_slug_ok` validation result is not an init guard.
- **Sub-state classes** — e.g. `cms_tinymce_formatted`, `cms_video_ready`, `cms_input_date_hidden` (pre-flatpickr setup).