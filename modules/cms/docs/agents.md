# CMS / system agent conventions

How to develop in this CMS (PHP, JS, SCSS panels). Ships with the CMS installation so other projects can reuse the same rules.

Related docs: [`system.md`](system.md) (bootstrap, Loader, Controller), [`cms_panel_js.md`](cms_panel_js.md), [`cms_module_extends.md`](cms_module_extends.md), [`access.md`](access.md), image/video docs under this folder.

**Do not** put long product/project rules here — those live in the site module’s docs (e.g. `modules/music/docs/agents.md`).

## Git (agents)

- **Never** run `git commit` or `git commit --amend` unless the user **explicitly** asks to commit in that turn.
- **Especially never** create an **initial commit** or first commit for a repo — `.gitignore`, user.name/email, remotes, and what should be tracked may not be set up yet.
- Staging (`git add`, `git rm --cached`) is OK when implementing an explicit request (e.g. untrack cache); **the human commits**, or the agent commits only when told to.
- Do not force-add ignored paths (`cache/*`, secrets, generated assets) unless the user asks.

---

## Context

Development is for a custom CMS written in PHP and JavaScript. CSS uses SCSS.

Answers should be in context of creating new panels (or other functionality) for this CMS.

There were some example panels historically; as this file ships with the project, those aren’t needed — there should be enough examples in the project.

## System runtime

Bootstrap, `Controller` vs panel libraries, Loader / shared models, panel pipeline, API entry, config boot — see **[`system.md`](system.md)**.

Open that file when changing or debugging core loading, request lifecycle, or routing at the framework level.

## Markup and CSS style (front / panel HTML)

- **Do not use `<button>` elements** — use `div` (or `a` when it is a real navigation link). Style and bind clicks with classes/JS.
- **Avoid `display: flex` / flexbox** for new layouts. Prefer **absolute positioning**, block/inline-block, and fixed rem sizes. (Existing productthumb/grid flex is legacy; do not add new flex.)
- **Why absolute is preferred:** content width is **fixed in rem** — standard **100rem** page content, or a fixed project width such as **120rem** when the design calls for it. With a known fixed band, absolute layout is predictable, easier to manage, and has fewer side effects than flex (wrapping, shrink/grow, nested flex, percentage height quirks).
- Prefer simple structure: outer full-width container → inner fixed-width content (`100rem` / `120rem` / `max-width: 100%`) → positioned children inside that content box.
- Do not set `cursor:` on public site panels when the custom cursors system is in use (it fights `elementsFromPoint`).
- **SCSS numeric decimals:** use **at least one** and **at most two** digits after the decimal point for lengths and similar values (`8.0rem`, `1.25rem`, `100.0vh`, `0.0`). **Exception:** `letter-spacing` may use more precision when needed (e.g. `0.05em`).

## HTTP from PHP

- **Do not use curl** (`curl_init`, `curl_exec`, etc.) in modules or system code.
- Prefer PHP streams: **`stream_context_create`** + **`file_get_contents`** (same pattern as `form_model`, `basic/pageshare` Bitly, reCAPTCHA, etc.).
- For POST JSON APIs: set `http.method`, `http.header` (Content-Type, Authorization), `http.content`, `http.timeout`, and usually `http.ignore_errors` so error bodies can be read; parse status from `$http_response_header` when needed.
- Never log secrets (API keys) from request headers or bodies.

## Third-party APIs (backend vs frontend)

- **Prefer backend (PHP / panel actions / models)** for calls to third-party providers (payments, AI, email, shopify, maps keys, etc.) whenever possible.
- Frontend JS should talk to **this CMS** (`get_ajax` / `get_ajax_panel` / form posts). The server holds secrets, enforces auth, and shapes the provider request.
- Avoid browser-direct provider SDKs/APIs that need secret keys, privileged webhooks, or business rules that must not be spoofed. Public publishable keys or pure client widgets are fine only when the provider’s model requires them (e.g. Stripe publishable key for Elements) — still keep charge/session creation on the server.
- Same idea as module **providers** (`provides` + domain panel orchestrating `shopify/checkout`, `stripe/subscription_checkout`, AI, …): domain FE → our panel → third party.
- **Full how-to (add a new provider without reverse-engineering):** [**provider_pattern.md**](provider_pattern.md).

