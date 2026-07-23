# Routing and slugs

Public URLs are resolved from the `cms_slug` table. Each row maps a unique **slug** (`cms_slug_id`) to a **target** string that the front controller understands.

**System bootstrap / Router / main controllers:** see [`system.md`](system.md). This file is about **slug storage, generation, admin edit, and route cache content**.

## Storage

| Column | Role |
|--------|------|
| `cms_slug_id` | URL path segment, e.g. `my-material-item` → `https://example.com/my-material-item/` |
| `target` | Internal route target |
| `status` | `0` = visible (routed, in sitemap); `1` = hidden |

Schema: [`modules/cms/schema/cms_slug.json`](../schema/cms_slug.json)

Model: [`modules/cms/models/cms_slug_model.php`](../models/cms_slug_model.php)

### Route cache file

Generated file: `cache/routes.php` (slug → `index/index/{target}/`).

- Written by `cms_slug_model::_regenerate_cache()` after slug changes.
- If the file is **missing** at request start, Router sets a flag; the main `Controller` constructor calls **`ensure_routes_cache()`** and, when the URI was non-empty, redirects once so routing reloads with the new map.
- Prefer calling `ensure_routes_cache()` anywhere that discovers a missing routes file (do not invent a dedicated HTTP controller for this).

Cron (repeating tasks) is a separate public API: **`/cms/cron/`** — see [`cms_video.md`](cms_video.md) / site settings **cron_trigger**.

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
| Timeout | `timeout` |

`show_404()` redirects to `/not-found/` when that slug is in the route cache (one clean page request — no nested page build).

## Slug generation

`cms_slug_model::slugify_slug()` normalises text then ensures uniqueness:

1. Lowercase, strip diacritics, replace non-alphanumeric runs with `-`
2. Drop common words (`a`, `an`, `the`)
3. Trim to 50 characters (cut at last `-` inside limit)
4. If empty after normalisation, random 4-letter fallback
5. If `cms_slug_id` already exists, append `-2`, `-3`, … until free

Manual edit uses `_slugify_candidate()` only — **no** auto-suffix. The operator must pick an available slug.

## Route cache

On every slug insert, delete, or status change, the model rebuilds:

- `cache/routes.php` — CodeIgniter `$route[...]` entries for visible slugs
- `cache/sitemap.xml` and `robots.txt` Sitemap line

[`system/core/Router.php`](../../../system/core/Router.php) loads `cache/routes.php` at bootstrap.

Example generated line:

```php
$route['my-slug'] = 'index/index/music/material=42/';
```

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
- Successful rename invalidates slug and list-item caches; `cms_slug_model::set_page_slug()` rebuilds route cache

## Link picker

[`cms_input_link`](../panels/cms_input_link.php) resolves list targets via `get_cms_slug_by_target()` so admin link fields show the public slug URL.