# CMS / system agent conventions

How to develop in this CMS (PHP, JS, SCSS panels). Ships with the CMS installation so other projects can reuse the same rules.

Related docs: [`system.md`](system.md) (bootstrap, Loader, Controller), [`cms_panel_js.md`](cms_panel_js.md), [`cms_module_extends.md`](cms_module_extends.md), [`access.md`](access.md), image/video docs under this folder.

**Do not** put long product/project rules here — those live in the site module’s docs (e.g. `modules/music/docs/agents.md`).

---

## Context

Development is for a custom CMS written in PHP and JavaScript. CSS uses SCSS.

Answers should be in context of creating new panels (or other functionality) for this CMS.

There were some example panels historically; as this file ships with the project, those aren’t needed — there should be enough examples in the project.

## System runtime

Bootstrap, `Controller` vs panel libraries, Loader / shared models, panel pipeline, API entry, config boot — see **[`system.md`](system.md)**.

Open that file when changing or debugging core loading, request lifecycle, or routing at the framework level.

## General programming style

All lowercase snake case in 99% cases:

```
$user_name
cms_page_panel_create()
```

Braces style – opening `{` always on the same line, with exactly one space before it:

```
function check_schema() { ...
if (condition) { ...
```

Javascript – no semicolons at line ends when possible.

File header – every PHP file starts with the BASEPATH guard (no direct script access).

Helpers and other non-namespaced PHP (one-line form is fine):

```php
<?php defined('BASEPATH') OR exit('No direct script access allowed');
```

**Class-defining files** (panels, models, controllers) should **preferably use namespaces**. Module name = namespace (lowercase). Core classes (`Controller`, `Model`) are referenced with a leading `\`. Example **panel controller** start:

```php
<?php

namespace user;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class login_google extends \Controller {