## No silent fails (APIs, config, optional paths)

**Do not fail silently** when something cannot be loaded, configured, reached, decoded, or authorised — even if it is **not** on the critical path and the main flow continues.

| Context | What to do |
|---------|------------|
| **Frontend / public request** | Log a clear warning (project error log / `error_log` with module + reason). Soft-fail the feature if needed, but leave a trace. |
| **CMS admin / sync / tools UI** | Surface a **message or final status line** the operator can see (e.g. sync status `no graphql conf`). May **also** write the error log. |
| **Missing config / scopes / host / token** | Explicit reason string (not an empty return with no side effects). Prefer reusable helpers that return `_reason` / flags **and** log or UI-warn once. |
| **Optional / soft dependencies** | Soft-skip is fine; **silent** empty success is not. Example: Shopify REST works without GraphQL host, but sync must still say **`no graphql conf`**. |

- Never log secrets (API keys, tokens) — same rule as HTTP section.
- Prefer short, stable phrases for status UIs so operators and docs can match them.
- Soft-fail paths that previously returned empty arrays/`''` with no log should at least log once per request (or set a status flag consumers already display).

## Encoding (UTF-8 / utf8mb4)

- **Storage and APIs use real UTF-8** (MySQL **utf8mb4** connection via `mysqli_set_charset`). Do **not** store HTML entities (`&aacute;`, etc.) for normal copy — use Unicode characters.
- `htmlspecialchars` is **not** the default for CMS field output (see “trust at render”). Use only for rare non-HTML embedding cases — not for normal template text or prepared attribute values.
- Sanitize untrusted / external strings with **`cms_utf8_string()`** / **`cms_utf8_tree()`** (`system/helpers/string_helper.php`) — e.g. AI API responses, translation save, param cache rebuild.
- HTTP status codes: **`set_status_header()`** in `system/helpers/error_helper.php` (not json_helper).
- JSON: prefer `JSON_UNESCAPED_UNICODE` so multibyte stays readable Unicode in cache/API.

## Admin CMS menu (`config.json` → `cms_menu`)

- Items merge from all modules by **`id`**. Duplicate ids are **merged** (later non-empty fields win; empty does not wipe). Implemented in `cms/cms_menu` (`_menu_merge_item`).
- Nest with `parent` id; optional `order`, `url`, `access`, `ctrl` (keyboard shortcut digit/letter on top bar).
- **When nesting under a top-level item** (e.g. Shop, Tools), **redefine that top-level** in the same module’s `cms_menu` (`id` + `name` + `order` as needed) so the bar entry exists even if the “owner” module is missing. Example: Stripe/Shopify redefine `shop`; form/analytics/xai redefine `cms_tools`.
- Missing parents are auto-stubbed as a last resort (`id` as name); prefer an explicit redefine for proper labels/`ctrl`.
- **Top level:** items with **`ctrl`** first, sorted by `order` only (Pages / Content / CMS / Tools / …). Remaining top items after that (Shop, …).
- **Nested levels:** **submenu groups first**, then **direct links**, then by `order` (`_menu_sort_top_level` / `_menu_sort_siblings`).
- Prefer groups under **Tools** for related admin areas (e.g. Analytics → Raport/Settings, xAI → Settings).
- **No empty settings links:** only add a `cms_menu` URL to `admin/panel_settings/{module}__{panel}/` when that panel’s definition has a **non-empty `settings`** array. `panel_settings` edits the global row (`cms_page_id = 0`) and shows **`settings` only** — not `item`. If `settings` is missing or `[]`, the UI is “This panel doesn't have settings fields”; do not put that link in the menu.
  - **Module / provider credentials** (API keys, cache minutes) → definition **`settings`** + optional menu link under the domain group (Energy, Weather, …).
  - **Page-placed panel fields** (providers, labels, lat/lon on a public panel) → definition **`item`**; edit on the page panel in Pages, not via an empty `panel_settings` menu entry.
  - A top-level group redefine with **no** child URL is fine when other modules add real settings children under it.

