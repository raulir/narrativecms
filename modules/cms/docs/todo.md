# CMS / system backlog

Platform and core CMS work. Ships with the CMS module (not project-root).

Related design notes also live in topic docs (`cms_email.md`, `cms_schema.md`, `cms_video.md`, `cms_image.md`, `language.md`, `cms_panel_js.md`, `cms_module_extends.md`, …).

**Legend:** `[ ]` open · `[x]` done

---

## Email

- [x] Central mail helper — [`cms_email_model`](../models/cms_email_model.php); see [`cms_email.md`](cms_email.md)
- [ ] **Async email sending** — queue + background worker so `send_mail()` does not block the HTTP request (reminder / verification feel slow under SMTP). Detail: [`cms_email.md`](cms_email.md) § TODO

---

## Page cache

- [ ] **Panel actions on cache HIT** — when a full page is served from page cache (`cache::try_serve()`), CI does not boot and `panel_action` does not run. On cache **write**, store metadata listing which panels had `panel_action` on that page (panel name + instance params as needed). On cache **HIT**, lightweight bootstrap runs those actions only (e.g. `analytics/beacon` php pageview) before or alongside serving HTML. Ref: [`system/libraries/cache.php`](../../../system/libraries/cache.php), analytics php tracking skipped today on cache HIT.
- [ ] **Cached page shared `data-beacon_id`** — full-page cache is anonymous/shared HTML; `data-beacon_id` is baked from whoever built the cache. Beacon API prefers POST `beacon_id` over cookie, so visitors may share one analytics session. Fix: omit `data-beacon_id` from cacheable HTML and/or prefer visitor cookie over posted id when both are valid; per-visitor id only via JS cookie or panel-action on HIT. (Coordinates with analytics module.)

---

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

- [ ] **Remove legacy SPA config translation** — in [`system/core/config.php`](../../../system/core/config.php), delete `position_wrappers` / `position_links` → `single_page_mode` mapping once all envs only store `single_page_mode` (see comment there)

---

## Updater — [`cms_update.md`](cms_update.md)

- [x] **Config major.minor + Release snapshot** (#430) — `config.json` `"version"`, manual [Release], serve clients from `cache/master/{id}/`
- [ ] **Admin UI to edit package major.minor** — today operators set `"version": "x.y"` in module `config.json`; ticket for CMS UI instead of hand-editing JSON

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

## Data dumps / backups — [`cms_dump.php`](../panels/cms_dump.php)

Nicer dump management page + multi-backup workflow (today: ad-hoc generate/download/upload of `_dump*.zip` only).

### Page / UX

- [ ] **Redesign dump management page** — modern CMS admin UI for `admin/dump/` (clear layout, status, actions; not raw table + bare forms)
- [ ] **Multiple backups management** — keep and list several named/timestamped backups on this page (not a single overwrite of `_dump.zip` / `_dump_2.zip` / `_dump_db.zip`)
- [ ] **Restore without downloading** — apply a backup that already sits on the server (from the list) without download/re-upload round-trip

### Dump generation

- [ ] **Resources as separate files by month** — split resource/image packs by `YYYY/MM` (easier partial transfer and restore) instead of one monolithic files zip
- [ ] **Option: import resources without optimised images** — restore/upload originals only; skip derivative `_name.WIDTH.ext` (and similar) so target rebuilds optimised sizes lazily

### Upload / restore safety

- [ ] **Preflight PHP limits** — before upload/restore, check `upload_max_filesize`, `post_max_size`, and `memory_limit`; warn clearly when limits will fail the transfer
- [ ] **Validate upload contents** — after resources are uploaded, verify structure/permissions/paths are allowed (no path traversal, expected layout, writable targets)
- [ ] **Resources checksum registry** — store checksums of resource files; show when an update/restore is needed (local vs registered / package mismatch)

---

## Languages — [`language.md`](language.md)

- [ ] **Languages as real CMS list** — replace settings grid (`languages[]` JSON on `cms/cms_languages`) with a proper list panel (one list item per language); editable grid on list items, drop bootstrap-from-targets / manual sync
- [ ] **local_labels admin toolbar** — Use `cms_languages` column 3 `local_labels` in CMS admin toolbar language dropdown (`cms_language_select`)
- [x] **Endonym frontend switcher** — `languages[].endonym` in frontend `basic/language` switcher (fallback Label)
- [ ] Remove stale `basic/language` `language_settings` / icon data from DB (harmless orphans after Languages page ships)

---

## Panel editor — field position calculations

- [ ] Investigate `cms_page_panel_fields_init` absolute positioning (e.g. site `landing` “CTAs” subtitle section overlapping fields above) — not a subtitle container height fix

---

## Panel JavaScript — `*_ok` / `*_destroy` (deferred)

Source: [`cms_panel_js.md`](cms_panel_js.md). Per-module remaining work also tracked in each module’s `docs/todo.md` where applicable.

- [x] **`*_ok` refactor — CMS module** — all `modules/cms/js` panel inits; destroy on `cms_input_image`, `cms_image`, `cms_video`, `cms_images` popup
- [ ] **Repeater `$root` scoping** — pass new repeater row as `$root` to `*_init` instead of global scan
- [ ] **Repeater row delete → `*_destroy`** — call destroy hooks when repeater blocks are removed
- [ ] Ticket #24 (panel JS to jQuery objects) — closed; `*_init` + `*_ok` class guards instead

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

## Module extends (legacy cleanup)

- [x] **config.json extends + JS** — `get_panel_filenames()` appends extension JS; detail: [`cms_module_extends.md`](cms_module_extends.md)
- [ ] **Remove legacy extend code from core** — definition JSON `"extends"` / `join_js` / `join_css` in `controller.php` + `cms_panel_model.php`; DB `_extends.*` param handling
- [ ] **config extends: PHP + template override** — wire extension `panels/` and `templates/` for target panels

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
