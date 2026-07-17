# Panel controllers: `panel_params` and separation of concerns

## Admin list titles (`_title`)

CMS list rows use a cached admin title stored as panel param **`_title`**.

| When | Behaviour |
|------|-----------|
| **`create_cms_page_panel`** | Always runs full title process after params are written (`panel_heading` / `heading` / badges). Pass `_update_title => 0` only for rare bulk creates that set the title later. |
| **`update_cms_page_panel`** | Runs full title process only when needed (see below). |
| **List UI** | If `_title` empty, may lazy-refresh once via `get_panel_admin_title`. |

### Update flag `_update_title` (not stored)

| Value | Meaning |
|-------|---------|
| `1` / true | Force recompute `_title` after this update |
| `0` / false | Skip title refresh |
| omitted | **Auto** |

**Auto refresh when:**

- `purge` is true (typical admin full save / full product replace), or  
- `title` column is written, or  
- `heading` (or other non-meta content fields) are written, or  
- `_targets` changes (badge prefix)

**Auto skip when** the update only touches meta keys such as `show`, `sort`, `sync_needed`, `shopify_checked_at`, `last_update`, `update_time`, `update_cms_user_id`, image hash stamps, etc.

List-item titles prefer `panel_heading()` when defined, else definition `heading`, else a fallback. Do not put HTML badges into the `cms_page_panel.title` column — badges live in `_title`.

## `panel_params` is frontend-only

`panel_params($params)` on a **site / page panel** (e.g. `music/engine`, `user/userforward`) runs when that panel is **rendered on the public site** (or via ajax panel / position load that builds frontend HTML).

It is the place to:

- Load models and assemble **template variables** for the visitor-facing view  
- Start clocks, redirects, score HTML, sets, etc. that affect **what the visitor sees**

It is **not** the place to prepare **CMS admin edit forms**.

### Admin panel edit must not call page-panel `panel_params`

The admin UI (`admin/cms_page_panel/…`, [`cms_page_panel`](../panels/cms_page_panel.php) + `print_fields()`) edits **definition-driven fields** plus **stored block JSON**. It must **not** invoke the page panel’s `panel_params`.

Reasons:

- Side effects (redirects, session, heavy compute) break admin (e.g. `user/userforward` used to 302 the editor away)  
- Admin language is CMS language; frontend `panel_params` often assumes visitor language / session  
- Form values come from the database block, not from runtime visitor state  

If something was “needed for the form”, it belonged on a **custom field panel**, not on every page panel’s `panel_params`.

### Custom CMS field panels (allowed on admin)

Field types such as `music/exercise_params`, `cms/cms_input_image`, `music/note_system_key` are **input widgets**. When [`print_fields()`](../helpers/cms_fields_helper.php) renders them via `_panel()`, those field panels run **their own** `panel_params` to prepare the control (`value`, labels, etc.). That is correct: the field panel owns its admin UI.

| Panel kind | Admin edit form | Frontend page render |
|------------|-----------------|----------------------|
| Page / site panel (`music/unit`, `user/userforward`, …) | Definition + stored block only — **no** page `panel_params` | **Yes** `panel_params` |
| Custom field type (`music/exercise_params`, …) | Field’s own `panel_params` when rendered as input | Rarely used as page panels |

Prefer the **custom field panel pattern** for any admin-side preparation. Do not bolt admin concerns onto site panel `panel_params`. If a dedicated admin-only hook is ever required, introduce a **separate** method (e.g. `panel_admin_params`) — do not overload frontend `panel_params`.

### Redirects and other side effects

Helpers such as `_position_link_redirect()` must no-op on CMS admin requests so accidental calls cannot bounce the editor. Prefer also guarding in the panel:

```php
if (!empty($GLOBALS['cms_admin_request'])){
	return $params;
}
```

(`$GLOBALS['cms_admin_request']` is set by the admin controller.)

---

## Separation of concerns (CMS design)

This CMS has several hard layers. Crossing them causes subtle bugs (admin redirects, wrong language, cache pollution, coupling site behaviour to editor UI).

### 1. System · CMS · site design

| Layer | Responsibility |
|-------|----------------|
| **System** (`system/`) | Core bootstrap, routing, panel render pipeline, helpers (`_panel`, `_lh`, …) — detail: [`system.md`](system.md) |
| **CMS module** (`modules/cms/`) | Admin UI, definitions, page/panel storage, languages, images, access |
| **Site modules** (`music/`, `user/`, `basic/`, …) | Visitor-facing panels, business logic, site settings |

Site modules may **use** CMS services (models, definitions, `_ib`). They must not assume admin session or admin request shape when rendering the public site. System/CMS must not embed music-specific rules.

### 2. Panel controllers vs models vs templates

| Piece | Role |
|-------|------|
| **Panel controller** | Thin: `panel_params` / `panel_action` for **that surface** (frontend or field widget); load models; return params |
| **Model** | Logic, DB, shared helpers (callable from panels and other models) |
| **Template** | Markup only; no heavy logic |

Keep `_private` helpers on the model when reused; avoid dumping business logic only in `panel_params` where tests and admin cannot reach them cleanly.

### 3. Site (visitor) vs CMS user interface

| Concern | Visitor / frontend | CMS admin UI |
|---------|-------------------|--------------|
| Language | `get_current_language()` | `get_cms_language()` |
| Request flag | normal page / position ajax | `$GLOBALS['cms_admin_request']` |
| Panel lifecycle | full `panel()` → `panel_params` → template | definition fields + block; field widgets only |
| Side effects | redirects, analytics, engine state | save/list/settings only — **no** visitor redirects |

Logged-in CMS user browsing the **public** site still gets the **visitor** path (visitor language, frontend `panel_params`). That is intentional. Admin-only behaviour must key off **admin request**, not merely `cms_user` session.

### 4. Practical checklist

- New redirect / session / “prepare visitor page” code → frontend `panel_params` only; guard admin.  
- New admin form control → custom field panel (+ definition `type`), not page `panel_params`.  
- New shared data → model method.  
- Do not call `run_panel_method(..., 'panel_params')` from admin form builders for the **page** panel being edited.

Related: [`cms_panel_js.md`](cms_panel_js.md) (JS init contract), [`language.md`](language.md) (admin vs visitor language), [`cms_input.md`](cms_input.md) (field types).
