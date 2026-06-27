# CMS visitor target groups

Enable in CMS settings: **Visitor target groups** (`targets_enabled`).

Configure groups in **CMS Target Groups** (`admin/panel_settings/cms__cms_targets/`). Assign per-panel visibility in the page panel editor **Targets** popup (toolbar hidden menu).

Panel settings definitions use the `settings` array in the panel JSON file.

## Strategies

| Strategy | Labels | Settings | Behaviour |
|----------|--------|----------|-----------|
| **language** | Display names | Language IDs (same order; first = default) | Resolves visitor language; see [`language.md`](language.md) |
| **mobile** | `desktop_label\|mobile_label` | Unused | `labels[0]` desktop, `labels[1]` mobile (`$_SESSION['mobile']` from User-Agent) |
| **loggedin** | `guest_label\|member_label` | Unused | `labels[0]` guest, `labels[1]` logged-in site user (`$_SESSION['user']`) |
| **admin** | `visitor_label\|admin_label` | Unused | `labels[0]` frontend, `labels[1]` CMS admin session |
| **random** | Division names | Positive weights (any numbers; proportional, e.g. `20\|30` → 40% / 60%) | One division per visitor session (sticky) |

Misconfigured groups are skipped silently (no frontend error).

## Per-panel visibility

Stored on the panel as `_targets`: map of group heading → division label. Empty / missing = visible to all divisions of that group.

Frontend filtering runs in [`_get_cms_page_panels()`](/system/core/controller_index.php) via [`panel_matches_visitor_targets()`](/modules/cms/models/cms_page_panel_model.php).

Admin display title is cached as `_title` in panel params (badge prefix + table `title`). Refreshed on panel save and targets save. Lazily backfilled when opening panel edit (toolbar etc.) if `_title` is missing. `cms_list_list` does not lazy-backfill — it uses `_title` when present, otherwise falls back to table `title`. UI does not inspect `_targets` for display.

## Random weights

Weights do not need to sum to 100. The system normalises by total weight (e.g. `20|30` and `40|60` behave the same). Label and weight counts must match; all weights must be positive numbers.

## Mobile / desktop panels

Use a **mobile** target group plus per-panel `_targets` instead of panel-specific responsiveness fields (e.g. `basic/mimage` no longer has a Responsiveness select).

## Cache

`$_SESSION['targets']` hash is included in panel HTML cache keys. Sticky strategies (random) keep cache stable per visitor session.