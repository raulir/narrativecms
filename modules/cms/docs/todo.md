# CMS / system backlog

Platform and core CMS work. Ships with the CMS module (not project-root).

Related design notes also live in topic docs (`cms_email.md`, `cms_schema.md`, `cms_video.md`, `cms_image.md`, `language.md`, `cms_panel_js.md`, `cms_module_extends.md`, …).

**Legend:** `[ ]` open · `[x]` done

---

## System / Composer

- [ ] **Composer packages before SCSS** — a fresh install needs `vendor/` from `composer install` before stylesheets can compile. `scssphp/scssphp` 2.1 lives there, with `league/uri`, `scssphp/source-span`, and `symfony/filesystem`. Longer term, most of `system/vendor` should move into that same Composer directory.

- [x] **SCSS compile in cms_css_model** — `pack_css()`, `pack_css_inline()`, and `pack_get_scss_path()` delegate to `modules/cms/models/cms_css_model.php`. Panel file lists and `add_css()` stay in the controller and the helper. Colour settings and `<link>` output stay for a later slice of [#576](https://github.com/raulir/narrativecms/issues/576).

## System / database

- [x] **#762 one mysqli + slim cms_db** — `$GLOBALS['db']` single connection; [`cms_db.php`](../../../system/core/cms_db.php) replaces `system/database/` (no AR/forge/multi-driver). See [`system.md`](system.md) § Database.
- [x] **Panel settings lookup** (2026-11-11). Every `_panel()` looked up its settings with a full scan of `cms_page_panel` (no `panel_name` index): about 8 ms per call.
  - **Fix:** index `panel_name_idx` in `schema/cms_page_panel.json`, applied by CMS Update; `get_cms_page_panel_settings()` keeps results for the request. See [`system.md`](system.md) § Performance note.
  - **Local result:** edit forms 572–1184 ms → 86–287 ms; the front page about 610 ms → 50 ms.
  - **Live:** run CMS Update (schema) once to create the index.

## System / panel ajax

Ajax input / output filter review (2026-11-05), items in priority order:

- [x] **1. Block merge vetted** — `get_panel()` / `get_panel_anchor()` merge the saved block only when it belongs to the requested panel and the caller may see it (shown, CMS user, translator); handed to `ajax_panel()` as internal `_cms_block` (set after the `_*` strip). `ajax_panel()` no longer loads a block from a posted `cms_page_panel_id`. Closes the anonymous read of hidden blocks (e.g. `cms/cms_settings` fields through another panel's `ajax.output`) and stops the admin save from being overwritten by the stored block
- [x] **2. Every returned key listed explicitly** — no `ajax.output` stays `cms_*` only (no automatic keys). All 94 panels with `panel_action` audited (`grok/tmp/ajax_output_audit.php` + JS reads per call, `grok/tmp/ajax_js_reads.php`): lists widened or corrected on 19 definitions (e.g. `cms_page_panel`, `cms_page_panel_import`, `cms_update` → `result` only, `user/reminder` → `cms` only, no more `key` / `row`), 11 new ajax-only definitions (`cms_page_operations`, `cms_edit_slug`, `cms_rebuild_routes`, `cms_schema`, uploads, `form/do_send` without its SendGrid key, …). `music/footer.js` app-shell fetch maps `cms_html` / `cms_error` back. Rule in [`agents.md`](agents.md) § Panel controllers
- [x] **3. Senders switched to `cms_do`** — native form fields (`cms_user_login`, `cms_user`, `cms_main_menu`, `cms_table`, `cms_images`, `cms_dump`, `form/basic`), the 15 internal `ds_*` callers, the `admin/dump` route (`cms_do` param; reads posted `cms_do`, then `do`), `api/image_resize` (reads `cms_do`, then `do`). Cross-module provider calls (`shop` fulfil / compose, `subscription` checkout) send `cms_do` **and** `do` for providers in modules not yet converted. `form/do_send` drops `cms_*` request keys from stored / emailed form data. Remaining `do` aliases are part of "Drop updater legacy ajax keys" below
- [x] **4. Params precedence** — decided order on ajax: panel settings (lowest) < posted keys that pass `ajax.input` < vetted saved block. `Controller::ajax_panel()` now merges `array_merge($settings, $params)`, then the block (live check: posted `login_text` beats the `user/header` setting; block 4466 heading beats a posted `heading`). No `ajax.input` still lets every key through: deprecated, a minor review item. Developer duties in [`agents.md`](agents.md) § Panel controllers and [`review.md`](review.md) § Contracts
- [ ] **5. Contract in system core, undocumented** — `controller_ajax_api.php` hard-codes `cms/cms_update*` / `cms_schema` legacy keys (move to definitions); `ajax.input` is used by no definition; no doc for `ajax.input` / `ajax.output` / rename map / merge order. (`_cms_inapp`: fixed 2026-11-06, `ajax_panel()` no longer overwrites `$_POST`, so analytics reads the raw flag; it stays out of panel params)

- [ ] **Panel localStorage eviction** — `get_ajax_panel` `_cache` keeps replies in localStorage. When storage is full, drop older panel cache entries. The `_cache`, `_cache_offline`, and `_cache_reload` flags do not do this.
- [x] **CI `Input` class removed (2026-11-06)** — `system/core/Input.php` deleted, no `$this->input`. Boot cleaning moved to `cms_clean_request()` (`cms_bootstrap.php`, called from `CodeIgniter.php`): control characters out, `\r\n` / `\r` → `\n`; the broken key check is gone. All 300+ panel reads moved to `$params` (`grok/tmp/input_migrate_apply.php`); helpers take `$params` (`cms_page_panel::_post_panel_form_input`, `cms_page_panel_export::_parse_options` / `_export_main_ids`, `cms_dump::_post_array`). `render()` now puts the query string and a full-page form POST (filtered by `ajax.input`) under each panel's block / page config (`_request_render_params()`), so admin forms keep working. `cms_do` / `do` are dropped from the query string (no action from a link). `ajax_panel()` no longer overwrites `$_POST`, so system readers see the raw request again (analytics `_cms_inapp`). Fixed on the way: list-item **Template** select posted `_template_page_id`, which the `_*` strip dropped (now `cms_template_page_id`); `productbuy` missing-dimension check (`FALSE` vs `null`). System code reads `$_POST` / `$_GET`; models take request values from their panel
- [ ] **Add `ajax.input` to panels that take ajax input** — no definition has one yet, so every posted key passes and can overwrite a settings value of the same name. Start with the public action panels (user, form, shop, subscription, basic/pageshare, music)
- [ ] **Convert full-page form posts to `get_ajax` (low priority)** — these still post the whole page and get their values through the render request layer: admin `cms_dump` (generate, upload, restore, delete row forms), `cms_images` (new / replace image upload forms), `cms_main_menu` (`.admin_main_menu_form`), `cms_user` (`.cms_user_form`), the page-panels add form (`cms_input_page_panels.js` builds `.cms_params_form` → `admin/cms_page_panel/0/`), `user/profile` (`.register_form`, no JS), and the admin login (`cms_user_login` → `cms_login` controller, reads `$_POST` as system code). When all are converted, `_request_render_params()` can drop POST so panels get POST only through ajax

## System / page panel API

- [x] **Filters on `cms_page_panel_id` loaded every block** (found 2026-11-13; fixed 2026-11-13). The base column list in `get_cms_page_panels_by()` / `count_cms_page_panels_by()` (`_cms_page_panels_query_context()`) and in `cms_page_panel_list_model` said `'cms_page_panel'`, so a `cms_page_panel_id` filter was not SQL: the query read every block (about 23,800 here) and the id was matched in PHP afterwards (and without `_fields`, every block was loaded in full first).
  - **Effect:** `admin/page/<id>/` took 1.7 s for a page with 3 blocks: `cms_input_page_panels` looks up each block by id (about 540 ms each). Now about 70 ms.
  - **Fix:** the list says `cms_page_panel_id`, so the filter is a bound `=` / `in (…)` / `!=` / `not in (…)`. An empty id list still matches no block, as before (an empty `!` list still filters nothing).
  - **Check:** `grok/tmp/cms_id_filter_check.php` (one id, full row equal to `get_cms_page_panel()`; id lists; `!`; empty string, 0, null, unknown id and an empty list give no rows; counts; the list model), plus the admin regression checks.

- [ ] **Control what a panel serves publicly** — one system for the page panel API. Params, settings, and other panel data should not always be sent. Default mode is a whitelist: only named fields are public. A panel can instead use a blacklist of fields that must stay private. See page panel params and settings.

## System / frontend page render

- [x] **Missing page `layout` → 500** — empty layout no longer includes `cms/layouts/.tpl.php`. HTTP 500, log to `errors_log`, render saved `internal-error` page if it has a layout, else red-frame HTML. Reserved list/system pages are not auto-inserted; admin shows grey **create** rows. First panel save creates the page from a session draft.
- [x] **Session boot + reserved slug cost** — one `cms_session_boot()` (cookie/GC from `session_length_days`; APIs must not `session_start()` raw). CMS admin re-login after 24h (`cms_password_last_checked`). `is_reserved_slug()` does not scan all panel defs on 404.
- [ ] **Analytics known by system code** (review 2026-11-13, horizontal). `controller_index::_record_position_pageview()` (`system/core/controller_index.php`) checks for the `analytics` module, loads `analytics/analytics_api_helper` and records the pageview of a position ajax load. It is guarded (soft), but system code should not name an optional module. Give position loads a hook (for example a `provides` entry or an after-render callback) that analytics registers.

## System / routing

- [ ] **Deprecate / remove module controller first-segment autoresolve** — public endpoints should use **module APIs** (`modules/{m}/api/{id}.php` + `config.json` `"api":[{"id":…}]`) or explicit reserved system controllers (`ajax_api`, `files`, `admin`, …). Avoid new public routes via `modules/*/controllers/{name}.php` (example: Stripe webhook moved to **`stripe/webhook`** API). Keep admin module controllers until admin routing is redesigned. See [`system.md`](system.md) § Bootstrap, [`routing.md`](routing.md).

---

## Email

- [x] Central mail helper — [`cms_email_model`](../models/cms_email_model.php); see [`cms_email.md`](cms_email.md)
- [x] **Async email sending** — default enqueue to `cache/email_queue/`; cron panel `cms/cms_email_queue`; `send_now` for password reminder / updated / verification. Settings: `email_queue_limit`, `email_queue_max_attempts`. Detail: [`cms_email.md`](cms_email.md)

---

## Panel export

- [x] **List export** — gear **Export** posts the ticked row ids as `export_ids`. One ticked row keeps the heading **Export page panel**. More than one says **Export page panels (N)**. One zip, `_main` is that array. See [`cms_export.md`](cms_export.md).
- [ ] **Import definition differences** — list import does not write `_panel_files` or change definitions. Later: detect when the zip’s definition differs and allow overwriting definitions and other panel source files.
- [ ] **Settings merge on import** — list import only adds settings values, including translations, that are not already stored. Later: a proper merge when the zip and the site both have a value.
- [ ] **Import match by more than id** — list import matches an existing panel by `cms_page_panel_id` only.

## Page cache

- [ ] **Panel actions on cache HIT** — when a full page is served from page cache (`cache::try_serve()`), CI does not boot and `panel_action` does not run. On cache **write**, store metadata listing which panels had `panel_action` on that page (panel name + instance params as needed). On cache **HIT**, lightweight bootstrap runs those actions only (e.g. `analytics/beacon` php pageview) before or alongside serving HTML. Ref: [`system/libraries/cache.php`](../../../system/libraries/cache.php), analytics php tracking skipped today on cache HIT.
- [ ] **Cached page shared `data-beacon_id`** — full-page cache is anonymous/shared HTML; `data-beacon_id` is baked from whoever built the cache. Beacon API prefers POST `beacon_id` over cookie, so visitors may share one analytics session. Fix: omit `data-beacon_id` from cacheable HTML and/or prefer visitor cookie over posted id when both are valid; per-visitor id only via JS cookie or panel-action on HIT. (Coordinates with analytics module.)

---

## Panel data integrity

- [x] **#114 ensure_data** — opt-in definition flag; create/purge fill missing fields from existing + defaults; JSON cache encode check. See [`cms_input.md`](cms_input.md) § Panel-level flags.
- [x] **Orphaned panel data purge** — gear menu **Data purge** (before Translation); popup scans instance + settings for keys not in definition and translation languages not in CMS; purge selected. Logic on panel `cms_page_panel_data_purge` (scan/purge methods).
- [x] **Orphan scan includes ghost translation fields** — `_translations.{lang}.*` keys not in item/settings definition (e.g. `yearly_badge` stuck on pricing instance). Panel still wins over settings on clash; clean via Data purge only.
- [x] **#763 split cms_page_panel_model** — runtime core stays on `cms_page_panel_model`; CMS admin APIs on `cms_page_panel_cms_model`; admin list UI on `cms_page_panel_list_model`. See [`agents.md`](agents.md) § Page panel models.
- [x] **#181 `count_cms_page_panels_by`** — shared `_cms_page_panels_query_context()` with `get_cms_page_panels_by` (LEFT JOIN); SQL `COUNT(*)` unless JSON/param filters; used by list-item delete counts + `get_list_stats`.

---

## Pages / publish (#625)

- [x] **Page hide + guarded delete** — remove Published status dropdown; toolbar **Hide/Show** (user + list main pages); gear **Delete** for user (enabled if no panels) and list templates (enabled if no list items, or list module/def gone, or merged `list.link_target` off after extends); system: neither. Visibility is binary visible/hidden. Empty main page FE shows red “No panels on the page” (not error_log). Hidden pages grey on Pages list (`cms_item_hidden`).

## Admin page load

- [ ] Lazy-load `cms_page_positions` via AJAX
- [ ] Prebuilt admin CSS/JS manifest
- [ ] `cache.pack_css: 1` for admin
- [ ] `cache.vcs_check: git` for per-page CSS cache
- [ ] Batch param loading in `get_cms_page_panels_by()` (N+1)
- [ ] `_fields` with meta/param keys — partial JSON decode
- [ ] Shared `cache/list_slugs.json` for `get_lists()` callers

---

## Config / SPA legacy

- [ ] **Remove legacy SPA config translation** — in [`cms_config.php`](../../../system/core/cms_config.php), delete `position_wrappers` / `position_links` → `single_page_mode` mapping once all envs only store `single_page_mode` (see comment there)

---

## Updater — [`cms_update.md`](cms_update.md)

- [x] **Config major.minor + Release snapshot** (#430) — `config.json` `"version"`, manual [Release], serve clients from `tmp/master/{id}/`
- [ ] **Admin UI to edit package major.minor** — today operators set `"version": "x.y"` in module `config.json`; ticket for CMS UI instead of hand-editing JSON
- [x] **Untrusted paths** (review 2026-11-05) — `api/updater.php` validates `module`; `_update_path_allowed()` keeps master file lists / posted filenames inside the package (list, stage, copy); popup renders filenames as text
- [x] **Failed download no longer deletes files** — update manifest per area; D only from the manifest; a missing U/A body is an error; 2 retries in the popup; any file still failing discards the whole package update (live tree untouched); `update_copy` refuses an incomplete download. See [`cms_update.md`](cms_update.md) § Download is all-or-nothing
- [x] **Warn before overwriting local edits** — `version_files` per-file baseline on every stamp; `cms_update_list` rescans and returns a plan with `local_edit` flags / unknown-changes warning; popup asks Continue / Cancel. See [`cms_update.md`](cms_update.md) § Local edits warning
- [x] **One master HTTP helper** — `_master_post()` with timeouts and transport errors; one `do=modules` index for all rows (`current_hash` + `history` added); row shows master errors; `cms_update_copy` rescans only its area. See [`cms_update.md`](cms_update.md) § Master requests
- [x] **Dead code** — removed panel `do=cms_update` branch (+ `ajax` flag / template `print_r`), legacy single-file staging + `update_file()`, `up()`, `get_master_file()` + legacy batch fallback; `run_sql()` moved to `cms_schema_model` (used by `cms_dump`)
- [x] **Interface improvements** (#429) — bold master rows, *inactive* marker, *Not allowed* instead of Update (Remove hidden too). See [`cms_update.md`](cms_update.md) § Row markers
- [ ] **Source per module** (#429, last item) — every package comes from the one `cms_update_url`. Options: show the host per row (read-only), or `update.source.{module}` overrides that every master call uses
- [ ] **Installation hardening check** (found 2026-11-13; too big for review round 2).
  - **Why:**
    - private folders were reachable on the web: `config/<host>.json` (database and admin passwords) answered 200, also as `…/../config/…`;
    - CMS Update never changes a site's root `.htaccess`, so a protection rule added later (now `config` and `data`, next to `tmp` and `log`) never reaches existing installs.
  - **Want:** a check, run by CMS Update (or as its own admin tool), that fortifies the installation and reports what it could not fix.
  - **Rules in the root `.htaccess`:** `tmp`, `log`, `data`, `config`, the dot-file and probe rules; add the missing ones in the install template's form, or show the exact lines to add.
  - **Live requests:** a self-request to a sample in each private folder (`config/<host>.json`, `tmp/`, `log/`, `data/`, `.git/`) must give 403. Report every 200, never the content.
  - **Per-folder `.htaccess`:** `Require all denied` where a folder is inside the web root and the root rules are missing (as `data/.htaccess` already is).
  - **Elsewhere:** no PHP runs in `img/` or `cache/`; folders outside the web root (`dir.*` absolute) need nothing.
  - **Notes:**
    - say which folders were reachable, and suggest changing the passwords when `config/` was;
    - the check makes no change without the operator's OK;
    - see the CMS inputs section, "Host config was served by Apache".
- [ ] **Drop updater legacy ajax keys** — older installs still post `do`, `no_html`, and `session_id`, and the update, popup, and schema replies still include `_html`, `html`, `js`, `css`, `_panel_css`, `_panel_css_force`, and `_panel_js` beside the `cms_*` names. The master endpoint still accepts `do` as well as `cms_do`. Remove those aliases once installed updaters post and read only the `cms_*` keys.

---

## Schema panel — [`cms_schema.md`](cms_schema.md)

- Show errors inside the panel
- JSON / DB errors as red rows in panel (not top system bar)
- `confirm()` before fix module
- Graceful ALTER error collection
- Sort: column errors before index errors per module

---

## Video encode — [`cms_video.md`](cms_video.md)

- `_probe_video()` validation guard (empty ffprobe / missing streams)
- Split encode into separate queue jobs (fast pass + per ladder step)
- Optional CLI background worker (off web PHP timeout)

---

## Images — [`cms_image.md`](cms_image.md)

- Future image transforms (section placeholder — no items yet)
- [x] **Unused image purge on Data dumps** — months + category filter, Test size estimate, soft-move to `cache/tmp/img/` ([`cms_image.md`](cms_image.md) § Purge unused images)

---

## Data and backup — [`cms_dump.md`](cms_dump.md) (#26)

Full checklist: **[`cms_dump.md`](cms_dump.md)**.

- [x] **Rename admin page to “Data and backup”** (menu + toolbar; URL `admin/dump/`)
- [x] Phase 1: generate section (options, multi backup under `tmp/backup/`, metadata JSON, resize images); layout sections
- [x] Phase 2: **Backups and restore** (collapsible list, upload to library, Restore/Download/Delete, `dump_<project>_date`, dump.json in zip, no `*_bu` on restore)
- [x] **Dir split** — public `cache/` (packed CSS/JS), private `tmp/`, `log/error.log` ([#790](https://github.com/raulir/narrativecms/issues/790))
- [ ] **Restore: select which tables to overwrite** in confirmation dialog (from dump meta / SQL)
- [ ] Success message + restore summary + keep-smaller image on generate — leftovers from #266, see `cms_dump.md`
- [ ] Preflight PHP limits, upload validation, checksum registry, help/warnings — see `cms_dump.md`
- [x] Rebuild routes control on dump page
- [x] Unused image purge on dump page

---

## Languages — [`language.md`](language.md)

- [ ] **Languages as real CMS list** — replace settings grid (`languages[]` JSON on `cms/cms_languages`) with a proper list panel (one list item per language); editable grid on list items, drop bootstrap-from-targets / manual sync
- [ ] **local_labels admin toolbar** — Use `cms_languages` column 3 `local_labels` in CMS admin toolbar language dropdown (`cms_language_select`)
- [x] **Endonym frontend switcher** — `languages[].endonym` in frontend `basic/language` switcher (fallback Label)
- [ ] Remove stale `basic/language` `language_settings` / icon data from DB (harmless orphans after Languages page ships)

---

## Panel editor — field position calculations

- [x] **CSS grid layout instead of JS positions** (review 2026-11-11, item 4; done 2026-11-12). See [`cms_input.md`](cms_input.md) § Form layout.
  - **CSS:** `.cms_page_panel_fields` is a two-column grid of 3.5 rem rows (`cms_page_panel.scss`). Spans come from `data-cms_input_height` / `data-cms_input_width`. A repeater is a full auto-height row; the grid input spans its rows.
  - **Removed:**
    - `cms_page_panel_fields_init` (JS layout);
    - the form hidden until scripts ran;
    - the re-layout calls (repeater add/delete/sort, grid rows);
    - the `container: "full"` input flag.
  - **Groups:** a tab's fields are moved right after the `groups` input (`cms_input_repeater.php`), and a field in several tabs now works (the JS gave it `top: NaN`). A row is as tall as its open tab, so switching tabs can move the fields below.
  - **Check:** `grok/tmp/cms_inputs_check.js after layout`: every input box on the 21 forms is within 3 px of the JS layout, also after adding a row. Screenshots of 8 forms are the same apart from a 1 px shift of the grid's Add button.
  - **Old item closed:** "Investigate `cms_page_panel_fields_init` absolute positioning (landing CTAs subtitle overlap)". The function is gone, and the landing form shows no overlap.

---

## Panel JavaScript — `*_ok` / `*_destroy` (deferred)

Source: [`cms_panel_js.md`](cms_panel_js.md). Per-module remaining work also tracked in each module’s `docs/todo.md` where applicable.

- [x] **`*_ok` refactor — CMS module** — all `modules/cms/js` panel inits; destroy on `cms_input_image`, `cms_image`, `cms_video`, `cms_images` popup
- [ ] **Repeater `$root` scoping** — pass new repeater row as `$root` to `*_init` instead of global scan
- [ ] **Repeater row delete → `*_destroy`** — call destroy hooks when repeater blocks are removed
- [ ] Ticket #24 (panel JS to jQuery objects) — closed; `*_init` + `*_ok` class guards instead

---

## CMS inputs — [`cms_input.md`](cms_input.md)

- [x] **#87 one path for every input** (2026-11-11):
  - `print_fields()` builds the same params for every field and always renders `cms/cms_input`;
  - repeater, cms_page_panels, select, grid and repeater_select lost their helper branches, and their params are made in their own controllers (`cms_input_repeater.php` is new);
  - the wrapper and value escaping come from the input's definition (`input`: `container`, `value_raw`);
  - one type→panel resolver (`cms_input_panel_name()`);
  - inner `.cms_input_container` wrappers removed (text, colour, fk, select, page_panels);
  - admin templates render their fields through the dispatcher;
  - aliases `panels`/`color` normalised when definitions load.

  See [`cms_input.md`](cms_input.md) § How an input is rendered.
- [x] **Repeater row from the definition** (review 2026-11-11, item 2, input trust; done 2026-11-12). "Add element" posted the whole `fields` structure (`data-fields`), and `cms/cms_input_repeater_item` rendered it, so the browser chose which panels ran.
  - **Fix:** the request now sends `block_panel`, `section`, `name`, `repeater_key` and `base_id` (`ajax.input` lists only these), and the fields come from the definition (`cms_repeater_definition()`). An unknown panel, section or repeater gives an error.
  - **Kept:** `_access_ok` is still passed to the input panel. Panel `access` keys are visitor keys, and a refused input would clear the site-user session and redirect the admin form to the site login. Input types come from definitions only. See the comment in `cms_input()` (`cms_fields_helper.php`; the `cms/cms_input` panel was replaced by it 2026-11-13).
  - **Check:** `grok/tmp/cms_repeater_rows_check.js`.
- [x] **Repeater row keys** (review 2026-11-11, item 3; done 2026-11-12). A row added after a delete took an existing row's key (key = row count), so classes such as `.cms_image_input_<name_clean>` matched two rows and a chosen image was set in both.
  - **Fix:** the next key comes from `data-next_key` on the rows area. The repeater's call to `cms_input_image_rename()` (it never matched) and its 1 s timeout are removed; the function itself went with the legacy table editor (2026-11-13).
  - **Check:** `grok/tmp/cms_repeater_rows_check.js` adds three rows, deletes one and adds one: all ids and image classes are unique.
- [x] **CMS files in a private data folder** (review 2026-11-13, item 1, input trust).
  - **The old problem:** file-input uploads of any type went to the web-served `img/` (`.php` ran there), and `cms_file_rename` followed `../`.
  - **Now:** every `cms_file` file is in `dir.data` (default `data/<year>/<month>/`), denied by the root `.htaccess` and by `data/.htaccess`, and reached only through the `cms/file` API, `cms/file/<year>/<month>/<name>` (`modules/cms/api/file.php`, `cms_file_url()`; the old `files/get` route and `controller_files.php` are removed) (see [`system.md`](system.md) § Directories, [`cms_input.md`](cms_input.md) § file). Rename stays inside the data folder.
  - **Live:** CMS Update creates `data/` and moves the existing files from `img/` (`cms_file_model::move_files_to_data()`). Adding `RewriteRule ^data(/|$) - [F,L]` to the site's root `.htaccess` is recommended; `data/.htaccess` already denies it.
  - **Check:** `grok/tmp/cms_data_files_check.php`.
- [ ] **Host config was served by Apache** (found 2026-11-13). `config/<host>.json` (database and admin passwords) answered 200, also as `…/../config/…`. `RewriteRule ^config(/|$) - [F,L]` is now in the root `.htaccess` and in the install template.
  - **Live:** CMS Update does not change a site's root `.htaccess`. Add the `config` and `data` rules to live by hand, then check that `config/<host>.json` gives 403. Change the passwords if it was reachable.
  - **Lasting fix:** the installation hardening check (§ Updater).
- [x] **Textarea preview ran any panel method** (review 2026-11-13, item 2, input trust; done 2026-11-13). The posted `md_filter` went to `run_panel_method()`.
  - **Fix:** `markdown_apply_md_filter()` now runs only `module/panel/md_filter_*` (`markdown_md_filter_valid()`, `modules/cms/helpers/cms_markdown_helper.php`), so every caller is covered. The preview answers `cms_error` for any other value, and the JS shows it.
  - **`ajax.input`:** `cms_input_textarea` has one (`cms`, `md_preview`, `text`, `md_filter`).
  - **Check:** `grok/tmp/cms_md_filter_check.php`, and the material form preview.
- [x] **Save took field names from the request** (review 2026-11-13, item 3, input trust; done 2026-11-13). Every posted `panel_params` key was stored (`_js`, `_cache_time`, `show` …), and a stored block's `panel_name`, page, parent and sort came from the post. The purge query also put posted key names into its SQL text.
  - **Fix:** the save keeps only the edit structure's fields (`filter_posted_panel_params()`), and a stored block keeps its row's type and place (`get_block_place()`). The reply reports the saved values, and the save JS shows `cms_error`. The purge query binds its key names (`cms_page_panel_model::update_cms_page_panel()`). See [`cms_input.md`](cms_input.md) § What a save stores.
  - **Grid cells:** `cms_grid_editable` `update_field` writes only a definition field, or runs only a definition grid's `ds`.
  - **`ajax.input`:** `cms/cms_page_panel` and `cms/cms_grid_editable` have one. It includes the panel picker's `target_type` and `target_input_name`: the picker opens the editor with a full-page POST, which the same list filters (a regression found in the review of item 3, fixed 2026-11-13).
  - **Check:** `grok/tmp/cms_save_allowlist_check.php`; `cms_save_forms_dump.js` + `cms_save_forms_check.php` (20 forms, nothing dropped); `cms_picker_place_check.php` (picker on a page and on a block).
- [x] **Review of the save allowlist** (2026-11-13, `grok/review_small_plan.md` items 2–5; all done 2026-11-13):
  - [x] **Parent field (input trust; done 2026-11-13):** a new child's posted `parent_name` named the field written on the parent (`save_cms_page_panel_admin()`), unchecked, and was printed into the form. Now `check_block_place()` accepts only a `cms_page_panels` field of the parent's edit structure, and a new block's parent and page must exist; the save answers `ok: 0` with the reason, and the editor stops with 400 before printing the form. Check: `grok/tmp/cms_picker_place_check.php`.
  - [x] **Caching (input trust; done 2026-11-13):** the `cms_page_panel_caching` action stored the posted `caching` and `lists` as `_cache_time` / `_cache_lists` on a posted `target_id`, unchecked. Now `check_caching()` allows only the popup's options (`get_caching_options()`, shared with the popup) and `get_lists()` names, on a stored block (see [`system.md`](system.md) § Cache). Refusals in `cms/cms_page_panel` `panel_action` go through `_refused()`, which keeps `no_html`. The popup's list picker no longer throws on a list name with `/`. Check: `grok/tmp/cms_caching_check.php`, `cms_caching_popup_check.js`.
  - [x] **Grid cell read path (input trust; done 2026-11-13):** `cms_grid_editable::panel_params` ran `ds_<posted ds>` (`L`) and read any field of any block; a refused cell came back empty. Now `get_grid_cell_target()` checks both the write and the read (a definition grid's `ds` on a stored base block, or a plain definition field of a stored block), a refusal prints none of the posted names, and the JS keeps the cell and puts its old value back. Check: `grok/tmp/cms_grid_cell_check.php`, `cms_grid_cell_browser_check.js`.
  - [x] **Small (done 2026-11-13):** the save looked up the block place twice (the early check is gone; `build_panel_data_for_save()` answers "Block not found", and the old data loads after it). The slug editor read `result.error`, but the reply carries `cms_error`, so neither the check nor Update showed a refused block's reason; both show it now, and the popup's form opens with an empty slug instead of a PHP warning. Check: `grok/tmp/cms_save_browser_check.js` (Save, a refused save, a new block opening its editor, the slug errors).
- [x] **SQL value lists built by hand** (found 2026-11-13; done 2026-11-13). The list filters built `in ('…')` with `str_replace` on quotes: a value ending in a backslash escaped the closing quote.
  - **Fix:** both list builders (`cms_page_panel_model::_cms_page_panels_query_context()`, `cms_page_panel_list_model`) bind their lists (`in (?, …)`, values through `sql_in_values()`), in the same order as the where parts. `_order`, put into the SQL text, is only `asc` or `desc`. The schema check (`_panel_table_sync_detail()`) binds its field names too.
  - **Legacy table editor (`cms/cms_table`, `admin/table/<table>/`):** it took the table name and column names from the request into its SQL, put ids in raw, and a save with no rows deleted every row of the posted table (`not in ('')`). Now a table needs a `cms_table_<table>` definition and must exist, columns come from that definition, and ids are bound. Its two definitions have never loaded (the lookup had no module, and the files have no `item` key), so no table was editable: every table was 404. The editor is now removed (see the Legacy table editor entry).
  - **Check:** `grok/tmp/cms_sql_lists_check.php`.
- [x] **Link input targets once per request** (review 2026-11-13, item 4; big plan item 2, done 2026-11-13). `cms_input_link` rebuilt every list, with one slug query per item, for each link input. Now `cms_page_panel_cms_model::get_link_targets()` (lists and pages) and `get_link_list()` (a list's titles and slugs: ids and titles only, one `cms_slug_model::get_slugs_by_targets()` query) keep their results for the request. Queries: 338 76 → 41, 4466 (three link inputs) 159 → 48 and 235 → 159 ms, block 1 72 → 37. Check: `grok/tmp/cms_link_targets_dump.php` (link input markup the same before and after), `cms_inputs_check.js after`.
- [x] **Edit page leftovers** (review 2026-11-13, item 5; big plan item 1, done 2026-11-13):
  - a repeater `meta: image` image now reaches `_images` (each row's image field; the `array_merge` result was thrown away, and it merged the whole repeater);
  - the dead `$('#block_id')` branch in `cms_page_panel_button_save.js` is gone;
  - `admin/panel_settings/<panel>/` creates a settings block only when the definition has `settings` (otherwise 404).
  - **Check:** `grok/tmp/cms_edit_leftovers_check.php`.
- [ ] **Groups tabs from the server** (review 2026-11-12; moved out of the big plan 2026-11-13). Until `cms_input_groups_init()` runs, every tab's fields show. `cms_input_repeater_item` knows the row's tab value (or the `groups` default): mark the other tabs' fields, and have `cms_input()` (`cms_fields_helper.php`) print `cms_input_groups_hidden` on their wrapper.
- [x] **Field layout CSS tidy** (review 2026-11-12; big plan item 5, done 2026-11-13). In `cms_page_panel.scss`, the span rules are one rule per height for form and repeater row fields (compiled CSS 27.4 → 25.2 KB); the 100-row cap is in `cms_input.md` § Form layout; the four spacing values have a comment each. Check: `cms_inputs_check.js after layout`, `cms_layout_width_check.js`.
- [x] **Fewer panels per field** (review 2026-11-11, item 5; big plan item 6, done 2026-11-13).
  - **Dispatcher:** `cms_input()` in `cms_fields_helper.php` replaces the `cms/cms_input` panel (panel and template removed); `print_fields()` and the admin templates call it.
  - **Help:** `cms_help()` replaces the `cms/cms_help` panel (template removed).
  - **Grid cells:** the grid template prints `cms/cms_grid_editable` cells itself (the panel keeps the cell's own ajax save).
  - **Label, help and translate icon:** printed by `cms_input_label()` in 22 input templates. The wrapper cannot print them: it is `display: contents`, and the label has to stay inside the `.cms_input` grid item.
  - **Result** (`cms_form_timing.php`): block 1 184 → 71 panels, 357 → 251 ms; note_system 100 → 35 panels, 358 → 225 ms; 5123 82 → 39 panels, 272 → 148 ms.
  - **Check:** `cms_inputs_check.js after` and `after layout` (all values, inputs and boxes match), `cms_layout_width_check.js`, `cms_help_check.js`, the grid cell, save, picker, caching and repeater row checks.
- [ ] **Configurable input types** (#87 "later") — which input types are available, e.g. per site or per module. The `input` definition flags are where a type would declare itself.
- [x] **Legacy table editor removed** (2026-11-13; a dead branch from early CMS development; `grok/review_small_plan.md` item 1). `admin/table/<table>/` (`cms/cms_table`) had never worked: its definitions never loaded, its template declared its own `print_fields()` with a panel that no longer exists (`admin_input_select`), and its save put request values into SQL. Removed: the route `admin::table()`, the panel, template, JS, the definitions `cms_table_cms_file.json` / `cms_table_user.json`, `cms_table_model`, the fk input's undocumented `target: table` branch (`cms_panel_model::get_cms_panel_fk_data()`), and `cms_input_image_rename()` (only `cms_table.js` called it). CMS Update deletes the files on client sites with the next core release.

- [ ] **xy / mask / transform targets JS** — target-image refresh (when the linked image field changes) for `cms_input_xy`, `cms_input_mask`, and imagemaker `cms_input_transform` should live in each input’s own JS (`cms_input_xy.js`, `cms_input_mask.js`, `cms_input_transform.js`), not only in `cms_input_image` or ad-hoc hooks. See `target` on those field types in [`cms_input.md`](cms_input.md).

---

## Page panel preview (#15 follow-ups)

Implemented core: admin split-view (`cms/cms_preview`), D|M toggle, saved-data iframe. Highlight stub: `cms_preview_highlight` cookie, `cms_preview_site_marker`, `cms/cms_preview_site` panel (outline + scroll disabled).

- [ ] **Panel highlight in preview** — re-enable `cms_preview_site` outline + scroll-to-panel via `cms_preview_highlight` cookie + `cms_preview_site_marker` (stub kept; useful when pages have multiple designed panels, e.g. marketing layouts)
- [ ] **Live unsaved preview** — POST merge like `cms_page_panel_preview_title`, bypass panel cache when preview cookie set
- [ ] **Open public page** link in panel toolbar (new tab)
- [ ] **Scroll iframe to `submenu_anchor`** when panel has anchor set
- [ ] **Preview host page** picker for list items with multiple template options
- [ ] Sync CMS navigation with iframe preview (explicitly not planned)

---

## Popup system — modernise layout

- [ ] Replace `display: table` / `table-cell` centreing in [`cms_popup.scss`](../css/cms_popup.scss) and `display: 'table'` in [`cms_popup.js`](../js/cms_popup.js) with flexbox (`display: flex`, `align-items: center`, `justify-content: center`)
- [ ] Replace float-based popup toolbars (`admin_left` / `cms_right`) with flex row layout in [`cms_popup.scss`](../css/cms_popup.scss) (targets popup gets scoped flex first)
- [ ] Review per-popup size overrides ([`cms_page_panel_button_export.scss`](../css/cms_page_panel_button_export.scss), [`cms_page_panel_button_targets.scss`](../css/cms_page_panel_button_targets.scss), mask picker, panel selector) after global shell change

---

## Ajax panel gate

- [x] **allow_ajax_panels gate** — `controller_ajax_api.php`: `do` / `no_html` / CMS admin bypass; setting gates public HTML render only
- [ ] **panel_params ajax data** — audit panels returning ajax data from `panel_params`; refactor to `panel_action` + early `no_html` return

---

## Updater

- [x] **Empty dirs after update** — `update_cleanup` deepest-first; drop legacy `application/` / root `js/` roots
- [x] **Install new modules** (#753) — list master `update.master` packages not on disk; Install → files + enable penultimate in settings `modules` (client gated by `update.allow`)

## Module extends

- [x] **config.json extends + JS** — `get_panel_filenames()` appends extension JS; detail: [`cms_module_extends.md`](cms_module_extends.md)
- [x] **config extends: template replace** (#715) — extension `templates/<source>.tpl.php` replaces target template; last module wins; see [`cms_module_extends.md`](cms_module_extends.md)
- [x] **Pages admin: Lists + System sections** — left column Pages / Lists / System; list shells `module_panel` slugs; system `not-found` / `internal-error` / `timeout`; 404 redirects to system page
- [ ] **System/list auto content** — helper panels on empty system + list template pages
- [x] **config extends: PHP controller** — extension `panels/<source>.php` chained after target; `panel_heading` last extender wins (reverse walk)
- [x] **config extends PHP optimise** — shared chain helper, method map (`_panel_controller_methods`), no double-load for heading probe
- [x] **`extends_by_target` / `extend_sources` at boot** — indexed from module config extends; used by merge + `get_panel_filenames`
- [x] **Remove legacy extend code from core** — definition JSON `"extends"` / `join_js` / `join_css` / `_extends` runtime plumbing removed; config.json target/source only
- [x] **Definition discovery** — `list_definition_panel_names` skips pure extend sources; `get_lists` / linkable types / schema use merged config
- [x] **`merge_structures` full key merge** — special rules for item/settings/list/buttons/js/label; unknowns overwrite
- [ ] **Panel definition cache keyed on git** — optional live-env mode: cache merged panel configs / discovery results and invalidate when git HEAD (or tree) changes, so deploys that only `git pull` skip repeated definition glob + merge work. Align with `cache.vcs_check: git` for CSS. Useful when code updates are git-only and definition collection is hot-path cost.

---

## Cross-installation RPC

Planned mechanism for one CMS installation to request work from another, RPC-style, over HTTPS. Each installation has an **installation API key** (or key pair); requests are authenticated and limited to an allowlist of operations.

### Use cases

**GeoIP lookup (analytics)**

- Site A has no GeoIP database; site B has MaxMind data and exposes a resolve endpoint.
- Analytics `_resolve_geo()` on site A can call site B; results still pass through local geo cache.
- Request carries anonymised IP only.

**Video encode offload**

- Site A basic CPU; site B GPU / faster ffmpeg as remote encode peer.
- Keep existing `cache/video_queue.json` model; optional remote worker step.

### Design constraints

- Installation identity + API key in site config (or CMS settings); keys rotatable.
- Explicit opt-in on **provider** installation (which RPC services: `geoip`, `video_encode`, …).
- **Consumer** config: peer URL + key + enabled services.
- Timeouts, size limits, audit log.
- No silent fallback — missing peer or failed RPC must be obvious in admin.

### Suggested order

1. Core RPC auth + request/response envelope in `cms` module.
2. GeoIP provider API on installations that host a GeoIP database file.
3. Analytics consumer path in `_resolve_geo()` when local mmdb missing.
4. Video encode remote worker protocol (heavier).

| Area | Location |
|------|----------|
| Local GeoIP | `modules/analytics/models/analytics_model.php` |
| Video queue / cron | [`cms_video_model`](../models/cms_video_model.php), [`cms_video_encode`](../panels/cms_video_encode.php) |
| Public API routing | [`system/cms.php`](../../../system/cms.php) |
