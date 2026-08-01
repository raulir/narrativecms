# Routing and slugs

Public URLs are resolved from the **`cms_route`** table. Each row maps a unique **slug** to a **target** string that the front controller understands.

**Trailing slash:** site paths built with `_l()` / `_lh()` always end with `/` on the path (before `?` or `#`). External `http(s)://`, `mailto:`, `tel:`, and static file paths (with an extension) are left unchanged.

**System bootstrap / Router / main controllers:** see [`system.md`](system.md). This file is about **slug storage, generation, admin edit, and route cache content**.

## Storage

| Column | Role |
|--------|------|
| `slug` | URL path segment, e.g. `my-material-item` → `https://example.com/my-material-item/` (PRIMARY KEY) |
| `target` | Internal route target |
| `status` | `0` = visible (routed, in sitemap); `1` = hidden |

Schema: [`modules/cms/schema/cms_route.json`](../schema/cms_route.json) (migrates from legacy `cms_slug` once)

Model: [`modules/cms/models/cms_slug_model.php`](../models/cms_slug_model.php) (loader name unchanged)

### Request resolve (DB, not PHP)

Public paths are resolved **early** in [`system/cms.php`](../../../system/cms.php) via [`cms_route_resolve()`](../../../system/core/cms_router.php) (#105):

1. Config loads and **opens mysqli** (`config.php`).
2. Path normalize + **module API** short-circuit (before session).
3. **`cms_route_resolve($path)`** — reserved controllers (`ajax_api`, `admin`, …) or **one PK query** on **`cms_route`** for visible slugs (#343).
4. Session, targets, page cache, then dispatch (`CodeIgniter.php` bridge until full `cms_dispatch`).

Table: **`cms_route`** (`slug` PK, `target`, `status`). Schema migrates from old `cms_slug` once. Model: [`cms_slug_model`](../models/cms_slug_model.php) (loader name kept).

Cron (repeating tasks) is a separate public API: **`/cms/cron/`** — see [`cms_video.md`](cms_video.md) / site settings **cron_trigger**.

## Rebuild routes (slugs)

After bad data (e.g. numeric product slugs), rebuild all public routes from current content:

| | |
|--|--|
| UI | Admin **Data and backup** page → **Rebuild routes** (`cms/cms_rebuild_routes`, ajax `panel_action`) |
| Method | `cms_slug_model::rebuild_all_routes()` |
| Backup | Zipped SQL under **`cache/db/cms_route_YYYYMMDD_HHMMSS.zip`** (never a `*_bu` table — see agents.md DB backup rule) |
| Effect | Then `TRUNCATE cms_route`, reinsert from main pages + all `link_target` list items (title/heading → slugify; repeated words dropped) |
| Status | List `show` → route `status` 0 visible / 1 hidden |

Also invalidates sitemap file cache.

## Sitemap (#643)

Dynamic XML sitemap from visible `cms_route` rows (`status = 0`). No static XML file in the repo root for crawlers — PHP builds it on demand with a short file cache.

| Piece | Detail |
|-------|--------|
| API | [`modules/cms/api/sitemap.php`](../api/sitemap.php) — registered as `"id":"sitemap"` in [`config.json`](../config.json) |
| Public URLs | **`/cms/sitemap`** and **`/sitemap.xml`** (same body) |
| Pretty path | `.htaccess` rewrites `sitemap.xml` → `index.php?/cms/sitemap`; [`cms_path.php`](../../../system/core/cms_path.php) also maps path `sitemap.xml` → `cms/sitemap` when `REQUEST_URI` is unchanged |
| Build | **Only** from the API: `get_sitemap_xml_cached()` → `build_sitemap_xml()`. Panels / shopify / admin never build XML. |
| File cache | `cache/sitemap.xml` — **TTL 300s** (or until invalidate) |
| HTTP cache | `Cache-Control: public, max-age=300` |
| Invalidate (not rebuild) | Slug insert / delete / status / rename calls `invalidate_sitemap_cache()` (unlink cache file only). Next `/sitemap.xml` or `/cms/sitemap` rebuilds. |
| robots.txt | On each **sitemap cache rebuild** (API only), `ensure_robots_txt()` checks/fixes the `Sitemap:` line. Custom crawl rules kept; missing file gets default Allow-all. |

Do **not** call build/regenerate from panels or product sync. Route writes only drop the cache; crawlers trigger rebuild via the fake XML / API path.

## Target formats

| Kind | Target example | Created by |
|------|----------------|------------|
| CMS page | `4` (numeric `cms_page_id`) | Page save / visibility |
| List item | `music/material=42` (`{panel_name}={cms_page_panel_id}`) | List item save when `list.link_target` is set in panel definition |

List items must have `"link_target": "1"` (or any truthy value) under `"list"` in the panel definition JSON. On save, [`cms_page_panel_model::save_cms_page_panel_admin()`](../models/cms_page_panel_model.php) (via [`cms_page_panel`](../panels/cms_page_panel.php) `panel_action`) generates a slug from the list item title and stores it via `cms_slug_model::set_page_slug()`.

Show/hide on a list item updates slug visibility through `cms_page_panel_model::set_cms_page_panel_show()` → `cms_slug_model::update_slug_status()`.

### List type template pages (admin Pages → Lists)

Each linkable list type gets a **main** CMS page used as the layout shell for all items of that type:

| Field | Example |
|-------|---------|
| `meta.page_class` | `list` |
| `meta.list_panel` | `shop/product` |
| Page slug | `shop_product` (`{module}_{panel}` with panel `_` → `-`) |

Front controller resolves the shell by that slug only (no bare `product` fallback). The shell’s own public route stays **hidden**; list **items** still use title-based public slugs.

### System pages (admin Pages → System)

Reserved main pages (`meta.page_class` = `system`), **non-numeric** slugs (numeric strings would clash with `cms_page_id` routing):

| Title | Slug |
|-------|------|
| 404 - Not found | `not-found` |
| 500 - Internal error | `internal-error` |
| 504 - Timeout | `timeout` |

`show_404()` redirects to `/not-found/` when that slug is in the route cache (one clean page request — no nested page build).

**PHP max execution time:** after API branching, front requests register a shutdown handler ([`system/helpers/error_helper.php`](../../../system/helpers/error_helper.php)). On `Maximum execution time…` fatal: HTTP 504 + soft redirect (`meta refresh`) to `/timeout/` when not already there, and minimal HTML “Script timeout. Click here” linking to site root. Module **API** scripts do not register this handler (normal fatals).

## Slug generation

`cms_slug_model::slugify_slug()` normalises text then ensures uniqueness:

1. Lowercase, strip diacritics, replace non-alphanumeric runs with spaces
2. Drop common words (`a`, `an`, `the`)
3. Drop **repeated words** (keep first occurrence only — e.g. no double `birthday`)
4. Join with `-`, trim to 50 characters (cut at last `-` inside limit)
5. If empty after normalisation, random 4-letter fallback
6. If `slug` already exists, append `-2`, `-3`, … until free

Manual edit uses `_slugify_candidate()` only — **no** auto-suffix. The operator must pick an available slug.

## Route writes

On every slug insert, delete, or status change the model:

- Updates **`cms_route`** (source of truth)
- Invalidates sitemap file cache only (`invalidate_sitemap_cache` — no XML rebuild)

Example DB row: `slug = my-slug`, `target = music/material=42`, `status = 0`.

## Page HTML cache

Renaming or hiding a slug does not rebuild page HTML automatically. After a manual slug change, [`cms_page_cache_model`](../models/cms_page_cache_model.php) invalidates:

- Old and new slug cache files (`invalidate_slug`)
- The list item partial cache (`invalidate_list_item`)

Fresh HTML is built on next request.

## Manual slug edit (admin)

For list items with `list.link_target` and an existing slug, the gears menu offers **Edit slug** (after Export).

| Piece | Path |
|-------|------|
| Popup panel | `cms/cms_edit_slug` |
| Toolbar button | `cms/cms_page_panel_button_edit_slug` |
| JS | `modules/cms/js/cms_page_panel_button_edit_slug.js` |
| SCSS | `modules/cms/css/cms_edit_slug.scss` |

Behaviour:

- Live check on input (500 ms debounce): **Slug available** (green), **Slug taken** (red), **Disallowed characters** (red) when typed text ≠ slugified form
- Current slug counts as available
- **Update** re-validates server-side; on collision returns error (no `-2` suffix)
- Successful rename invalidates slug and list-item caches; `cms_slug_model::set_page_slug()` updates `cms_route` and drops sitemap file cache (rebuild waits for next `/sitemap.xml` hit)

## Link picker

[`cms_input_link`](../panels/cms_input_link.php) resolves list targets via `get_cms_slug_by_target()` so admin link fields show the public slug URL.