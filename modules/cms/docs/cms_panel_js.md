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

When a repeater adds a row, [`cms_input_repeater.js`](../js/cms_input_repeater.js) preloads field panel JS and calls hooks from `data-init_hooks` on the Add button (derived from field types in PHP). Each hook is a global `<panel>_init` function.

## jQuery plugins

Some panels delegate to `$.fn.*` plugins (`cms_images_lazy`, `cms_images_hq`, `cms_window_height`). The plugin applies element-level `*_ok` on each target; the panel `*_init` still follows the scope/guard pattern at the entry point.

## What not to use

- `.data()` keys for init guards
- Ticket #24 jQuery-object-per-panel pattern
- Unnamespaced `.on('click', ...)` on selectors that may be re-inited

## document.ready

Panel files call `<panel>_init()` from `$(document).ready()` (or `$(() => ...)`). Re-init is triggered by repeaters, `cms_input_repeater_select_reinit`, or other CMS code calling the same init function again — safe because of `*_ok`.

## Exceptions

- **`cms_page_panel_fields_init($root)`** — layout-only positioning; intentionally has no `*_ok` guard so it can re-run on resize.
- **`cms_cron_run_init($root)`** — no panel DOM; guard `cms_cron_run_ok` on `body`.
- **Feature state on `.data()`** — e.g. `cms_edit_slug_ok` validation result is not an init guard.
- **Sub-state classes** — e.g. `cms_tinymce_formatted`, `cms_video_ready`, `cms_input_date_hidden` (pre-flatpickr setup).