	function panel_params($params){
		// ...
		return $params;
	}

}
```

Namespaced class definition notes:

- File: `modules/<module>/panels/<panel>.php` → `namespace <module>;` and `class <panel> extends \Controller`
- File: `modules/<module>/models/<model>.php` → `namespace <module>;` and `class <model> extends \Model`
- Load models with the same path as before: `$this->load->model('cms/cms_access_model')` → `$this->cms_access_model` (Loader prefers `cms\cms_access_model` when present)
- Extend `\Controller` / `\Model` inside a namespace (leading backslash), not bare `Controller` / `Model` / `CI_*`
- Legacy un-namespaced panels/models still work; prefer namespaces for new class files. CMS module controllers, panels, and models are namespaced.

### Avoid stub panel controllers

**Do not** add `panels/<name>.php` when it only:

- extends `\Controller` with an empty body, or  
- has `__construct` that only calls `parent::__construct()` / admin session redirect, or  
- has `panel_params` / `panel_action` that only `return $params`

The runtime already supports **template-only / definition-only panels**: if `modules/<m>/panels/<name>.php` is missing, `get_panel_filenames()` leaves `controller` empty and skips loading a class (`Controller::panel()` / `run_panel_method()`). Templates, SCSS, JS, and definition `"js"` still load as usual.

**Do** add a panel controller when it has real work: prepare data in `panel_params`, handle `do=` in `panel_action`, `panel_heading` for admin lists, `add_css` / `add_js` that must run server-side, model/DB logic, redirects, etc.

If the only need is CSS/JS, prefer definition `"js"` / panel SCSS (or `add_*` from a parent that already runs) over an empty PHP class.

Config access – use `$GLOBALS['config']`:

- `$GLOBALS['config']['base_path']` — CMS installation absolute root (filesystem)
- Cache: `$GLOBALS['config']['base_path'].'cache/'`
- Images/files: `$GLOBALS['config']['base_path'].'img/'` (also `upload_path`)

Helper methods – always start with underscore: `_deep_merge()`, `_get_db_columns()`

Always use `cms_json_decode($json_data, $filename = '')` instead of `json_decode()` for JSON files/data (shows exact file + line + column on error). `$filename` is for display only; if non-file JSON, leave empty.

General philosophy – make errors impossible or instantly obvious (no silent failures, no whitespace-sensitive formats like YAML).

### HTTP redirects

All HTTP redirects in this CMS must be **soft** (302 or 303). Never use permanent redirects (301 / 308) for app navigation, access control, login, logout, or post-action redirects. Permanent status is for true URL moves of static resources only, if ever.

### Controllers and models (short)

Prefer `extends Controller` / `extends Model` — not new `CI_*` names. Details and Loader rules: [`system.md`](system.md).

### CMS field values (no serve-time migration / empty fallbacks)

Do **not** paper over missing or old CMS param values at request time:

- No controller checks like `if (empty($params['heading'])) { $params['heading'] = '…'; }` for labels/copy that live in the panel definition defaults
- No template guards solely for “maybe the field was not re-saved after a definition change” (`if (!empty($heading))` around every label)
- No regex stripping / normalising of legacy stored strings on every page view (e.g. turning `"--- or ---"` into `"or"`)
- **No dual-key / rename fallbacks** in production code, e.g. `$params['cart_label'] ?? $params['number_template'] ?? '…'`. After a field rename, the code uses **only** the new name.

**Why:** empty or stale values only appear when a developer changed definition/template mid-project, or data was never re-saved. That is rare and fixed by updating the DB (or re-saving admin) **at change time**, not by carrying forever-fallbacks into live for shapes that never existed on live. Running fallbacks on every page generation costs every visitor for almost no real cases, and the codebase accumulates thousands of dead branches.

**Do at rename / reshape time (dev):**

1. Change definition JSON (`"name"`, `"default"`)
2. Update PHP/templates/JS to the **new** key only
3. **Migrate stored data now** — e.g. `UPDATE cms_page_panel_param SET name = 'cart_label', value = '…' WHERE name = 'number_template'` and fix the cached `name = ''` JSON blob if present
4. Or re-save the settings panel in admin so defaults and new keys stick

Do **not** leave “read old or new” in the runtime path. One-off SQL/scripts outside request handling are fine; dual serve-time paths are not.

**Select value `0` / “No”:** never use PHP `empty()` when reading CMS field values for admin print or templates — `empty('0')` is true and wipes legitimate No/0 choices. Prefer `isset` / `array_key_exists`, or `(int)$value > 0` when the scale is 0/1/2 show flags.

### Frontend copy (labels, headings, button text)

**All visitor-facing text must come from CMS panel fields** (definition `"item"` / `"settings"` with `"default"` where needed) — not hard-coded strings in templates, PHP, or JS for UI labels.

This includes column headings, empty states, button labels, menu column titles, cart badge templates, etc. Hard-coded copy cannot be edited in admin, translated, or A/B-tested without a deploy.

**Do:** put strings in the relevant panel definition; print `$label_…` / `$params['…']` in the template (or pass into JS via `data-*` if the client needs them).

**Do not:** write English (or any language) UI sentences directly in `.tpl.php` / panel PHP / front-end JS except for truly technical non-UI cases (console debug, API error keys for developers).

### Cache

Do not care about legacy cache. Either it has all been cleared/rebuilt, or old files are fine until they are naturally replaced. Do not add serve-time workarounds for outdated on-disk formats (regex stripping, migration branches, dual code paths). When the cache format changes, purge/rebuild on save; on cache hit, serve the file as-is. (System-level cache notes: [`system.md`](system.md).)

### Controllers vs models

Controller only calls public methods. All logic lives inside the model until the data is mostly put together. The goal is to keep refactoring models safe — all `_methods` are not dependencies outside. If there is really a need to call a helper method from outside, leave it public.

Use single quotes for strings — `'string'`. In PHP do not use `"{$variable}"`, use `'.$variable.'`.

Syntax is British: use in function names and variables `normalise`, `colour`, etc.

## Definition files

`text.json` contains a structural description for data and variable properties of a `text` panel.

Panel ids are normally `module/panel`. Where a module prefix is expected inside the **current** module’s JSON or `config.json`, use `//` instead of repeating the module name — e.g. `"image": "//panel_login.png"` in `music/definitions/login.json` → `music/panel_login.png`; `"source": "//user_login"` in `music/config.json` → `music/user_login`. Handlers: [`cms_panel_model.php`](../models/cms_panel_model.php) (definition strings), [`config.php`](../../../system/core/config.php) (extends). Detail: [`cms_module_extends.md`](cms_module_extends.md).

