# CMS panel inputs

Panel definition fields live in `modules/<module>/definitions/<panel>.json` under `item` (per-instance panel params) or `settings` (global panel settings). The admin UI renders them via [`print_fields()`](../helpers/cms_fields_helper.php). Every field, built-in or a module's own type (such as `music/exercise_params`), goes through the input function `cms_input()`, which renders the `cms/cms_input_*` panel for its type (see [How an input is rendered](#how-an-input-is-rendered)).

## How an input is rendered

One path for every input (narrativecms #87):

1. **`print_fields($structure, $data, $prefix, $key)`** builds the same params for each field:
   - the definition keys;
   - `value` (the stored value, or `default` when there is none; `:date:` and `:rnd:` defaults are made here);
   - `name` (`panel_params[<field>]`, or `panel_params[<prefix>][<field>][]` in a repeater row), `name_clean` and `field_name` (the definition `name`);
   - `prefix` and `key` (the repeater and row);
   - `panel_structure`;
   - `base_id` (the block id) and `base_name` (the block's panel).

   It then calls `cms_input($field)` (with `_return`). It has no branch per type.
2. **`cms_input($params)` ([`cms_fields_helper.php`](../helpers/cms_fields_helper.php))**, a function, not a panel, prepares what every input shares:
   - the input panel for the `type` (`cms_input_panel_name()`: `module/panel` as is, a built-in type as `cms/cms_input_<type>` when its template exists; aliases `color` → `colour`, `panels` → `cms_page_panels`);
   - `mandatory_class` and `*` on the label; `[no label]` when the field has no `label` key; `help`; `readonly` (`'0'`); `groups` as an array;
   - `"` → `&quot;` in a string `value` and in `default`, unless the input declares `value_raw` or the type is a module's;
   - the access pass for the input panel (the type comes from a definition, and panel `access` keys are visitor keys);
   - `cms_input.scss`.

   It renders only for a CMS user. It prints the input, or returns it when `_return` is set.
3. **The input panel's own controller** prepares everything else for its type, for example:
   - select: empty option, mandatory;
   - repeater: `data`, sub-field `base_id`, JS preload;
   - grid: columns and rows from `ds_<ds>`.

   Its template's root is the `.cms_input` element (or `.cms_repeater_container` / `.cms_grid_container`), without its own `.cms_input_container`. Inside the root, it prints its head with `cms_input_label($label, $help, $for, $translate_type)`: the label, the help icon (`cms_help()`), and the translate icon when the template passes its type (only for a field that translates and is not read-only). Inputs with their own label layout (image, mask, xy, page panels, groups, subtitle) print the label themselves and call `cms_help($help)`.
4. **The wrapper** comes from `cms_input()`: one `.cms_input_container` (with `cms_input_container_groups` and `data-groups`), unless the input declares otherwise.

**Panels per field:** one, the input panel. The help icon is printed by `cms_help()` (not a panel), and a grid's `cms/cms_grid_editable` cells are printed by the grid template (the panel still serves a cell's own ajax save and reload).

**Input flags,** in the input panel's definition (`modules/cms/definitions/cms_input_<type>.json`):

```json
{ "input": { "value_raw": "1" } }
```

| Flag | Values | Used by |
|------|--------|---------|
| `container` | `""` (default) one `.cms_input_container`; `"none"`, no wrapper (the input is its own row in the form) | `repeater` none |
| `value_raw` | `"1"`: the value is passed as it is (an array, or compared with option keys) | select, cms_page_panels, repeater, grid, repeater_select |

**Admin templates** use the same path for their own fields: `cms_input(['type' => 'text', 'name' => …, 'value' => …, 'label' => …])`, as in `cms_page.tpl.php` and `cms_page_panel.tpl.php`. Their panel loads `cms/cms_fields_helper`. Pass `'label' => ''` for an input without a label. Only an input that contains another input renders it directly: `layout` and `repeater_select` render `cms/cms_input_select`; `modules` and `multifk` render `cms/cms_input_multi`. The outer input gets the wrapper.

**A module's own input type** is a panel `module/panel` used as the field `type`. It gets the params above (value as it is), runs its own `panel_params`, and can declare `input` flags in its definition. Its root element is `.cms_input` with `data-cms_input_height` (rows) and, for the full width, `data-cms_input_width="2"`, so the form lays it out (see [Form layout](#form-layout)). It prints its head with `cms_input_label($label, $help ?? '')`.

### Form layout

The block edit form (`.cms_page_panel_fields`, `cms_page_panel.scss`) is a CSS grid of two columns and 3.5 rem rows; no JS places the fields.

- **Size:** each `.cms_input` spans the rows in its `data-cms_input_height` (1 when missing) and both columns when `data-cms_input_width` is `2`. Its height comes from those rows; the input's own CSS height does not apply there.
- **Row cap:** the span rules go up to 100 rows (`$cms_field_height_max`). An input that declares more gets one row, so its content covers the fields below. Keep declared heights at 100 or less, or raise the cap.
- **Order:** fields are placed in definition order, each in the shorter column; a two-column field goes below both.
- **Growing inputs:** a repeater is a full row as tall as its content. The grid input spans its rows (`style` and `data-cms_input_height`, updated when a row is added or deleted).
- **Repeater rows:** one column of 3.5 rem rows below the toolbar, in field order. The fields of a `groups` tab are moved right after the `groups` input (`cms_input_repeater.php`), and only the open tab's fields are shown, so a row is as tall as its open tab.

### What a save stores

Save (`cms/cms_page_panel` `cms_page_panel_save`, `cms_page_panel_cms_model::build_panel_data_for_save()`) takes from the posted `panel_params` only what the block's edit structure has fields for (`filter_posted_panel_params()`):
- each field's `name`, and each repeater's row field names;
- each grid's `{name}_log`;
- never a name starting with `_`, or a block column (`show`, `sort`, `panel_name`, …). The server adds `_template_page_id`, `_js`, `_css` and `_images` itself.

Below a field, the value is the input's own: for example, a link posts `[target]`, `[cms_page_id]` and `[_value]`. So an input that posts more than one name must post them under its field name.

A stored block keeps its stored `panel_name`, `cms_page_id`, `parent_id` and `sort`; the hidden inputs are read only for a new block. A new block's page and parent must exist, and the parent field it is added to (`parent_name`, from the panel picker's `target_input_name`) must be a `cms_page_panels` field of the parent's edit structure (`cms_page_panel_cms_model::check_block_place()`). The editor checks the picker's target the same way before it prints the form. An editable grid cell (`cms/cms_grid_editable`) outside a grid log writes only a field of the item's definition, and with `ds` only the `ds` of a grid field in the block's definition.

