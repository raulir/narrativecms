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