CMS field `"label"` values — do not repeat the word “label” in the label text. The admin UI has limited room and “label” is usually cropped. Use the thing itself, e.g. `"Correct answer"` not `"Correct answer label"`.

## Template files

`text.tpl.php` contains PHP template markup for the same panel.

All styles on the template have to start with `<panel name>_` prefix.

All panels must have `<panel name>_container` and `<panel name>_content` elements around the rest of the content.

HTML `data-*` attributes with multi-word names use underscores after the prefix: `data-unit_id`, `data-label_correct`. Do not use hyphens between words (`data-unit-id`, `data-label-correct`).

### CMS field output (trust admin content)

Values that come from CMS panel fields / settings are **admin-controlled**. Print them simply:

```php
<?= $search_placeholder ?>
<?= $heading ?>
```

**Do not** wrap ordinary CMS text in `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')` (or equivalent) on every output. That adds serve-time work for no benefit under this project’s trust model, and fights the preferred low-ceremony template style.

**Do not** invent dual defaults on print either (`<?= $x ?? 'fallback' ?>` for CMS labels) when the field has a definition `"default"` — re-save settings / migrate data instead (see “CMS field values” above).

**Exceptions** (rare): building a raw JavaScript string literal by hand, or other non-HTML embedding where the context is not normal template text. Prefer `data-*` attributes or `json_encode` for structured handoff rather than ad-hoc escaping of CMS copy.

### Panel template partials

When a panel needs reusable markup chunks (shared rows, embed fragments, popup bodies), prefer a **subfolder named after the panel**:

```text
modules/<module>/templates/<panel_name>.tpl.php          ← main panel template (unchanged path)
modules/<module>/templates/<panel_name>/                 ← partials for that panel
  module_section.tpl.php
  fragment.tpl.php
  …
```

Example: [`cms_schema.tpl.php`](../templates/cms_schema.tpl.php) includes [`cms_schema/module_section.tpl.php`](../templates/cms_schema/module_section.tpl.php) and [`cms_schema/fragment.tpl.php`](../templates/cms_schema/fragment.tpl.php).

- Subfolder name = **panel name** (not a generic `partials/`), so ownership stays obvious.
- Partials are **mainly for that panel**, but may be included from elsewhere when useful (e.g. updater popup embedding schema fragment).
- Include with an explicit path, e.g. `include __DIR__.'/cms_schema/module_section.tpl.php';` — no auto-discovery.
- Related precedent: music score pieces under `modules/music/templates/score/`.

## Models

- Class name – fully lowercase (including first letter), exactly matches filename: `cms_schema_model`
- Extends – always `Model` (never `CI_Model`)
- Constructor – do not create one when there is no need from model functionality (no `parent::__construct()` usually)
- Database – `$this->db` is always available
- Visibility – no `public` keyword anywhere; `private` and `protected` are allowed but usually not used
- Keep a thin `_execute($sql)` wrapper in schema-related models (makes future logging / dry-run / error collection easy)
- There is no `$db->error()` — older CodeIgniter db library

Loader / shared instances: [`system.md`](system.md).

## Database schema (only when needed — mostly “cms” module)

- Schema files – always `.json`, one per table, inside `modules/<module>/schema/`
- Schema layering – later-loaded modules override earlier ones (deep merge on same table name)
- Error keys – format `module:table:columns:column:property` (or `:indexes:indexname`)

## Panel controllers

Example: `cms_language_select.php`

`function panel_params($params)` prepares parameters for the template.

`$params` is an associative array where keys are template variable names and values are values.

`panel_params` returns the modified `$params` array.

If no `$params` modification is needed (e.g. they come directly from db or ajax), no `panel_params` function is needed — and if the controller would only pass params through, **omit the panel PHP file entirely** (see “Avoid stub panel controllers” above).

If the panel needs to update system state, use `panel_action($params)`, which works similarly to `panel_params()`, but all `panel_action` calls run before any `panel_params()` on the page.

Load models as `$this->load->model('cms/cms_schema_model')`.

The model appears as e.g. `$this->cms_schema_model`, where you can call member functions directly.