**Admin vs frontend:** preparing values for these **field** panels may use that field’s own `panel_params`. Do **not** run the **page panel’s** frontend `panel_params` when building the admin form — see [`cms_panel_params.md`](cms_panel_params.md).

Client behaviour follows [`cms_panel_js.md`](cms_panel_js.md): each input panel has `cms_input_<type>_init($root)` and a `cms_input_<type>_ok` guard class on its root element. Repeater fields preload the panel JS and call the matching init hook when a row is added.

`*` = required where noted.

## Panel-level flags (definition root)

| Property | Description |
|----------|-------------|
| `ensure_data` | Truthy (`"1"`) — before **create** and **purge** saves, fill missing top-level fields from existing DB values, then definition defaults / type fallbacks (#114). Runs on the **full** field bag **before** `table:1` split, so panel-table columns are filled the same way as param fields (existing is loaded with table overlay). Does **not** run on partial non-purge updates. Non-purge already keeps unmentioned param rows and table columns. On **purge**, extra param keys not in the write (and not refilled) are still deleted; only definition fields + `_…` meta are refilled. Intentionally empty posted values are kept. V1 only ensures **top-level** fields (repeaters become `[]` if missing, not deep-filled). |

```json
{
  "label": "My panel",
  "ensure_data": "1",
  "item": [ … ]
}
```

Cost is intentional: only panels that need this integrity should set the flag. Cached param JSON encode failures always abort the cache write (all panels).

## Universal properties

| Property | Description |
|----------|-------------|
| `type` * | Input type — see list below. Alias: `color` → `colour`. |
| `name` * | Template variable name. Lowercase alphanumerics and underscores only; first character must be a letter. Good: `heading`, `image`, `testing_text`. Bad: `testing text`, `Text`, `123`. |
| `label` * | Text shown next to the input in the CMS. Do not repeat the word “label” in the label text. |
| `help` | Help text in the CMS. `[` `]` bold, `{` `}` italic, `\|` line break. |
| `mandatory` | `"1"` — field must be non-empty before the panel can be shown. |
| `readonly` | `"1"` — display-only (supported on `text`, `textarea`, `select`, `repeater`, `image`). On `textarea` with `html`, TinyMCE is not loaded; content is shown in a plain readonly textarea. |
| `translate` | `"1"` — value is stored per language when multilingual CMS is enabled. |
| `groups` | String or array — only inside a `repeater`; show this field when the active repeater tab matches (used with a sibling `groups` input). |

### `multi` / `modules` — sticky chips

| Property | Description |
|----------|-------------|
| `values` | Map of selectable keys → labels. |
| `sticky` | Same map shape as `values`. Keys always appear first, cannot be removed or reordered (no drag, no ×). Still submitted in the field value. Labels fall back to `values` if omitted. |

Example (Site settings modules — also hard-coded as default on `cms/cms_input_modules`):

```json
"sticky": { "cms": "cms" }
```

## Default values

| Pattern | Use |
|---------|-----|
| plain string | Static default |
| `today` | Today’s date (`date` / `datetime`) |
| `:date:Y-m-d` | PHP `date()` format at render time |
| `:date:Y-m-d:86400` | `date(format, time() + offset_seconds)` |
| `:rnd:12` | Random lowercase alphanumeric string, length 12 |
| `:meta:page:seo_title` | Pull default from another field on the same panel (text / textarea) |

---

## Input types

### text

| Property | Description |
|----------|-------------|
| `default` | Default value |
| `search` | Search weight `0`–`3` |
| `max_chars` | Maximum character length |

### textarea

| Property | Description |
|----------|-------------|
| `default` | Default value |
| `lines` * | Height in CMS line-heights |
| `width` | `"wide"` — double-column width |
| `md` | `"1"` — plain textarea with **Preview** / **Edit** toggle; markdown rendered server-side via `cms/cms_input_textarea`. No TinyMCE. Works with `translate`. |
| `md_filter` | `"module/panel/md_filter_<name>"` — optional pre-markdown filter; the method name must start with `md_filter_` (anything else is refused, `markdown_md_filter_valid()`); receives live `text` and caller `cms_page_panel_id`, returns filtered `text` and optionally `images` for `![alt](id)` resolution. Example: `"music/material/md_filter_preview_text"`. |
| `html` | Enables WYSIWYG editor. String of feature letters: `H` header, `L` list, `A` link, `B` bold, `I` italic, `U` underline, `C` colour, `Q` quote, `P` line breaks as `<p>` (default is `<br>`), `M` media (image upload). Ignored when `md` is set. |
| `html_class` | CSS class on TinyMCE body |
| `html_css` | Stylesheet URL for TinyMCE content |
| `styles` | With `M` — image style formats, e.g. `[{"name":"Left","style":{"float":"left"}}]` |
| `search` | Search weight `0`–`3` |
| `max_chars` | Maximum character length |

### date

| Property | Description |
|----------|-------------|
| `default` | Default value; `today` = today |

Returns string `YYYY-MM-DD`.

### datetime

| Property | Description |
|----------|-------------|
| `default` | Default value; `today` = today |
| `format` | `"timestamp"` — store Unix timestamp (seconds) instead of `YYYY-MM-DD HH:MM` |

### image

| Property | Description |
|----------|-------------|
| `category` | Image library category — common: `content`, `icon`, `diagram`, `logo` |
| `meta` | `"image"` — also used as page SEO / social image |
| `size` | `"small"` — compact input without static preview |
| `default` | Upload path (e.g. `2020/02/test.png`) or module default (`mymodule/test.png` → `modules/mymodule/img/test.png`) |

Detail: [`cms_input_image.md`](cms_input_image.md).

### select

| Property | Description |
|----------|-------------|
| `values` * | Object of `value` → `label` |
| `default` | Selected value (must exist in `values`) |
| `add_empty` | `"1"` — prepend `-- not specified --` option (value `''`) |
| `mandatory` | `"1"` — requires a selection; implies `add_empty`. Empty values `''` and `0` fail validation |

### multi

| Property | Description |
|----------|-------------|
| `values` * | Object of `value` → `label` |

Checkbox list; stores selected keys as an array.

### link

| Property | Description |
|----------|-------------|
| `targets` | Comma-separated target kinds. Default: `none,manual,page,lists`. `none` — empty allowed; `manual` — typed URL; `page` — CMS pages; `lists` — list items with `link_target`; `[list_name]` — e.g. `article` for one list type |
| `format` | `"short"` — single-line compact layout |

### file

| Property | Description |
|----------|-------------|
| `accept` | Extensions offered in the browser's file picker, e.g. `.mp4` |

The file is a CMS file (`cms_file`), stored in the data folder (`dir.data`, default `data/`) in `<year>/<month>/` by upload time. That folder is never served: the file is reached through the `cms/file` API, `cms/file/<year>/<month>/<name>` (`cms_file_url()`, `_lf()`, `_lfd()`), which sends it as a download. A file with a `cms_user_id` is sent only to that logged-in site user. Any type may be uploaded. Rename keeps the file in its folder and keeps its extension.

### cms_page_panels / panels

| Property | Description |
|----------|-------------|
| `panels` * | Comma-separated panel ids allowed (must exist in module `config.json`) |
| `size` | Visible panel rows before scroll; default `4` |

Nested page panels editor.

### panel / cms_panel

| Property | Description |
|----------|-------------|
| `flag` | Limit choices to panels with this flag in `config.json` (e.g. `cron`). Omit to list every `panels` entry. |
| `add_empty` | `"1"` — prepend empty option |

Select panel type from installed modules. Page placement uses `"placeable": 1` on `config.json` `panels` (`cms_panel_selector`), not this field.

### list

| Property | Description |
|----------|-------------|
| `add_empty` | `"1"` — prepend `-- not specified --` option (value `''`) |
| `mandatory` | `"1"` — requires a selection; implies `add_empty`. Empty values `''` and `0` fail validation |
| `link_target` | `"1"` — only lists with definition `list.link_target` set (linkable / public slug lists). `"0"` or omit — all lists |

Select one **list panel type** (definitions with a `"list"` block), e.g. `shop/product`. Labels are formatted `Shop/Product` (each path segment capitalised). Stored value is the raw id `module/panel`.

### fk

| Property | Description |
|----------|-------------|
| `list` | List id (e.g. `mymodule/mylist`) |
| `add_empty` | `"1"` — prepend `-- not specified --` option (value `''`) |
| `mandatory` | `"1"` — requires a selection; implies `add_empty`. Empty values `''` and `0` fail validation |
| `extend` | `"1"` — on load, keep the stored id and add `_fk_{name}` pointing at that panel in the request cache (id plus language). Empty, `0`, and a missing target add no pointer. One level only: the loaded panel does not get `_fk_` fields of its own. `_fk_*` is not saved. A save or delete sets that cache slot to empty, so existing pointers become empty until the next load. Use the pointer only when it is not empty. Top-level fk, or an fk one level inside a repeater (pointer on the row). Not `multifk`. |

Deprecated alternative (block target):

| Property | Description |
|----------|-------------|
| `target` * | `"block"` |
| `name` * | `"[target_panel]_id"` |
| `label_field` | Field used as option label; default `heading` |

### repeater

| Property | Description |
|----------|-------------|
| `fields` * | Array of input definitions |
| `height` | Minimum block height in rems |

Only one nesting level: a `repeater` or `grid` inside a repeater row is not rendered; it shows an error naming the field instead.

**Adding a row:** "Add element" asks `cms/cms_input_repeater_item` (ajax) for one row, sending:
- `block_panel`: the block's panel;
- `section`: `item` or `settings`, the definition section the repeater is in;
- `name`: the repeater's name;
- `repeater_key`;
- `base_id`: the block id.

The row's fields come from that definition (`cms_repeater_definition()`, `cms_fields_helper.php`), never from the request. An unknown panel, section or repeater gives an error, not a row.

**Row keys:** a key only makes a row's ids and classes unique, because field names end in `[]`. The rows area holds the next free key (`data-next_key`), so a row added after a delete never takes an existing row's key.

### repeater_select

| Property | Description |
|----------|-------------|
| `target` * | Repeater field name to read options from |
| `field` * | Repeater sub-field to use as label (`text` or `textarea`) |
| `add_empty` | `"1"` — prepend empty option |
| `labels` | Optional label map |

### subtitle

Section heading in the CMS form. No `name` required.

### list_link

Shows a list item URL on the site. No `name` required.

### groups

Repeater-only tab control. Same shape as `select` (`values`, etc.). Other fields in the same repeater block use `"groups": ["tab_id"]` (or array) to show only when that tab is active. They are shown right after the `groups` input, whatever their place in the definition; a field may be in several tabs.

### xy

| Property | Description |
|----------|-------------|
| `target` * | Image field name on the same panel |
| `default` | `"50,50"` — x,y percentages |

Returns `['x' => …, 'y' => …]` — click position on image, percentages from top-left.

### mask

| Property | Description |
|----------|-------------|
| `target` * | Image field name on the same panel |
| `definition` | Average side count of grid divisions |

Returns `['width' => …, 'height' => …, [0/1 grid values…]]`.

### imagemaker/cms_input_transform

Module field (Imagemaker). Edge control points for warping overlays onto a base image.

| Property | Description |
|----------|-------------|
| `target` * | Image field name on the same panel (e.g. `print_background`) |
| `points` | Points per edge including corners (default `5`) |

Hidden JSON value (`units: percent`, sparse grid). Popup: drag handles + polyline. See [`modules/imagemaker/docs/imagemaker.md`](../../imagemaker/docs/imagemaker.md).

### colour

| Property | Description |
|----------|-------------|
| `default` | Default colour value |

### layout

Page layout selector (from module `config.json` `layouts`).

### modules

Module load order selector (folders under `modules/`).

### page

CMS page selector (all pages).

### multifk

| Property | Description |
|----------|-------------|
| `targets` * | Array of list ids — merged into one select |

### grid

Table grid tied to panel model `ds_*` data-source methods. Can be embedded in a template (`form/form_grid`) or used in panel `settings` via `type: grid` (see [`note_system.json`](../../music/definitions/note_system.json)).

| Property | Description |
|----------|-------------|
| `ds` * | Data source name — panel implements `ds_{name}()` |
| `fields` | Column definitions (`text`, `id`, `cms/cms_grid_editable`, or `module/panel`) |
| `operations` | `S` schema, `L` list, `C` create row, `D` delete row |
| `base_name` | Panel name when `base_id` not available |

**`ds_*` operations:**

| Op | Meaning |
|----|---------|
| `S` | Return column `fields` (may merge dynamic columns — see `form/basic` `ds_subscribers`) |
| `L` | Return row arrays. Each row needs `id`. That id is stable: it stays on that row when another row is deleted. Do not use the array index. |
| `C` | Create row. `id` in the call is the parent panel. **Must return `id`**: the new row’s stable id. The grid log uses that to replace `tmp_<n>`. |
| `D` | Delete row; with `base_id`, `id` = parent and `row_id` = line to remove |
| `U` | Update one cell. `col` is the field name. `dim_{id}` is a dim value on shop variants. |
| `C` / `U` / `D` from the browser | Not called while editing. The grid posts `panel_params[{name}_log]`, a JSON list applied in order on panel Save. |

**Row id.** Each rendered row has a hidden `.cms_grid_row_id`. Existing rows get the `id` from `L`. A row added in the browser gets `tmp_<n>` and keeps it until Save. These ids do not change when another row is removed.

**`C` returns `id`.** This is the grid contract. `apply_logs` stops with “Grid add did not return a row id” when `C` does not return one. The returned `id` replaces that row’s `tmp_<n>` on the page. `grid_ids` in the Save result is per grid name, and only that grid’s rows are rewritten.

**Change log.** Add, set, and delete are recorded in the hidden textarea and sent with Save. Deleting a row drops every earlier line for that same id. A `tmp_` row then leaves no lines at all, not even a delete. Setting the same cell again keeps only the latest value. Save calls `cms/cms_input_grid` `apply_logs`, which runs the lines in order (`C`, then `U`, then `D`). A refused line stops the log. If that line belongs to a row created earlier in the same Save, that new row is deleted and its `add` is put back in the log under the original `tmp_` id. Save returns `ok: 0` and the reason. The textarea keeps the lines that still need to run. The log is not stored.

**Editable cells:** inside a grid, the grid template prints `cms/cms_grid_editable` cells itself (its template, not a panel per cell), and they update the log. Outside a grid the panel still blur-saves (`update_field`), and an ajax call without `value` reads the cell's stored value. Both the write and the read go through `cms_page_panel_cms_model::get_grid_cell_target()`:
- with `ds`: the base block is stored, and its definition has a grid with that `ds` (the `ds` handler checks the column);
- without: the item is a stored block, and `name` is a plain field of its definition (not a grid, a `{name}_log`, a repeater, a block column or a `_` key).

A refused cell replies with `data-save_error` (the reason) and none of the posted names; the JS keeps the cell, puts its old value back and shows the reason as an error.

**Reference:** [`form/basic.php`](../../form/panels/basic.php) `ds_subscribers` (read-only grid, `form_data` table). [`music/note_system.php`](../../music/panels/note_system.php) `ds_rows` (editable grid, `headers` + `rows` in panel settings JSON).

---

## See also

- [`cms_input_image.md`](cms_input_image.md) — image/video input and selector stack
- [`agents.md`](agents.md) — panel and definition conventions