## JavaScript / jQuery

- **`$.trim` is not a function** in the jQuery build used by this CMS — do not use it. Prefer native **`String.prototype.trim`**: `String(value || '').trim()`.
- Prefer the same for other removed/legacy jQuery helpers when native APIs exist.

## General programming style


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
- All module panels, models, and route controllers use namespaces; new class files must follow this. Core keeps `CI_Controller` / `CI_Model` as `class_alias` only for external/legacy code.

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

### Database backups (no `*_bu` tables)

**Do not** create backup / shadow tables in MySQL (`cms_route_bu`, `cms_slug_bu`, `*_backup`, `CREATE TABLE … AS SELECT` for recovery, etc.).

When code needs a DB snapshot before a destructive change:

1. Export SQL (same approach as Data dumps: `system/vendor/mysqldump/mysqldump.php` → `Export_Database(…, $tables, $sql_path)`)
2. Store under **`cache/db/`** as a **zipped** file, e.g. `cache/db/{table}_YYYYMMDD_HHMMSS.zip` containing `{table}_….sql`
3. Optionally keep only the tables you will mutate (single-table zip is fine)

Full environment dumps stay on the dump page under **`cache/backup/`** (`dump_<project>_YYYY_MM_DD[_N].zip` + sidecar `.json` + embedded `dump.json`). Table-level recovery archives live in `cache/db/`.

Helper methods – always start with underscore: `_deep_merge()`, `_get_db_columns()`

Always use `cms_json_decode($json_data, $filename = '')` instead of `json_decode()` for JSON files/data (shows exact file + line + column on error). `$filename` is for display only; if non-file JSON, leave empty.

General philosophy – make errors impossible or instantly obvious (no silent failures, no whitespace-sensitive formats like YAML). See also **No silent fails** above for APIs/config/optional paths.

### HTTP redirects

All HTTP redirects in this CMS must be **soft** (302 or 303). Never use permanent redirects (301 / 308) for app navigation, access control, login, logout, or post-action redirects. Permanent status is for true URL moves of static resources only, if ever.

### Controllers and models (short)

Prefer `extends Controller` / `extends Model` — not new `CI_*` names. Details and Loader rules: [`system.md`](system.md).

**SQL lives in models only.** Panel controllers, templates, helpers, and APIs must not run `$this->db->query` / raw SQL. Put queries on a model (e.g. `timmy/timmy_shop_model`, `cms/cms_page_panel_model`) and call the model from the panel. Controllers assemble params and call models; models own the database.

### Page panel models (#763)

| Model | Role | Who loads it |
|-------|------|----------------|
| `cms/cms_page_panel_model` | **Runtime core** — get/create/update/delete, `get_list`, settings, panel tables, param cache, visitor targets, titles | FE + system + domain modules + CMS |
| `cms/cms_page_panel_cms_model` | **CMS admin** — editor save pipeline, show/copy, FK options, translation param write, lists discovery helpers used only from admin | CMS panels/models; **FE translate** (`user/page_translate*`) may also load it |
| `cms/cms_page_panel_list_model` | **Admin list UI** — filtered list pages (`get_cms_page_panels_list_by`), move first | `cms_list`, `cms_list_list` |

**Rule:** main model must **not** load cms/list models. CMS/list models may call main. Orphan data purge logic lives on panel `cms_page_panel_data_purge` (admin-only), not on the runtime model.

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

Panel ids are normally `module/panel`. Where a module prefix is expected inside the **current** module’s JSON or `config.json`, use `//` instead of repeating the module name — e.g. `"image": "//panel_login.png"` in `music/definitions/login.json` → `music/panel_login.png`; `"source": "//user_login"` in `music/config.json` → `music/user_login`. Handlers: [`cms_panel_model.php`](../models/cms_panel_model.php) (definition strings), [`cms_config.php`](../../../system/core/cms_config.php) (extends). Detail: [`cms_module_extends.md`](cms_module_extends.md).

CMS field `"label"` values — do not repeat the word “label” in the label text. The admin UI has limited room and “label” is usually cropped. Use the thing itself, e.g. `"Correct answer"` not `"Correct answer label"`.

