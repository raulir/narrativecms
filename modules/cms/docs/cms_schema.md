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
- **Latest fix errors** (session `cms_schema_latest_errors`, partial `cms_schema/latest_errors.tpl.php`, used by the page and the fragment): a small **dismiss** button in its upper right corner posts `do=dismiss_errors`, which clears them on the server (only the filtered module's in a module view) and removes the section

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

Reasons on data rows: `Legacy params still present`, `List items missing table rows (N)` (joined with `; ` when both). These are data checks: a line can come back with no change to any definition or table, whenever items are written that way.

Integer fields with no param value sync as `0` so every list item gets a row (INNER JOIN safe).

The data fix visits only the items that need it (no row, or table fields still in params). An item that has no value for any table field cannot get a row: the fix reports it as an error with its ids ("N item(s) have no value for any table field …"), not as OK, and the line stays until the item is filled in or deleted.

### Writing items with a panel table

- **Create** (`create_cms_page_panel`): the table row is written right after the `cms_page_panel` row. If the database refuses it (a unique index, e.g. two requests creating the same item at once), the new item is removed again, the reason goes to the error log, and the create returns `0`. So no item is left without its row. A caller that may race on a unique field checks for `0` and reads the existing item (music: `engine_model::ensure_exercise_row`, which also creates under a lock per hash).
- **Update**: a refused row keeps the values as params, so nothing is lost; the check then lists the item as `Legacy params still present`.
- `_write_panel_table_row` returns `false` on a refusal; mysqli throws by default (PHP 8), so the write is caught (`_panel_table_query`) instead of ending the request.
- The params cache row (`name = ''`) is written with an upsert: a reader that finds no cache while a save rebuilds it builds it at the same time, and a plain insert of the second one hit the unique key.

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
