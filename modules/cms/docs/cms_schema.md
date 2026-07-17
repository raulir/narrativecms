# cms_schema panel

Panel: `modules/cms/cms_schema`

## Purpose

Diagnostic panel showing schema differences from
`cms_schema_model->check_schema()`.

Supports granular fixing via `cms_schema_model->fix_schema($path)`.

## Layout

Diagnostic panel standard for this project:

- Toolbar with cms_tool_text "Database schema"
- Errors grouped by module
- Per-module header: title + "fix module" button (right-aligned, flex)
- Per-error row:
  - Location — raw error key (e.g.
    `cms:cms_image:columns:type:collation`)
  - Description — message from model
  - Action — fix button

## Unified fix buttons

- Single class for ALL fix buttons: `cms_schema_fix`
- Always use `data-key="..."` attribute containing exact path passed to
  `fix_schema()`

## Template specifics

- Full-width flex rows (no fixed container width)

## Controller notes

- `panel_action()` will receive `key=...` and call `fix_schema($key)`

## JS notes

- One click handler for `.cms_schema_fix` using `data-key`

## Model integration

- `check_schema()` → error keys exactly as displayed
- `fix_schema($path)` supports module / table / column / property / index
  level
- Always re-checks issue still exists before any SQL

## Panel tables (definition-driven)

List panel fields may opt into a real SQL table `{module}_{panel}` (e.g. `shopify_product`) for fast filters.

| Definition key | Meaning |
|----------------|---------|
| `"table": "1"` | Store field on the panel table; `get_list` / `get_cms_page_panels_by` filters this field in **SQL** (not a PHP loop over all rows) |
| `"table_type"` | Column type: `int` / `int:N` (unsigned INT default 0), `int_signed` / `int_signed:N`, `varchar:N`, or omit for TEXT |
| `"table_index"` | Non-empty → secondary index; `"unique"` → unique index |

Workflow:

1. **Schema fix** (create/alter panel table) — builds structure **and** copies existing
   field values from `cms_page_panel_param` (named row or JSON blob) into the new/updated
   table, then removes legacy named param rows for those fields. Same work as “sync panel
   tables”, done automatically after a successful structure fix for a definition panel table.
2. **Sync panel tables** (optional) — re-run data migrate only (recovery if something was
   skipped, or when structure is already OK but named params remain).

Integer fields with no param value sync as `0` so every list item gets a row (INNER JOIN safe).

### Demote / remove (reverse)

| Change in definition | Schema fix does |
|----------------------|-----------------|
| Remove `"table": "1"` but keep the field in `item` | Copy column → panel params, then `DROP COLUMN` |
| Remove field from definition entirely | `DROP COLUMN` only (no param restore — no official place for the data) |
| No `"table": "1"` fields left on the panel | Orphan error: restore remaining **item** fields from table → params, then `DROP TABLE` |

Orphan detection is **definition-driven** only: list+item panel JSON → table name `{module}_{panel}`. No scanning of arbitrary `{module}_*` database tables (avoids false positives). All managed tables come from `schema/*.json` and/or panel `table` fields.

## TODO

- Show errors inside the panel
- Show JSON syntax errors (and database errors etc — needs more general
  system for CMS) as normal red rows inside the panel (instead of the top
  system red bar)
- Confirmation for module fix — simple JS `confirm()` before "fix module"
  (to prevent accidental mass changes)
- Graceful SQL error handling — collect ALTER errors and show them nicely
  in the panel instead of raw MySQL output
- Explicit error sorting — always show column errors before index errors
  in each module group