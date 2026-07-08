# CMS languages

Multilingual content requires **Visitor target groups** enabled in CMS settings (`targets_enabled`). Saving **CMS → Languages** enables this automatically.

Language IDs (e.g. `en`, `en-us`, `et`) identify locales. They are not separate sites. IDs are **case-insensitive** (`en-US` and `en-us` match the same language); use **lowercase** in CMS config.

## Admin configuration

| Panel | Admin path | Purpose |
|-------|------------|---------|
| **Languages** | `admin/panel_settings/cms__cms_languages/` | Primary UI: language grid (ID, label, local name). Syncs the `language` target group automatically. |
| CMS Target Groups | `admin/panel_settings/cms__cms_targets/` | Advanced groups (random, mobile, logged-in, etc.). The `language` group is auto-managed from Languages — edits here are overwritten on next Languages save. |
| CMS settings | `admin/panel_settings/cms__cms_settings/` | Site default language ID (`language` field). |

The frontend `basic/language` panel (page composition) reads language list from target groups. Dropdown prefix (`select_label`) is edited on the Languages page and synced to `basic/language` settings.

### Languages grid columns

| Column | Stored as | Notes |
|--------|-----------|-------|
| Language ID | `languages[].language_id` | Lowercase; locked after first save. First row = default language. |
| Label | `languages[].label` | Canonical name (e.g. `Estonian`). Synced to target group `labels` pipe. |
| Endonym | `languages[].endonym` | Native language name for frontend switcher (e.g. `Eesti`). `basic/language` uses endonym, falls back to Label. |
| Name (current CMS language) | `_translations.{cms_language}.local_labels.{language_id}` | Per-admin-language display names. Planned for CMS admin toolbar (`cms_language_select`). |

### Example after save

Target group `language`:

| Field | Example |
|-------|---------|
| Settings | `en\|et\|es` |
| Labels | `English\|Estonian\|Spanish` |

## Runtime visitor language

Resolved in [`system/core/targets.php`](../../system/core/targets.php), in order:

1. Cookie `language` (if ID is in the configured list)
2. Site default from CMS settings (if set and in the list)
3. `Accept-Language` header — full tag match first (`en-US` → `en-us`), then 2-letter prefix (`en` → `en`)
4. First ID in the target group list

Result is stored in `$GLOBALS['language']`:

- `language_id` — active visitor language
- `default` — default language ID
- `languages` — map of ID → label

## Admin editing language

Toolbar language selector sets `$_SESSION['cms_language']` (via `cms/cms_language_operations`).

Panel definition fields with `"translate":"1"` store values per language:

- Default language — main param rows (`cms_page_panel_param.language` is empty)
- Other languages — rows with `language` set to the language ID

Cached panel JSON uses `_translations.{language_id}.{field}`.

## Frontend display

- [`basic/language`](../../modules/basic/panels/language.php) panel — visitor language switcher (cookie). Option labels from Languages grid **Endonym**, fallback **Label**. Text dropdown with chevron (no flag icons).
- Translatable panel/page content — translation merged over default in [`get_cms_page_panel_params()`](../models/cms_page_panel_model.php)
- **Missing translation falls back to the default language** (`en` content shown when `en-us` has no value)

## Adding a language

1. Open **CMS → Languages**, add a row (ID + label). Target group and `targets_enabled` update on save.
2. Optionally enter translations in the admin per panel; untranslated fields use the default language automatically.
3. For MVP Spanish: add `es` / `Spanish`, then translate content with CMS toolbar language set to `es`.

## Bootstrap

On first open, if the Languages grid is empty, rows are imported from the existing `language` target group. `select_label` is copied from legacy `basic/language` settings when present.