Extra CSS and JS can be added to the page using global helpers `add_css()` and `add_js()` (not includes in scss or js, because it allows whole-page optimisation of css and js files), for example:

```php
add_css('modules/cms/css/cms_page_panel_toolbar.scss');
```

Yes/no confirmation — reuse `cms/cms_popup_yes_no` with `panels_display_popup()`; detail: [`cms_popup_yes_no.md`](cms_popup_yes_no.md).

**Frontend vs admin for `panel_params`:** [`cms_panel_params.md`](cms_panel_params.md). **Pipeline / bootstrap:** [`system.md`](system.md).

## Module panel extends

Site module extends base panels via **`config.json` `"extends"`** (`target` / `source`, `//panel_name` convention). Merges definition fields, SCSS, and JS only — PHP/template override is todo. Detail: [`cms_module_extends.md`](cms_module_extends.md).

**Deprecated (do not use):** definition JSON `"extends"` + `join_js` / `join_css`; per-block DB `_extends.*` params. Legacy handling code remains in `system/` temporarily — see [`todo.md`](todo.md).

## Panel JavaScript

Each panel JS file exposes `<panel>_init($root)` and optionally `<panel>_destroy($root)`. Init guards use a `<panel>_ok` CSS class on the panel root — not `.data()` flags. Without `$root`, init scans the whole document; with `$root`, only that subtree. Repeater fields auto-call `{panel}_init` via `data-init_hooks`. Full contract: [`cms_panel_js.md`](cms_panel_js.md).

## Images

Architecture — single resource store: `cms_image` table + files under `img/` (`$GLOBALS['config']['upload_path']`).

- **Parent** — source upload; crop is not stored on parent meta
- **Child** — `{basename}_vN.{ext}` with JSON `meta` (`parent_cms_image_id`, `parent_filename`, `crop`, brightness/contrast/overlay/rotation, zoom/pan for editor)
- **Image child** — GD export to a physical file on save ([`save_cms_image_child()`](../models/cms_image_model.php))
- **Video child** — meta-only `.mp4` row (no child mp4 file); optional `{child}.data/cover.jpg` when ffmpeg available
- **Public embed** — [`_ib()`](../../../system/helpers/image_helper.php): lazy derivatives, mp4 branch, child view `data-*` attrs

Three UI layers (detail in module docs):

1. **Input field** — `cms_input_image` panel → [`cms_input_image.md`](cms_input_image.md)
2. **Selector grid** — `cms_images` popup + `cms_images_page` ajax grid
3. **Crop editor** — `cms_image` overlay on grid → [`cms_image.md`](cms_image.md)

Coding — logic in [`cms_image_model`](../models/cms_image_model.php); panel thin (`cms/cms_images` `panel_params` + `panel_action` for save/delete/check). JS chain: `cms_input_image.js` → `cms_images.js` → `cms_image.js`; frontend transforms in `cms_media_view.js`. Video child without physical file: [`cms_input_image.php`](../panels/cms_input_image.php) uses `get_video_view_meta()` so missing-file error is skipped when parent mp4/fallback exists.

UI — crop `%` in unrotated source space; zoom/pan are editor-only (not applied on frontend). Save overlay: **Exporting ...** (images) / **Saving ...** (video). Grid thumbnails `_ib(..., 150)`; input preview `_ib(..., 300)`.

## Video

Two concerns — keep separate:

1. **Encode** (server, cron) — [`cms_video_model`](../models/cms_video_model.php) queue → `filename.mp4.data/` (cover, fallback, DASH). Detail: [`cms_video.md`](cms_video.md)
2. **Playback** (client) — `_ib()` mp4 attrs → `cms_video_init()` plain parent, or `data-cms_video_view="1"` child path + [`cms_media_view.js`](../js/cms_media_view.js)

Child video — DB row only; playback URLs resolve to parent mp4/fallback. Crop/adjust applied in JS, not re-encoded. Poster: `{child}.data/cover.jpg` when exported, else parent cover.

Playback resilience (grid, many muted loops) — viewport gate pauses off-screen videos (keeps `currentTime`); warden resumes stuck on-screen loops from current position. Not attached inside `.cms_image_container` (editor).