## Template files

`text.tpl.php` contains PHP template markup for the same panel.

All styles on the template have to start with `<panel name>_` prefix.

All panels must have `<panel name>_container` and `<panel name>_content` elements around the rest of the content.

HTML `data-*` attributes with multi-word names use underscores after the prefix: `data-unit_id`, `data-label_correct`. Do not use hyphens between words (`data-unit-id`, `data-label-correct`).

### CMS field output (trust at render)

**Validate and sanitize on the way in** — CMS admin forms, API/webhook handlers, user input, third-party payloads. Once data lives in the CMS (admin fields, catalogue, settings, params **after model prep**), templates **trust** it.

Print prepared values simply:

```php
<?= $search_placeholder ?>
<?= $heading ?>
<?= $card['cta_text'] ?>
```

**Do not** re-check at template time:

- No `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')` on ordinary CMS / model-prepared text (including normal `data-*` attribute values when the model already typed ints/enums/admin strings)
- No defensive `?? ''`, `!empty(...)`, or `is_array(...)` for keys the model always sets
- Templates are **not** a second validation layer

**Do not** invent dual defaults on print either (`<?= $x ?? 'fallback' ?>` for CMS labels) when the field has a definition `"default"` — re-save settings / migrate data instead (see “CMS field values” above).

**Exceptions** (rare): embedding into a raw JS string literal by hand; content that was stored without sanitization and is truly untrusted (prefer fix at write). Prefer `data-*` + JS or `json_encode` for structured handoff.

Related: Encoding section — `htmlspecialchars` is for edge cases, not the default for every CMS field echo.

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
- Prefer **one item per partial**; the **parent template owns `foreach`** (how many / which items is the parent’s business logic). Filtering, visibility flags, active labels → model or parent prep, not the item partial.
- Partials are **mainly for that panel**, but may be included from elsewhere when useful (e.g. manage reusing `pricing/card.tpl.php`).
- Include with an explicit path, e.g. `include __DIR__.'/cms_schema/module_section.tpl.php';` — no auto-discovery.
- Related precedent: music score pieces under `modules/music/templates/score/`.
- **Reusable domain controls** (e.g. currency dropdown) belong as their **own panel** (`_panel('shop/currency_selector', …)`), not as ad-hoc host-page partials.

## Models

- Class name – fully lowercase (including first letter), exactly matches filename: `cms_schema_model`
- Extends – always `Model` (never `CI_Model`)
- Constructor – do not create one when there is no need from model functionality (no `parent::__construct()` usually)
- Database – `$this->db` is always a **`cms_db`** instance (see [`system.md`](system.md) § Database); write raw SQL + `query()` / binds — no Active Record
- Visibility – no `public` keyword anywhere; `private` and `protected` are allowed but usually not used
- Keep a thin `_execute($sql)` wrapper in schema-related models (makes future logging / dry-run / error collection easy)

Loader / shared instances: [`system.md`](system.md).

## Database schema (only when needed — mostly “cms” module)

- Schema files – always `.json`, one per table, inside `modules/<module>/schema/`
- **Table names are singular** and **prefixed with the module name** (snake_case): `{module}_{entity}`  
  Examples: `subscription_subscription`, `music_user_unit`, `music_user_set`.  
  Primary key: `{table}_id` (auto-increment INT unsigned).
- Schema layering – later-loaded modules override earlier ones (deep merge on same table name)
- Error keys – format `module:table:columns:column:property` (or `:indexes:indexname`)
- Table renames: use schema `"migrate_from": { "table": "old_name", "columns": { "old_col": "new_col" } }` then CMS schema fix

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

Site module extends base panels via **`config.json` `"extends"`** (`target` / `source`, `//panel_name` convention). Target panel name stays the public id; sources add fields, list meta, SCSS, JS, optional template replace and PHP chain. Definitions load only via `get_cms_panel_config()` (merge rules + discovery: [`cms_module_extends.md`](cms_module_extends.md)).

**Do not use:** definition JSON `"extends"` / `join_js` / `join_css` (removed from core).

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
