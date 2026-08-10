# cms_schema panel

Panel: `modules/cms/cms_schema`

## Purpose

Diagnostic panel showing schema differences from
`cms_schema_model->check_schema()`.

Supports granular fixing via `cms_schema_model->fix_schema($path)`.

## Backups before destructive DB work

Do **not** leave recovery copies as MySQL `*_bu` / shadow tables. Snapshot tables to **`cache/db/*.zip`** (SQL dump inside) — see [`agents.md`](agents.md) § Database backups. Example: route rebuild uses `cms_slug_model::backup_table_sql_zip('cms_route')`.

## Layout

Diagnostic panel standard for this project:

- Toolbar with cms_tool_text "Database schema"
- Green **“All database tables and data match…”** only when `check_schema()` has no errors (structure **and** panel-table data)
- Errors grouped by module
- Per-module header: title + **fix module**
- Per-error row:
  - Location — raw error key (e.g. `cms:cms_image:columns:type:collation`, or `shop:panel_table:shop_product`)
  - Description — message from model
  - Action — **fix** button

Within a module, structure/orphan rows come first; panel-table data rows last.

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
- After **fix** (module or row): HTML is replaced with a **fresh full schema check** (all modules on the main page)
- Status toasts use **`cms_notification`** (top edge): success or remaining-module message from the server; errors use the error style

## Model integration

- `check_schema()` → error keys exactly as displayed
- `fix_schema($path)` supports module / table / column / property / index /
  `panel_table` level
- Always re-checks issue still exists before any SQL

## Panel tables (definition-driven)

List panel fields may opt into a real SQL table `{module}_{panel}` (e.g. `shopify_product`) for fast filters.

| Definition key | Meaning |
|----------------|---------|
| `"table": "1"` | Store field on the panel table; `get_list` / `get_cms_page_panels_by` filters this field in **SQL** (not a PHP loop over all rows) |
| `"table_type"` | Column type: `int` / `int:N` (unsigned INT default 0), `int_signed` / `int_signed:N`, `varchar:N`, or omit for TEXT |
| `"table_index"` | Non-empty → secondary index; `"unique"` → unique index |

### Error keys and fix

| Key | Meaning | fix does |
|-----|---------|----------|
| `{module}:{table}` / columns / indexes | Structure | Create/alter as usual; after a definition panel table is OK, auto-syncs param → table data |
| `{module}:{table}:orphan` | Table left after definition dropped table fields | Reverse-migrate remaining item fields, drop table |
| `{module}:panel_table:{table}` | Data only (legacy params and/or missing table rows) | `synchronise_panel_table_data` for that table |

**fix module** (`key` = module name): structure + orphans first, then panel-table data for that module.

Reasons on data rows: `Legacy params still present`, `List items missing table rows` (joined with `; ` when both).

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
